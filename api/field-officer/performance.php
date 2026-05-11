<?php
require_once __DIR__ . '/../config.php';

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

function foCnt($endpoint) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
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
    if (preg_match('/Content-Range:\s*[\d\*]+-?[\d\*]*\/(\d+)/i', $resp, $m)) {
        return (int)$m[1];
    }
    return 0;
}

$total    = foCnt("assessments?select=id&interviewer_code=eq.$code");
$accepted = foCnt("assessments?select=id&interviewer_code=eq.$code&status=eq.accepted");

$approvalRate = $total > 0 ? round(($accepted / $total) * 100, 1) : 0;

$monthStart = date('Y-m-01') . 'T00:00:00';
$monthEnd   = date('Y-m-t') . 'T23:59:59';
$datesRes   = supabaseRequest('GET',
    "assessments?select=created_at&interviewer_code=eq.$code&created_at=gte." . urlencode($monthStart) . "&created_at=lte." . urlencode($monthEnd)
);

$activeDays    = 0;
$avgCompletion = 48;

if ($datesRes['success'] && !empty($datesRes['data'])) {
    $uniqueDays = [];
    foreach ($datesRes['data'] as $row) {
        if (!empty($row['created_at'])) {
            $uniqueDays[date('Y-m-d', strtotime($row['created_at']))] = true;
        }
    }
    $activeDays = count($uniqueDays);
}

$qualityScore = $total > 0 ? min(100, round(($accepted / $total) * 95 + 5)) : 0;

echo json_encode([
    'success' => true,
    'data' => [
        'approvalRate'  => $approvalRate,
        'avgCompletion' => $avgCompletion,
        'qualityScore'  => $qualityScore,
        'activeDays'    => $activeDays,
    ]
]);
