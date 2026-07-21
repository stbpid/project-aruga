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

function stuIntNormalizeRegion($r) {
    return normalizeRegion($r) ?: '—';
}

// Normalize the incoming region so it matches what's stored in various formats.
// Fetch all interviewers and filter in PHP to handle inconsistent DB values.
$normalizedRegion = stuIntNormalizeRegion($region);

$interviewersData = supabaseFetchAll(
    'interviewers?select=id,full_name,interviewer_code,region,province,position,office,status&order=full_name.asc'
);

$monthStart = date('Y-m-01T00:00:00');
$monthEnd   = date('Y-m-t') . 'T23:59:59';

$codes = array_filter(array_column($interviewersData, 'interviewer_code'));

$totalMap      = [];
$completedMap  = [];
$monthMap      = [];
$completedMonthMap = [];
$lastActiveMap = [];

if (!empty($codes)) {
    $codeIn = implode(',', $codes);
    $assessmentsData = supabaseFetchAll(
        'assessments?select=interviewer_code,status,created_at&interviewer_code=in.(' . $codeIn . ')'
    );
    foreach ($assessmentsData as $a) {
        $code = $a['interviewer_code'] ?? '';
        if (!$code) continue;
        $totalMap[$code] = ($totalMap[$code] ?? 0) + 1;
        if ($a['status'] === 'completed') $completedMap[$code] = ($completedMap[$code] ?? 0) + 1;
        $ca = $a['created_at'] ?? '';
        if ($ca >= $monthStart && $ca <= $monthEnd) {
            $monthMap[$code] = ($monthMap[$code] ?? 0) + 1;
            if ($a['status'] === 'completed') $completedMonthMap[$code] = ($completedMonthMap[$code] ?? 0) + 1;
        }
        if ($ca && (!isset($lastActiveMap[$code]) || $ca > $lastActiveMap[$code])) {
            $lastActiveMap[$code] = $ca;
        }
    }

    $sessionsData = supabaseFetchAll(
        'sessions?select=interviewer_code,created_at&interviewer_code=in.(' . $codeIn . ')'
    );
    foreach ($sessionsData as $s) {
        $code = $s['interviewer_code'] ?? '';
        $ca   = $s['created_at'] ?? '';
        if (!$code || !$ca) continue;
        if (!isset($lastActiveMap[$code]) || $ca > $lastActiveMap[$code]) {
            $lastActiveMap[$code] = $ca;
        }
    }
}

// Filter by normalized region
$filtered = array_filter($interviewersData, function($r) use ($normalizedRegion) {
    return stuIntNormalizeRegion($r['region'] ?? '') === $normalizedRegion;
});

$rows = array_map(function($r) use ($totalMap, $completedMap, $monthMap, $completedMonthMap, $lastActiveMap) {
    $code  = $r['interviewer_code'] ?? '—';
    return [
        'id'                => $r['id'] ?? null,
        'name'              => $r['full_name'] ?? '—',
        'code'              => $code,
        'region'            => stuIntNormalizeRegion($r['region'] ?? ''),
        'province'          => $r['province'] ?? '—',
        'position'          => $r['position'] ?? '—',
        'office'            => $r['office'] ?? '—',
        'status'            => $r['status'] ?? 'active',
        'submissions_total' => $totalMap[$code] ?? 0,
        'completed_total'   => $completedMap[$code] ?? 0,
        'submissions_month' => $monthMap[$code] ?? 0,
        'completed_month'   => $completedMonthMap[$code] ?? 0,
        'last_active'       => $lastActiveMap[$code] ?? null,
    ];
}, $filtered);

echo json_encode(['success' => true, 'data' => array_values($rows)]);
