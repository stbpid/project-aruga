<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

function normalizeRegion($r) {
    $r = trim($r ?? '');
    $map = [
        'NCR'                          => 'NCR (National Capital Region)',
        'NCR – Metro Manila'           => 'NCR (National Capital Region)',
        'NCR - Metro Manila'           => 'NCR (National Capital Region)',
        'National Capital Region'      => 'NCR (National Capital Region)',
        'Region I – Ilocos Region'     => 'Region I (Ilocos Region)',
        'Region I - Ilocos Region'     => 'Region I (Ilocos Region)',
        'Region II – Cagayan Valley'   => 'Region II (Cagayan Valley)',
        'Region II - Cagayan Valley'   => 'Region II (Cagayan Valley)',
        'Region III – Central Luzon'   => 'Region III (Central Luzon)',
        'Region III - Central Luzon'   => 'Region III (Central Luzon)',
        'Region IV-A – CALABARZON'     => 'Region IV-A (CALABARZON)',
        'Region IV-A - CALABARZON'     => 'Region IV-A (CALABARZON)',
        'CALABARZON'                   => 'Region IV-A (CALABARZON)',
        'Region IV-B – MIMAROPA'       => 'Region IV-B (MIMAROPA)',
        'Region IV-B - MIMAROPA'       => 'Region IV-B (MIMAROPA)',
        'MIMAROPA'                     => 'Region IV-B (MIMAROPA)',
        'Region V – Bicol Region'      => 'Region V (Bicol Region)',
        'Region V - Bicol Region'      => 'Region V (Bicol Region)',
        'Bicol Region'                 => 'Region V (Bicol Region)',
        'Region V'                     => 'Region V (Bicol Region)',
        'Region V (Bicol)'             => 'Region V (Bicol Region)',
        'Region VI – Western Visayas'  => 'Region VI (Western Visayas)',
        'Region VI - Western Visayas'  => 'Region VI (Western Visayas)',
        'Region VI'                    => 'Region VI (Western Visayas)',
        'Region VII – Central Visayas' => 'Region VII (Central Visayas)',
        'Region VII - Central Visayas' => 'Region VII (Central Visayas)',
        'Region VII'                   => 'Region VII (Central Visayas)',
        'Region VIII – Eastern Visayas'=> 'Region VIII (Eastern Visayas)',
        'Region VIII - Eastern Visayas'=> 'Region VIII (Eastern Visayas)',
        'Region VIII'                  => 'Region VIII (Eastern Visayas)',
        'Region IX – Zamboanga Peninsula'=> 'Region IX (Zamboanga Peninsula)',
        'Region IX - Zamboanga Peninsula'=> 'Region IX (Zamboanga Peninsula)',
        'Region IX'                    => 'Region IX (Zamboanga Peninsula)',
        'Region X – Northern Mindanao' => 'Region X (Northern Mindanao)',
        'Region X - Northern Mindanao' => 'Region X (Northern Mindanao)',
        'Region X'                     => 'Region X (Northern Mindanao)',
        'Region XI – Davao Region'     => 'Region XI (Davao Region)',
        'Region XI - Davao Region'     => 'Region XI (Davao Region)',
        'Region XI'                    => 'Region XI (Davao Region)',
        'Region XII – SOCCSKSARGEN'    => 'Region XII (SOCCSKSARGEN)',
        'Region XII - SOCCSKSARGEN'    => 'Region XII (SOCCSKSARGEN)',
        'Region XII'                   => 'Region XII (SOCCSKSARGEN)',
        'Region XIII – Caraga'         => 'Region XIII (Caraga)',
        'Region XIII - Caraga'         => 'Region XIII (Caraga)',
        'Caraga'                       => 'Region XIII (Caraga)',
        'Region XIII'                  => 'Region XIII (Caraga)',
        'CAR – Cordillera'             => 'CAR (Cordillera Administrative Region)',
        'CAR - Cordillera'             => 'CAR (Cordillera Administrative Region)',
        'CAR'                          => 'CAR (Cordillera Administrative Region)',
        'Cordillera'                   => 'CAR (Cordillera Administrative Region)',
        'BARMM'                        => 'BARMM (Bangsamoro)',
        'Bangsamoro'                   => 'BARMM (Bangsamoro)',
    ];
    return $map[$r] ?? ($r ?: '');
}

// Fetch non-deleted assessment IDs, then count children by region for those assessments only
$assRes = supabaseRequest('GET', 'assessments?select=id&deleted_at=is.null&limit=100000');
$validIds = ($assRes['success'] && is_array($assRes['data'])) ? array_flip(array_column($assRes['data'], 'id')) : [];

$res = supabaseRequest('GET', 'children?select=region,assessment_id&limit=10000');

$counts = [];
if ($res['success'] && is_array($res['data'])) {
    foreach ($res['data'] as $row) {
        if (!isset($validIds[$row['assessment_id'] ?? ''])) continue;
        $r = normalizeRegion($row['region'] ?? '');
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
