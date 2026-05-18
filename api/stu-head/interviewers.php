<?php
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://project-aruga.vercel.app');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

$region = trim($_GET['region'] ?? '');
if (!$region) {
    echo json_encode(['success' => false, 'message' => 'region required']); exit;
}

function normalizeRegion($r) {
    $r = trim($r ?? '');
    if (!$r) return '—';
    $map = [
        // canonical forms map to themselves
        'NCR (National Capital Region)'          => 'NCR (National Capital Region)',
        'Region I (Ilocos Region)'               => 'Region I (Ilocos Region)',
        'Region II (Cagayan Valley)'             => 'Region II (Cagayan Valley)',
        'Region III (Central Luzon)'             => 'Region III (Central Luzon)',
        'Region IV-A (CALABARZON)'               => 'Region IV-A (CALABARZON)',
        'Region IV-B (MIMAROPA)'                 => 'Region IV-B (MIMAROPA)',
        'Region V (Bicol Region)'                => 'Region V (Bicol Region)',
        'Region VI (Western Visayas)'            => 'Region VI (Western Visayas)',
        'Region VII (Central Visayas)'           => 'Region VII (Central Visayas)',
        'Region VIII (Eastern Visayas)'          => 'Region VIII (Eastern Visayas)',
        'Region IX (Zamboanga Peninsula)'        => 'Region IX (Zamboanga Peninsula)',
        'Region X (Northern Mindanao)'           => 'Region X (Northern Mindanao)',
        'Region XI (Davao Region)'               => 'Region XI (Davao Region)',
        'Region XII (SOCCSKSARGEN)'              => 'Region XII (SOCCSKSARGEN)',
        'Region XIII (Caraga)'                   => 'Region XIII (Caraga)',
        'CAR (Cordillera Administrative Region)' => 'CAR (Cordillera Administrative Region)',
        'BARMM (Bangsamoro)'                     => 'BARMM (Bangsamoro)',
        // aliases
        'NCR'                             => 'NCR (National Capital Region)',
        'NCR – Metro Manila'              => 'NCR (National Capital Region)',
        'NCR - Metro Manila'              => 'NCR (National Capital Region)',
        'National Capital Region'         => 'NCR (National Capital Region)',
        'Region I – Ilocos Region'        => 'Region I (Ilocos Region)',
        'Region I - Ilocos Region'        => 'Region I (Ilocos Region)',
        'Region II – Cagayan Valley'      => 'Region II (Cagayan Valley)',
        'Region II - Cagayan Valley'      => 'Region II (Cagayan Valley)',
        'Region III – Central Luzon'      => 'Region III (Central Luzon)',
        'Region III - Central Luzon'      => 'Region III (Central Luzon)',
        'Region IV-A – CALABARZON'        => 'Region IV-A (CALABARZON)',
        'Region IV-A - CALABARZON'        => 'Region IV-A (CALABARZON)',
        'CALABARZON'                      => 'Region IV-A (CALABARZON)',
        'Region IV-B – MIMAROPA'          => 'Region IV-B (MIMAROPA)',
        'Region IV-B - MIMAROPA'          => 'Region IV-B (MIMAROPA)',
        'MIMAROPA'                        => 'Region IV-B (MIMAROPA)',
        'Region V – Bicol Region'         => 'Region V (Bicol Region)',
        'Region V - Bicol Region'         => 'Region V (Bicol Region)',
        'Region V (Bicol)'                => 'Region V (Bicol Region)',
        'Bicol Region'                    => 'Region V (Bicol Region)',
        'Region VI – Western Visayas'     => 'Region VI (Western Visayas)',
        'Region VI - Western Visayas'     => 'Region VI (Western Visayas)',
        'Region VII – Central Visayas'    => 'Region VII (Central Visayas)',
        'Region VII - Central Visayas'    => 'Region VII (Central Visayas)',
        'Region VIII – Eastern Visayas'   => 'Region VIII (Eastern Visayas)',
        'Region VIII - Eastern Visayas'   => 'Region VIII (Eastern Visayas)',
        'Region IX – Zamboanga Peninsula' => 'Region IX (Zamboanga Peninsula)',
        'Region IX - Zamboanga Peninsula' => 'Region IX (Zamboanga Peninsula)',
        'Region X – Northern Mindanao'    => 'Region X (Northern Mindanao)',
        'Region X - Northern Mindanao'    => 'Region X (Northern Mindanao)',
        'Region XI – Davao Region'        => 'Region XI (Davao Region)',
        'Region XI - Davao Region'        => 'Region XI (Davao Region)',
        'Region XII – SOCCSKSARGEN'       => 'Region XII (SOCCSKSARGEN)',
        'Region XII - SOCCSKSARGEN'       => 'Region XII (SOCCSKSARGEN)',
        'Region XIII – Caraga'            => 'Region XIII (Caraga)',
        'Region XIII - Caraga'            => 'Region XIII (Caraga)',
        'Caraga'                          => 'Region XIII (Caraga)',
        'CAR – Cordillera'                => 'CAR (Cordillera Administrative Region)',
        'CAR - Cordillera'                => 'CAR (Cordillera Administrative Region)',
        'CAR'                             => 'CAR (Cordillera Administrative Region)',
        'Cordillera'                      => 'CAR (Cordillera Administrative Region)',
        'BARMM'                           => 'BARMM (Bangsamoro)',
        'Bangsamoro'                      => 'BARMM (Bangsamoro)',
    ];
    if (isset($map[$r])) return $map[$r];
    // case-insensitive fallback
    $rLower = mb_strtolower($r);
    foreach ($map as $key => $val) {
        if (mb_strtolower($key) === $rLower) return $val;
    }
    return $r;
}

// Normalize the incoming region so it matches what's stored in various formats.
// Fetch all interviewers and filter in PHP to handle inconsistent DB values.
$normalizedRegion = normalizeRegion($region);

$res = supabaseRequest('GET',
    'interviewers?select=id,full_name,interviewer_code,email,region,province,position,office,status&order=full_name.asc&limit=10000'
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

// Filter by normalized region
$filtered = array_filter($res['data'], function($r) use ($normalizedRegion) {
    return normalizeRegion($r['region'] ?? '') === $normalizedRegion;
});

$rows = array_map(function($r) use ($totalMap, $completedMap, $lastActiveMap) {
    $code  = $r['interviewer_code'] ?? '—';
    return [
        'id'               => $r['id'] ?? null,
        'name'             => $r['full_name'] ?? '—',
        'code'             => $code,
        'email'            => $r['email'] ?? '—',
        'region'           => normalizeRegion($r['region'] ?? ''),
        'province'         => $r['province'] ?? '—',
        'position'         => $r['position'] ?? '—',
        'office'           => $r['office'] ?? '—',
        'status'           => $r['status'] ?? 'active',
        'submissions_total'=> $totalMap[$code] ?? 0,
        'completed_total'  => $completedMap[$code] ?? 0,
        'last_active'      => $lastActiveMap[$code] ?? null,
    ];
}, $filtered);

echo json_encode(['success' => true, 'data' => $rows]);
