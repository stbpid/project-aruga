<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
requireRole(['admin', 'central', 'stu_head']);

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

$res = supabaseRequest('GET',
    'signatories?select=id,signatory_fullname,signatory_position,signatory_office,signatory_region,signatory_function,signatory_status&order=signatory_fullname.asc&limit=10000'
);

if (!$res['success']) {
    echo json_encode(['success' => false, 'data' => []]); exit;
}

echo json_encode(['success' => true, 'data' => $res['data']]);
