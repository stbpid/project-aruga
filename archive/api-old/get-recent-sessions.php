<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

$limit = isset($_GET['limit']) ? min((int)$_GET['limit'], 100) : 8;
$res = supabaseRequest('GET',
    'sessions?select=id,interviewer_code,started_at,status,interviewers(full_name,region)&order=started_at.desc&limit='.$limit
);

if (!$res['success']) {
    echo json_encode(['success' => false, 'data' => []]); exit;
}

function timeAgo($datetime) {
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return $diff . 's ago';
    if ($diff < 3600) return floor($diff/60) . 'm ago';
    if ($diff < 86400) return floor($diff/3600) . 'h ago';
    return floor($diff/86400) . 'd ago';
}

$rows = [];
foreach ($res['data'] as $s) {
    $interviewer = is_array($s['interviewers']) ? (isset($s['interviewers'][0]) ? $s['interviewers'][0] : $s['interviewers']) : null;
    $name   = $interviewer['full_name'] ?? $s['interviewer_code'] ?? 'Unknown';
    $region = $interviewer['region'] ?? '—';
    $rows[] = [
        'code'      => $s['interviewer_code'] ?? '—',
        'name'      => $name,
        'region'    => $region,
        'time'      => timeAgo($s['started_at']),
        'status'    => $s['status'] ?? 'active',
        'started_at'=> $s['started_at'],
    ];
}

echo json_encode(['success' => true, 'data' => $rows]);
