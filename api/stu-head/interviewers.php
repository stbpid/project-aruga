<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

$region = trim($_GET['region'] ?? '');
if (!$region) {
    echo json_encode(['success' => false, 'message' => 'region required']); exit;
}

$regionEncoded = urlencode($region);

$res = supabaseRequest('GET',
    'interviewers?select=id,full_name,interviewer_code,email,region,province,position,office,status&region=eq.' . $regionEncoded . '&order=full_name.asc&limit=10000'
);

if (!$res['success']) {
    echo json_encode(['success' => false, 'data' => []]); exit;
}

$monthStart = date('Y-m-01T00:00:00');
$monthEnd   = date('Y-m-t') . 'T23:59:59';

$codes = array_filter(array_column($res['data'], 'interviewer_code'));

$totalMap      = [];
$completedMap  = [];
$lastActiveMap = [];

if (!empty($codes)) {
    $codeIn = implode(',', $codes);
    $aRes = supabaseRequest('GET',
        'assessments?select=interviewer_code,status,created_at&interviewer_code=in.(' . $codeIn . ')&limit=100000'
    );
    if ($aRes['success'] && is_array($aRes['data'])) {
        foreach ($aRes['data'] as $a) {
            $code = $a['interviewer_code'] ?? '';
            if (!$code) continue;
            $totalMap[$code] = ($totalMap[$code] ?? 0) + 1;
            if ($a['status'] === 'completed') $completedMap[$code] = ($completedMap[$code] ?? 0) + 1;
            $ca = $a['created_at'] ?? '';
            if ($ca && (!isset($lastActiveMap[$code]) || $ca > $lastActiveMap[$code])) {
                $lastActiveMap[$code] = $ca;
            }
        }
    }

    $sRes = supabaseRequest('GET',
        'sessions?select=interviewer_code,created_at&interviewer_code=in.(' . $codeIn . ')&limit=100000'
    );
    if ($sRes['success'] && is_array($sRes['data'])) {
        foreach ($sRes['data'] as $s) {
            $code = $s['interviewer_code'] ?? '';
            $ca   = $s['created_at'] ?? '';
            if (!$code || !$ca) continue;
            if (!isset($lastActiveMap[$code]) || $ca > $lastActiveMap[$code]) {
                $lastActiveMap[$code] = $ca;
            }
        }
    }
}

$rows = array_map(function($r) use ($totalMap, $completedMap, $lastActiveMap) {
    $code  = $r['interviewer_code'] ?? '—';
    return [
        'id'               => $r['id'] ?? null,
        'name'             => $r['full_name'] ?? '—',
        'code'             => $code,
        'email'            => $r['email'] ?? '—',
        'region'           => $r['region'] ?? '—',
        'province'         => $r['province'] ?? '—',
        'position'         => $r['position'] ?? '—',
        'office'           => $r['office'] ?? '—',
        'status'           => $r['status'] ?? 'active',
        'submissions_total'=> $totalMap[$code] ?? 0,
        'completed_total'  => $completedMap[$code] ?? 0,
        'last_active'      => $lastActiveMap[$code] ?? null,
    ];
}, $res['data']);

echo json_encode(['success' => true, 'data' => $rows]);
