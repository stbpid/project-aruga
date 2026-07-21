<?php
/**
 * Interviewers Router — consolidates:
 *   - add-interviewer.php    (action=add)
 *   - get-interviewers.php   (action=list)
 *   - update-interviewer.php (action=update)
 *
 * All three source files require auth.php unconditionally.
 */
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';

$action = $_GET['action'] ?? '';

// Shared helper — identical local normalizeRegion() copy used only by get-interviewers.php originally
if (!function_exists('normalizeRegion')) {
    function normalizeRegion($r) {
        $r = trim($r ?? '');
        $map = [
            'NCR'                          => 'NCR (National Capital Region)',
            'NCR – Metro Manila'           => 'NCR (National Capital Region)',
            'NCR - Metro Manila'           => 'NCR (National Capital Region)',
            'National Capital Region'      => 'NCR (National Capital Region)',
            'Region I – Ilocos Region'     => 'Region I (Ilocos Region)',
            'Region I - Ilocos Region'     => 'Region I (Ilocos Region)',
            'Region II – Cagayan Valley'   => 'Region II (Cagayan Valley)',
            'Region II - Cagayan Valley'   => 'Region II (Cagayan Valley)',
            'Region III – Central Luzon'   => 'Region III (Central Luzon)',
            'Region III - Central Luzon'   => 'Region III (Central Luzon)',
            'Region IV-A – CALABARZON'     => 'Region IV-A (CALABARZON)',
            'Region IV-A - CALABARZON'     => 'Region IV-A (CALABARZON)',
            'CALABARZON'                   => 'Region IV-A (CALABARZON)',
            'Region IV-B – MIMAROPA'       => 'Region IV-B (MIMAROPA)',
            'Region IV-B - MIMAROPA'       => 'Region IV-B (MIMAROPA)',
            'MIMAROPA'                     => 'Region IV-B (MIMAROPA)',
            'Region V – Bicol Region'      => 'Region V (Bicol Region)',
            'Region V - Bicol Region'      => 'Region V (Bicol Region)',
            'Bicol Region'                 => 'Region V (Bicol Region)',
            'Region VI – Western Visayas'  => 'Region VI (Western Visayas)',
            'Region VI - Western Visayas'  => 'Region VI (Western Visayas)',
            'Region VII – Central Visayas' => 'Region VII (Central Visayas)',
            'Region VII - Central Visayas' => 'Region VII (Central Visayas)',
            'Region VIII – Eastern Visayas'=> 'Region VIII (Eastern Visayas)',
            'Region VIII - Eastern Visayas'=> 'Region VIII (Eastern Visayas)',
            'Region IX – Zamboanga Peninsula'=> 'Region IX (Zamboanga Peninsula)',
            'Region IX - Zamboanga Peninsula'=> 'Region IX (Zamboanga Peninsula)',
            'Region X – Northern Mindanao' => 'Region X (Northern Mindanao)',
            'Region X - Northern Mindanao' => 'Region X (Northern Mindanao)',
            'Region XI – Davao Region'     => 'Region XI (Davao Region)',
            'Region XI - Davao Region'     => 'Region XI (Davao Region)',
            'Region XII – SOCCSKSARGEN'    => 'Region XII (SOCCSKSARGEN)',
            'Region XII - SOCCSKSARGEN'    => 'Region XII (SOCCSKSARGEN)',
            'Region XIII – Caraga'         => 'Region XIII (Caraga)',
            'Region XIII - Caraga'         => 'Region XIII (Caraga)',
            'Caraga'                       => 'Region XIII (Caraga)',
            'CAR – Cordillera'             => 'CAR (Cordillera Administrative Region)',
            'CAR - Cordillera'             => 'CAR (Cordillera Administrative Region)',
            'CAR'                          => 'CAR (Cordillera Administrative Region)',
            'Cordillera'                   => 'CAR (Cordillera Administrative Region)',
            'BARMM'                        => 'BARMM (Bangsamoro)',
            'Bangsamoro'                   => 'BARMM (Bangsamoro)',
        ];
        return $map[$r] ?? ($r ?: '—');
    }
}

switch ($action) {

    // ================================================================
    // action=add  (was api/add-interviewer.php)
    // ================================================================
    case 'add': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');
        requireRole(['admin']);

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) { echo json_encode(['success' => false, 'message' => 'Invalid JSON']); exit; }

        $name          = trim($body['full_name']        ?? '');
        $code          = trim($body['interviewer_code'] ?? '');
        $region        = trim($body['region']           ?? '');
        $province      = trim($body['province']         ?? '');
        $position      = trim($body['position']         ?? '');
        $office        = trim($body['office']           ?? '');
        $email          = trim($body['email']            ?? '');
        $password       = trim($body['password']         ?? '');
        $accountStatus  = trim($body['accountStatus']    ?? 'active');
        $dashboardRole  = trim($body['dashboard_role']   ?? 'field_officer');

        if (!$name || !$code || !$region) {
            echo json_encode(['success' => false, 'message' => 'Name, Code, and Region are required.']); exit;
        }

        if ($email && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'message' => 'Invalid email format.']); exit;
        }

        if ($email && strlen($password) < 8) {
            echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']); exit;
        }

        // Check email uniqueness
        if ($email) {
            $check = supabaseRequest('GET', 'interviewers?select=id&email=eq.' . urlencode($email) . '&limit=1');
            if ($check['success'] && !empty($check['data'])) {
                echo json_encode(['success' => false, 'message' => 'An interviewer with this email already exists.']); exit;
            }
        }

        $payload = [
            'full_name'        => $name,
            'interviewer_code' => $code,
            'region'           => $region,
            'province'         => $province,
            'position'         => $position,
            'office'           => $office,
            'status'           => $accountStatus,
            'dashboard_role'   => $dashboardRole,
        ];

        if ($email)    $payload['email']         = $email;
        if ($password) $payload['password_hash'] = password_hash($password, PASSWORD_DEFAULT);

        $res = supabaseRequest('POST', 'interviewers', $payload);

        if (!$res['success']) {
            error_log('add-interviewer error: ' . ($res['error'] ?? 'Unknown'));
            echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']); exit;
        }

        $newId = $res['data'][0]['id'] ?? null;
        logAudit('create', 'interviewers', $newId, null,
            ['full_name' => $name, 'interviewer_code' => $code, 'region' => $region, 'email' => $email ?: null, 'status' => $accountStatus, 'dashboard_role' => $dashboardRole],
            $newId
        );

        echo json_encode(['success' => true, 'message' => 'Interviewer added successfully']);
        break;
    }

    // ================================================================
    // action=list  (was api/get-interviewers.php)
    // ================================================================
    case 'list': {
        header('Content-Type: application/json');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false]); exit;
        }

        $res = supabaseRequest('GET',
            'interviewers?select=id,full_name,interviewer_code,email,region,province,position,office,status,dashboard_role&order=full_name.asc&limit=10000'
        );

        if (!$res['success']) {
            echo json_encode(['success' => false, 'data' => []]); exit;
        }

        // Fetch all assessments for submission counts
        $monthStart = date('Y-m-01T00:00:00');
        $monthEnd   = date('Y-m-t') . 'T23:59:59';

        $allRes = supabaseRequest('GET',
            'assessments?select=interviewer_code,status,created_at&limit=100000'
        );

        // Build per-code totals, monthly counts, and last active date
        $totalMap       = [];
        $monthMap       = [];
        $completedTotal = [];
        $completedMonth = [];
        $lastActiveMap  = [];

        if ($allRes['success'] && is_array($allRes['data'])) {
            foreach ($allRes['data'] as $a) {
                $code = $a['interviewer_code'] ?? '';
                if (!$code) continue;
                $totalMap[$code] = ($totalMap[$code] ?? 0) + 1;
                if ($a['status'] === 'completed') $completedTotal[$code] = ($completedTotal[$code] ?? 0) + 1;
                $createdAt = $a['created_at'] ?? '';
                if ($createdAt >= $monthStart && $createdAt <= $monthEnd) {
                    $monthMap[$code] = ($monthMap[$code] ?? 0) + 1;
                    if ($a['status'] === 'completed') $completedMonth[$code] = ($completedMonth[$code] ?? 0) + 1;
                }
                if ($createdAt && (!isset($lastActiveMap[$code]) || $createdAt > $lastActiveMap[$code])) {
                    $lastActiveMap[$code] = $createdAt;
                }
            }
        }

        // Also check sessions for last login activity
        $sessRes = supabaseRequest('GET', 'sessions?select=interviewer_code,created_at&limit=100000');
        if ($sessRes['success'] && is_array($sessRes['data'])) {
            foreach ($sessRes['data'] as $s) {
                $code = $s['interviewer_code'] ?? '';
                $createdAt = $s['created_at'] ?? '';
                if (!$code || !$createdAt) continue;
                if (!isset($lastActiveMap[$code]) || $createdAt > $lastActiveMap[$code]) {
                    $lastActiveMap[$code] = $createdAt;
                }
            }
        }

        $rows = array_map(function($r) use ($totalMap, $monthMap, $completedTotal, $completedMonth, $lastActiveMap) {
            $code  = $r['interviewer_code'] ?? '—';
            $total = $totalMap[$code] ?? 0;
            $month = $monthMap[$code] ?? 0;
            $cTotal = $completedTotal[$code] ?? 0;
            $cMonth = $completedMonth[$code] ?? 0;
            $lastActive = $lastActiveMap[$code] ?? null;
            return [
                'id'               => $r['id'] ?? null,
                'name'             => $r['full_name'] ?? '—',
                'code'             => $code,
                'email'            => $r['email'] ?? '—',
                'dashboard_role'   => $r['dashboard_role'] ?? '—',
                'region'           => normalizeRegion($r['region'] ?? ''),
                'province'         => $r['province'] ?? '—',
                'position'         => $r['position'] ?? '—',
                'office'           => $r['office'] ?? '—',
                'status'           => $r['status'] ?? 'active',
                'submissions_month'=> $month,
                'submissions_total'=> $total,
                'completed_month'  => $cMonth,
                'completed_total'  => $cTotal,
                'last_active'      => $lastActive,
            ];
        }, $res['data']);

        echo json_encode(['success' => true, 'data' => $rows]);
        break;
    }

    // ================================================================
    // action=update  (was api/update-interviewer.php)
    // ================================================================
    case 'update': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: POST, OPTIONS');
        requireRole(['admin']);
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }

        $body = json_decode(file_get_contents('php://input'), true);
        if (!$body) { echo json_encode(['success' => false, 'message' => 'Invalid JSON']); exit; }

        $code = trim($body['interviewer_code'] ?? '');
        if (!$code) { echo json_encode(['success' => false, 'message' => 'interviewer_code is required']); exit; }

        $fields = [];
        if (!empty($body['full_name']))  $fields['full_name']  = trim($body['full_name']);
        if (!empty($body['region']))     $fields['region']     = trim($body['region']);
        if (!empty($body['province']))   $fields['province']   = trim($body['province']);
        if (!empty($body['position']))   $fields['position']   = trim($body['position']);
        if (!empty($body['office']))     $fields['office']     = trim($body['office']);
        if (isset($body['status']))          $fields['status']         = trim($body['status']);
        if (isset($body['dashboard_role']))  $fields['dashboard_role'] = trim($body['dashboard_role']);

        // Code change
        if (!empty($body['new_code'])) {
            $newCode = trim($body['new_code']);
            if ($newCode !== $code) {
                $codeCheck = supabaseRequest('GET', 'interviewers?select=id&interviewer_code=eq.' . urlencode($newCode) . '&limit=1');
                if ($codeCheck['success'] && !empty($codeCheck['data'])) {
                    echo json_encode(['success' => false, 'message' => 'This code is already used by another interviewer.']); exit;
                }
                $fields['interviewer_code'] = $newCode;
            }
        }

        // Email
        if (isset($body['email']) && $body['email'] !== '') {
            $email = trim($body['email']);
            if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                echo json_encode(['success' => false, 'message' => 'Invalid email format.']); exit;
            }
            // Check uniqueness (exclude self)
            $check = supabaseRequest('GET', 'interviewers?select=id&email=eq.' . urlencode($email) . '&interviewer_code=neq.' . urlencode($code) . '&limit=1');
            if ($check['success'] && !empty($check['data'])) {
                echo json_encode(['success' => false, 'message' => 'This email is already used by another interviewer.']); exit;
            }
            $fields['email'] = $email;
        }

        // Password change
        if (!empty($body['password'])) {
            $pw = $body['password'];
            if (strlen($pw) < 8) {
                echo json_encode(['success' => false, 'message' => 'Password must be at least 8 characters.']); exit;
            }
            $fields['password_hash'] = password_hash($pw, PASSWORD_DEFAULT);
        }

        if (empty($fields)) {
            echo json_encode(['success' => false, 'message' => 'No fields to update']); exit;
        }

        // Fetch old values before update for audit
        $oldRes = supabaseRequest('GET', 'interviewers?select=id,full_name,email,region,status,dashboard_role&interviewer_code=eq.' . urlencode($code) . '&limit=1');
        $old    = ($oldRes['success'] && !empty($oldRes['data'])) ? $oldRes['data'][0] : null;

        $res = supabaseRequest('PATCH',
            'interviewers?interviewer_code=eq.' . urlencode($code),
            $fields
        );

        if (!$res['success']) {
            error_log('update-interviewer error: ' . ($res['error'] ?? 'Unknown'));
            echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']); exit;
        }

        $logFields = $fields;
        unset($logFields['password_hash']); // never log password hashes
        logAudit('update', 'interviewers', $old['id'] ?? null,
            $old ? array_intersect_key($old, $logFields) : null,
            $logFields,
            $old['id'] ?? null
        );

        echo json_encode(['success' => true, 'message' => 'Interviewer updated successfully']);
        break;
    }

    default: {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
    }
}
