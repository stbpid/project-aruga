<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Total beneficiaries = total assessments (one child per assessment)
$totalRes = supabaseRequest('GET', 'assessments?select=id&limit=1&offset=0', null);
// Use count header — re-request with Prefer: count=exact
$url = SUPABASE_URL . '/rest/v1/assessments?select=id';
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, array_merge(getSupabaseHeaders(), ['Prefer: count=exact']));
curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'HEAD');
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_NOBODY, true);
curl_setopt($ch, CURLOPT_TIMEOUT, 15);
$resp = curl_exec($ch);
$totalBeneficiaries = 0;
if (preg_match('/Content-Range:\s*\*\/(\d+)/i', $resp, $m)) {
    $totalBeneficiaries = (int)$m[1];
}
curl_close($ch);

// Completed assessments count
$ch2 = curl_init();
$url2 = SUPABASE_URL . '/rest/v1/assessments?select=id&status=eq.completed';
curl_setopt($ch2, CURLOPT_URL, $url2);
curl_setopt($ch2, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch2, CURLOPT_HTTPHEADER, array_merge(getSupabaseHeaders(), ['Prefer: count=exact']));
curl_setopt($ch2, CURLOPT_CUSTOMREQUEST, 'HEAD');
curl_setopt($ch2, CURLOPT_HEADER, true);
curl_setopt($ch2, CURLOPT_NOBODY, true);
curl_setopt($ch2, CURLOPT_TIMEOUT, 15);
$resp2 = curl_exec($ch2);
$completedCount = 0;
if (preg_match('/Content-Range:\s*\*\/(\d+)/i', $resp2, $m2)) {
    $completedCount = (int)$m2[1];
}
curl_close($ch2);

// Active interviewers
$ch3 = curl_init();
$url3 = SUPABASE_URL . '/rest/v1/interviewers?select=id&status=eq.active';
curl_setopt($ch3, CURLOPT_URL, $url3);
curl_setopt($ch3, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch3, CURLOPT_HTTPHEADER, array_merge(getSupabaseHeaders(), ['Prefer: count=exact']));
curl_setopt($ch3, CURLOPT_CUSTOMREQUEST, 'HEAD');
curl_setopt($ch3, CURLOPT_HEADER, true);
curl_setopt($ch3, CURLOPT_NOBODY, true);
curl_setopt($ch3, CURLOPT_TIMEOUT, 15);
$resp3 = curl_exec($ch3);
$activeInterviewers = 0;
if (preg_match('/Content-Range:\s*\*\/(\d+)/i', $resp3, $m3)) {
    $activeInterviewers = (int)$m3[1];
}
curl_close($ch3);

// Regions covered = distinct regions from assessments via children table
$regionsRes = supabaseRequest('GET', 'children?select=region');
$regionsCovered = 0;
if ($regionsRes['success'] && is_array($regionsRes['data'])) {
    $regions = array_unique(array_filter(array_column($regionsRes['data'], 'region')));
    $regionsCovered = count($regions);
}

// Completion rate
$completionRate = $totalBeneficiaries > 0
    ? round(($completedCount / $totalBeneficiaries) * 100, 1)
    : 0;

echo json_encode([
    'success' => true,
    'data' => [
        'total_beneficiaries' => $totalBeneficiaries,
        'active_interviewers' => $activeInterviewers,
        'regions_covered'     => $regionsCovered,
        'completion_rate'     => $completionRate,
    ]
]);
