<?php
require_once __DIR__ . '/../config.php';

require_once __DIR__ . '/../../auth.php';
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$interviewerCode = trim($_GET['interviewerCode'] ?? '');
if (empty($interviewerCode)) {
    echo json_encode(['success' => false, 'message' => 'interviewerCode required']);
    exit;
}

$code = urlencode($interviewerCode);

$res = supabaseRequest('GET',
    "assessments?select=id,aruga_id,status,updated_at,children(first_name,last_name)&interviewer_code=eq.$code&status=eq.correction&order=updated_at.desc"
);

if (!$res['success']) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch data']);
    exit;
}

$items = [];
foreach ($res['data'] as $a) {
    $child    = is_array($a['children']) ? ($a['children'][0] ?? $a['children']) : null;
    $fullName = trim(($child['first_name'] ?? '') . ' ' . ($child['last_name'] ?? '')) ?: 'Unknown';
    $items[]  = [
        'id'               => $a['id'] ?? '',
        'aruga_id'         => $a['aruga_id'] ?? '—',
        'name'             => $fullName,
        'correction_notes' => 'This submission has been flagged for correction. Please review and update the required fields.',
        'updated_at'       => $a['updated_at'] ? date('M j, Y', strtotime($a['updated_at'])) : '—',
    ];
}

echo json_encode(['success' => true, 'data' => $items, 'count' => count($items)]);
