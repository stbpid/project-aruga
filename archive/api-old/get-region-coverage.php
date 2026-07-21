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

$counts = getActiveChildrenCountsByRegion();
$regionTargets = getRegionTargets();

// Sort descending by count
arsort($counts);

$data = [];
foreach ($counts as $region => $count) {
    $target = $regionTargets[$region] ?? null;
    $data[] = ['region' => $region, 'count' => $count, 'target' => $target];
}

echo json_encode(['success' => true, 'data' => $data]);
