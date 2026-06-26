<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

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

$fullname = trim($body['signatory_fullname'] ?? '');
$position = trim($body['signatory_position'] ?? '');
$office   = trim($body['signatory_office']   ?? '');
$region   = trim($body['signatory_region']   ?? '');
$status   = trim($body['signatory_status']   ?? 'active');

if (!$fullname || !$position || !$office || !$region) {
    echo json_encode(['success' => false, 'message' => 'Full Name, Position, Office, and Region are required.']); exit;
}

if (!in_array($status, ['active', 'inactive'], true)) {
    $status = 'active';
}

$payload = [
    'signatory_fullname' => $fullname,
    'signatory_position' => $position,
    'signatory_office'   => $office,
    'signatory_region'   => $region,
    'signatory_status'   => $status,
];

$res = supabaseRequest('POST', 'signatories', $payload);

if (!$res['success']) {
    error_log('add-signatory error: ' . ($res['error'] ?? 'Unknown'));
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']); exit;
}

$newId = $res['data'][0]['id'] ?? null;
logAudit('create', 'signatories', $newId, null, $payload, null);

echo json_encode(['success' => true, 'message' => 'Signatory added successfully']);
