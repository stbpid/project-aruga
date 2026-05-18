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

$interviewerCode = getStr('interviewerCode');
if (empty($interviewerCode)) {
    echo json_encode(['success' => false, 'message' => 'interviewerCode required']);
    exit;
}

$code       = urlencode($interviewerCode);
$monthStart = date('Y-m-01') . 'T00:00:00';
$monthEnd   = date('Y-m-t') . 'T23:59:59';

$url = SUPABASE_URL . '/rest/v1/assessments?select=id&interviewer_code=eq.' . $code
    . '&created_at=gte.' . urlencode($monthStart)
    . '&created_at=lte.' . urlencode($monthEnd);

$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
    'Prefer: count=exact',
    'Range: 0-0',
]);
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$resp = curl_exec($ch);
curl_close($ch);

$submitted = 0;
if (preg_match('/Content-Range:\s*[\d\*]+-?[\d\*]*\/(\d+)/i', $resp, $m)) {
    $submitted = (int)$m[1];
}

$target     = 20;
$percentage = $target > 0 ? min(100, round(($submitted / $target) * 100)) : 0;

echo json_encode([
    'success' => true,
    'data' => [
        'submitted'  => $submitted,
        'target'     => $target,
        'percentage' => $percentage,
        'month'      => date('F Y'),
    ]
]);
