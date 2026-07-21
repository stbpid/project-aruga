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

    default: {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
    }
}
