<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/region-coverage-helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

function supabaseCount($endpoint) {
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

    // Content-Range: 0-0/14  or  Content-Range: */14
    if (preg_match('/Content-Range:\s*[\d\*]+-?[\d\*]*\/(\d+)/i', $resp, $m)) {
        return (int)$m[1];
    }
    return 0;
}

// Total beneficiaries = total rows in assessments table
$totalBeneficiaries = supabaseCount('assessments?select=id&deleted_at=is.null');

// Active interviewers — no status filter first, count all, then try with active
$activeInterviewers = supabaseCount('interviewers?select=id&status=eq.active');
// Fallback: if 0, count all interviewers (status column may not exist or have different value)
if ($activeInterviewers === 0) {
    $activeInterviewers = supabaseCount('interviewers?select=id');
}

// Regions covered = number of top-level regions in get-locations.php dropdown
// Parse the PHP file, extract $locations array keys without executing it
$locRaw = file_get_contents(__DIR__ . '/get-locations.php');
preg_match('/\$locations\s*=\s*\[(.+)\];/s', $locRaw, $arrMatch);
$regionsCovered = 0;
if (!empty($arrMatch[1])) {
    preg_match_all("/^    '([^']+)'\s*=>/m", $arrMatch[1], $keys);
    $regionsCovered = count($keys[1]);
}

// Completion rate = total active children across all regions / total region targets * 100
$regionCounts = getActiveChildrenCountsByRegion();
$regionTargets = getRegionTargets();
$totalActive = 0;
$totalTarget = 0;
foreach ($regionTargets as $region => $target) {
    $totalActive += $regionCounts[$region] ?? 0;
    $totalTarget += $target;
}
$completionRate = $totalTarget > 0
    ? round(($totalActive / $totalTarget) * 100, 1)
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
