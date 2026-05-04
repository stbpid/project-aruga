<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

$res = supabaseRequest('GET',
    'interviewers?select=id,full_name,interviewer_code,region,province,position,office,status&order=full_name.asc&limit=10000'
);

if (!$res['success']) {
    echo json_encode(['success' => false, 'data' => []]); exit;
}

$rows = array_map(function($r) {
    return [
        'id'       => $r['id'] ?? null,
        'name'     => $r['full_name'] ?? '—',
        'code'     => $r['interviewer_code'] ?? '—',
        'region'   => $r['region'] ?? '—',
        'province' => $r['province'] ?? '—',
        'position' => $r['position'] ?? '—',
        'office'   => $r['office'] ?? '—',
        'status'   => $r['status'] ?? 'active',
    ];
}, $res['data']);

echo json_encode(['success' => true, 'data' => $rows]);
