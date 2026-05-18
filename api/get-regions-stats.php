<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://project-aruga.vercel.app');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
}

$filterRegion = isset($_GET['region']) ? trim($_GET['region']) : '';
$filterProvince = isset($_GET['province']) ? trim($_GET['province']) : '';

// Pull all children with location + assessment status + disability
$childRes = supabaseRequest('GET',
    'children?select=assessment_id,region,province,city_municipality,barangay,sex,date_of_birth&limit=100000'
);

// Pull assessments for status
$assRes = supabaseRequest('GET',
    'assessments?select=id,status,readiness_score,created_at,interviewer_code&limit=100000'
);

// Pull interviewers count per region
$intRes = supabaseRequest('GET',
    'interviewers?select=region,status&limit=100000'
);

// Pull disabilities
$disRes = supabaseRequest('GET',
    'child_education_health?select=assessment_id,disabilities&limit=100000'
);

if (!$childRes['success']) {
    echo json_encode(['success' => false, 'data' => [], 'regions' => [], 'provinces' => [], 'summary' => []]); exit;
}

// Build assessment lookup
$assMap = [];
if ($assRes['success'] && is_array($assRes['data'])) {
    foreach ($assRes['data'] as $a) {
        $assMap[$a['id']] = [
            'status'         => $a['status'] ?? 'in_progress',
            'readiness_score'=> $a['readiness_score'] ?? null,
            'created_at'     => $a['created_at'] ?? null,
            'interviewer_code'=> $a['interviewer_code'] ?? null,
        ];
    }
}

// Build disability lookup
$disMap = [];
if ($disRes['success'] && is_array($disRes['data'])) {
    foreach ($disRes['data'] as $d) {
        $dis = $d['disabilities'] ?? [];
        if (is_string($dis)) $dis = json_decode($dis, true) ?? [];
        $disMap[$d['assessment_id']] = $dis;
    }
}

// Normalize legacy region strings to canonical format
function normalizeRegion($r) {
    $r = trim($r);
    $map = [
        'NCR'                         => 'NCR (National Capital Region)',
        'NCR – Metro Manila'          => 'NCR (National Capital Region)',
        'NCR - Metro Manila'          => 'NCR (National Capital Region)',
        'National Capital Region'     => 'NCR (National Capital Region)',
        'Region I – Ilocos Region'    => 'Region I (Ilocos Region)',
        'Region I - Ilocos Region'    => 'Region I (Ilocos Region)',
        'Region II – Cagayan Valley'  => 'Region II (Cagayan Valley)',
        'Region II - Cagayan Valley'  => 'Region II (Cagayan Valley)',
        'Region III – Central Luzon'  => 'Region III (Central Luzon)',
        'Region III - Central Luzon'  => 'Region III (Central Luzon)',
        'Region IV-A – CALABARZON'    => 'Region IV-A (CALABARZON)',
        'Region IV-A - CALABARZON'    => 'Region IV-A (CALABARZON)',
        'CALABARZON'                  => 'Region IV-A (CALABARZON)',
        'Region IV-B – MIMAROPA'      => 'Region IV-B (MIMAROPA)',
        'Region IV-B - MIMAROPA'      => 'Region IV-B (MIMAROPA)',
        'MIMAROPA'                    => 'Region IV-B (MIMAROPA)',
        'Region V – Bicol Region'     => 'Region V (Bicol Region)',
        'Region V - Bicol Region'     => 'Region V (Bicol Region)',
        'Bicol Region'                => 'Region V (Bicol Region)',
        'Region VI – Western Visayas' => 'Region VI (Western Visayas)',
        'Region VI - Western Visayas' => 'Region VI (Western Visayas)',
        'Region VII – Central Visayas'=> 'Region VII (Central Visayas)',
        'Region VII - Central Visayas'=> 'Region VII (Central Visayas)',
        'Region VIII – Eastern Visayas'=> 'Region VIII (Eastern Visayas)',
        'Region VIII - Eastern Visayas'=> 'Region VIII (Eastern Visayas)',
        'Region IX – Zamboanga Peninsula'=> 'Region IX (Zamboanga Peninsula)',
        'Region IX - Zamboanga Peninsula'=> 'Region IX (Zamboanga Peninsula)',
        'Region X – Northern Mindanao'=> 'Region X (Northern Mindanao)',
        'Region X - Northern Mindanao'=> 'Region X (Northern Mindanao)',
        'Region XI – Davao Region'    => 'Region XI (Davao Region)',
        'Region XI - Davao Region'    => 'Region XI (Davao Region)',
        'Region XII – SOCCSKSARGEN'   => 'Region XII (SOCCSKSARGEN)',
        'Region XII - SOCCSKSARGEN'   => 'Region XII (SOCCSKSARGEN)',
        'Region XIII – Caraga'        => 'Region XIII (Caraga)',
        'Region XIII - Caraga'        => 'Region XIII (Caraga)',
        'Caraga'                      => 'Region XIII (Caraga)',
        'CAR – Cordillera'            => 'CAR (Cordillera Administrative Region)',
        'CAR - Cordillera'            => 'CAR (Cordillera Administrative Region)',
        'CAR'                         => 'CAR (Cordillera Administrative Region)',
        'Cordillera'                  => 'CAR (Cordillera Administrative Region)',
        'BARMM'                       => 'BARMM (Bangsamoro)',
        'Bangsamoro'                  => 'BARMM (Bangsamoro)',
    ];
    return $map[$r] ?? $r;
}

// Build interviewer counts per region
$intRegionCount = [];
if ($intRes['success'] && is_array($intRes['data'])) {
    foreach ($intRes['data'] as $iv) {
        $r = normalizeRegion($iv['region'] ?? '');
        if ($r === '') continue;
        $intRegionCount[$r] = ($intRegionCount[$r] ?? 0) + 1;
    }
}

// --- Aggregate ---
$regionMap   = [];
$provinceMap = [];
$cityMap     = [];
$disCount    = [];
$monthlyMap  = [];
$totalProfiled = 0;

foreach ($childRes['data'] as $row) {
    $region   = normalizeRegion(trim($row['region']   ?? ''));
    $province = trim($row['province']          ?? '');
    $city     = trim($row['city_municipality'] ?? '');
    $barangay = trim($row['barangay']          ?? '');
    $aid      = $row['assessment_id']          ?? null;
    $sex      = trim($row['sex']               ?? '');
    $dob      = $row['date_of_birth']          ?? null;

    if ($region === '') continue;
    if ($filterRegion  !== '' && $region   !== $filterRegion)  continue;
    if ($filterProvince !== '' && $province !== $filterProvince) continue;

    $ass    = $assMap[$aid]    ?? ['status'=>'in_progress','readiness_score'=>null,'created_at'=>null];
    $disArr = $disMap[$aid]    ?? [];
    $status = $ass['status'];
    $month  = $ass['created_at'] ? substr($ass['created_at'],0,7) : null;

    // Age
    $age = null;
    if ($dob) {
        try { $age = (int)(new DateTime($dob))->diff(new DateTime())->y; } catch(Exception $e){}
    }

    $totalProfiled++;

    // Region
    if (!isset($regionMap[$region])) {
        $regionMap[$region] = ['region'=>$region,'total'=>0,'completed'=>0,'in_progress'=>0,'abandoned'=>0,'male'=>0,'female'=>0,'interviewers'=>$intRegionCount[$region]??0,'provinces'=>[]];
    }
    $regionMap[$region]['total']++;
    if ($status === 'completed')  $regionMap[$region]['completed']++;
    elseif ($status === 'abandoned') $regionMap[$region]['abandoned']++;
    else $regionMap[$region]['in_progress']++;
    if ($sex === 'Male')   $regionMap[$region]['male']++;
    if ($sex === 'Female') $regionMap[$region]['female']++;
    if ($province !== '') $regionMap[$region]['provinces'][$province] = true;

    // Province
    $pKey = $region . '||' . $province;
    if (!isset($provinceMap[$pKey])) {
        $provinceMap[$pKey] = ['region'=>$region,'province'=>$province,'total'=>0,'completed'=>0,'in_progress'=>0,'cities'=>[]];
    }
    $provinceMap[$pKey]['total']++;
    if ($status === 'completed') $provinceMap[$pKey]['completed']++;
    else $provinceMap[$pKey]['in_progress']++;
    if ($city !== '') $provinceMap[$pKey]['cities'][$city] = true;

    // City
    $cKey = $region . '||' . $province . '||' . $city;
    if (!isset($cityMap[$cKey])) {
        $cityMap[$cKey] = ['region'=>$region,'province'=>$province,'city'=>$city,'total'=>0,'completed'=>0];
    }
    $cityMap[$cKey]['total']++;
    if ($status === 'completed') $cityMap[$cKey]['completed']++;

    // Disabilities
    foreach ($disArr as $d) {
        if ($d) $disCount[$d] = ($disCount[$d] ?? 0) + 1;
    }

    // Monthly
    if ($month) $monthlyMap[$month] = ($monthlyMap[$month] ?? 0) + 1;
}

// Finalize regions
$regions = [];
foreach ($regionMap as $r) {
    $r['province_count'] = count($r['provinces']);
    unset($r['provinces']);
    $r['rate'] = $r['total'] > 0 ? round(($r['completed'] / $r['total']) * 100) : 0;
    $regions[] = $r;
}
usort($regions, fn($a,$b) => $b['total'] - $a['total']);

// Finalize provinces
$provinces = [];
foreach ($provinceMap as $p) {
    $p['city_count'] = count($p['cities']);
    unset($p['cities']);
    $p['rate'] = $p['total'] > 0 ? round(($p['completed'] / $p['total']) * 100) : 0;
    $provinces[] = $p;
}
usort($provinces, fn($a,$b) => $b['total'] - $a['total']);

// Finalize cities
$cities = [];
foreach ($cityMap as $c) {
    $c['rate'] = $c['total'] > 0 ? round(($c['completed'] / $c['total']) * 100) : 0;
    $cities[] = $c;
}
usort($cities, fn($a,$b) => $b['total'] - $a['total']);

// Top disabilities
arsort($disCount);
$topDisabilities = array_slice(array_map(fn($k,$v)=>['label'=>$k,'count'=>$v], array_keys($disCount), array_values($disCount)), 0, 10);

// Monthly trend (last 12)
ksort($monthlyMap);
$monthlyTrend = [];
foreach (array_slice($monthlyMap, -12, 12, true) as $m => $cnt) {
    $monthlyTrend[] = ['month'=>$m,'count'=>$cnt];
}

// Summary
$totalCompleted   = array_sum(array_column($regions,'completed'));
$totalInProgress  = array_sum(array_column($regions,'in_progress'));
$totalAbandoned   = array_sum(array_column($regions,'abandoned'));
$totalInterviewers= array_sum(array_column($regions,'interviewers'));

echo json_encode([
    'success'       => true,
    'summary'       => [
        'total_profiled'  => $totalProfiled,
        'completed'       => $totalCompleted,
        'in_progress'     => $totalInProgress,
        'abandoned'       => $totalAbandoned,
        'regions_count'   => count($regions),
        'interviewers'    => $totalInterviewers,
    ],
    'regions'       => $regions,
    'provinces'     => $provinces,
    'cities'        => array_slice($cities, 0, 100),
    'disabilities'  => $topDisabilities,
    'monthly_trend' => $monthlyTrend,
]);
