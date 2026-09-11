# DSA Monitoring — Design

**Date:** 2026-09-11
**Status:** Approved for planning

## Problem

Beneficiaries receive a monthly Daily Subsistence Allowance (DSA) of 2000 pesos.
There is no system of record for who has been paid. The Manage DSA grid in
`public/dashboard-admin.html` is a front-end mockup: its circles live in a
JavaScript variable (`dsaState`) and are lost on page reload.

Two facts make a naive "click each circle" approach unworkable:

1. **Volume.** A region can have 400+ beneficiaries. Marking a month by hand is
   hundreds of clicks per region per month.
2. **Irregular funding.** Some regions release monthly. Others release every
   three or more months depending on fund availability, paying the equivalent of
   however many months are covered. A single release event can cover N months.

## Core Model

Two distinct things, deliberately separated:

- **A release** is one disbursement event: a region received funds on a date,
  covering a set of months, at an amount per beneficiary. This is the audit
  record.
- **A payment** is one beneficiary's status for one month: paid, or missed with
  a reason. This is what the grid renders.

One release creates many payments. A release covering three months for 400
beneficiaries creates 1200 payment rows.

Months are the truth and the payout date is an attribute of the release, rather
than the reverse. This keeps the existing 12-column grid as the mental model and
answers the question implementers actually ask: is this person current or behind?

## Recording Flow

The exception-based flow is what makes volume tractable. The officer names who
did *not* get paid, not who did.

1. Field officer opens Manage DSA, clicks **Record Release**.
2. **Modal step 1 — details.** Release date, months covered (multi-select),
   amount per beneficiary. The months field defaults to the earliest unpaid
   month for that region. Selecting an already-covered month warns before
   proceeding.
3. **Modal step 2 — exceptions.** A textarea accepts ARUGA IDs, one per line,
   typed or pasted. Each ID resolves to a beneficiary name as it is entered, so
   unknown or mistyped IDs surface immediately rather than at save. Each resolved
   person gets a reason dropdown. A running summary shows paid count, missed
   count, and total amount.
4. **Confirm and save.** Saving locks the release.

Everyone in the region not named as an exception is marked paid for every month
in the release.

## Rules

- **Miss reasons are a fixed set of three:** `deceased`, `transferred`,
  `not_claimed`. No free text — an open box yields hundreds of differently
  worded versions of the same reason and nothing countable.
- **Deceased and transferred remove a beneficiary from future releases.** The
  list cleans itself. This is a standing status, not a fact about one month, so
  it is stored on `dsa_beneficiary_status` rather than inferred from payment
  rows. Marking someone deceased or transferred in a release writes that row as
  a side effect.
- **`not_claimed` means money is still owed.** These beneficiaries stay on the
  list and their months-behind counter rises, surfacing them for follow-up.
- **Saving locks the release.** There is no separate submit step: a month left
  unfinalized because nobody pressed a button is worse than one closed slightly
  early. Locked circles are not clickable.
- **Only `admin` may reopen a release**, and must give a reason. Reopens and
  corrections are recorded rather than overwriting the original, so a release
  always reconciles against what was liquidated.
- **A `field_officer` sees and touches only their own region.** `admin` and
  `central` see all regions.
- **A beneficiary-month can be paid at most once.** Enforced by a database
  uniqueness constraint, not by UI discipline alone.

## Schema

Four new tables.

```sql
CREATE TABLE dsa_releases (
  id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  region_name    TEXT NOT NULL,
  release_date   DATE NOT NULL,
  months_covered TEXT[] NOT NULL,           -- ['2026-06','2026-07','2026-08']
  amount_per_month INTEGER NOT NULL DEFAULT 2000 CHECK (amount_per_month > 0),
  recorded_by    UUID NOT NULL REFERENCES interviewers(id),
  is_locked      BOOLEAN NOT NULL DEFAULT TRUE,
  created_at     TIMESTAMPTZ DEFAULT NOW()
);

CREATE TABLE dsa_payments (
  id            UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  release_id    UUID NOT NULL REFERENCES dsa_releases(id) ON DELETE CASCADE,
  aruga_id      TEXT NOT NULL,
  period_month  TEXT NOT NULL,              -- 'YYYY-MM'
  status        TEXT NOT NULL CHECK (status IN
                  ('paid','deceased','transferred','not_claimed')),
  created_at    TIMESTAMPTZ DEFAULT NOW(),
  UNIQUE (aruga_id, period_month)
);

CREATE TABLE dsa_release_audit (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  release_id  UUID NOT NULL REFERENCES dsa_releases(id) ON DELETE CASCADE,
  action      TEXT NOT NULL CHECK (action IN ('reopen','correct','relock')),
  reason      TEXT NOT NULL,
  performed_by UUID NOT NULL REFERENCES interviewers(id),
  details     JSONB,
  created_at  TIMESTAMPTZ DEFAULT NOW()
);

-- Standing eligibility. Absence of a row means active.
CREATE TABLE dsa_beneficiary_status (
  aruga_id    TEXT PRIMARY KEY,
  status      TEXT NOT NULL CHECK (status IN ('deceased','transferred')),
  effective_from TEXT NOT NULL,            -- 'YYYY-MM'
  set_by_release UUID REFERENCES dsa_releases(id),
  created_at  TIMESTAMPTZ DEFAULT NOW()
);

CREATE INDEX idx_dsa_payments_aruga ON dsa_payments(aruga_id);
CREATE INDEX idx_dsa_payments_month ON dsa_payments(period_month);
CREATE INDEX idx_dsa_releases_region ON dsa_releases(region_name);
```

`aruga_id` is used as the beneficiary key because it is the identifier officers
read off paperwork and the one the grid already displays. It is carried on the
`assessments` table.

The `UNIQUE (aruga_id, period_month)` constraint is the load-bearing rule: it
makes double-paying a month impossible at the database level.

## API

New actions on `api/payroll-router.php`. **No new file** — Vercel's Hobby tier
caps deployments at 12 serverless functions and `api/` already holds 9. A new
router would leave only two slots. DSA is disbursement, which is what the
payroll router already handles, and the file's existing header comment shows the
codebase has consolidated for this reason before.

| Action | Method | Role | Purpose |
|---|---|---|---|
| `dsa-grid` | GET | all | Grid rows for a region: payments by month, months-behind count |
| `dsa-resolve-ids` | POST | field_officer, admin, central | Resolve pasted ARUGA IDs to names; flag unknown ones |
| `dsa-record-release` | POST | field_officer, admin | Create release + payment rows; locks on save |
| `dsa-reopen` | POST | admin | Unlock a release, write audit row |
| `dsa-region-summary` | GET | admin, central | Per-region last release date and beneficiaries behind |

Access control reuses the existing helpers in `api/lib/auth.php`:
`requireRole()` for role gating and `requireRegion()` for confining a field
officer to their own region. No new access-control machinery is needed.

### Recording is a single bulk insert

`dsa-record-release` fetches the region's beneficiaries, subtracts the
exceptions, and writes all payment rows in one Supabase call. For 400
beneficiaries across 3 months that is 1200 rows in one request, well inside
Hobby's 10-second execution ceiling.

The endpoint must reject the whole request if any beneficiary-month in it is
already paid, rather than partially applying. Partial application would leave a
release whose payment rows do not match its stated coverage.

## Front End

Changes to `public/dashboard-admin.html`:

- `dsaState` is populated from `dsa-grid` instead of being an empty object.
  Locked circles render non-interactive.
- A **months behind** column is added to the grid and is sortable. This is what
  turns the sheet from a record of the past into something actionable: it shows
  who keeps getting missed.
- A two-step **Record Release** modal, as described above. A modal rather than a
  full page, so the officer lands back on the grid with circles filled in.
- A reopen dialog for `admin`, requiring a reason.

The paste-box approach assumes exceptions are a small fraction of a region. For
the rare case of many exceptions, the grid's individual circles remain available
before a release is recorded.

## Build Order

1. Schema migration (`supabase-migrations/dsa.sql`), verified against the live
   Supabase instance before anything is built on it.
2. `dsa-grid` + wire the existing grid to real data, replacing `dsaState`.
3. `dsa-resolve-ids` + `dsa-record-release` + the modal.
4. `dsa-reopen` + audit trail.
5. `dsa-region-summary` + central office view.

Each step is independently verifiable. Step 2 is useful on its own: the grid
stops losing its data on reload.

## Out of Scope

- Editing or voiding a release outright. Reopen plus correction covers the real
  cases; deletion would break liquidation reconciliation.
- Exporting liquidation reports. Worth doing, but it depends on this data
  existing first.
- Back-filling DSA history from before this feature ships.
