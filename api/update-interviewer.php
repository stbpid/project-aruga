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

$code = trim($body['interviewer_code'] ?? '');
if (!$code) { echo json_encode(['success' => false, 'message' => 'interviewer_code is required']); exit; }

$fields = [];
if (!empty($body['full_name']))  $fields['full_name']  = trim($body['full_name']);
if (!empty($body['region']))     $fields['region']     = trim($body['region']);
if (!empty($body['province']))   $fields['province']   = trim($body['province']);
if (!empty($body['position']))   $fields['position']   = trim($body['position']);
if (!empty($body['office']))     $fields['office']     = trim($body['office']);

if (empty($fields)) {
    echo json_encode(['success' => false, 'message' => 'No fields to update']); exit;
}

$res = supabaseRequest('PATCH',
    'interviewers?interviewer_code=eq.' . urlencode($code),
    $fields
);

if (!$res['success']) {
    echo json_encode(['success' => false, 'message' => $res['error'] ?? 'Failed to update interviewer']); exit;
}

echo json_encode(['success' => true, 'message' => 'Interviewer updated successfully']);
