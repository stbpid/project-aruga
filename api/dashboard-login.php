<?php
require_once 'config.php';

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

// Rate limit: block after 5 failed attempts from same IP in 15 minutes
$ip = getUserIP();
$window = date('Y-m-d\TH:i:s\Z', strtotime('-15 minutes'));
$rateCheck = supabaseRequest('GET',
    'audit_logs?action=eq.login_failed&ip_address=eq.' . urlencode($ip) .
    '&created_at=gte.' . urlencode($window) .
    '&select=id&limit=10'
);
$failCount = count($rateCheck['data'] ?? []);
// DEBUG - remove after testing
if (isset($input['debug']) && $input['debug'] === 'rate_check') {
    // Test writing a login_failed log and reading it back
    $testLog = logAuditDebug('login_failed', 'sessions', null, null,
        ['event' => 'login_failed', 'email' => $email, 'reason' => 'debug_test']
    );
    $allFails = supabaseRequest('GET', 'audit_logs?action=eq.login_failed&ip_address=eq.' . urlencode($ip) . '&select=*&limit=5&order=id.desc');
    sendResponse(true, 'Rate check debug', [
        'ip' => $ip,
        'window' => $window,
        'fail_count' => $failCount,
        'test_log_result' => $testLog,
        'recent_fails_no_filter' => $allFails['data'] ?? [],
    ]);
}
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
    logAudit('login_failed', 'sessions', null, null,
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
    logAudit('login_failed', 'sessions', null, null,
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
?>
