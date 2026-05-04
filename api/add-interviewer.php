<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
}

$body = json_decode(file_get_contents('php://input'), true);
if (!$body) { echo json_encode(['success' => false, 'message' => 'Invalid JSON']); exit; }

$name     = trim($body['full_name']        ?? '');
$code     = trim($body['interviewer_code'] ?? '');
$region   = trim($body['region']           ?? '');
$province = trim($body['province']         ?? '');
$position = trim($body['position']         ?? '');
$office   = trim($body['office']           ?? '');

if (!$name || !$code || !$region) {
    echo json_encode(['success' => false, 'message' => 'full_name, interviewer_code, and region are required']); exit;
}

$res = supabaseRequest('POST', 'interviewers', [
    'full_name'        => $name,
    'interviewer_code' => $code,
    'region'           => $region,
    'province'         => $province,
    'position'         => $position,
    'office'           => $office,
    'status'           => 'active',
]);

if (!$res['success']) {
    echo json_encode(['success' => false, 'message' => $res['error'] ?? 'Failed to add interviewer']); exit;
}

echo json_encode(['success' => true, 'message' => 'Interviewer added successfully']);
