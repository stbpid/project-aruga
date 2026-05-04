<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

// Fetch all assessments for the given year — just created_at
$res = supabaseRequest('GET', 'assessments?select=created_at&created_at=gte.' . $year . '-01-01T00:00:00&created_at=lt.' . ($year + 1) . '-01-01T00:00:00&limit=10000');

$months = array_fill(1, 12, 0);

if ($res['success'] && is_array($res['data'])) {
    foreach ($res['data'] as $row) {
        if (!empty($row['created_at'])) {
            $m = (int)date('n', strtotime($row['created_at']));
            if ($m >= 1 && $m <= 12) $months[$m]++;
        }
    }
}

$labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
$data = [];
for ($i = 1; $i <= 12; $i++) {
    $data[] = ['month' => $i, 'label' => $labels[$i - 1], 'value' => $months[$i]];
}

echo json_encode(['success' => true, 'year' => $year, 'data' => $data]);
