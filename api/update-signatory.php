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

$id = trim($body['id'] ?? '');
if (!$id) { echo json_encode(['success' => false, 'message' => 'id is required']); exit; }

$fields = [];
if (!empty($body['signatory_fullname'])) $fields['signatory_fullname'] = trim($body['signatory_fullname']);
if (!empty($body['signatory_position'])) $fields['signatory_position'] = trim($body['signatory_position']);
if (!empty($body['signatory_office']))   $fields['signatory_office']   = trim($body['signatory_office']);
if (!empty($body['signatory_region']))   $fields['signatory_region']   = trim($body['signatory_region']);
if (isset($body['signatory_status']) && in_array($body['signatory_status'], ['active', 'inactive'], true)) {
    $fields['signatory_status'] = $body['signatory_status'];
}

if (empty($fields)) {
    echo json_encode(['success' => false, 'message' => 'No fields to update']); exit;
}

$oldRes = supabaseRequest('GET', 'signatories?select=id,signatory_fullname,signatory_position,signatory_office,signatory_region,signatory_status&id=eq.' . urlencode($id) . '&limit=1');
$old    = ($oldRes['success'] && !empty($oldRes['data'])) ? $oldRes['data'][0] : null;

$res = supabaseRequest('PATCH', 'signatories?id=eq.' . urlencode($id), $fields);

if (!$res['success']) {
    error_log('update-signatory error: ' . ($res['error'] ?? 'Unknown'));
    echo json_encode(['success' => false, 'message' => 'A server error occurred. Please try again.']); exit;
}

logAudit('update', 'signatories', $id, $old ? array_intersect_key($old, $fields) : null, $fields, null);

echo json_encode(['success' => true, 'message' => 'Signatory updated successfully']);
