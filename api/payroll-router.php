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

        if (!function_exists('supabaseCountAS')) {
            function supabaseCountAS($endpoint) {
                $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
                    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
                    'Prefer: count=exact',
                    'Range: 0-0',
                ]);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $resp = curl_exec($ch);
                curl_close($ch);

                if (preg_match('/Content-Range:\s*[\d\*]+-?[\d\*]*\/(\d+)/i', $resp, $m)) {
                    return (int)$m[1];
                }
                return 0;
            }
        }

        $severe   = supabaseCountAS('assessments?select=id&deleted_at=is.null&readiness_score=eq.severe');
        $moderate = supabaseCountAS('assessments?select=id&deleted_at=is.null&readiness_score=eq.moderate');
        $low      = supabaseCountAS('assessments?select=id&deleted_at=is.null&readiness_score=eq.low');
        $stable   = supabaseCountAS('assessments?select=id&deleted_at=is.null&readiness_score=eq.stable');

        echo json_encode([
            'success' => true,
            'data' => [
                'severe'   => $severe,
                'moderate' => $moderate,
                'low'      => $low,
                'stable'   => $stable,
                'total'    => $severe + $moderate + $low + $stable,
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

    default: {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
    }
}
