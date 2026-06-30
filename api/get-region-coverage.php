<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Fetch non-deleted assessment IDs, then count children by region for those assessments only
$assRes = supabaseRequest('GET', 'assessments?select=id&deleted_at=is.null&limit=100000');
$validIds = ($assRes['success'] && is_array($assRes['data'])) ? array_flip(array_column($assRes['data'], 'id')) : [];

$res = supabaseRequest('GET', 'children?select=region,assessment_id&limit=10000');

$counts = [];
if ($res['success'] && is_array($res['data'])) {
    foreach ($res['data'] as $row) {
        if (!isset($validIds[$row['assessment_id'] ?? ''])) continue;
        $r = trim($row['region'] ?? '');
        if ($r === '') continue;
        $counts[$r] = ($counts[$r] ?? 0) + 1;
    }
}

$regionTargets = [
    'Region I (Ilocos Region)'    => 150,
    'Region II (Cagayan Valley)'  => 100,
    'Region III (Central Luzon)'  => 100,
    'Region IV-A (CALABARZON)'    => 100,
    'Region IV-B (MIMAROPA)'      => 150,
    'Region V (Bicol Region)'     => 100,
    'Region VI (Western Visayas)' => 150,
    'Region XI (Davao Region)'      => 150,
    'NCR (National Capital Region)' => 140,
];

// Sort descending by count
arsort($counts);

$data = [];
foreach ($counts as $region => $count) {
    $target = $regionTargets[$region] ?? null;
    $data[] = ['region' => $region, 'count' => $count, 'target' => $target];
}

echo json_encode(['success' => true, 'data' => $data]);
