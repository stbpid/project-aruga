<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

$region  = getStr('region');
$search  = trim($_GET['search']  ?? '');
$status  = trim($_GET['status']  ?? '');
$limit   = max(1, min(100, (int)($_GET['limit']  ?? 50)));
$offset  = getInt('offset', 0, 0);

if (!$region) {
    echo json_encode(['success' => false, 'message' => 'region required']); exit;
}

function stuNormalizeRegion($r) {
    $r = trim($r ?? '');
    $map = [
        'NCR'                              => 'NCR (National Capital Region)',
        'NCR – Metro Manila'               => 'NCR (National Capital Region)',
        'NCR - Metro Manila'               => 'NCR (National Capital Region)',
        'National Capital Region'          => 'NCR (National Capital Region)',
        'Region I – Ilocos Region'         => 'Region I (Ilocos Region)',
        'Region I - Ilocos Region'         => 'Region I (Ilocos Region)',
        'Region II – Cagayan Valley'       => 'Region II (Cagayan Valley)',
        'Region II - Cagayan Valley'       => 'Region II (Cagayan Valley)',
        'Region III – Central Luzon'       => 'Region III (Central Luzon)',
        'Region III - Central Luzon'       => 'Region III (Central Luzon)',
        'Region IV-A – CALABARZON'         => 'Region IV-A (CALABARZON)',
        'Region IV-A - CALABARZON'         => 'Region IV-A (CALABARZON)',
        'CALABARZON'                       => 'Region IV-A (CALABARZON)',
        'Region IV-B – MIMAROPA'           => 'Region IV-B (MIMAROPA)',
        'Region IV-B - MIMAROPA'           => 'Region IV-B (MIMAROPA)',
        'MIMAROPA'                         => 'Region IV-B (MIMAROPA)',
        'Region V – Bicol Region'          => 'Region V (Bicol Region)',
        'Region V - Bicol Region'          => 'Region V (Bicol Region)',
        'Bicol Region'                     => 'Region V (Bicol Region)',
        'Region V (Bicol)'                 => 'Region V (Bicol Region)',
        'Region VI – Western Visayas'      => 'Region VI (Western Visayas)',
        'Region VI - Western Visayas'      => 'Region VI (Western Visayas)',
        'Region VII – Central Visayas'     => 'Region VII (Central Visayas)',
        'Region VII - Central Visayas'     => 'Region VII (Central Visayas)',
        'Region VIII – Eastern Visayas'    => 'Region VIII (Eastern Visayas)',
        'Region VIII - Eastern Visayas'    => 'Region VIII (Eastern Visayas)',
        'Region IX – Zamboanga Peninsula'  => 'Region IX (Zamboanga Peninsula)',
        'Region IX - Zamboanga Peninsula'  => 'Region IX (Zamboanga Peninsula)',
        'Region X – Northern Mindanao'     => 'Region X (Northern Mindanao)',
        'Region X - Northern Mindanao'     => 'Region X (Northern Mindanao)',
        'Region XI – Davao Region'         => 'Region XI (Davao Region)',
        'Region XI - Davao Region'         => 'Region XI (Davao Region)',
        'Region XII – SOCCSKSARGEN'        => 'Region XII (SOCCSKSARGEN)',
        'Region XII - SOCCSKSARGEN'        => 'Region XII (SOCCSKSARGEN)',
        'Region XIII – Caraga'             => 'Region XIII (Caraga)',
        'Region XIII - Caraga'             => 'Region XIII (Caraga)',
        'Caraga'                           => 'Region XIII (Caraga)',
        'CAR – Cordillera'                 => 'CAR (Cordillera Administrative Region)',
        'CAR - Cordillera'                 => 'CAR (Cordillera Administrative Region)',
        'CAR'                              => 'CAR (Cordillera Administrative Region)',
        'Cordillera'                       => 'CAR (Cordillera Administrative Region)',
        'BARMM'                            => 'BARMM (Bangsamoro)',
        'Bangsamoro'                       => 'BARMM (Bangsamoro)',
    ];
    return $map[$r] ?? ($r ?: '');
}

$normalizedTarget = stuNormalizeRegion($region);

// Fetch all assessments with child region — filter by children.region match
$res = supabaseRequest('GET',
    'assessments?select=id,aruga_id,interviewer_code,status,created_at,readiness_score,children(first_name,last_name,date_of_birth,sex,barangay,region),child_education_health(disabilities)&order=created_at.desc&limit=100000'
);

if (!$res['success']) {
    echo json_encode(['success' => false, 'data' => [], 'total' => 0]); exit;
}

$rows = [];
foreach ($res['data'] as $a) {
    $child = is_array($a['children'])
        ? (isset($a['children'][0]) ? $a['children'][0] : $a['children'])
        : null;

    $childRegion = stuNormalizeRegion($child['region'] ?? '');
    if ($childRegion !== $normalizedTarget) continue;

    $firstName = $child['first_name'] ?? '';
    $lastName  = $child['last_name']  ?? '';
    $fullName  = trim($firstName . ' ' . $lastName) ?: 'Unknown';

    $dob = $child['date_of_birth'] ?? null;
    $age = '—';
    if ($dob) {
        $age = (int)(new DateTime($dob))->diff(new DateTime())->y;
    }

    $arugaId  = $a['aruga_id']          ?? '—';
    $code     = $a['interviewer_code']  ?? '—';
    $readiness= $a['readiness_score']   ?? '—';
    $date     = $a['created_at'] ? date('M j, Y', strtotime($a['created_at'])) : '—';
    $rowStatus= $a['status']            ?? 'pending';

    $cehRaw = $a['child_education_health'] ?? [];
    $ceh = is_array($cehRaw) ? (isset($cehRaw[0]) ? $cehRaw[0] : $cehRaw) : [];
    $disabilities = $ceh['disabilities'] ?? [];
    if (is_string($disabilities)) $disabilities = json_decode($disabilities, true) ?? [];
    if (!is_array($disabilities)) $disabilities = [];
    $disability = !empty($disabilities) ? $disabilities[0] : '—';

    $childRegion = stuNormalizeRegion($child['region'] ?? '');

    if ($status !== '' && $rowStatus !== $status) continue;
    if ($search !== '') {
        $hay = strtolower($fullName . ' ' . $arugaId . ' ' . $childRegion . ' ' . $code . ' ' . implode(' ', $disabilities));
        if (strpos($hay, strtolower($search)) === false) continue;
    }

    $rows[] = [
        'id'             => $a['id']  ?? '',
        'aruga_id'       => $arugaId,
        'name'           => $fullName,
        'age'            => $age,
        'disability'     => $disability,
        'disabilities'   => $disabilities,
        'region'         => $childRegion,
        'interviewer'    => $code,
        'readiness_score'=> $readiness,
        'date'           => $date,
        'status'         => $rowStatus,
    ];
}

$total = count($rows);
$paged = array_slice($rows, $offset, $limit);

echo json_encode(['success' => true, 'data' => $paged, 'total' => $total]);
