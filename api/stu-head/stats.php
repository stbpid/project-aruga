<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../region-coverage-helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

requireRole(['stu_head', 'admin']);
$region = getStr('region');
if (!$region) {
    echo json_encode(['success' => false, 'message' => 'region required']); exit;
}
requireRegion($region);

function stuStatsNormalizeRegion($r) {
    return normalizeRegion($r) ?: '';
}

$normalizedTarget = stuStatsNormalizeRegion($region);

// Fetch all assessments + interviewers in parallel (paginated — Supabase caps each response at 1000 rows)
$fetched = supabaseFetchAllMulti([
    'assessments'  => 'assessments?select=id,status,children(region)&deleted_at=is.null',
    'interviewers' => 'interviewers?select=interviewer_code,region',
]);

$totalBeneficiaries = 0;
$completedCount     = 0;

foreach ($fetched['assessments'] as $a) {
    $child = is_array($a['children'])
        ? (isset($a['children'][0]) ? $a['children'][0] : $a['children'])
        : null;
    $childRegion = stuStatsNormalizeRegion($child['region'] ?? '');
    if ($childRegion !== $normalizedTarget) continue;
    $totalBeneficiaries++;
    if (($a['status'] ?? '') === 'completed') $completedCount++;
}

$pendingCount = $totalBeneficiaries - $completedCount;

// Interviewer count — filter by normalized region to handle inconsistent stored values
$interviewerCount = 0;
foreach ($fetched['interviewers'] as $r) {
    if (!empty($r['interviewer_code']) && stuStatsNormalizeRegion($r['region'] ?? '') === $normalizedTarget) {
        $interviewerCount++;
    }
}

echo json_encode([
    'success' => true,
    'data' => [
        'total_beneficiaries' => $totalBeneficiaries,
        'completed'           => $completedCount,
        'pending'             => $pendingCount,
        'interviewer_count'   => $interviewerCount,
    ]
]);
