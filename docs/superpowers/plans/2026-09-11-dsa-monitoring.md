# DSA Monitoring Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Replace the front-end-only Manage DSA mockup with a persisted system of record for monthly DSA disbursement, supporting multi-month releases, exception-based recording, and locked releases with an audit trail.

**Architecture:** Four new Supabase tables store releases (the disbursement event), payments (one beneficiary-month), standing eligibility, and an audit log. Five new actions are added to the existing `api/payroll-router.php` — no new file, because Vercel Hobby caps deployments at 12 serverless functions and `api/` already holds 9. The existing grid in `public/dashboard-admin.html` is rewired from its throwaway `dsaState` variable to real data.

**Tech Stack:** PHP 8.3 (vercel-php@0.7.1), Supabase PostgREST via cURL, vanilla JavaScript (no framework, no build step for JS), Tailwind for CSS only.

**Spec:** `docs/superpowers/specs/2026-09-11-dsa-monitoring-design.md`

## Global Constraints

- **No new files in `api/`.** Vercel Hobby tier caps at 12 serverless functions; `api/` holds 9. All DSA actions go on `api/payroll-router.php`.
- **This project has no test framework.** There is no `tests/` directory, no test runner in `package.json`, and no assertion library. Do not introduce one as part of this feature — that is a separate decision. Every task is instead verified by running the real endpoint against a local PHP server with `curl` and checking actual output. Verification steps below give exact commands and exact expected output. **A task is not done until its verification command has been run and its output matches.**
- **Beneficiary key is `aruga_id`** (TEXT, e.g. `ARUGA-2026-NCR-0991`), carried on the `assessments` table. Not the assessment UUID.
- **Month format is `YYYY-MM`** everywhere — in the database, the API, and the JavaScript. Never a month index, never a name.
- **Default DSA amount is 2000** pesos per beneficiary per month, stored per release so historical reports stay correct if the rate changes.
- **Roles are exactly:** `admin`, `central`, `stu_head`, `field_officer` (from `interviewers.dashboard_role`).
- **The front end reads the current user's role from** `sessionStorage.getItem('dashboard_role')`, set at login in `public/login-dashboard.html:179`. Note that `dashGetUser()` in `public/js/dashboard-auth.js` does **not** include the role — do not reach for `dashGetUser().dashboard_role`, it is always `undefined`. Front-end role checks are for hiding controls only; the server enforces access regardless.
- **Auth is via existing helpers** in `api/lib/auth.php`: `requireRole(array)` and `requireRegion(string)`. `auth.php` calls `requireAuth()` on include. Do not write new access-control code.
- **Supabase access is via** `supabaseRequest($method, $endpoint, $data = null)` from `api/lib/config.php`, which returns `['success' => bool, 'data' => mixed]`. The endpoint is a PostgREST path without a leading slash.
- **PostgREST caps rows per request** regardless of the `limit` param. Any query that can exceed 1000 rows must paginate with `limit`/`offset`, following the existing loop in `api/payroll-router.php:33-51`.

---

## Local Verification Setup

Every task's verification uses a local PHP server. Start it once and leave it running in a second terminal:

```bash
cd "C:/Users/lenovo/Downloads/project-aruga"
php -S localhost:8000
```

API requests need auth headers. Get a real session by logging into the deployed dashboard, opening DevTools, and reading `localStorage`. Then export them:

```bash
export SID="<session_id>"
export IID="<interviewer_id>"
```

A convenience alias used throughout this plan:

```bash
alias dsacurl='curl -s -H "X-Session-ID: $SID" -H "X-Interviewer-ID: $IID"'
```

If `php -S` cannot reach Supabase, check that `api/lib/config.php` reads credentials from the environment and that those variables are set in the shell running the server.

---

## File Structure

| File | Change | Responsibility |
|---|---|---|
| `supabase-migrations/dsa.sql` | Create | Four tables, constraints, indexes |
| `api/payroll-router.php` | Modify | Add 5 `case` blocks to the existing `switch ($action)` |
| `public/dashboard-admin.html` | Modify | Replace `dsaState` mockup (lines ~3135-3155), add Record Release modal and reopen dialog |

`payroll-router.php` grows from 190 to roughly 550 lines. That is within the range of other routers in this codebase (`admin-router.php` is larger), so no split is warranted.

---

### Task 1: Database schema

**Files:**
- Create: `supabase-migrations/dsa.sql`

**Interfaces:**
- Consumes: existing `interviewers(id)` table
- Produces: tables `dsa_releases`, `dsa_payments`, `dsa_beneficiary_status`, `dsa_release_audit`

- [ ] **Step 1: Write the migration file**

Create `supabase-migrations/dsa.sql`:

```sql
-- DSA Monitoring: releases, per-month payments, standing eligibility, audit.
-- A "release" is one disbursement event covering N months for one region.
-- A "payment" is one beneficiary's status for one month.

CREATE TABLE IF NOT EXISTS dsa_releases (
  id               UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  region_name      TEXT NOT NULL,
  release_date     DATE NOT NULL,
  months_covered   TEXT[] NOT NULL,
  amount_per_month INTEGER NOT NULL DEFAULT 2000 CHECK (amount_per_month > 0),
  recorded_by      UUID NOT NULL REFERENCES interviewers(id),
  is_locked        BOOLEAN NOT NULL DEFAULT TRUE,
  created_at       TIMESTAMPTZ DEFAULT NOW(),
  CHECK (array_length(months_covered, 1) > 0)
);

CREATE TABLE IF NOT EXISTS dsa_payments (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  release_id   UUID NOT NULL REFERENCES dsa_releases(id) ON DELETE CASCADE,
  aruga_id     TEXT NOT NULL,
  period_month TEXT NOT NULL CHECK (period_month ~ '^\d{4}-\d{2}$'),
  status       TEXT NOT NULL CHECK (status IN
                 ('paid','deceased','transferred','not_claimed')),
  created_at   TIMESTAMPTZ DEFAULT NOW(),
  UNIQUE (aruga_id, period_month)
);

-- Standing eligibility. Absence of a row means active.
CREATE TABLE IF NOT EXISTS dsa_beneficiary_status (
  aruga_id       TEXT PRIMARY KEY,
  status         TEXT NOT NULL CHECK (status IN ('deceased','transferred')),
  effective_from TEXT NOT NULL CHECK (effective_from ~ '^\d{4}-\d{2}$'),
  set_by_release UUID REFERENCES dsa_releases(id) ON DELETE SET NULL,
  created_at     TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS dsa_release_audit (
  id           UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  release_id   UUID NOT NULL REFERENCES dsa_releases(id) ON DELETE CASCADE,
  action       TEXT NOT NULL CHECK (action IN ('reopen','correct','relock')),
  reason       TEXT NOT NULL,
  performed_by UUID NOT NULL REFERENCES interviewers(id),
  details      JSONB,
  created_at   TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX IF NOT EXISTS idx_dsa_payments_aruga   ON dsa_payments(aruga_id);
CREATE INDEX IF NOT EXISTS idx_dsa_payments_month   ON dsa_payments(period_month);
CREATE INDEX IF NOT EXISTS idx_dsa_payments_release ON dsa_payments(release_id);
CREATE INDEX IF NOT EXISTS idx_dsa_releases_region  ON dsa_releases(region_name);
```

- [ ] **Step 2: Apply it in the Supabase SQL editor**

Paste the file contents into the Supabase SQL editor and run. Expected: `Success. No rows returned`.

- [ ] **Step 3: Verify the uniqueness constraint actually bites**

This is the load-bearing rule of the whole design, so prove it rather than assuming it. In the SQL editor:

```sql
INSERT INTO dsa_releases (region_name, release_date, months_covered, recorded_by)
VALUES ('TEST REGION', '2026-01-15', ARRAY['2026-01'],
        (SELECT id FROM interviewers LIMIT 1))
RETURNING id;
-- use the returned id below, twice:
INSERT INTO dsa_payments (release_id, aruga_id, period_month, status)
VALUES ('<id>', 'TEST-DUP-001', '2026-01', 'paid');
INSERT INTO dsa_payments (release_id, aruga_id, period_month, status)
VALUES ('<id>', 'TEST-DUP-001', '2026-01', 'paid');
```

Expected: the first insert succeeds, the second fails with
`duplicate key value violates unique constraint "dsa_payments_aruga_id_period_month_key"`.

- [ ] **Step 4: Clean up the test rows**

```sql
DELETE FROM dsa_releases WHERE region_name = 'TEST REGION';
```

Expected: the cascade also removes the test payment row. Confirm with
`SELECT COUNT(*) FROM dsa_payments WHERE aruga_id = 'TEST-DUP-001';` returning 0.

- [ ] **Step 5: Commit**

```bash
git add supabase-migrations/dsa.sql
git commit -m "feat(dsa): add schema for releases, payments, status, audit"
```

---

### Task 2: `dsa-grid` action + rewire the grid

This task is worth shipping on its own: it makes the grid stop losing its data on reload, even before any release can be recorded.

**Files:**
- Modify: `api/payroll-router.php` (new case before the `default:` block at line ~186)
- Modify: `public/dashboard-admin.html` (replace `dsaState` block at lines ~3135-3155)

**Interfaces:**
- Consumes: tables from Task 1
- Produces: `GET /api/payroll-router.php?action=dsa-grid&region=<name>&year=<YYYY>` returning
  `{success: bool, data: [{aruga_id, name, region, months: {"YYYY-MM": "paid"|"not_claimed"|...}, months_behind: int, standing: null|"deceased"|"transferred"}], year: int}`

- [ ] **Step 1: Add the `dsa-grid` case**

In `api/payroll-router.php`, insert immediately before `default: {`:

```php
    // ================================================================
    // action=dsa-grid — one row per beneficiary with per-month status
    // ================================================================
    case 'dsa-grid': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin', 'central', 'stu_head', 'field_officer']);

        $region = getStr('region');
        $year   = (int)(getStr('year') ?: date('Y'));

        // A field officer is confined to their own region; admin/central see all.
        $role = $authInterviewer['dashboard_role'] ?? '';
        if ($role === 'field_officer') {
            $region = trim($authInterviewer['region'] ?? '');
            if ($region === '') {
                echo json_encode(['success' => false, 'message' => 'No region assigned.']); exit;
            }
        } elseif ($region !== '') {
            requireRegion($region);
        }

        // 1. Beneficiaries (paginated — PostgREST caps rows per request).
        $pageSize = 1000; $offset = 0; $assessments = [];
        while (true) {
            $endpoint = 'assessments?select=aruga_id,children(first_name,last_name,middle_name,name_extension,region)'
                . '&deleted_at=is.null&order=created_at.desc&limit=' . $pageSize . '&offset=' . $offset;
            $res = supabaseRequest('GET', $endpoint);
            if (!$res['success']) {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch beneficiaries']); exit;
            }
            $page = $res['data'] ?? [];
            $assessments = array_merge($assessments, $page);
            if (count($page) < $pageSize) break;
            $offset += $pageSize;
        }

        if ($region !== '') {
            $assessments = array_values(array_filter($assessments, function ($a) use ($region) {
                return isset($a['children']['region']) && $a['children']['region'] === $region;
            }));
        }

        // 2. Payments for the requested year (paginated).
        $payMap = []; $offset = 0;
        while (true) {
            $res = supabaseRequest('GET',
                'dsa_payments?select=aruga_id,period_month,status'
                . '&period_month=gte.' . $year . '-01'
                . '&period_month=lte.' . $year . '-12'
                . '&limit=' . $pageSize . '&offset=' . $offset);
            if (!$res['success']) break;
            $page = $res['data'] ?? [];
            foreach ($page as $p) {
                $payMap[$p['aruga_id']][$p['period_month']] = $p['status'];
            }
            if (count($page) < $pageSize) break;
            $offset += $pageSize;
        }

        // 3. Standing eligibility.
        $standing = [];
        $res = supabaseRequest('GET', 'dsa_beneficiary_status?select=aruga_id,status&limit=10000');
        if ($res['success']) {
            foreach ($res['data'] ?? [] as $s) $standing[$s['aruga_id']] = $s['status'];
        }

        // 4. Months behind counts only elapsed months of the current year.
        $lastMonth = ((int)date('Y') === $year) ? (int)date('n') : 12;

        $rows = [];
        foreach ($assessments as $a) {
            $arugaId = $a['aruga_id'] ?? '';
            if ($arugaId === '') continue;
            $c = $a['children'] ?? [];
            $ext = trim($c['name_extension'] ?? '');
            if (strcasecmp($ext, 'None') === 0) $ext = '';
            $name = trim(implode(' ', array_filter([
                trim($c['first_name'] ?? ''), trim($c['middle_name'] ?? ''),
                trim($c['last_name'] ?? ''), $ext,
            ])));

            $months = $payMap[$arugaId] ?? [];
            $behind = 0;
            if (!isset($standing[$arugaId])) {
                for ($m = 1; $m <= $lastMonth; $m++) {
                    $key = sprintf('%04d-%02d', $year, $m);
                    if (($months[$key] ?? '') !== 'paid') $behind++;
                }
            }

            $rows[] = [
                'aruga_id'      => $arugaId,
                'name'          => $name ?: '—',
                'region'        => $c['region'] ?? '—',
                'months'        => (object)$months,
                'months_behind' => $behind,
                'standing'      => $standing[$arugaId] ?? null,
            ];
        }

        echo json_encode(['success' => true, 'data' => $rows, 'year' => $year]);
        break;
    }

```

- [ ] **Step 2: Verify the endpoint returns real rows**

With the local server running:

```bash
dsacurl "http://localhost:8000/api/payroll-router.php?action=dsa-grid&year=2026" | head -c 600
```

Expected: JSON starting `{"success":true,"data":[{"aruga_id":"ARUGA-2026-...`, each row carrying `months`, `months_behind`, and `standing`. Since no releases exist yet, every `months` is `{}` and `months_behind` equals the number of elapsed months this year.

- [ ] **Step 3: Verify auth is actually enforced**

```bash
curl -s "http://localhost:8000/api/payroll-router.php?action=dsa-grid&year=2026"
```

Expected: `{"success":false,"message":"Authentication required."}` with HTTP 401. If this returns data, stop — auth is not wired and nothing else in this plan is safe.

- [ ] **Step 4: Replace the mockup state in the front end**

In `public/dashboard-admin.html`, replace the block from `let dsaState = {};` through the end of `function toggleDsaMonth` (lines ~3135-3145) with:

```javascript
  // Keyed by aruga_id -> { "YYYY-MM": "paid"|"not_claimed"|"deceased"|"transferred" }
  let dsaState = {};
  let dsaBehind = {};
  let dsaStanding = {};
  let dsaYear = new Date().getFullYear();
  let dsaLoaded = false;

  function dsaMonthKey(mi) {
    return dsaYear + '-' + String(mi + 1).padStart(2, '0');
  }

  async function loadDsaGrid() {
    const region = document.getElementById('all-ben-region').value || '';
    const url = '/api/payroll-router.php?action=dsa-grid&year=' + dsaYear
              + (region ? '&region=' + encodeURIComponent(region) : '');
    const res = await authFetch(url);
    const json = await res.json();
    if (!json.success) { alert(json.message || 'Failed to load DSA data.'); return; }
    dsaState = {}; dsaBehind = {}; dsaStanding = {};
    json.data.forEach(r => {
      dsaState[r.aruga_id]    = r.months || {};
      dsaBehind[r.aruga_id]   = r.months_behind;
      dsaStanding[r.aruga_id] = r.standing;
    });
    dsaLoaded = true;
    renderAllBen();
  }

  function dsaCircleHtml(arugaId, mi) {
    const status = (dsaState[arugaId] || {})[dsaMonthKey(mi)];
    const cls = status === 'paid' ? ' on' : (status ? ' missed' : '');
    const title = DSA_MONTHS[mi] + (status ? ' — ' + status.replace('_', ' ') : ' — no record');
    // Every circle reflects a locked release, so none are clickable.
    return `<span class="dsa-dot${cls}" title="${title}"></span>`;
  }
```

Note that `toggleDsaMonth` is deleted entirely. Circles are now a record of locked releases, not a toggle.

- [ ] **Step 5: Show months-behind in the row**

Replace `function dsaRow(r)` with:

```javascript
  function dsaRow(r) {
    const behind = dsaBehind[r.aruga_id] ?? 0;
    const standing = dsaStanding[r.aruga_id];
    const behindHtml = standing
      ? `<span style="font-size:0.65rem;color:#9ca3af;text-transform:capitalize;">${standing}</span>`
      : `<span style="font-weight:700;color:${behind > 0 ? '#dc2626' : '#16a34a'};">${behind}</span>`;
    return `<tr class="table-row-hover">
      <td><div style="font-weight:600;color:#111811;text-transform:uppercase;">${r.name}</div><div style="font-size:0.65rem;color:#9ca3af;">${r.aruga_id}</div></td>
      ${DSA_MONTHS.map((m, mi) => `<td style="text-align:center;">${dsaCircleHtml(r.aruga_id, mi)}</td>`).join('')}
      <td style="text-align:center;">${behindHtml}</td>
    </tr>`;
  }
```

- [ ] **Step 6: Update the DSA table header**

Replace the `ALL_BEN_THEAD_DSA` definition (line ~3126) with:

```javascript
  const ALL_BEN_THEAD_DSA = `<tr id="all-ben-thead-row">
    <th style="white-space:nowrap;">Beneficiary</th>
    ${DSA_MONTHS.map(m => `<th style="white-space:nowrap;text-align:center;">${m}</th>`).join('')}
    <th style="white-space:nowrap;text-align:center;">Behind</th>
  </tr>`;
```

The old `editDsa` per-row button is gone, so delete `function editDsa` as well.

- [ ] **Step 7: Load data when the DSA view opens**

In `toggleDsaView`, replace the final `renderAllBen();` with:

```javascript
    if (allBenDsaMode && !dsaLoaded) { loadDsaGrid(); } else { renderAllBen(); }
```

- [ ] **Step 8: Add styling for missed circles**

Find the existing `.dsa-dot` CSS rule and add alongside it:

```css
    .dsa-dot.missed { background: #fee2e2; border-color: #dc2626; }
```

- [ ] **Step 9: Verify in the browser**

Open `http://localhost:8000/dashboard-admin`, log in, click **Manage DSA**. Expected: the grid renders with a Behind column, every circle is empty, and Behind shows the count of elapsed months in red. Reload the page and reopen the view — the data comes back from the server rather than resetting, which is the whole point of this task.

- [ ] **Step 10: Commit**

```bash
git add api/payroll-router.php public/dashboard-admin.html
git commit -m "feat(dsa): persist grid from dsa-grid endpoint, add months-behind"
```

---

### Task 3: `dsa-resolve-ids` action

**Files:**
- Modify: `api/payroll-router.php`

**Interfaces:**
- Consumes: `assessments` table
- Produces: `POST /api/payroll-router.php?action=dsa-resolve-ids` with body `{region: string, aruga_ids: string[]}` returning `{success: bool, data: {found: [{aruga_id, name, region}], unknown: string[], wrong_region: string[]}}`

- [ ] **Step 1: Add the case**

Insert before `default:` in `api/payroll-router.php`:

```php
    // ================================================================
    // action=dsa-resolve-ids — turn pasted ARUGA IDs into names
    // ================================================================
    case 'dsa-resolve-ids': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin', 'central', 'field_officer']);

        $body = json_decode(file_get_contents('php://input'), true) ?? [];
        $ids    = $body['aruga_ids'] ?? [];
        $region = trim($body['region'] ?? '');

        $role = $authInterviewer['dashboard_role'] ?? '';
        if ($role === 'field_officer') {
            $region = trim($authInterviewer['region'] ?? '');
        } elseif ($region !== '') {
            requireRegion($region);
        }

        if (!is_array($ids) || empty($ids)) {
            echo json_encode(['success' => true,
                'data' => ['found' => [], 'unknown' => [], 'wrong_region' => []]]); exit;
        }

        // Normalize: trim, uppercase, drop blanks, de-duplicate.
        $ids = array_values(array_unique(array_filter(array_map(function ($s) {
            return strtoupper(trim((string)$s));
        }, $ids), function ($s) { return $s !== ''; })));

        if (count($ids) > 1000) {
            echo json_encode(['success' => false, 'message' => 'Too many IDs (max 1000).']); exit;
        }

        $quoted = array_map(function ($id) { return '"' . str_replace('"', '', $id) . '"'; }, $ids);
        $res = supabaseRequest('GET',
            'assessments?select=aruga_id,children(first_name,last_name,middle_name,name_extension,region)'
            . '&deleted_at=is.null&aruga_id=in.(' . urlencode(implode(',', $quoted)) . ')&limit=1000');

        if (!$res['success']) {
            echo json_encode(['success' => false, 'message' => 'Lookup failed']); exit;
        }

        $found = []; $wrongRegion = []; $seen = [];
        foreach ($res['data'] ?? [] as $a) {
            $arugaId = $a['aruga_id'] ?? '';
            if ($arugaId === '') continue;
            $seen[$arugaId] = true;
            $c = $a['children'] ?? [];
            $rowRegion = $c['region'] ?? '';

            if ($region !== '' && $rowRegion !== $region) { $wrongRegion[] = $arugaId; continue; }

            $ext = trim($c['name_extension'] ?? '');
            if (strcasecmp($ext, 'None') === 0) $ext = '';
            $name = trim(implode(' ', array_filter([
                trim($c['first_name'] ?? ''), trim($c['middle_name'] ?? ''),
                trim($c['last_name'] ?? ''), $ext,
            ])));

            $found[] = ['aruga_id' => $arugaId, 'name' => $name ?: '—', 'region' => $rowRegion];
        }

        $unknown = array_values(array_filter($ids, function ($id) use ($seen) {
            return !isset($seen[$id]);
        }));

        echo json_encode(['success' => true, 'data' => [
            'found' => $found, 'unknown' => $unknown, 'wrong_region' => $wrongRegion,
        ]]);
        break;
    }

```

- [ ] **Step 2: Verify a real ID resolves and a fake one is reported**

Take a real `aruga_id` from the Task 2 output, then:

```bash
dsacurl -X POST -H "Content-Type: application/json" \
  -d '{"aruga_ids":["ARUGA-2026-NCR-0991","ARUGA-FAKE-999"]}' \
  "http://localhost:8000/api/payroll-router.php?action=dsa-resolve-ids"
```

Expected: `found` contains one entry with the real ID and a non-empty `name`; `unknown` is exactly `["ARUGA-FAKE-999"]`.

- [ ] **Step 3: Verify whitespace and case are tolerated**

Officers paste from spreadsheets, so this path matters more than it looks.

```bash
dsacurl -X POST -H "Content-Type: application/json" \
  -d '{"aruga_ids":["  aruga-2026-ncr-0991  ",""]}' \
  "http://localhost:8000/api/payroll-router.php?action=dsa-resolve-ids"
```

Expected: the same single entry in `found`, and the empty string does not appear in `unknown`.

- [ ] **Step 4: Commit**

```bash
git add api/payroll-router.php
git commit -m "feat(dsa): add dsa-resolve-ids for pasted beneficiary IDs"
```

---

### Task 4: `dsa-record-release` action

**Files:**
- Modify: `api/payroll-router.php`

**Interfaces:**
- Consumes: `dsa-resolve-ids` output shape
- Produces: `POST ?action=dsa-record-release` with body
  `{region, release_date: "YYYY-MM-DD", months: ["YYYY-MM"], amount_per_month: int, exceptions: [{aruga_id, reason}]}`
  returning `{success, data: {release_id, paid_count, missed_count, total_amount}}`

- [ ] **Step 1: Add the case**

Insert before `default:`:

```php
    // ================================================================
    // action=dsa-record-release — create a locked release + payment rows
    // ================================================================
    case 'dsa-record-release': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin', 'field_officer']);

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $region = trim($body['region'] ?? '');
        $date   = trim($body['release_date'] ?? '');
        $months = $body['months'] ?? [];
        $amount = (int)($body['amount_per_month'] ?? 2000);
        $exceptions = $body['exceptions'] ?? [];

        $role = $authInterviewer['dashboard_role'] ?? '';
        if ($role === 'field_officer') {
            $region = trim($authInterviewer['region'] ?? '');
        } else {
            requireRegion($region);
        }

        // ---- Validate before touching the database ----
        if ($region === '') {
            echo json_encode(['success' => false, 'message' => 'Region is required.']); exit;
        }
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'Release date must be YYYY-MM-DD.']); exit;
        }
        if (!is_array($months) || empty($months)) {
            echo json_encode(['success' => false, 'message' => 'At least one month is required.']); exit;
        }
        $months = array_values(array_unique($months));
        foreach ($months as $m) {
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)) {
                echo json_encode(['success' => false, 'message' => 'Invalid month: ' . $m]); exit;
            }
        }
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Amount must be greater than zero.']); exit;
        }

        $validReasons = ['deceased', 'transferred', 'not_claimed'];
        $exMap = [];
        foreach ($exceptions as $ex) {
            $exId     = strtoupper(trim($ex['aruga_id'] ?? ''));
            $exReason = trim($ex['reason'] ?? '');
            if ($exId === '') continue;
            if (!in_array($exReason, $validReasons, true)) {
                echo json_encode(['success' => false,
                    'message' => 'Invalid reason for ' . $exId . '.']); exit;
            }
            $exMap[$exId] = $exReason;
        }

        // ---- Region roster, minus those with a standing status ----
        $pageSize = 1000; $offset = 0; $assessments = [];
        while (true) {
            $res = supabaseRequest('GET',
                'assessments?select=aruga_id,children(region)&deleted_at=is.null'
                . '&limit=' . $pageSize . '&offset=' . $offset);
            if (!$res['success']) {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch roster']); exit;
            }
            $page = $res['data'] ?? [];
            $assessments = array_merge($assessments, $page);
            if (count($page) < $pageSize) break;
            $offset += $pageSize;
        }

        $standing = [];
        $sres = supabaseRequest('GET', 'dsa_beneficiary_status?select=aruga_id&limit=10000');
        if ($sres['success']) {
            foreach ($sres['data'] ?? [] as $s) $standing[$s['aruga_id']] = true;
        }

        $roster = [];
        foreach ($assessments as $a) {
            $arugaId = $a['aruga_id'] ?? '';
            if ($arugaId === '') continue;
            if (($a['children']['region'] ?? '') !== $region) continue;
            if (isset($standing[$arugaId])) continue;  // deceased/transferred drop off
            $roster[] = $arugaId;
        }
        $roster = array_values(array_unique($roster));

        if (empty($roster)) {
            echo json_encode(['success' => false,
                'message' => 'No active beneficiaries in this region.']); exit;
        }

        // ---- Reject the whole request if any beneficiary-month is already paid ----
        // Partial application would leave a release whose rows contradict its
        // stated coverage, which cannot be reconciled against a liquidation.
        $monthList = implode(',', array_map(function ($m) { return '"' . $m . '"'; }, $months));
        $clash = supabaseRequest('GET',
            'dsa_payments?select=aruga_id,period_month&period_month=in.('
            . urlencode($monthList) . ')&limit=10000');
        if ($clash['success'] && !empty($clash['data'])) {
            $rosterSet = array_flip($roster);
            $conflicts = [];
            foreach ($clash['data'] as $c) {
                if (isset($rosterSet[$c['aruga_id']])) {
                    $conflicts[] = $c['aruga_id'] . ' (' . $c['period_month'] . ')';
                }
            }
            if (!empty($conflicts)) {
                echo json_encode(['success' => false,
                    'message' => 'Already recorded for: ' . implode(', ', array_slice($conflicts, 0, 5))
                        . (count($conflicts) > 5 ? ' and ' . (count($conflicts) - 5) . ' more' : '')
                        . '. Nothing was saved.']); exit;
            }
        }

        // ---- Create the release ----
        $relRes = supabaseRequest('POST', 'dsa_releases', [
            'region_name'      => $region,
            'release_date'     => $date,
            'months_covered'   => $months,
            'amount_per_month' => $amount,
            'recorded_by'      => $authInterviewer['id'],
            'is_locked'        => true,
        ]);
        if (!$relRes['success'] || empty($relRes['data'][0]['id'])) {
            echo json_encode(['success' => false, 'message' => 'Failed to create release.']); exit;
        }
        $releaseId = $relRes['data'][0]['id'];

        // ---- Build every payment row ----
        $rows = [];
        foreach ($roster as $arugaId) {
            $status = $exMap[$arugaId] ?? 'paid';
            foreach ($months as $m) {
                $rows[] = ['release_id' => $releaseId, 'aruga_id' => $arugaId,
                           'period_month' => $m, 'status' => $status];
            }
        }

        // Insert in chunks so a large region stays inside the request ceiling.
        foreach (array_chunk($rows, 500) as $chunk) {
            $insRes = supabaseRequest('POST', 'dsa_payments', $chunk);
            if (!$insRes['success']) {
                // Roll back: the cascade removes any rows already written.
                supabaseRequest('DELETE', 'dsa_releases?id=eq.' . urlencode($releaseId));
                echo json_encode(['success' => false,
                    'message' => 'Failed to save payments. Nothing was saved.']); exit;
            }
        }

        // ---- Standing status for deceased/transferred ----
        sort($months);
        $effectiveFrom = $months[0];
        foreach ($exMap as $exId => $reason) {
            if ($reason === 'not_claimed') continue;
            supabaseRequest('POST', 'dsa_beneficiary_status', [
                'aruga_id' => $exId, 'status' => $reason,
                'effective_from' => $effectiveFrom, 'set_by_release' => $releaseId,
            ]);
        }

        $missed = count(array_intersect(array_keys($exMap), $roster));
        $paid   = count($roster) - $missed;

        echo json_encode(['success' => true, 'data' => [
            'release_id'   => $releaseId,
            'paid_count'   => $paid,
            'missed_count' => $missed,
            'total_amount' => $paid * $amount * count($months),
        ]]);
        break;
    }

```

- [ ] **Step 2: Record a real release**

Use a region with a small number of beneficiaries to keep the output readable.

```bash
dsacurl -X POST -H "Content-Type: application/json" \
  -d '{"region":"NCR (National Capital Region)","release_date":"2026-09-15","months":["2026-07","2026-08"],"amount_per_month":2000,"exceptions":[{"aruga_id":"ARUGA-2026-NCR-0991","reason":"not_claimed"}]}' \
  "http://localhost:8000/api/payroll-router.php?action=dsa-record-release"
```

Expected: `{"success":true,"data":{"release_id":"...","paid_count":N,"missed_count":1,"total_amount":...}}` where `total_amount` equals `paid_count * 2000 * 2`. Verify that multiplication by hand — it is the number a liquidation is checked against.

- [ ] **Step 3: Verify the same months are now refused**

This is the rule that prevents double payment, so prove it rather than trusting the constraint.

Run the exact same command from Step 2 again.

Expected: `{"success":false,"message":"Already recorded for: ... Nothing was saved."}`

- [ ] **Step 4: Verify the refusal left nothing behind**

```bash
dsacurl "http://localhost:8000/api/payroll-router.php?action=dsa-grid&year=2026" \
  | python -c "import json,sys; d=json.load(sys.stdin)['data']; print(len([r for r in d if r['months']]))"
```

Expected: the same count as after Step 2, not double. A second release row must not exist.

- [ ] **Step 5: Verify the grid reflects the release**

Reopen Manage DSA in the browser. Expected: Jul and Aug circles are filled for everyone in NCR except `ARUGA-2026-NCR-0991`, whose two circles show the missed style, and whose Behind count is higher than its neighbours'.

- [ ] **Step 6: Verify a bad reason is rejected**

```bash
dsacurl -X POST -H "Content-Type: application/json" \
  -d '{"region":"NCR (National Capital Region)","release_date":"2026-09-15","months":["2026-10"],"exceptions":[{"aruga_id":"ARUGA-2026-NCR-0991","reason":"on vacation"}]}' \
  "http://localhost:8000/api/payroll-router.php?action=dsa-record-release"
```

Expected: `{"success":false,"message":"Invalid reason for ARUGA-2026-NCR-0991."}` and no new release row.

- [ ] **Step 7: Commit**

```bash
git add api/payroll-router.php
git commit -m "feat(dsa): add dsa-record-release with all-or-nothing conflict check"
```

---

### Task 5: Record Release modal

**Files:**
- Modify: `public/dashboard-admin.html`

**Interfaces:**
- Consumes: `dsa-resolve-ids`, `dsa-record-release`, `loadDsaGrid()` from Task 2
- Produces: `openDsaReleaseModal()`, `closeDsaReleaseModal()`

- [ ] **Step 1: Add the Record Release button**

Next to the existing `all-ben-manage-dsa-btn` at line ~336, add:

```html
              <button class="btn btn-sm btn-primary" id="dsa-record-btn" onclick="openDsaReleaseModal()" style="display:none;align-items:center;gap:0.3rem;white-space:nowrap;"><span class="material-symbols-outlined" style="font-size:0.875rem;">add_card</span>Record Release</button>
```

In `toggleDsaView`, show it alongside the view toggle:

```javascript
    document.getElementById('dsa-record-btn').style.display = allBenDsaMode ? 'flex' : 'none';
```

- [ ] **Step 2: Add the modal markup**

Before the closing `</body>`, following the existing modal pattern at line ~522:

```html
    <div id="dsa-release-modal" style="display:none;position:fixed;inset:0;z-index:9999;align-items:center;justify-content:center;background:rgba(0,0,0,0.45);backdrop-filter:blur(3px);">
      <div style="background:white;border-radius:0.75rem;width:min(38rem,92vw);max-height:90vh;overflow-y:auto;padding:1.5rem;">
        <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:1rem;">
          <div>
            <div style="font-size:1.05rem;font-weight:700;color:#111811;">Record DSA Release</div>
            <div style="font-size:0.75rem;color:#6b7280;" id="dsa-modal-step-label">Step 1 of 2 — Release details</div>
          </div>
          <button class="btn btn-ghost btn-sm" onclick="closeDsaReleaseModal()"><span class="material-symbols-outlined">close</span></button>
        </div>

        <!-- Step 1 -->
        <div id="dsa-step-1">
          <label style="display:block;font-size:0.75rem;font-weight:600;margin-bottom:0.25rem;">Region</label>
          <select id="dsa-rel-region" style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:0.375rem;margin-bottom:0.75rem;"></select>

          <label style="display:block;font-size:0.75rem;font-weight:600;margin-bottom:0.25rem;">Release date</label>
          <input type="date" id="dsa-rel-date" style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:0.375rem;margin-bottom:0.75rem;">

          <label style="display:block;font-size:0.75rem;font-weight:600;margin-bottom:0.25rem;">Months covered</label>
          <div id="dsa-rel-months" style="display:grid;grid-template-columns:repeat(4,1fr);gap:0.4rem;margin-bottom:0.75rem;"></div>

          <label style="display:block;font-size:0.75rem;font-weight:600;margin-bottom:0.25rem;">Amount per beneficiary per month</label>
          <input type="number" id="dsa-rel-amount" value="2000" min="1" style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:0.375rem;margin-bottom:1rem;">

          <div id="dsa-step1-error" style="display:none;font-size:0.75rem;color:#dc2626;margin-bottom:0.75rem;"></div>
          <div style="display:flex;justify-content:flex-end;gap:0.5rem;">
            <button class="btn btn-secondary btn-sm" onclick="closeDsaReleaseModal()">Cancel</button>
            <button class="btn btn-primary btn-sm" onclick="dsaGotoStep2()">Next</button>
          </div>
        </div>

        <!-- Step 2 -->
        <div id="dsa-step-2" style="display:none;">
          <div style="font-size:0.75rem;color:#6b7280;margin-bottom:0.5rem;">Everyone in this region is marked <strong>paid</strong>. List only those who did <strong>not</strong> receive it, one ARUGA ID per line.</div>
          <textarea id="dsa-rel-exceptions" rows="5" placeholder="ARUGA-2026-NCR-0991&#10;ARUGA-2026-NCR-0992" style="width:100%;padding:0.5rem;border:1px solid #d1d5db;border-radius:0.375rem;font-family:monospace;font-size:0.75rem;"></textarea>
          <button class="btn btn-secondary btn-sm" onclick="dsaResolveExceptions()" style="margin:0.5rem 0;">Check IDs</button>
          <div id="dsa-resolved-list" style="margin-bottom:0.75rem;"></div>
          <div id="dsa-rel-summary" style="background:#f9fafb;border-radius:0.375rem;padding:0.75rem;font-size:0.8125rem;margin-bottom:1rem;"></div>
          <div style="font-size:0.7rem;color:#b45309;margin-bottom:0.75rem;">Saving locks this release. Only an admin can reopen it.</div>
          <div id="dsa-step2-error" style="display:none;font-size:0.75rem;color:#dc2626;margin-bottom:0.75rem;"></div>
          <div style="display:flex;justify-content:space-between;">
            <button class="btn btn-secondary btn-sm" onclick="dsaBackToStep1()">Back</button>
            <button class="btn btn-primary btn-sm" id="dsa-save-btn" onclick="dsaSaveRelease()">Save &amp; Lock</button>
          </div>
        </div>
      </div>
    </div>
```

- [ ] **Step 3: Add the modal logic**

Alongside the other DSA functions:

```javascript
  let dsaResolved = [];      // [{aruga_id, name, reason}]
  let dsaRosterCount = 0;

  function openDsaReleaseModal() {
    const regionSel = document.getElementById('dsa-rel-region');
    const src = document.getElementById('all-ben-region');
    regionSel.innerHTML = Array.from(src.options)
      .filter(o => o.value)
      .map(o => `<option value="${o.value}">${o.text}</option>`).join('');
    if (src.value) regionSel.value = src.value;

    document.getElementById('dsa-rel-date').value = new Date().toISOString().slice(0, 10);
    document.getElementById('dsa-rel-amount').value = 2000;
    document.getElementById('dsa-rel-exceptions').value = '';
    document.getElementById('dsa-resolved-list').innerHTML = '';
    document.getElementById('dsa-rel-summary').innerHTML = '';
    dsaResolved = [];
    dsaBuildMonthChecklist();
    dsaBackToStep1();
    document.getElementById('dsa-release-modal').style.display = 'flex';
  }

  function closeDsaReleaseModal() {
    document.getElementById('dsa-release-modal').style.display = 'none';
  }

  // Months already fully covered are disabled, so the common mistake is unavailable
  // rather than merely discouraged. The earliest unpaid month is pre-ticked.
  function dsaBuildMonthChecklist() {
    const covered = {};
    Object.values(dsaState).forEach(months => {
      Object.keys(months || {}).forEach(k => { covered[k] = (covered[k] || 0) + 1; });
    });
    const nowMonth = (new Date().getFullYear() === dsaYear) ? new Date().getMonth() + 1 : 12;
    let firstUnpaid = null;
    const html = DSA_MONTHS.map((m, mi) => {
      const key = dsaMonthKey(mi);
      const isCovered = !!covered[key];
      if (!isCovered && mi + 1 <= nowMonth && firstUnpaid === null) firstUnpaid = key;
      return `<label style="display:flex;align-items:center;gap:0.25rem;font-size:0.75rem;${isCovered ? 'opacity:0.4;' : ''}">
        <input type="checkbox" value="${key}" ${isCovered ? 'disabled' : ''}> ${m}
      </label>`;
    }).join('');
    document.getElementById('dsa-rel-months').innerHTML = html;
    if (firstUnpaid) {
      const box = document.querySelector(`#dsa-rel-months input[value="${firstUnpaid}"]`);
      if (box) box.checked = true;
    }
  }

  function dsaSelectedMonths() {
    return Array.from(document.querySelectorAll('#dsa-rel-months input:checked')).map(i => i.value);
  }

  function dsaBackToStep1() {
    document.getElementById('dsa-step-1').style.display = 'block';
    document.getElementById('dsa-step-2').style.display = 'none';
    document.getElementById('dsa-modal-step-label').textContent = 'Step 1 of 2 — Release details';
  }

  function dsaGotoStep2() {
    const err = document.getElementById('dsa-step1-error');
    const months = dsaSelectedMonths();
    if (!months.length) {
      err.textContent = 'Select at least one month.';
      err.style.display = 'block';
      return;
    }
    if (!document.getElementById('dsa-rel-date').value) {
      err.textContent = 'Enter the release date.';
      err.style.display = 'block';
      return;
    }
    err.style.display = 'none';
    document.getElementById('dsa-step-1').style.display = 'none';
    document.getElementById('dsa-step-2').style.display = 'block';
    document.getElementById('dsa-modal-step-label').textContent = 'Step 2 of 2 — Who did not receive it';

    const region = document.getElementById('dsa-rel-region').value;
    dsaRosterCount = Object.keys(dsaState).filter(id => !dsaStanding[id]).length;
    dsaRenderSummary();
  }

  async function dsaResolveExceptions() {
    const ids = document.getElementById('dsa-rel-exceptions').value
      .split(/[\n,]/).map(s => s.trim()).filter(Boolean);
    if (!ids.length) { dsaResolved = []; document.getElementById('dsa-resolved-list').innerHTML = ''; dsaRenderSummary(); return; }

    const res = await authFetch('/api/payroll-router.php?action=dsa-resolve-ids', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ region: document.getElementById('dsa-rel-region').value, aruga_ids: ids })
    });
    const json = await res.json();
    if (!json.success) { alert(json.message || 'Lookup failed.'); return; }

    dsaResolved = json.data.found.map(f => ({ ...f, reason: 'not_claimed' }));

    let html = dsaResolved.map((f, i) => `
      <div style="display:flex;align-items:center;gap:0.5rem;padding:0.35rem 0;border-bottom:1px solid #f3f4f6;font-size:0.75rem;">
        <div style="flex:1;"><strong style="text-transform:uppercase;">${f.name}</strong><br><span style="color:#9ca3af;">${f.aruga_id}</span></div>
        <select onchange="dsaResolved[${i}].reason=this.value;dsaRenderSummary();" style="padding:0.25rem;border:1px solid #d1d5db;border-radius:0.25rem;font-size:0.7rem;">
          <option value="not_claimed">Did not claim</option>
          <option value="deceased">Deceased</option>
          <option value="transferred">Moved away</option>
        </select>
      </div>`).join('');

    if (json.data.unknown.length) {
      html += `<div style="font-size:0.72rem;color:#dc2626;margin-top:0.5rem;">Not found: ${json.data.unknown.join(', ')}</div>`;
    }
    if (json.data.wrong_region.length) {
      html += `<div style="font-size:0.72rem;color:#b45309;margin-top:0.25rem;">Not in this region: ${json.data.wrong_region.join(', ')}</div>`;
    }

    document.getElementById('dsa-resolved-list').innerHTML = html;
    dsaRenderSummary();
  }

  function dsaRenderSummary() {
    const months = dsaSelectedMonths();
    const amount = parseInt(document.getElementById('dsa-rel-amount').value, 10) || 0;
    const missed = dsaResolved.length;
    const paid = Math.max(0, dsaRosterCount - missed);
    const total = paid * amount * months.length;
    document.getElementById('dsa-rel-summary').innerHTML =
      `<div><strong>${paid}</strong> paid &nbsp;·&nbsp; <strong>${missed}</strong> not paid</div>
       <div style="color:#6b7280;">${months.length} month(s) × ₱${amount.toLocaleString()} — total <strong>₱${total.toLocaleString()}</strong></div>`;
  }

  async function dsaSaveRelease() {
    const btn = document.getElementById('dsa-save-btn');
    const err = document.getElementById('dsa-step2-error');
    const months = dsaSelectedMonths();
    if (!confirm(`Save and lock this release? ${months.length} month(s) will be closed for editing.`)) return;

    btn.disabled = true; btn.textContent = 'Saving…';
    err.style.display = 'none';
    try {
      const res = await authFetch('/api/payroll-router.php?action=dsa-record-release', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({
          region: document.getElementById('dsa-rel-region').value,
          release_date: document.getElementById('dsa-rel-date').value,
          months: months,
          amount_per_month: parseInt(document.getElementById('dsa-rel-amount').value, 10),
          exceptions: dsaResolved.map(f => ({ aruga_id: f.aruga_id, reason: f.reason }))
        })
      });
      const json = await res.json();
      if (!json.success) {
        err.textContent = json.message || 'Failed to save.';
        err.style.display = 'block';
        return;
      }
      closeDsaReleaseModal();
      await loadDsaGrid();
      alert(`Release saved. ${json.data.paid_count} paid, ${json.data.missed_count} not paid. Total ₱${json.data.total_amount.toLocaleString()}.`);
    } finally {
      btn.disabled = false; btn.textContent = 'Save & Lock';
    }
  }
```

- [ ] **Step 4: Recalculate the summary when inputs change**

In `openDsaReleaseModal`, after `dsaBuildMonthChecklist()`:

```javascript
    document.getElementById('dsa-rel-amount').oninput = dsaRenderSummary;
    document.getElementById('dsa-rel-months').onchange = dsaRenderSummary;
```

- [ ] **Step 5: Verify the whole flow in the browser**

Open Manage DSA, click Record Release. Expected, in order:
1. Region defaults to the grid's filter; today's date is filled in; months already covered by Task 4's test release are greyed out and unticked; the earliest uncovered month is ticked.
2. Next moves to step 2 and the summary shows the full region as paid.
3. Pasting a real ARUGA ID and a fake one, then Check IDs, shows the real name with a reason dropdown and the fake one in red under "Not found".
4. The summary drops by one paid and rises by one not paid.
5. Save & Lock asks for confirmation, then closes and the grid shows the new filled circles.

- [ ] **Step 6: Verify the conflict message reaches the user**

Record a release for a month that is already covered, by unticking nothing and re-selecting a covered month via DevTools (remove the `disabled` attribute). Expected: the red error inside step 2 reads "Already recorded for… Nothing was saved," and the modal stays open so the officer can correct the selection.

- [ ] **Step 7: Commit**

```bash
git add public/dashboard-admin.html
git commit -m "feat(dsa): add two-step Record Release modal"
```

---

### Task 6: `dsa-reopen` action + admin dialog

**Files:**
- Modify: `api/payroll-router.php`
- Modify: `public/dashboard-admin.html`

**Interfaces:**
- Consumes: `dsa_releases`, `dsa_release_audit`
- Produces: `GET ?action=dsa-releases&region=` returning release history;
  `POST ?action=dsa-reopen` with body `{release_id, reason}` returning `{success, message}`

- [ ] **Step 1: Add both cases**

Insert before `default:`:

```php
    // ================================================================
    // action=dsa-releases — release history for a region
    // ================================================================
    case 'dsa-releases': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin', 'central', 'stu_head', 'field_officer']);

        $region = getStr('region');
        $role   = $authInterviewer['dashboard_role'] ?? '';
        if ($role === 'field_officer') {
            $region = trim($authInterviewer['region'] ?? '');
        } elseif ($region !== '') {
            requireRegion($region);
        }

        $endpoint = 'dsa_releases?select=id,region_name,release_date,months_covered,'
            . 'amount_per_month,is_locked,created_at&order=release_date.desc&limit=500';
        if ($region !== '') $endpoint .= '&region_name=eq.' . urlencode($region);

        $res = supabaseRequest('GET', $endpoint);
        if (!$res['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch releases']); exit;
        }
        echo json_encode(['success' => true, 'data' => $res['data'] ?? []]);
        break;
    }

    // ================================================================
    // action=dsa-reopen — admin-only unlock, always audited
    // ================================================================
    case 'dsa-reopen': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin']);

        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $releaseId = trim($body['release_id'] ?? '');
        $reason    = trim($body['reason'] ?? '');

        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        if (!preg_match($uuidPattern, $releaseId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid release id.']); exit;
        }
        if (strlen($reason) < 5) {
            echo json_encode(['success' => false,
                'message' => 'A reason of at least 5 characters is required.']); exit;
        }

        $cur = supabaseRequest('GET',
            'dsa_releases?select=id,is_locked&id=eq.' . urlencode($releaseId) . '&limit=1');
        if (!$cur['success'] || empty($cur['data'])) {
            echo json_encode(['success' => false, 'message' => 'Release not found.']); exit;
        }
        if (!$cur['data'][0]['is_locked']) {
            echo json_encode(['success' => false, 'message' => 'Release is already open.']); exit;
        }

        // Audit first: an unlock that was never recorded is the failure mode
        // that matters, so it must not be possible.
        $audit = supabaseRequest('POST', 'dsa_release_audit', [
            'release_id'   => $releaseId,
            'action'       => 'reopen',
            'reason'       => $reason,
            'performed_by' => $authInterviewer['id'],
        ]);
        if (!$audit['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to write audit record.']); exit;
        }

        $upd = supabaseRequest('PATCH',
            'dsa_releases?id=eq.' . urlencode($releaseId), ['is_locked' => false]);
        if (!$upd['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to reopen release.']); exit;
        }

        echo json_encode(['success' => true, 'message' => 'Release reopened.']);
        break;
    }

```

- [ ] **Step 2: Verify a non-admin is refused**

Using a `field_officer` session:

```bash
dsacurl -X POST -H "Content-Type: application/json" \
  -d '{"release_id":"<id from Task 4>","reason":"wrong month entered"}' \
  "http://localhost:8000/api/payroll-router.php?action=dsa-reopen"
```

Expected: HTTP 403, `{"success":false,"message":"Access denied."}`

- [ ] **Step 3: Verify an admin reopen works and is audited**

```bash
dsacurl -X POST -H "Content-Type: application/json" \
  -d '{"release_id":"<id from Task 4>","reason":"wrong month entered"}' \
  "http://localhost:8000/api/payroll-router.php?action=dsa-reopen"
```

Expected: `{"success":true,"message":"Release reopened."}`. Then in the Supabase SQL editor:

```sql
SELECT action, reason, performed_by FROM dsa_release_audit ORDER BY created_at DESC LIMIT 1;
```

Expected: one `reopen` row with the reason text and the acting admin's id.

- [ ] **Step 4: Verify a thin reason is rejected**

```bash
dsacurl -X POST -H "Content-Type: application/json" \
  -d '{"release_id":"<id>","reason":"x"}' \
  "http://localhost:8000/api/payroll-router.php?action=dsa-reopen"
```

Expected: `{"success":false,"message":"A reason of at least 5 characters is required."}` and no new audit row.

- [ ] **Step 5: Add the release history panel**

In the DSA view, below the grid, add:

```html
    <div id="dsa-releases-panel" style="display:none;margin-top:1rem;background:white;border-radius:0.75rem;padding:1rem;">
      <div style="font-weight:700;font-size:0.875rem;margin-bottom:0.5rem;">Release History</div>
      <div id="dsa-releases-list" style="font-size:0.75rem;"></div>
    </div>
```

And the logic:

```javascript
  async function loadDsaReleases() {
    const region = document.getElementById('all-ben-region').value || '';
    const res = await authFetch('/api/payroll-router.php?action=dsa-releases'
      + (region ? '?region=' + encodeURIComponent(region) : ''));
    const json = await res.json();
    if (!json.success) return;

    const isAdmin = (sessionStorage.getItem('dashboard_role') === 'admin');
    document.getElementById('dsa-releases-list').innerHTML = json.data.length
      ? json.data.map(r => `
        <div style="display:flex;align-items:center;gap:0.75rem;padding:0.5rem 0;border-bottom:1px solid #f3f4f6;">
          <div style="flex:1;">
            <strong>${r.release_date}</strong> — ${r.region_name}<br>
            <span style="color:#9ca3af;">${(r.months_covered || []).join(', ')} · ₱${r.amount_per_month}/mo</span>
          </div>
          <span style="font-size:0.7rem;padding:0.15rem 0.5rem;border-radius:999px;background:${r.is_locked ? '#fee2e2' : '#dcfce7'};color:${r.is_locked ? '#991b1b' : '#166534'};">${r.is_locked ? 'Locked' : 'Open'}</span>
          ${(isAdmin && r.is_locked) ? `<button class="btn btn-ghost btn-sm" onclick="dsaReopen('${r.id}')">Reopen</button>` : ''}
        </div>`).join('')
      : '<div style="color:#9ca3af;">No releases recorded yet.</div>';
  }

  async function dsaReopen(releaseId) {
    const reason = prompt('Why is this release being reopened? (required, min 5 characters)');
    if (reason === null) return;
    const res = await authFetch('/api/payroll-router.php?action=dsa-reopen', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ release_id: releaseId, reason: reason })
    });
    const json = await res.json();
    alert(json.message || (json.success ? 'Reopened.' : 'Failed.'));
    if (json.success) { await loadDsaReleases(); await loadDsaGrid(); }
  }
```

In `toggleDsaView`, alongside the Record Release button:

```javascript
    document.getElementById('dsa-releases-panel').style.display = allBenDsaMode ? 'block' : 'none';
    if (allBenDsaMode) loadDsaReleases();
```

And in `dsaSaveRelease`, after `await loadDsaGrid();` add `await loadDsaReleases();`.

- [ ] **Step 6: Verify the panel in the browser**

Open Manage DSA as an admin. Expected: the history panel lists the Task 4 release with its months and a Locked or Open badge, and a Reopen button appears only on locked ones. Log in as a field officer: the same list appears with no Reopen buttons.

- [ ] **Step 7: Commit**

```bash
git add api/payroll-router.php public/dashboard-admin.html
git commit -m "feat(dsa): add release history and admin-only audited reopen"
```

---

### Task 7: `dsa-region-summary` for central office

**Files:**
- Modify: `api/payroll-router.php`
- Modify: `public/dashboard-admin.html`

**Interfaces:**
- Consumes: `dsa_releases`, `dsa_payments`
- Produces: `GET ?action=dsa-region-summary&year=` returning
  `{success, data: [{region, last_release_date, months_covered_count, beneficiaries_behind, total_disbursed}]}`

- [ ] **Step 1: Add the case**

Insert before `default:`:

```php
    // ================================================================
    // action=dsa-region-summary — which regions are lagging
    // ================================================================
    case 'dsa-region-summary': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin', 'central']);

        $year = (int)(getStr('year') ?: date('Y'));

        $relRes = supabaseRequest('GET',
            'dsa_releases?select=region_name,release_date,months_covered,amount_per_month'
            . '&order=release_date.desc&limit=2000');
        if (!$relRes['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch releases']); exit;
        }

        $byRegion = [];
        foreach ($relRes['data'] ?? [] as $r) {
            $rn = $r['region_name'];
            if (!isset($byRegion[$rn])) {
                $byRegion[$rn] = ['region' => $rn, 'last_release_date' => $r['release_date'],
                                  'months_covered_count' => 0, 'beneficiaries_behind' => 0,
                                  'total_disbursed' => 0];
            }
            $byRegion[$rn]['months_covered_count'] += count($r['months_covered'] ?? []);
        }

        // Paid rows per region, for the disbursed total.
        $pageSize = 1000; $offset = 0; $paidByRegion = [];
        while (true) {
            $res = supabaseRequest('GET',
                'dsa_payments?select=status,period_month,dsa_releases(region_name,amount_per_month)'
                . '&status=eq.paid&period_month=gte.' . $year . '-01'
                . '&period_month=lte.' . $year . '-12'
                . '&limit=' . $pageSize . '&offset=' . $offset);
            if (!$res['success']) break;
            $page = $res['data'] ?? [];
            foreach ($page as $p) {
                $rn = $p['dsa_releases']['region_name'] ?? null;
                if ($rn === null) continue;
                $amt = (int)($p['dsa_releases']['amount_per_month'] ?? 2000);
                $paidByRegion[$rn] = ($paidByRegion[$rn] ?? 0) + $amt;
            }
            if (count($page) < $pageSize) break;
            $offset += $pageSize;
        }
        foreach ($paidByRegion as $rn => $total) {
            if (isset($byRegion[$rn])) $byRegion[$rn]['total_disbursed'] = $total;
        }

        $out = array_values($byRegion);
        usort($out, function ($a, $b) {
            return strcmp($a['last_release_date'], $b['last_release_date']);  // oldest first
        });

        echo json_encode(['success' => true, 'data' => $out, 'year' => $year]);
        break;
    }

```

- [ ] **Step 2: Verify the endpoint**

```bash
dsacurl "http://localhost:8000/api/payroll-router.php?action=dsa-region-summary&year=2026"
```

Expected: one entry per region that has a release, sorted with the longest-waiting region first. The region used in Task 4 shows `total_disbursed` equal to `paid_count × 2000 × 2` from that task's output. Check that figure matches — it is the reconciliation number.

- [ ] **Step 3: Verify a field officer is refused**

```bash
dsacurl "http://localhost:8000/api/payroll-router.php?action=dsa-region-summary"
```

with a `field_officer` session. Expected: HTTP 403, `{"success":false,"message":"Access denied."}`

- [ ] **Step 4: Add the summary panel**

Above the release history panel:

```html
    <div id="dsa-region-summary-panel" style="display:none;margin-top:1rem;background:white;border-radius:0.75rem;padding:1rem;">
      <div style="font-weight:700;font-size:0.875rem;margin-bottom:0.5rem;">Regional DSA Status</div>
      <div style="font-size:0.7rem;color:#6b7280;margin-bottom:0.5rem;">Longest since last release first.</div>
      <div id="dsa-region-summary-list" style="font-size:0.75rem;"></div>
    </div>
```

```javascript
  async function loadDsaRegionSummary() {
    const role = sessionStorage.getItem('dashboard_role');
    if (role !== 'admin' && role !== 'central') return;
    document.getElementById('dsa-region-summary-panel').style.display = 'block';
    const res = await authFetch('/api/payroll-router.php?action=dsa-region-summary&year=' + dsaYear);
    const json = await res.json();
    if (!json.success) return;
    document.getElementById('dsa-region-summary-list').innerHTML = json.data.length
      ? json.data.map(r => `
        <div style="display:flex;align-items:center;gap:0.75rem;padding:0.4rem 0;border-bottom:1px solid #f3f4f6;">
          <div style="flex:1;">${r.region}</div>
          <div style="color:#6b7280;">last ${r.last_release_date}</div>
          <div style="font-weight:600;">₱${(r.total_disbursed || 0).toLocaleString()}</div>
        </div>`).join('')
      : '<div style="color:#9ca3af;">No releases recorded yet.</div>';
  }
```

Call it from `toggleDsaView` next to `loadDsaReleases()`.

- [ ] **Step 5: Verify in the browser**

As an admin, open Manage DSA. Expected: the Regional DSA Status panel lists regions with a release, oldest first, with the disbursed total. As a field officer, the panel does not appear.

- [ ] **Step 6: Commit**

```bash
git add api/payroll-router.php public/dashboard-admin.html
git commit -m "feat(dsa): add regional DSA status summary for central office"
```

---

### Task 8: Deployment check

**Files:** none modified — this is a gate before merging.

- [ ] **Step 1: Confirm the function count is still under the Hobby cap**

```bash
ls api/*.php | wc -l
```

Expected: `9`. If this is 10 or more, a new router file was added against the plan's constraint and the deployment will fail.

- [ ] **Step 2: Confirm no syntax errors in the router**

```bash
php -l api/payroll-router.php
```

Expected: `No syntax errors detected in api/payroll-router.php`

- [ ] **Step 3: Confirm every action is reachable**

```bash
for a in dsa-grid dsa-releases dsa-region-summary; do
  echo -n "$a: "
  dsacurl "http://localhost:8000/api/payroll-router.php?action=$a" | head -c 80
  echo
done
```

Expected: each returns JSON with `"success":true` or a role-based `"Access denied."` — never `"Unknown action"`, which would mean a case was misnamed or placed after `default:`.

- [ ] **Step 4: Confirm the original payroll actions still work**

The DSA cases were added to a file that already had users, so prove they were not disturbed.

```bash
dsacurl "http://localhost:8000/api/payroll-router.php?action=assessment-status" | head -c 120
```

Expected: the original severity counts JSON, unchanged.

- [ ] **Step 5: Commit any fixes and push**

```bash
git add -A
git commit -m "chore(dsa): deployment verification fixes"
git push
```

---

## Self-Review Notes

**Spec coverage.** Every section of the spec maps to a task: schema to Task 1, the grid and months-behind to Task 2, ID resolution to Task 3, recording with all-or-nothing conflict handling to Task 4, the two-step modal to Task 5, lock and audited reopen to Task 6, central office view to Task 7, and the Vercel function-count constraint to Task 8.

**Known gaps, deliberately left out of scope** (consistent with the spec's Out of Scope section):

- **Correcting a reopened release.** Task 6 unlocks a release and audits it, but editing the payment rows afterwards is not built. The `correct` and `relock` audit actions exist in the schema for it. An admin currently reopens, then fixes rows in Supabase directly. This should be the next piece of work after this plan lands.
- **Reports and export.** Depends on this data existing. The release table carries the per-release amount specifically so historical reports stay correct when the DSA rate changes.
- **Tests.** This project has no test framework, so every task is verified by running the real endpoint. Introducing a framework is a separate decision, not a side effect of this feature.
