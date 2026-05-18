<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://project-aruga.vercel.app');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$arugaId = getStr('arugaId');
if (empty($arugaId)) {
    echo json_encode(['success' => false, 'message' => 'arugaId required']);
    exit;
}

$encoded = urlencode($arugaId);

$res = supabaseRequest('GET',
    "assessments?select=id,aruga_id,status,created_at,readiness_score,children(first_name,last_name,date_of_birth,barangay)&aruga_id=eq.$encoded&limit=1"
);

if (!$res['success'] || empty($res['data'])) {
    echo json_encode(['success' => false, 'message' => 'Beneficiary not found']);
    exit;
}

$a     = $res['data'][0];
$child = is_array($a['children'])
    ? (isset($a['children'][0]) ? $a['children'][0] : $a['children'])
    : null;

$firstName = $child['first_name'] ?? '';
$lastName  = $child['last_name']  ?? '';
$fullName  = trim($firstName . ' ' . $lastName) ?: 'Unknown';

$dob = $child['date_of_birth'] ?? null;
$age = '—';
if ($dob) {
    $age = (int)(new DateTime($dob))->diff(new DateTime())->y;
}

echo json_encode([
    'success' => true,
    'data' => [
        'arugaId'      => $a['aruga_id']        ?? $arugaId,
        'name'         => $fullName,
        'age'          => $age,
        'barangay'     => $child['barangay']     ?? '—',
        'status'       => $a['status']           ?? 'pending',
        'readinessScore' => $a['readiness_score'] ?? '—',
        'dateAssessed' => $a['created_at'] ? date('M j, Y', strtotime($a['created_at'])) : '—',
    ]
]);
