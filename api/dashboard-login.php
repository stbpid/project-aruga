<?php
/**
 * Dashboard Login — email + password authentication
 * Endpoint: POST /api/dashboard-login.php
 *
 * Request Body:
 * { "email": "admin@dswd.gov.ph", "password": "plaintext" }
 *
 * Response:
 * { "success": true, "data": { "id", "full_name", "email", "role", "dashboard_role" } }
 */

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

// Fetch interviewer by email — only dashboard-enabled accounts
$endpoint = 'interviewers?email=eq.' . urlencode($email) . '&status=eq.active&select=*';
$result   = supabaseRequest('GET', $endpoint);

if (!$result['success']) {
    sendResponse(false, 'Database error', null, 500);
}

if (empty($result['data'])) {
    sendResponse(false, 'Invalid email or password', null, 401);
}

$user = $result['data'][0];

// Must have a dashboard role set
if (empty($user['dashboard_role'])) {
    sendResponse(false, 'This account does not have dashboard access', null, 403);
}

// Must have a password hash set
if (empty($user['password_hash'])) {
    sendResponse(false, 'Account not yet set up for dashboard login. Contact your administrator.', null, 403);
}

// Verify password
if (!password_verify($password, $user['password_hash'])) {
    sendResponse(false, 'Invalid email or password', null, 401);
}

// Create session record
$sessionData = [
    'interviewer_id'   => $user['id'],
    'interviewer_code' => $user['interviewer_code'],
    'started_at'       => date('c'),
    'status'           => 'active',
    'ip_address'       => getUserIP(),
    'user_agent'       => getUserAgent()
];

$sessionResult = supabaseRequest('POST', 'sessions', $sessionData);
$session = ($sessionResult['success'] && !empty($sessionResult['data'])) ? $sessionResult['data'][0] : null;

sendResponse(true, 'Login successful', [
    'id'               => $user['id'],
    'interviewer_code' => $user['interviewer_code'],
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
