<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
}

// Pull all assessments with region + status
$assRes = supabaseRequest('GET', 'assessments?select=region,status&limit=100000');
// Pull all interviewers with region + status
$intRes = supabaseRequest('GET', 'interviewers?select=region,status&limit=100000');

$regionMap = [];

if ($assRes['success'] && is_array($assRes['data'])) {
    foreach ($assRes['data'] as $row) {
        $r = trim($row['region'] ?? '');
        if ($r === '') continue;
        if (!isset($regionMap[$r])) {
            $regionMap[$r] = ['completed' => 0, 'pending' => 0, 'interviewers' => 0];
        }
        $status = strtolower(trim($row['status'] ?? ''));
        if ($status === 'completed') {
            $regionMap[$r]['completed']++;
        } else {
            $regionMap[$r]['pending']++;
        }
    }
}

if ($intRes['success'] && is_array($intRes['data'])) {
    foreach ($intRes['data'] as $row) {
        $r = trim($row['region'] ?? '');
        if ($r === '') continue;
        if (!isset($regionMap[$r])) {
            $regionMap[$r] = ['completed' => 0, 'pending' => 0, 'interviewers' => 0];
        }
        $regionMap[$r]['interviewers']++;
    }
}

$data = [];
foreach ($regionMap as $region => $stats) {
    $total = $stats['completed'] + $stats['pending'];
    $rate  = $total > 0 ? round(($stats['completed'] / $total) * 100) : 0;
    $data[] = [
        'region'       => $region,
        'completed'    => $stats['completed'],
        'pending'      => $stats['pending'],
        'total'        => $total,
        'rate'         => $rate,
        'interviewers' => $stats['interviewers'],
    ];
}

// Sort descending by completed
usort($data, fn($a, $b) => $b['completed'] - $a['completed']);

echo json_encode(['success' => true, 'data' => $data]);
