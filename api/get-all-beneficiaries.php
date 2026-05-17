<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
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
        'Region VI – Western Visayas'  => 'Region VI (Western Visayas)',
        'Region VI - Western Visayas'  => 'Region VI (Western Visayas)',
        'Region VII – Central Visayas' => 'Region VII (Central Visayas)',
        'Region VII - Central Visayas' => 'Region VII (Central Visayas)',
        'Region VIII – Eastern Visayas'=> 'Region VIII (Eastern Visayas)',
        'Region VIII - Eastern Visayas'=> 'Region VIII (Eastern Visayas)',
        'Region IX – Zamboanga Peninsula'=> 'Region IX (Zamboanga Peninsula)',
        'Region IX - Zamboanga Peninsula'=> 'Region IX (Zamboanga Peninsula)',
        'Region X – Northern Mindanao' => 'Region X (Northern Mindanao)',
        'Region X - Northern Mindanao' => 'Region X (Northern Mindanao)',
        'Region XI – Davao Region'     => 'Region XI (Davao Region)',
        'Region XI - Davao Region'     => 'Region XI (Davao Region)',
        'Region XII – SOCCSKSARGEN'    => 'Region XII (SOCCSKSARGEN)',
        'Region XII - SOCCSKSARGEN'    => 'Region XII (SOCCSKSARGEN)',
        'Region XIII – Caraga'         => 'Region XIII (Caraga)',
        'Region XIII - Caraga'         => 'Region XIII (Caraga)',
        'Caraga'                       => 'Region XIII (Caraga)',
        'CAR – Cordillera'             => 'CAR (Cordillera Administrative Region)',
        'CAR - Cordillera'             => 'CAR (Cordillera Administrative Region)',
        'CAR'                          => 'CAR (Cordillera Administrative Region)',
        'Cordillera'                   => 'CAR (Cordillera Administrative Region)',
        'BARMM'                        => 'BARMM (Bangsamoro)',
        'Bangsamoro'                   => 'BARMM (Bangsamoro)',
    ];
    return $map[$r] ?? ($r ?: '—');
}

$limit          = isset($_GET['limit'])          ? (int)$_GET['limit']              : 50;
$offset         = isset($_GET['offset'])         ? (int)$_GET['offset']             : 0;
$search         = isset($_GET['search'])         ? trim($_GET['search'])            : '';
$region         = isset($_GET['region'])         ? trim($_GET['region'])            : '';
$readinessScore = isset($_GET['readiness_score'])? trim($_GET['readiness_score'])   : '';
$disabilityType = isset($_GET['disability_type'])? trim($_GET['disability_type'])   : '';
$only4ps        = isset($_GET['is_4ps_member'])  && $_GET['is_4ps_member'] === '1';

// Fetch assessments with readiness_score
$res = supabaseRequest('GET',
    'assessments?select=id,aruga_id,interviewer_code,created_at,readiness_score,children(first_name,last_name,date_of_birth,region),child_education_health(disabilities)&order=created_at.desc&limit=10000'
);

if (!$res['success']) {
    echo json_encode(['success' => false, 'data' => [], 'total' => 0]); exit;
}

// Fetch 4Ps data
$pqRes = supabaseRequest('GET', 'pre_qualification?select=assessment_id,is_4ps_member&limit=100000');
$pqMap = [];
if ($pqRes['success'] && is_array($pqRes['data'])) {
    foreach ($pqRes['data'] as $pq) {
        $pqMap[$pq['assessment_id']] = (bool)($pq['is_4ps_member'] ?? false);
    }
}

$rows = [];
foreach ($res['data'] as $a) {
    $child = is_array($a['children'])
        ? (isset($a['children'][0]) ? $a['children'][0] : $a['children'])
        : null;
    $edu = is_array($a['child_education_health'])
        ? (isset($a['child_education_health'][0]) ? $a['child_education_health'][0] : $a['child_education_health'])
        : null;

    $firstName = $child['first_name'] ?? '';
    $lastName  = $child['last_name']  ?? '';
    $fullName  = trim($firstName . ' ' . $lastName) ?: 'Unknown';

    $dob = $child['date_of_birth'] ?? null;
    $age = '—';
    if ($dob) {
        $birth = new DateTime($dob);
        $age   = (int)$birth->diff(new DateTime())->y;
    }

    $disabilities = $edu['disabilities'] ?? [];
    if (is_string($disabilities)) $disabilities = json_decode($disabilities, true) ?? [];
    if (!is_array($disabilities)) $disabilities = [];
    $disability = !empty($disabilities) ? $disabilities[0] : '—';

    $childRegion   = normalizeRegion($child['region'] ?? '');
    $arugaId       = $a['aruga_id']         ?? '—';
    $code          = $a['interviewer_code'] ?? '—';
    $date          = $a['created_at'] ? date('M j, Y', strtotime($a['created_at'])) : '—';
    $readiness     = $a['readiness_score']  ?? '—';
    $is4ps         = $pqMap[$a['id']] ?? false;

    // Filters
    if ($region !== '' && $childRegion !== normalizeRegion($region)) continue;
    if ($readinessScore !== '' && strtolower($readiness) !== strtolower($readinessScore)) continue;
    if ($disabilityType !== '') {
        $found = false;
        foreach ($disabilities as $d) {
            if (stripos($d, $disabilityType) !== false) { $found = true; break; }
        }
        if (!$found) continue;
    }
    if ($only4ps && !$is4ps) continue;
    if ($search !== '') {
        $hay = strtolower($fullName . ' ' . $arugaId . ' ' . $code . ' ' . $childRegion . ' ' . $readiness);
        if (strpos($hay, strtolower($search)) === false) continue;
    }

    $rows[] = [
        'name'            => $fullName,
        'aruga_id'        => $arugaId,
        'age'             => $age,
        'disability'      => $disability,
        'disabilities'    => $disabilities,
        'region'          => $childRegion,
        'interviewer'     => $code,
        'date'            => $date,
        'readiness_score' => $readiness,
        'is_4ps_member'   => $is4ps,
    ];
}

$total = count($rows);
$paged = array_slice($rows, $offset, $limit);

// Disability type counts (from full unfiltered set — run separately if needed)
echo json_encode(['success' => true, 'data' => $paged, 'total' => $total]);
