<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireRole(['field_officer', 'admin']);
$interviewerCode = getStr('interviewerCode');
if (empty($interviewerCode)) {
    echo json_encode(['success' => false, 'message' => 'interviewerCode required']);
    exit;
}
requireOwnCode($interviewerCode);

$code = urlencode($interviewerCode);

function foCount($endpoint) {
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

$total       = foCount("assessments?select=id&interviewer_code=eq.$code&deleted_at=is.null");
$accepted    = foCount("assessments?select=id&interviewer_code=eq.$code&status=eq.accepted&deleted_at=is.null");
$underReview = foCount("assessments?select=id&interviewer_code=eq.$code&status=eq.pending&deleted_at=is.null");
$needsCorr   = foCount("assessments?select=id&interviewer_code=eq.$code&status=eq.correction&deleted_at=is.null");

echo json_encode([
    'success' => true,
    'data' => [
        'totalSubmitted'  => $total,
        'accepted'        => $accepted,
        'underReview'     => $underReview,
        'needsCorrection' => $needsCorr,
    ]
]);
