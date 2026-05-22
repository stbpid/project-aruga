<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

$region = getStr('region');
if (!$region) {
    echo json_encode(['success' => false, 'message' => 'region required']); exit;
}

function stuStatsNormalizeRegion($r) {
    $r = trim($r ?? '');
    if (!$r) return '';
    $map = [
        // canonical forms
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

$normalizedTarget = stuStatsNormalizeRegion($region);

// Fetch all assessments with child region
$res = supabaseRequest('GET',
    'assessments?select=id,status,children(region)&limit=100000'
);

$totalBeneficiaries = 0;
$completedCount     = 0;

if ($res['success'] && is_array($res['data'])) {
    foreach ($res['data'] as $a) {
        $child = is_array($a['children'])
            ? (isset($a['children'][0]) ? $a['children'][0] : $a['children'])
            : null;
        $childRegion = stuStatsNormalizeRegion($child['region'] ?? '');
        if ($childRegion !== $normalizedTarget) continue;
        $totalBeneficiaries++;
        if (($a['status'] ?? '') === 'completed') $completedCount++;
    }
}

$pendingCount = $totalBeneficiaries - $completedCount;

// Interviewer count — fetch all and filter by normalized region to handle inconsistent stored values
$intRes = supabaseRequest('GET',
    'interviewers?select=interviewer_code,region&limit=10000'
);
$interviewerCount = 0;
if ($intRes['success'] && is_array($intRes['data'])) {
    foreach ($intRes['data'] as $r) {
        if (!empty($r['interviewer_code']) && stuStatsNormalizeRegion($r['region'] ?? '') === $normalizedTarget) {
            $interviewerCount++;
        }
    }
}

echo json_encode([
    'success' => true,
    'data' => [
        'total_beneficiaries' => $totalBeneficiaries,
        'completed'           => $completedCount,
        'pending'             => $pendingCount,
        'interviewer_count'   => $interviewerCount,
    ]
]);
