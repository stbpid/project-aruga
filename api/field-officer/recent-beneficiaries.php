<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://project-aruga.vercel.app');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$interviewerCode = trim($_GET['interviewerCode'] ?? '');
$limit = (int)($_GET['limit'] ?? 5);
if (empty($interviewerCode)) {
    echo json_encode(['success' => false, 'message' => 'interviewerCode required']);
    exit;
}

$code = urlencode($interviewerCode);

$res = supabaseRequest('GET',
    "assessments?select=id,aruga_id,status,created_at,readiness_score,children(first_name,last_name,barangay,date_of_birth)&interviewer_code=eq.$code&order=created_at.desc&limit=$limit"
);

if (!$res['success']) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch data']);
    exit;
}

$rows = [];
foreach ($res['data'] as $a) {
    $child = is_array($a['children']) ? ($a['children'][0] ?? $a['children']) : null;

    $fullName = trim(($child['first_name'] ?? '') . ' ' . ($child['last_name'] ?? '')) ?: 'Unknown';
    $barangay = $child['barangay'] ?? '—';

    $dob = $child['date_of_birth'] ?? null;
    $age = '—';
    if ($dob) {
        $age = (int)(new DateTime($dob))->diff(new DateTime())->y;
    }

    $rows[] = [
        'id'        => $a['id'] ?? '',
        'aruga_id'  => $a['aruga_id'] ?? '—',
        'name'      => $fullName,
        'age'       => $age,
        'barangay'  => $barangay,
        'readiness' => $a['readiness_score'] ?? '—',
        'date'      => $a['created_at'] ? date('M j, Y', strtotime($a['created_at'])) : '—',
        'status'    => $a['status'] ?? 'pending',
    ];
}

echo json_encode(['success' => true, 'data' => $rows]);
