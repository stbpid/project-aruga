<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://project-aruga.vercel.app');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Fetch all children rows with just region column (up to 10000)
$res = supabaseRequest('GET', 'children?select=region&limit=10000');

$counts = [];
if ($res['success'] && is_array($res['data'])) {
    foreach ($res['data'] as $row) {
        $r = trim($row['region'] ?? '');
        if ($r === '') continue;
        $counts[$r] = ($counts[$r] ?? 0) + 1;
    }
}

// Sort descending by count
arsort($counts);

$data = [];
foreach ($counts as $region => $count) {
    $data[] = ['region' => $region, 'count' => $count];
}

echo json_encode(['success' => true, 'data' => $data]);
