<?php
/**
 * Auth Router — consolidates:
 *   - dashboard-login.php        (action=login)
 *   - get-profile.php            (action=profile-get)
 *   - settings/profile.php       (action=profile-update)   [GET/PUT, requires auth]
 *   - settings/security.php      (action=security-update)  [GET/PUT, requires auth+admin]
 *   - validate-interviewer.php   (action=validate-interviewer)
 *
 * NOTE: auth.php calls requireAuth() immediately when included, which requires
 * X-Session-ID / X-Interviewer-ID headers. Only 'profile-update' and
 * 'security-update' require auth in the originals, so auth.php is required lazily
 * (only for those two actions) to preserve exact per-action behavior.
 */
require_once __DIR__ . '/lib/config.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    // ================================================================
    // action=login  (was api/dashboard-login.php)
    // ================================================================
    case 'login': {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(200); exit; }
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') { sendResponse(false, 'Method not allowed', null, 405); }

        $input = json_decode(file_get_contents('php://input'), true);

        if (empty($input['email']) || empty($input['password'])) {
            sendResponse(false, 'Email and password are required', null, 400);
        }

        $email    = strtolower(trim($input['email']));
        $password = $input['password'];

        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            sendResponse(false, 'Invalid email address', null, 400);
        }

        // Rate limit: block after 5 failed attempts for same IP + email in 15 minutes
        $ip = getUserIP();
        $window = date('Y-m-d\TH:i:s\Z', strtotime('-15 minutes'));
        $rateCheck = supabaseRequest('GET',
            'audit_logs?action=eq.view&ip_address=eq.' . urlencode($ip) .
            '&new_values=cs.' . urlencode('{"event":"login_failed","email":"' . $email . '"}') .
            '&created_at=gte.' . urlencode($window) .
            '&select=id&limit=10'
        );
        $failCount = count($rateCheck['data'] ?? []);

        if ($failCount >= 5) {
            sendResponse(false, 'Too many failed login attempts. Please try again in 15 minutes.', null, 429);
        }

        // Fetch interviewer by email
        $endpoint = 'interviewers?email=eq.' . urlencode($email) . '&status=eq.active&select=*';
        $result   = supabaseRequest('GET', $endpoint);

        if (!$result['success']) {
            sendResponse(false, 'Database error', null, 500);
        }

        if (empty($result['data'])) {
            logAudit('view', 'sessions', null, null,
                ['event' => 'login_failed', 'email' => $email, 'reason' => 'user_not_found']
            );
            sendResponse(false, 'Invalid email or password', null, 401);
        }

        $user = $result['data'][0];

        if (empty($user['dashboard_role'])) {
            sendResponse(false, 'This account does not have dashboard access', null, 403);
        }

        if (empty($user['password_hash'])) {
            sendResponse(false, 'Account not yet set up for dashboard login. Contact your administrator.', null, 403);
        }

        // Verify password using Supabase RPC (pgcrypto crypt comparison)
        $rpcResult = supabaseRPC('verify_password', [
            'input_password' => $password,
            'stored_hash'    => $user['password_hash']
        ]);

        $passwordValid = false;

        if ($rpcResult['success'] && isset($rpcResult['data'])) {
            $passwordValid = (bool) $rpcResult['data'];
        }

        // Fallback: try PHP password_verify with $2a$ -> $2y$ swap
        if (!$passwordValid) {
            $hash = $user['password_hash'];
            $compatible = preg_replace('/^\$2a\$/', '$2y$', $hash);
            $passwordValid = password_verify($password, $compatible);
        }

        if (!$passwordValid) {
            logAudit('view', 'sessions', null, null,
                ['event' => 'login_failed', 'email' => $email, 'reason' => 'wrong_password', 'role' => $user['dashboard_role'] ?? null],
                $user['id']
            );
            sendResponse(false, 'Invalid email or password', null, 401);
        }

        // Create session record
        $sessionData = [
            'interviewer_id'   => $user['id'],
            'interviewer_code' => $user['interviewer_code'] ?? null,
            'started_at'       => date('c'),
            'status'           => 'active',
            'ip_address'       => getUserIP(),
            'user_agent'       => getUserAgent()
        ];

        $sessionResult = supabaseRequest('POST', 'sessions', $sessionData);
        $session = ($sessionResult['success'] && !empty($sessionResult['data'])) ? $sessionResult['data'][0] : null;

        logAudit('view', 'sessions', $session['id'] ?? null, null,
            ['event' => 'login', 'email' => $user['email'], 'role' => $user['dashboard_role'] ?? null],
            $user['id']
        );

        sendResponse(true, 'Login successful', [
            'id'               => $user['id'],
            'interviewer_code' => $user['interviewer_code'] ?? null,
            'full_name'        => $user['full_name'],
            'email'            => $user['email'],
            'role'             => $user['dashboard_role'],
            'region'           => $user['region']   ?? null,
            'province'         => $user['province'] ?? null,
            'office'           => $user['office']   ?? null,
            'session_id'       => $session['id']    ?? null,
            'started_at'       => $session['started_at'] ?? date('c'),
        ]);
        break;
    }

    // ================================================================
    // action=profile-get  (was api/get-profile.php)
    // ================================================================
    case 'profile-get': {
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        $arugaId = isset($_GET['id']) ? trim($_GET['id']) : '';
        if (!$arugaId) {
            sendResponse(false, 'Missing id parameter', null, 400);
        }

        // Fetch assessment by aruga_id
        $encoded  = urlencode($arugaId);
        $result   = supabaseRequest('GET', "assessments?aruga_id=eq.{$encoded}&select=id,aruga_id,created_at,readiness_score,interviewer_code");
        if (!$result['success'] || empty($result['data'][0])) {
            sendResponse(false, 'Profile not found', null, 404);
        }

        $assessment   = $result['data'][0];
        $assessmentId = $assessment['id'];

        // Fetch child name
        $childResult = supabaseRequest('GET', "children?assessment_id=eq.{$assessmentId}&select=first_name,middle_name,last_name");
        $child       = $childResult['data'][0] ?? [];
        $childName   = trim(($child['first_name'] ?? '') . ' ' . ($child['middle_name'] ? $child['middle_name'] . ' ' : '') . ($child['last_name'] ?? ''));

        sendResponse(true, 'Profile found', [
            'aruga_id'        => $assessment['aruga_id'],
            'child_name'      => $childName,
            'registered_at'   => $assessment['created_at'],
            'readiness_score' => $assessment['readiness_score'],
        ]);
        break;
    }

    // ================================================================
    // action=profile-update  (was api/settings/profile.php)
    // ================================================================
    case 'profile-update': {
        require_once __DIR__ . '/lib/auth.php';

        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, X-Session-ID, X-Interviewer-ID');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

        $userId = $authInterviewer['id'];

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            echo json_encode([
                'success' => true,
                'data' => [
                    'full_name'      => $authInterviewer['full_name']      ?? '',
                    'position'       => $authInterviewer['position']       ?? '',
                    'office'         => $authInterviewer['office']         ?? '',
                    'email'          => $authInterviewer['email']          ?? '',
                    'dashboard_role' => $authInterviewer['dashboard_role'] ?? '',
                ]
            ]);
            exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            $body = json_decode(file_get_contents('php://input'), true);
            if (!$body) {
                http_response_code(400);
                echo json_encode(['success' => false, 'message' => 'Invalid JSON']);
                exit;
            }

            $updates = [];

            if (isset($body['full_name'])) {
                $name = trim($body['full_name']);
                if (strlen($name) < 2 || strlen($name) > 120) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Full name must be 2–120 characters.']);
                    exit;
                }
                $updates['full_name'] = $name;
            }

            if (isset($body['position'])) {
                $pos = trim($body['position']);
                if (strlen($pos) > 120) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Position must be 120 characters or fewer.']);
                    exit;
                }
                $updates['position'] = $pos;
            }

            // Office only editable for central_office role
            if (isset($body['office'])) {
                if (($authInterviewer['dashboard_role'] ?? '') !== 'central_office') {
                    http_response_code(403);
                    echo json_encode(['success' => false, 'message' => 'You are not allowed to change your office.']);
                    exit;
                }
                $off = trim($body['office']);
                if (strlen($off) > 120) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Office must be 120 characters or fewer.']);
                    exit;
                }
                $updates['office'] = $off;
            }

            // Password change
            if (!empty($body['new_password'])) {
                $newPw = $body['new_password'];
                $curPw = $body['current_password'] ?? '';

                if (empty($curPw)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Current password is required.']);
                    exit;
                }

                // Verify current password
                $storedHash = $authInterviewer['password_hash'] ?? '';
                $valid = false;
                $rpc = supabaseRPC('verify_password', ['input_password' => $curPw, 'stored_hash' => $storedHash]);
                if ($rpc['success'] && isset($rpc['data'])) $valid = (bool)$rpc['data'];
                if (!$valid) {
                    $compatible = preg_replace('/^\$2a\$/', '$2y$', $storedHash);
                    $valid = password_verify($curPw, $compatible);
                }
                if (!$valid) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'Current password is incorrect.']);
                    exit;
                }

                if (strlen($newPw) < 8) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'New password must be at least 8 characters.']);
                    exit;
                }
                if (!preg_match('/[A-Z]/', $newPw)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'New password must contain at least one uppercase letter.']);
                    exit;
                }
                if (!preg_match('/[0-9]/', $newPw)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'New password must contain at least one number.']);
                    exit;
                }
                if (!preg_match('/[^A-Za-z0-9]/', $newPw)) {
                    http_response_code(400);
                    echo json_encode(['success' => false, 'message' => 'New password must contain at least one special character.']);
                    exit;
                }

                $updates['password_hash'] = password_hash($newPw, PASSWORD_BCRYPT, ['cost' => 12]);
            }

            if (empty($updates)) {
                echo json_encode(['success' => true, 'message' => 'No changes.']);
                exit;
            }

            $res = supabaseRequest('PATCH', 'interviewers?id=eq.' . urlencode($userId), $updates);
            if (!$res['success']) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save profile.']);
                exit;
            }

            $changedFields = array_keys($updates);
            if (in_array('password_hash', $changedFields)) {
                $changedFields = array_diff($changedFields, ['password_hash']);
                $changedFields[] = 'password';
            }
            logAudit('update', 'interviewers', $userId, null,
                ['event' => 'profile_updated', 'fields' => array_values($changedFields)],
                $userId
            );

            echo json_encode(['success' => true, 'data' => array_filter($updates, fn($k) => $k !== 'password_hash', ARRAY_FILTER_USE_KEY)]);
            exit;
        }

        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
    }

    // ================================================================
    // action=security-update  (was api/settings/security.php)
    // ================================================================
    case 'security-update': {
        require_once __DIR__ . '/lib/auth.php';

        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET, PUT, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
        requireRole(['admin']);

        $DEFAULTS = [
            'idleTimeout'          => '30',
            'absoluteTimeout'      => '8',
            'timeoutWarning'       => '5',
            'rememberMeDuration'   => '30',
            'maxFailedAttempts'    => '5',
            'lockoutDuration'      => '30',
            'forcePasswordChange'  => 'true',
            'passwordMinLength'    => '8',
            'requireUppercase'     => 'true',
            'requireNumbers'       => 'true',
            'requireSpecialChars'  => 'true',
            'passwordExpiry'       => '90',
            'preventPasswordReuse' => 'false',
        ];

        $FIELDS = array_keys($DEFAULTS);

        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            $res = supabaseRequest('GET', 'system_settings?select=key,value&key=in.(' . implode(',', $FIELDS) . ')&limit=100');
            if (!$res['success']) {
                echo json_encode(['success' => true, 'data' => $DEFAULTS]); exit;
            }
            $data = $DEFAULTS;
            foreach (($res['data'] ?? []) as $row) {
                if (isset($DEFAULTS[$row['key']])) $data[$row['key']] = $row['value'];
            }
            echo json_encode(['success' => true, 'data' => $data]); exit;
        }

        if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
            $body = json_decode(file_get_contents('php://input'), true);
            if (!$body) { http_response_code(400); echo json_encode(['success' => false, 'message' => 'Invalid JSON']); exit; }

            $upserts = [];
            foreach ($FIELDS as $field) {
                if (isset($body[$field])) {
                    $upserts[] = ['key' => $field, 'value' => (string)$body[$field]];
                }
            }

            if (empty($upserts)) { echo json_encode(['success' => true]); exit; }

            $res = supabaseRequest('POST', 'system_settings?on_conflict=key', $upserts);
            if (!$res['success']) {
                http_response_code(500);
                echo json_encode(['success' => false, 'message' => 'Failed to save settings']);
                exit;
            }
            $changedKeys = array_column($upserts, 'key');
            logAudit('update', 'system_settings', null, null,
                ['event' => 'security_settings_changed', 'fields' => $changedKeys]
            );
            echo json_encode(['success' => true]); exit;
        }

        http_response_code(405);
        echo json_encode(['success' => false, 'message' => 'Method not allowed']);
        break;
    }

    // ================================================================
    // action=validate-interviewer  (was api/validate-interviewer.php)
    // ================================================================
    case 'validate-interviewer': {
        // Handle CORS preflight
        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
            http_response_code(200);
            exit;
        }

        // Only accept POST requests
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendResponse(false, 'Method not allowed. Use POST.', null, 405);
        }

        // Get JSON input
        $input = json_decode(file_get_contents('php://input'), true);

        // Validate input
        if (!isset($input['interviewer_code']) || empty(trim($input['interviewer_code']))) {
            sendResponse(false, 'Interviewer code is required', null, 400);
        }

        $interviewerCode = strtoupper(trim($input['interviewer_code']));

        // Validate format: 8 characters, alphanumeric
        if (!preg_match('/^[A-Z0-9]{8}$/', $interviewerCode)) {
            sendResponse(false, 'Invalid interviewer code format. Must be 8 alphanumeric characters.', null, 400);
        }

        // Rate limit: block after 5 failed attempts from same IP in 15 minutes
        $ip = getUserIP();
        $window = date('Y-m-d\TH:i:s\Z', strtotime('-15 minutes'));
        $rateCheck = supabaseRequest('GET',
            'audit_logs?action=eq.view&ip_address=eq.' . urlencode($ip) .
            '&new_values=cs.' . urlencode('{"event":"login_failed"}') .
            '&created_at=gte.' . urlencode($window) .
            '&select=id&limit=10'
        );
        $failCount = count($rateCheck['data'] ?? []);
        if ($failCount >= 5) {
            sendResponse(false, 'Too many failed login attempts. Please try again in 15 minutes.', null, 429);
        }

        // Query Supabase for interviewer
        $endpoint = "interviewers?interviewer_code=eq.$interviewerCode&status=eq.active&select=*";
        $result = supabaseRequest('GET', $endpoint);

        if (!$result['success']) {
            error_log('validate-interviewer error: ' . ($result['error'] ?? 'Unknown'));
            sendResponse(false, 'A server error occurred. Please try again.', null, 500);
        }

        // Check if interviewer exists and is active
        if (empty($result['data']) || count($result['data']) === 0) {
            logAudit('view', 'sessions', null, null,
                ['event' => 'login_failed', 'interviewer_code' => $interviewerCode, 'reason' => 'invalid_or_inactive']
            );
            sendResponse(false, 'Invalid interviewer code or account is inactive', null, 401);
        }

        $interviewer = $result['data'][0];

        // Create session record
        $sessionData = [
            'interviewer_id' => $interviewer['id'],
            'interviewer_code' => $interviewer['interviewer_code'],
            'started_at' => date('c'),
            'status' => 'active',
            'ip_address' => getUserIP(),
            'user_agent' => getUserAgent()
        ];

        $sessionResult = supabaseRequest('POST', 'sessions', $sessionData);

        if (!$sessionResult['success']) {
            sendResponse(false, 'Failed to create session', null, 500);
        }

        $session = $sessionResult['data'][0] ?? null;

        if (!$session) {
            sendResponse(false, 'Session creation failed', null, 500);
        }

        logAudit('login', 'sessions', $session['id'], null,
            ['event' => 'login', 'interviewer_code' => $interviewer['interviewer_code'], 'full_name' => $interviewer['full_name'], 'region' => $interviewer['region']],
            $interviewer['id']
        );

        // Return success with interviewer and session data
        sendResponse(true, 'Login successful', [
            'interviewer_id' => $interviewer['id'],
            'interviewer_code' => $interviewer['interviewer_code'],
            'full_name' => $interviewer['full_name'],
            'region' => $interviewer['region'],
            'province' => $interviewer['province'],
            'office' => $interviewer['office'] ?? null,
            'position' => $interviewer['position'] ?? null,
            'session_id' => $session['id'],
            'started_at' => $session['started_at']
        ], 200);
        break;
    }

    default: {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
    }
}
