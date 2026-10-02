<?php
/**
 * Payroll Router — consolidates:
 *   - get-payout-data.php       (action=payout-data)
 *   - get-assessment-status.php (action=assessment-status)
 *
 * Both source files require auth.php unconditionally.
 */
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/region-coverage-helper.php';

// ── Payroll → DSA release helpers ─────────────────────────────────

/** "Last, First Middle Ext" — same format printed on the payroll. */
function payrollFormatName(array $child): string {
    $first = trim($child['first_name'] ?? '');
    $last  = trim($child['last_name'] ?? '');
    $mid   = trim($child['middle_name'] ?? '');
    $ext   = trim($child['name_extension'] ?? '');
    if (strcasecmp($ext, 'None') === 0) $ext = '';
    $name = $last;
    if ($first) $name .= ', ' . $first;
    if ($mid)   $name .= ' ' . $mid;
    if ($ext)   $name .= ' ' . $ext;
    return $name;
}

/** Normalised, de-duplicated Aruga IDs printed on a payroll. */
function payrollBeneficiaryIds(array $payroll): array {
    $ids = [];
    foreach ($payroll['beneficiary_ids'] ?? [] as $id) {
        $id = strtoupper(trim((string)$id));
        if ($id !== '') $ids[$id] = true;
    }
    return array_keys($ids);
}

/**
 * Loads a generated payroll and checks it can be recorded as a DSA release.
 * Returns [payrollRow, null, null] when it has not been recorded yet, or
 * [null, errorMessage, null]. With $allowOpen, a payroll whose release an
 * admin has reopened returns [payrollRow, null, releaseRow] for correction.
 * Region access is checked by the caller (requireRegion).
 */
function loadPayrollForRelease(string $payrollId, bool $allowOpen = false): array {
    if (!preg_match('/^DSA-\d{8}-\d{4,}$/', $payrollId)) {
        return [null, 'Enter a valid Payroll ID.', null];
    }
    $res = supabaseRequest('GET', 'payroll_generations?select=payroll_id,region,period,payroll_type,'
        . 'months_covered,amount_per_month,beneficiary_ids,beneficiary_count,total_amount,created_at'
        . '&payroll_id=eq.' . urlencode($payrollId) . '&limit=1');
    if (!$res['success']) return [null, 'Could not look up the Payroll ID. Please try again.', null];
    $pg = $res['data'][0] ?? null;
    if (!$pg) return [null, 'Payroll ID not found.', null];
    if (empty($pg['beneficiary_ids']) || empty($pg['months_covered']) || empty($pg['amount_per_month'])) {
        return [null, 'Outdated payroll. Please regenerate.', null];
    }
    if (trim($pg['region'] ?? '') === '') {
        return [null, 'Payroll must be for one region. Please regenerate.', null];
    }
    $rel = supabaseRequest('GET', 'dsa_releases?select=id,release_date,is_locked'
        . '&payroll_id=eq.' . urlencode($payrollId) . '&limit=1');
    if (!$rel['success']) return [null, 'Could not look up the Payroll ID. Please try again.', null];
    if (!empty($rel['data'])) {
        $release = $rel['data'][0];
        if (!$release['is_locked'] && $allowOpen) return [$pg, null, $release];
        return [null, 'Already recorded on ' . date('F j, Y', strtotime($release['release_date']))
            . ($release['is_locked'] ? '. Ask Admin to reopen it.' : '.'), null];
    }
    return [$pg, null, null];
}

/**
 * Months already recorded (any status) for any of the given beneficiaries.
 * dsa_payments allows one row per beneficiary per month, so any existing
 * row means that month can't be paid again for that person.
 * Returns [] when clear, else [['month' => '2026-10', 'count' => 12], ...].
 */
function payrollRecordedMonths(array $ids, array $months): array {
    if (empty($ids) || empty($months)) return [];
    $set = [];
    foreach ($ids as $id) $set[strtoupper(trim((string)$id))] = true;
    $list = implode(',', array_map(fn($m) => '"' . $m . '"', $months));
    $rows = supabaseFetchAll('dsa_payments?select=aruga_id,period_month'
        . '&period_month=in.(' . urlencode($list) . ')&order=id.asc');
    $byMonth = [];
    foreach ($rows as $r) {
        $id = strtoupper(trim($r['aruga_id'] ?? ''));
        if (isset($set[$id])) $byMonth[$r['period_month']][$id] = true;
    }
    ksort($byMonth);
    $out = [];
    foreach ($byMonth as $m => $who) $out[] = ['month' => $m, 'count' => count($who)];
    return $out;
}

/** "October 2026 (12 beneficiaries) and November 2026 (3 of 15 beneficiaries)" */
function payrollRecordedSummary(array $recorded, ?int $total = null): string {
    $parts = array_map(function ($r) use ($total) {
        $n = $r['count'];
        return date('F Y', strtotime($r['month'] . '-01'))
            . ' (' . $n . ($total !== null ? ' of ' . $total : ' beneficiar' . ($n === 1 ? 'y' : 'ies')) . ')';
    }, $recorded);
    $last = array_pop($parts);
    return $parts ? implode(', ', $parts) . ' and ' . $last : $last;
}

$action = $_GET['action'] ?? '';

switch ($action) {

    // ================================================================
    // action=payout-data  (was api/get-payout-data.php)
    // ================================================================
    case 'payout-data': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false]); exit;
        }

        $region = getStr('region');

        // Fetch assessments with child data, paginating since PostgREST caps rows per request
        // regardless of the `limit` query param (project max-rows setting).
        $pageSize = 1000;
        $offset = 0;
        $assessments = [];
        while (true) {
            $endpoint = 'assessments?select=id,aruga_id,status,children(first_name,last_name,middle_name,name_extension,region)'
                . '&deleted_at=is.null&order=created_at.asc&limit=' . $pageSize . '&offset=' . $offset;
            if ($region) {
                $endpoint .= '&children.region=eq.' . urlencode($region);
            }

            $result = supabaseRequest('GET', $endpoint);

            if (!$result['success']) {
                echo json_encode(['success' => false, 'message' => 'Failed to fetch data']); exit;
            }

            $page = $result['data'] ?? [];
            $assessments = array_merge($assessments, $page);

            if (count($page) < $pageSize) break;
            $offset += $pageSize;
        }

        // Filter by region on PHP side (Supabase embedded filter may not work as expected)
        if ($region) {
            $assessments = array_filter($assessments, function($a) use ($region) {
                return isset($a['children']['region']) && $a['children']['region'] === $region;
            });
            $assessments = array_values($assessments);
        }

        if (empty($assessments)) {
            echo json_encode(['success' => true, 'data' => []]); exit;
        }

        // Get all assessment IDs
        $ids = array_column($assessments, 'id');

        // Fetch family members marked as authorized claimant for each assessment (paginated)
        $familyMap = [];
        $famOffset = 0;
        while (true) {
            $familyResult = supabaseRequest('GET',
                'family_members?select=assessment_id,full_name,is_authorized_claimant&is_authorized_claimant=eq.true'
                . '&limit=' . $pageSize . '&offset=' . $famOffset
            );

            if (!$familyResult['success']) break;

            $famPage = $familyResult['data'] ?? [];
            foreach ($famPage as $fm) {
                $aid = $fm['assessment_id'];
                $name = trim($fm['full_name'] ?? '');
                if ($name === '') continue;
                if (!isset($familyMap[$aid])) {
                    $familyMap[$aid] = [];
                }
                $familyMap[$aid][] = $name;
            }

            if (count($famPage) < $pageSize) break;
            $famOffset += $pageSize;
        }

        // Build final payout rows
        $rows = [];
        foreach ($assessments as $a) {
            $child = $a['children'] ?? [];
            $firstName     = trim($child['first_name']     ?? '');
            $lastName      = trim($child['last_name']      ?? '');
            $middleName    = trim($child['middle_name']    ?? '');
            $nameExtension = trim($child['name_extension'] ?? '');
            if (strcasecmp($nameExtension, 'None') === 0) $nameExtension = '';

            // Format: Last Name, First Name Middle Name Extension
            $beneficiaryName = $lastName;
            if ($firstName) $beneficiaryName .= ', ' . $firstName;
            if ($middleName) $beneficiaryName .= ' ' . $middleName;
            if ($nameExtension) $beneficiaryName .= ' ' . $nameExtension;

            // Authorized claimant (only one allowed)
            $claimantNames = $familyMap[$a['id']] ?? [];
            $claimantName = $claimantNames[0] ?? '';

            $rows[] = [
                'aruga_id'         => $a['aruga_id']   ?? '—',
                'beneficiary_name' => $beneficiaryName ?: '—',
                'claimant_name'    => $claimantName    ?: '—',
                'region'           => $child['region'] ?? '—',
                'amount'           => 2000,
            ];
        }

        echo json_encode(['success' => true, 'data' => $rows, 'total' => count($rows)]);
        break;
    }

    // ================================================================
    // action=assessment-status  (was api/get-assessment-status.php)
    // ================================================================
    case 'assessment-status': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        // Same population as dashboard-stats' total_beneficiaries: non-deleted
        // assessments, excluding deceased/transferred (dsa_beneficiary_status)
        $rows = supabaseFetchAll('assessments?select=aruga_id,readiness_score&deleted_at=is.null');
        $excludedIds = getExcludedArugaIds();
        $counts = ['severe' => 0, 'moderate' => 0, 'low' => 0, 'stable' => 0];
        $total = 0;
        foreach ($rows as $r) {
            if (isset($excludedIds[$r['aruga_id'] ?? ''])) continue;
            $total++;
            $rs = strtolower(trim($r['readiness_score'] ?? ''));
            if (isset($counts[$rs])) $counts[$rs]++;
        }
        ['severe' => $severe, 'moderate' => $moderate, 'low' => $low, 'stable' => $stable] = $counts;

        echo json_encode([
            'success' => true,
            'data' => [
                'severe'   => $severe,
                'moderate' => $moderate,
                'low'      => $low,
                'stable'   => $stable,
                'total'    => $total,
            ]
        ]);
        break;
    }

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
            $paidCount = 0;
            // Paid-month history is real regardless of current standing (a
            // transferred/deceased beneficiary can still have prior paid
            // months, and the Total column must reflect that). "Behind"
            // only applies to beneficiaries still actively expected to be paid.
            for ($m = 1; $m <= $lastMonth; $m++) {
                $key = sprintf('%04d-%02d', $year, $m);
                if (($months[$key] ?? '') === 'paid') $paidCount++;
                elseif (!isset($standing[$arugaId])) $behind++;
            }

            $rows[] = [
                'aruga_id'      => $arugaId,
                'name'          => $name ?: '—',
                'region'        => $c['region'] ?? '—',
                'months'        => (object)$months,
                'months_behind' => $behind,
                'months_paid'   => $paidCount,
                'standing'      => $standing[$arugaId] ?? null,
            ];
        }

        echo json_encode(['success' => true, 'data' => $rows, 'year' => $year]);
        break;
    }

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

    // ================================================================
    // action=dsa-record-release — create a locked release + payment rows
    // ================================================================
    case 'dsa-record-release': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin', 'field_officer']);

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $payrollId  = strtoupper(trim($body['payroll_id'] ?? ''));
        $date       = trim($body['release_date'] ?? '');
        $exceptions = $body['exceptions'] ?? [];

        // Every release is recorded against a verified, unused payroll.
        if ($payrollId === '') {
            echo json_encode(['success' => false, 'message' => 'A verified Payroll ID is required to record a release.']); exit;
        }
        [$pg, $err] = loadPayrollForRelease($payrollId);
        if ($err) { echo json_encode(['success' => false, 'message' => $err]); exit; }
        requireRegion($pg['region']);

        // Region, months and amount come from the payroll, never from the request.
        $region = $pg['region'];
        $months = array_values(array_unique($pg['months_covered']));
        $amount = (int)$pg['amount_per_month'];

        // ---- Validate before touching the database ----
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
            echo json_encode(['success' => false, 'message' => 'Release date must be YYYY-MM-DD.']); exit;
        }
        foreach ($months as $m) {
            if (!preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)) {
                echo json_encode(['success' => false, 'message' => 'Invalid month on payroll: ' . $m]); exit;
            }
        }
        if ($amount <= 0) {
            echo json_encode(['success' => false, 'message' => 'Payroll amount is invalid.']); exit;
        }

        // ---- Roster: exactly the beneficiaries printed on the payroll ----
        $roster = payrollBeneficiaryIds($pg);
        $rosterSet = array_flip($roster);

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
            if (!isset($rosterSet[$exId])) {
                echo json_encode(['success' => false,
                    'message' => $exId . ' is not on payroll ' . $payrollId . '.']); exit;
            }
            $exMap[$exId] = $exReason;
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
            'payroll_id'       => $payrollId,
            'region_name'      => $region,
            'release_date'     => $date,
            'months_covered'   => $months,
            'amount_per_month' => $amount,
            'recorded_by'      => $authInterviewer['id'],
            'is_locked'        => true,
        ]);
        if (!$relRes['success'] || empty($relRes['data'][0]['id'])) {
            // The unique payroll_id index rejects a second release of the same payroll.
            [, $usedErr] = loadPayrollForRelease($payrollId);
            echo json_encode(['success' => false, 'message' => $usedErr ?: 'Failed to create release.']); exit;
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

    // ================================================================
    // action=dsa-correct-by-payroll — update who received a reopened release,
    // found by its Payroll ID, then relock it. Months, region, amount and
    // payout date stay as recorded; only each person's status changes.
    // ================================================================
    case 'dsa-correct-by-payroll': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin', 'field_officer']);

        $body       = json_decode(file_get_contents('php://input'), true) ?? [];
        $payrollId  = strtoupper(trim($body['payroll_id'] ?? ''));
        $exceptions = $body['exceptions'] ?? [];

        [$pg, $err, $release] = loadPayrollForRelease($payrollId, true);
        if ($err) { echo json_encode(['success' => false, 'message' => $err]); exit; }
        if (!$release) {
            echo json_encode(['success' => false, 'message' => 'This payroll has not been recorded yet.']); exit;
        }
        requireRegion($pg['region']);
        $releaseId = $release['id'];

        $rosterSet = array_flip(payrollBeneficiaryIds($pg));
        $validReasons = ['deceased', 'transferred', 'not_claimed'];
        $exMap = [];
        foreach ($exceptions as $ex) {
            $exId     = strtoupper(trim($ex['aruga_id'] ?? ''));
            $exReason = trim($ex['reason'] ?? '');
            if ($exId === '') continue;
            if (!in_array($exReason, $validReasons, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid reason for ' . $exId . '.']); exit;
            }
            if (!isset($rosterSet[$exId])) {
                echo json_encode(['success' => false, 'message' => $exId . ' is not on payroll ' . $payrollId . '.']); exit;
            }
            $exMap[$exId] = $exReason;
        }

        // Everyone not listed as "did not receive" is paid, for every month.
        $payments = supabaseFetchAll('dsa_payments?select=id,aruga_id,period_month,status'
            . '&release_id=eq.' . urlencode($releaseId) . '&order=id.asc');
        if (empty($payments)) {
            echo json_encode(['success' => false, 'message' => 'Could not load this release. Please try again.']); exit;
        }
        $applied = [];
        foreach ($payments as $p) {
            $to = $exMap[strtoupper($p['aruga_id'])] ?? 'paid';
            if ($p['status'] === $to) continue;
            $applied[] = ['payment_id' => $p['id'], 'aruga_id' => $p['aruga_id'],
                          'period_month' => $p['period_month'], 'from' => $p['status'], 'to' => $to];
        }

        // Audit before changing data, as with reopen/correct.
        if ($applied) {
            $audit = supabaseRequest('POST', 'dsa_release_audit', [
                'release_id'   => $releaseId,
                'action'       => 'correct',
                'reason'       => 'Corrected via Payroll ID ' . $payrollId . '.',
                'performed_by' => $authInterviewer['id'],
                'details'      => $applied,
            ]);
            if (!$audit['success']) {
                echo json_encode(['success' => false, 'message' => 'Failed to write audit record.']); exit;
            }
            foreach ($applied as $ch) {
                $upd = supabaseRequest('PATCH', 'dsa_payments?id=eq.' . urlencode($ch['payment_id']), ['status' => $ch['to']]);
                if (!$upd['success']) {
                    echo json_encode(['success' => false,
                        'message' => 'Failed partway through saving. The release is still open — please retry.']); exit;
                }
            }

            // Standing status follows the correction: set for newly deceased /
            // transferred, cleared when this release had set it and it no longer applies.
            $months = $pg['months_covered']; sort($months);
            $touched = [];
            foreach ($applied as $ch) $touched[strtoupper($ch['aruga_id'])] = $ch['aruga_id'];
            foreach ($touched as $key => $arugaId) {
                $newStatus = $exMap[$key] ?? 'paid';
                if ($newStatus === 'deceased' || $newStatus === 'transferred') {
                    supabaseRequest('POST', 'dsa_beneficiary_status', [
                        'aruga_id' => $arugaId, 'status' => $newStatus,
                        'effective_from' => $months[0], 'set_by_release' => $releaseId,
                    ]);
                } else {
                    supabaseRequest('DELETE', 'dsa_beneficiary_status?aruga_id=eq.' . urlencode($arugaId)
                        . '&set_by_release=eq.' . urlencode($releaseId));
                }
            }
        }

        supabaseRequest('POST', 'dsa_release_audit', [
            'release_id'   => $releaseId,
            'action'       => 'relock',
            'reason'       => $applied ? 'Auto-relocked after correction.' : 'Relocked with no changes.',
            'performed_by' => $authInterviewer['id'],
        ]);
        $lock = supabaseRequest('PATCH', 'dsa_releases?id=eq.' . urlencode($releaseId), ['is_locked' => true]);
        if (!$lock['success']) {
            echo json_encode(['success' => false,
                'message' => 'Changes saved but the release could not be relocked. Please try saving again.']); exit;
        }

        $missed = 0;
        foreach (array_keys($rosterSet) as $id) if (isset($exMap[$id])) $missed++;
        $paid = count($rosterSet) - $missed;
        echo json_encode(['success' => true, 'data' => [
            'changed'      => count($applied),
            'paid_count'   => $paid,
            'missed_count' => $missed,
            'total_amount' => $paid * (int)$pg['amount_per_month'] * count($pg['months_covered']),
        ]]);
        break;
    }

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

        $endpoint = 'dsa_releases?select=id,payroll_id,region_name,release_date,months_covered,'
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

    // ================================================================
    // action=dsa-relock — admin locks an open release again, audited
    // ================================================================
    case 'dsa-relock': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin']);

        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $releaseId = trim($body['release_id'] ?? '');
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $releaseId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid release id.']); exit;
        }
        $cur = supabaseRequest('GET', 'dsa_releases?select=id,is_locked&id=eq.' . urlencode($releaseId) . '&limit=1');
        if (!$cur['success'] || empty($cur['data'])) {
            echo json_encode(['success' => false, 'message' => 'Release not found.']); exit;
        }
        if ($cur['data'][0]['is_locked']) {
            echo json_encode(['success' => false, 'message' => 'Release is already locked.']); exit;
        }
        $audit = supabaseRequest('POST', 'dsa_release_audit', [
            'release_id'   => $releaseId,
            'action'       => 'relock',
            'reason'       => 'Locked manually from Release History.',
            'performed_by' => $authInterviewer['id'],
        ]);
        if (!$audit['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to write audit record.']); exit;
        }
        $upd = supabaseRequest('PATCH', 'dsa_releases?id=eq.' . urlencode($releaseId), ['is_locked' => true]);
        if (!$upd['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to lock release.']); exit;
        }
        echo json_encode(['success' => true, 'message' => 'Release locked.']);
        break;
    }

    // ================================================================
    // action=dsa-release-detail — a reopened release's own rows, for editing
    // ================================================================
    case 'dsa-release-detail': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin']);

        $releaseId = getStr('release_id');
        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        if (!preg_match($uuidPattern, $releaseId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid release id.']); exit;
        }

        $relRes = supabaseRequest('GET',
            'dsa_releases?select=id,region_name,release_date,months_covered,amount_per_month,is_locked'
            . '&id=eq.' . urlencode($releaseId) . '&limit=1');
        if (!$relRes['success'] || empty($relRes['data'])) {
            echo json_encode(['success' => false, 'message' => 'Release not found.']); exit;
        }
        $release = $relRes['data'][0];
        if ($release['is_locked']) {
            echo json_encode(['success' => false,
                'message' => 'Release is locked. Reopen it before editing.']); exit;
        }

        // This release's own payment rows only — editing is scoped to what
        // this release actually created, not the whole region.
        $payRes = supabaseRequest('GET',
            'dsa_payments?select=id,aruga_id,period_month,status'
            . '&release_id=eq.' . urlencode($releaseId) . '&order=aruga_id.asc&limit=10000');
        if (!$payRes['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch payments']); exit;
        }

        $arugaIds = array_values(array_unique(array_column($payRes['data'] ?? [], 'aruga_id')));
        $nameMap = [];
        if (!empty($arugaIds)) {
            $quoted = array_map(function ($id) { return '"' . str_replace('"', '', $id) . '"'; }, $arugaIds);
            $nameRes = supabaseRequest('GET',
                'assessments?select=aruga_id,children(first_name,last_name,middle_name,name_extension)'
                . '&aruga_id=in.(' . urlencode(implode(',', $quoted)) . ')&limit=10000');
            if ($nameRes['success']) {
                foreach ($nameRes['data'] ?? [] as $a) {
                    $c = $a['children'] ?? [];
                    $ext = trim($c['name_extension'] ?? '');
                    if (strcasecmp($ext, 'None') === 0) $ext = '';
                    $nameMap[$a['aruga_id']] = trim(implode(' ', array_filter([
                        trim($c['first_name'] ?? ''), trim($c['middle_name'] ?? ''),
                        trim($c['last_name'] ?? ''), $ext,
                    ]))) ?: '—';
                }
            }
        }

        // Group by beneficiary so the edit UI shows one row per person,
        // one column per month covered by this release.
        $byBeneficiary = [];
        foreach ($payRes['data'] ?? [] as $p) {
            $aid = $p['aruga_id'];
            if (!isset($byBeneficiary[$aid])) {
                $byBeneficiary[$aid] = ['aruga_id' => $aid, 'name' => $nameMap[$aid] ?? '—', 'months' => []];
            }
            $byBeneficiary[$aid]['months'][$p['period_month']] = ['payment_id' => $p['id'], 'status' => $p['status']];
        }

        echo json_encode(['success' => true, 'data' => [
            'release' => $release,
            'beneficiaries' => array_values($byBeneficiary),
        ]]);
        break;
    }

    // ================================================================
    // action=dsa-correct-release — apply edits to a reopened release, relock
    // ================================================================
    case 'dsa-correct-release': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin']);

        $body      = json_decode(file_get_contents('php://input'), true) ?? [];
        $releaseId = trim($body['release_id'] ?? '');
        $reason    = trim($body['reason'] ?? '');
        $changes   = $body['changes'] ?? [];   // [{payment_id, status}]

        $uuidPattern = '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i';
        if (!preg_match($uuidPattern, $releaseId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid release id.']); exit;
        }
        if (strlen($reason) < 5) {
            echo json_encode(['success' => false,
                'message' => 'A reason of at least 5 characters is required.']); exit;
        }
        if (!is_array($changes) || empty($changes)) {
            echo json_encode(['success' => false, 'message' => 'No changes to apply.']); exit;
        }

        $cur = supabaseRequest('GET',
            'dsa_releases?select=id,is_locked&id=eq.' . urlencode($releaseId) . '&limit=1');
        if (!$cur['success'] || empty($cur['data'])) {
            echo json_encode(['success' => false, 'message' => 'Release not found.']); exit;
        }
        if ($cur['data'][0]['is_locked']) {
            echo json_encode(['success' => false,
                'message' => 'Release is locked. Reopen it before editing.']); exit;
        }

        // Load current rows for this release so we know what is actually
        // changing (for the audit trail) and can reject ids that don't belong.
        $payRes = supabaseRequest('GET',
            'dsa_payments?select=id,aruga_id,period_month,status&release_id=eq.' . urlencode($releaseId)
            . '&limit=10000');
        if (!$payRes['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to load current rows']); exit;
        }
        $current = [];
        foreach ($payRes['data'] ?? [] as $p) $current[$p['id']] = $p;

        $validStatuses = ['paid', 'deceased', 'transferred', 'not_claimed'];
        $applied = [];
        foreach ($changes as $ch) {
            $pid    = trim($ch['payment_id'] ?? '');
            $status = trim($ch['status'] ?? '');
            if (!preg_match($uuidPattern, $pid)) {
                echo json_encode(['success' => false, 'message' => 'Invalid payment id: ' . $pid]); exit;
            }
            if (!in_array($status, $validStatuses, true)) {
                echo json_encode(['success' => false, 'message' => 'Invalid status: ' . $status]); exit;
            }
            if (!isset($current[$pid])) {
                echo json_encode(['success' => false,
                    'message' => 'Payment ' . $pid . ' does not belong to this release.']); exit;
            }
            if ($current[$pid]['status'] === $status) continue; // no-op, skip
            $applied[] = [
                'payment_id' => $pid,
                'aruga_id'   => $current[$pid]['aruga_id'],
                'period_month' => $current[$pid]['period_month'],
                'from' => $current[$pid]['status'],
                'to'   => $status,
            ];
        }

        if (empty($applied)) {
            echo json_encode(['success' => false, 'message' => 'Nothing changed.']); exit;
        }

        // Audit the exact before/after values first — same reasoning as
        // reopen: a correction that isn't recorded is the failure mode that
        // matters, so writing it must happen before the data changes.
        $audit = supabaseRequest('POST', 'dsa_release_audit', [
            'release_id'   => $releaseId,
            'action'       => 'correct',
            'reason'       => $reason,
            'performed_by' => $authInterviewer['id'],
            'details'      => $applied,
        ]);
        if (!$audit['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to write audit record.']); exit;
        }

        foreach ($applied as $ch) {
            $upd = supabaseRequest('PATCH',
                'dsa_payments?id=eq.' . urlencode($ch['payment_id']), ['status' => $ch['to']]);
            if (!$upd['success']) {
                echo json_encode(['success' => false,
                    'message' => 'Failed partway through applying changes. Release is still open — review and retry.']); exit;
            }
        }

        // Relock as part of the same save — an edit left open is a release
        // nobody has actually finished correcting.
        $relockAudit = supabaseRequest('POST', 'dsa_release_audit', [
            'release_id'   => $releaseId,
            'action'       => 'relock',
            'reason'       => 'Auto-relocked after correction.',
            'performed_by' => $authInterviewer['id'],
        ]);
        $upd = supabaseRequest('PATCH',
            'dsa_releases?id=eq.' . urlencode($releaseId), ['is_locked' => true]);
        if (!$upd['success']) {
            echo json_encode(['success' => false,
                'message' => 'Changes saved but relock failed. Release is still open — relock manually.']); exit;
        }

        echo json_encode(['success' => true, 'data' => ['applied' => count($applied)]]);
        break;
    }

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

    // ================================================================
    // action=dsa-region-breakdown — one region's releases for a year with
    // paid / not-paid counts and amounts (read-only, for DSA Status)
    // ================================================================
    case 'dsa-region-breakdown': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin', 'central']);

        $region = getStr('region');
        $year   = (int)(getStr('year') ?: date('Y'));
        if ($region === '') {
            echo json_encode(['success' => false, 'message' => 'Region is required.']); exit;
        }

        $relRes = supabaseRequest('GET', 'dsa_releases?select=id,payroll_id,release_date,months_covered,'
            . 'amount_per_month,is_locked&region_name=eq.' . urlencode($region)
            . '&order=release_date.desc&limit=500');
        if (!$relRes['success']) {
            echo json_encode(['success' => false, 'message' => 'Failed to fetch releases']); exit;
        }
        $releases = $relRes['data'] ?? [];

        $stats = [];
        if ($releases) {
            $ids = array_column($releases, 'id');
            $payments = supabaseFetchAll('dsa_payments?select=release_id,aruga_id,period_month,status'
                . '&release_id=in.(' . urlencode(implode(',', $ids)) . ')&order=id.asc');
            $amountOf = array_column($releases, 'amount_per_month', 'id');
            foreach ($payments as $p) {
                // Same year scope as DSA Status' Total Disbursed.
                if (substr($p['period_month'], 0, 4) !== (string)$year) continue;
                $rid = $p['release_id'];
                $st  = &$stats[$rid];
                $st['all'][$p['aruga_id']] = true;
                if ($p['status'] === 'paid') {
                    $st['amount'] = ($st['amount'] ?? 0) + (int)($amountOf[$rid] ?? 2000);
                } else {
                    $st['missed'][$p['aruga_id']] = true;
                }
                unset($st);
            }
        }

        $rows = []; $total = 0; $paidPeople = 0; $missedPeople = 0;
        foreach ($releases as $r) {
            if (!isset($stats[$r['id']])) continue;   // nothing in this year
            $st = $stats[$r['id']];
            $people = count($st['all']);
            $missed = count($st['missed'] ?? []);
            $amount = $st['amount'] ?? 0;
            $total += $amount; $paidPeople += $people - $missed; $missedPeople += $missed;
            $months = $r['months_covered'] ?? [];
            sort($months);
            $rows[] = [
                'id'             => $r['id'],
                'payroll_id'     => $r['payroll_id'],
                'release_date'   => $r['release_date'],
                'months_covered' => $months,
                'is_locked'      => $r['is_locked'],
                'beneficiaries'  => $people,
                'paid'           => $people - $missed,
                'not_paid'       => $missed,
                'amount'         => $amount,
            ];
        }

        echo json_encode(['success' => true, 'data' => [
            'region'          => $region,
            'year'            => $year,
            'total_disbursed' => $total,
            'release_count'   => count($rows),
            'paid'            => $paidPeople,
            'not_paid'        => $missedPeople,
            'releases'        => $rows,
        ]]);
        break;
    }

    // ================================================================
    // action=dsa-release-view — everyone on one release with their status
    // per month (read-only; works for locked releases)
    // ================================================================
    case 'dsa-release-view': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin', 'central']);

        $releaseId = getStr('release_id');
        if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $releaseId)) {
            echo json_encode(['success' => false, 'message' => 'Invalid release id.']); exit;
        }
        $relRes = supabaseRequest('GET', 'dsa_releases?select=id,payroll_id,region_name,release_date,'
            . 'months_covered,amount_per_month,is_locked&id=eq.' . urlencode($releaseId) . '&limit=1');
        if (!$relRes['success'] || empty($relRes['data'])) {
            echo json_encode(['success' => false, 'message' => 'Release not found.']); exit;
        }
        $release = $relRes['data'][0];
        $amount  = (int)$release['amount_per_month'];

        $payments = supabaseFetchAll('dsa_payments?select=aruga_id,period_month,status'
            . '&release_id=eq.' . urlencode($releaseId) . '&order=id.asc');

        $names = [];
        foreach (supabaseFetchAll('assessments?select=aruga_id,deleted_at,'
            . 'children(first_name,last_name,middle_name,name_extension)') as $a) {
            $k = $a['aruga_id'] ?? '';
            if ($k === '' || (isset($names[$k]) && !empty($a['deleted_at']))) continue;
            $names[$k] = payrollFormatName($a['children'] ?? []);
        }

        $byPerson = [];
        foreach ($payments as $p) {
            $aid = $p['aruga_id'];
            if (!isset($byPerson[$aid])) {
                $byPerson[$aid] = ['aruga_id' => $aid, 'name' => ($names[$aid] ?? '') ?: $aid,
                                   'months' => [], 'amount' => 0];
            }
            $byPerson[$aid]['months'][$p['period_month']] = $p['status'];
            if ($p['status'] === 'paid') $byPerson[$aid]['amount'] += $amount;
        }
        $rows = array_values($byPerson);
        usort($rows, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        $months = $release['months_covered'] ?? [];
        sort($months);
        $release['months_covered'] = $months;
        echo json_encode(['success' => true, 'data' => ['release' => $release, 'beneficiaries' => $rows]]);
        break;
    }

    // ================================================================
    // action=dsa-verify-payroll — checks a Payroll ID can be recorded as a
    // release and returns its snapshot, flagging anyone whose record
    // changed since the payroll was printed
    // ================================================================
    case 'dsa-verify-payroll': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin', 'field_officer']);

        $payrollId = strtoupper(trim(getStr('payroll_id')));
        [$pg, $err, $openRelease] = loadPayrollForRelease($payrollId, true);
        if ($err) { echo json_encode(['success' => false, 'message' => $err]); exit; }
        requireRegion($pg['region']);

        $ids = payrollBeneficiaryIds($pg);

        // Correction mode: each person's current status on the reopened release
        // (the first non-paid month wins, since a correction sets all their months).
        $currentStatus = [];
        if ($openRelease) {
            foreach (supabaseFetchAll('dsa_payments?select=aruga_id,status&release_id=eq.'
                . urlencode($openRelease['id']) . '&order=id.asc') as $p) {
                $k = strtoupper($p['aruga_id']);
                if (!isset($currentStatus[$k]) || $currentStatus[$k] === 'paid') $currentStatus[$k] = $p['status'];
            }
        }

        // Reject now rather than at save if any month is already recorded.
        $recorded = $openRelease ? [] : payrollRecordedMonths($ids, $pg['months_covered']);
        if ($recorded) {
            echo json_encode(['success' => false, 'message' =>
                'Already recorded: '
                . payrollRecordedSummary($recorded, count($ids)) . '.']); exit;
        }

        // Current state of everyone on the payroll (deleted rows included,
        // so a removed record can be reported rather than silently missing).
        $byId = [];
        foreach (supabaseFetchAll('assessments?select=aruga_id,deleted_at,'
            . 'children(first_name,last_name,middle_name,name_extension,region)') as $a) {
            $k = strtoupper(trim($a['aruga_id'] ?? ''));
            if ($k === '') continue;
            if (!isset($byId[$k]) || (empty($a['deleted_at']) && !empty($byId[$k]['deleted_at']))) $byId[$k] = $a;
        }
        $standing = [];
        foreach (supabaseFetchAll('dsa_beneficiary_status?select=aruga_id,status,created_at') as $st) {
            $standing[strtoupper($st['aruga_id'])] = $st;
        }
        $fmtDate = fn($ts) => $ts ? ' on ' . date('M j, Y', strtotime($ts)) : '';

        $beneficiaries = [];
        foreach ($ids as $id) {
            $a = $byId[$id] ?? null;
            $name = $a ? payrollFormatName($a['children'] ?? []) : '';
            $flag = null;
            if (!$a || !empty($a['deleted_at'])) {
                $flag = ['type' => 'deleted', 'reason' => 'not_claimed',
                         'label' => 'Record deleted' . $fmtDate($a['deleted_at'] ?? null)];
            } elseif (isset($standing[$id])) {
                $st = $standing[$id];
                $flag = ['type' => $st['status'], 'reason' => $st['status'],
                         'label' => 'Marked ' . ($st['status'] === 'deceased' ? 'Deceased' : 'Transferred') . $fmtDate($st['created_at'] ?? null)];
            } elseif (($a['children']['region'] ?? '') !== $pg['region']) {
                $flag = ['type' => 'moved', 'reason' => null,
                         'label' => 'Now in ' . (($a['children']['region'] ?? '') ?: 'another region')];
            }
            $beneficiaries[] = ['aruga_id' => $id, 'name' => $name !== '' ? $name : $id, 'flag' => $flag,
                                'current_status' => $currentStatus[$id] ?? null];
        }

        $months = $pg['months_covered'];
        sort($months);
        $amount = (int)$pg['amount_per_month'];
        echo json_encode(['success' => true, 'data' => [
            'payroll_id'       => $pg['payroll_id'],
            'region'           => $pg['region'],
            'period'           => $pg['period'],
            'payroll_type'     => $pg['payroll_type'] ?: 'complete',
            'months_covered'   => $months,
            'amount_per_month' => $amount,
            'beneficiary_count'=> count($ids),
            'total_amount'     => count($ids) * $amount * count($months),
            'generated_at'     => $pg['created_at'],
            'mode'             => $openRelease ? 'correct' : 'record',
            'release_id'       => $openRelease['id'] ?? null,
            'release_date'     => $openRelease['release_date'] ?? null,
            'beneficiaries'    => $beneficiaries,
        ]]);
        break;
    }

    // ================================================================
    // action=create-payroll-id — assigns a Payroll ID (DSA-MMDDYYYY-NNNN)
    // to a generated payroll and records it in payroll_generations
    // ================================================================
    case 'create-payroll-id': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin', 'central', 'stu_head']);

        $body   = json_decode(file_get_contents('php://input'), true) ?? [];
        $region = trim($body['region'] ?? '');
        $period = trim($body['period'] ?? '');
        $count  = max(0, (int)($body['beneficiary_count'] ?? 0));
        $total  = max(0, (float)($body['total_amount'] ?? 0));

        // Snapshot of exactly what was printed, used later to record the release.
        $type = ($body['payroll_type'] ?? '') === 'special' ? 'special' : 'complete';
        $monthsCovered = [];
        foreach ((array)($body['months_covered'] ?? []) as $m) {
            if (is_string($m) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $m)) $monthsCovered[$m] = true;
        }
        $monthsCovered = array_keys($monthsCovered);
        sort($monthsCovered);
        $amountPerMonth = (int)($body['amount_per_month'] ?? 0);
        $benIds = [];
        foreach ((array)($body['beneficiary_ids'] ?? []) as $id) {
            $id = strtoupper(trim((string)$id));
            if ($id !== '' && preg_match('/^[A-Z0-9-]{1,40}$/', $id)) $benIds[$id] = true;
        }
        $benIds = array_keys($benIds);
        if (count($benIds) > 20000) {
            echo json_encode(['success' => false, 'message' => 'Payroll is too large.']); exit;
        }

        // Don't issue an ID (and so don't print) a payroll that could never be recorded.
        $recorded = payrollRecordedMonths($benIds, $monthsCovered);
        if ($recorded) {
            echo json_encode(['success' => false, 'message' =>
                'Already recorded: '
                . payrollRecordedSummary($recorded) . '. Choose another period.']); exit;
        }

        $rpc = supabaseRPC('create_payroll_generation', [
            'p_generated_by'      => $authInterviewer['id'],
            'p_region'            => $region !== '' ? $region : null,
            'p_period'            => $period,
            'p_beneficiary_count' => $count,
            'p_total_amount'      => $total,
            'p_ip_address'        => getUserIP(),
            'p_payroll_type'      => $type,
            'p_months_covered'    => $monthsCovered ?: null,
            'p_amount_per_month'  => $amountPerMonth > 0 ? $amountPerMonth : null,
            'p_beneficiary_ids'   => $benIds ?: null,
        ]);

        if (!$rpc['success'] || !is_string($rpc['data']) || $rpc['data'] === '') {
            echo json_encode(['success' => false, 'message' => 'Could not assign a Payroll ID.']); exit;
        }

        $payrollId = $rpc['data'];
        logAudit('create', 'payroll_generations', null, null, [
            'payroll_id'        => $payrollId,
            'region'            => $region,
            'period'            => $period,
            'beneficiary_count' => $count,
            'total_amount'      => $total,
            'payroll_type'      => $type,
            'months_covered'    => $monthsCovered,
        ], $authInterviewer['id']);

        echo json_encode(['success' => true, 'payroll_id' => $payrollId]);
        break;
    }

    // ================================================================
    // action=payroll-generations — most recent generated payrolls
    // ================================================================
    case 'payroll-generations': {
        header('Content-Type: application/json');
        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }
        requireRole(['admin', 'central']);

        $limit = getInt('limit', 40, 1, 1000);
        $res = supabaseRequest('GET',
            'payroll_generations?select=payroll_id,region,period,beneficiary_count,total_amount,ip_address,created_at,'
            . 'interviewers(full_name,interviewer_code,region)'
            . '&order=created_at.desc&limit=' . $limit);

        if (!$res['success'] || !is_array($res['data'])) {
            echo json_encode(['success' => false, 'data' => []]); exit;
        }

        $rows = array_map(function ($r) {
            $int = $r['interviewers'] ?? null;
            return [
                'payroll_id'        => $r['payroll_id'],
                'region'            => $r['region'] ?? '',
                'period'            => $r['period'] ?? '',
                'beneficiary_count' => (int)($r['beneficiary_count'] ?? 0),
                'total_amount'      => (float)($r['total_amount'] ?? 0),
                'ip_address'        => $r['ip_address'] ?: '—',
                'timestamp_raw'     => $r['created_at'],
                'user'              => $int['full_name'] ?? 'System',
                'code'              => $int['interviewer_code'] ?? '—',
                'user_region'       => $int['region'] ?? '—',
            ];
        }, $res['data']);

        echo json_encode(['success' => true, 'data' => $rows]);
        break;
    }

    default: {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
    }
}
