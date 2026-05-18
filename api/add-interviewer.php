<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://project-aruga.vercel.app');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

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
    echo json_encode(['success' => false, 'message' => $res['error'] ?? 'Failed to add interviewer']); exit;
}

$newId = $res['data'][0]['id'] ?? null;
logAudit('create', 'interviewers', $newId, null,
    ['full_name' => $name, 'interviewer_code' => $code, 'region' => $region, 'email' => $email ?: null, 'status' => $accountStatus, 'dashboard_role' => $dashboardRole],
    $newId
);

echo json_encode(['success' => true, 'message' => 'Interviewer added successfully']);
