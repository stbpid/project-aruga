<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://project-aruga.vercel.app');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Fetch 5 most recent assessments with joined children and child_education_health
$res = supabaseRequest('GET',
    'assessments?select=id,aruga_id,interviewer_code,created_at,children(first_name,last_name,date_of_birth,region),child_education_health(disabilities)&order=created_at.desc&limit=5'
);

if (!$res['success']) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch data']);
    exit;
}

$rows = [];
foreach ($res['data'] as $a) {
    $child = is_array($a['children']) ? (isset($a['children'][0]) ? $a['children'][0] : $a['children']) : null;
    $edu   = is_array($a['child_education_health']) ? (isset($a['child_education_health'][0]) ? $a['child_education_health'][0] : $a['child_education_health']) : null;

    $firstName = $child['first_name'] ?? '';
    $lastName  = $child['last_name'] ?? '';
    $fullName  = trim($firstName . ' ' . $lastName) ?: 'Unknown';

    $dob = $child['date_of_birth'] ?? null;
    $age = '';
    if ($dob) {
        $birth = new DateTime($dob);
        $now   = new DateTime();
        $age   = (int)$birth->diff($now)->y;
    }

    $disabilities = $edu['disabilities'] ?? [];
    if (is_string($disabilities)) {
        $disabilities = json_decode($disabilities, true) ?? [];
    }
    $disability = !empty($disabilities) ? $disabilities[0] : '—';

    $rawRegion = $child['region'] ?? '';
    $regionMap = ['NCR'=>'NCR (National Capital Region)','NCR – Metro Manila'=>'NCR (National Capital Region)','NCR - Metro Manila'=>'NCR (National Capital Region)','National Capital Region'=>'NCR (National Capital Region)','Region I – Ilocos Region'=>'Region I (Ilocos Region)','Region I - Ilocos Region'=>'Region I (Ilocos Region)','Region II – Cagayan Valley'=>'Region II (Cagayan Valley)','Region II - Cagayan Valley'=>'Region II (Cagayan Valley)','Region III – Central Luzon'=>'Region III (Central Luzon)','Region III - Central Luzon'=>'Region III (Central Luzon)','Region IV-A – CALABARZON'=>'Region IV-A (CALABARZON)','Region IV-A - CALABARZON'=>'Region IV-A (CALABARZON)','CALABARZON'=>'Region IV-A (CALABARZON)','Region IV-B – MIMAROPA'=>'Region IV-B (MIMAROPA)','Region IV-B - MIMAROPA'=>'Region IV-B (MIMAROPA)','MIMAROPA'=>'Region IV-B (MIMAROPA)','Region V – Bicol Region'=>'Region V (Bicol Region)','Region V - Bicol Region'=>'Region V (Bicol Region)','Bicol Region'=>'Region V (Bicol Region)','Region VI – Western Visayas'=>'Region VI (Western Visayas)','Region VI - Western Visayas'=>'Region VI (Western Visayas)','Region VII – Central Visayas'=>'Region VII (Central Visayas)','Region VII - Central Visayas'=>'Region VII (Central Visayas)','Region VIII – Eastern Visayas'=>'Region VIII (Eastern Visayas)','Region VIII - Eastern Visayas'=>'Region VIII (Eastern Visayas)','Region IX – Zamboanga Peninsula'=>'Region IX (Zamboanga Peninsula)','Region IX - Zamboanga Peninsula'=>'Region IX (Zamboanga Peninsula)','Region X – Northern Mindanao'=>'Region X (Northern Mindanao)','Region X - Northern Mindanao'=>'Region X (Northern Mindanao)','Region XI – Davao Region'=>'Region XI (Davao Region)','Region XI - Davao Region'=>'Region XI (Davao Region)','Region XII – SOCCSKSARGEN'=>'Region XII (SOCCSKSARGEN)','Region XII - SOCCSKSARGEN'=>'Region XII (SOCCSKSARGEN)','Region XIII – Caraga'=>'Region XIII (Caraga)','Region XIII - Caraga'=>'Region XIII (Caraga)','Caraga'=>'Region XIII (Caraga)','CAR – Cordillera'=>'CAR (Cordillera Administrative Region)','CAR - Cordillera'=>'CAR (Cordillera Administrative Region)','CAR'=>'CAR (Cordillera Administrative Region)','Cordillera'=>'CAR (Cordillera Administrative Region)','BARMM'=>'BARMM (Bangsamoro)','Bangsamoro'=>'BARMM (Bangsamoro)'];
    $region = $regionMap[trim($rawRegion)] ?? ($rawRegion ?: '—');
    $arugaId = $a['aruga_id'] ?? '—';
    $code    = $a['interviewer_code'] ?? '—';
    $date    = $a['created_at'] ? date('M j, Y', strtotime($a['created_at'])) : '—';

    $rows[] = [
        'name'        => $fullName,
        'aruga_id'    => $arugaId,
        'age'         => $age !== '' ? $age : '—',
        'disability'  => $disability,
        'region'      => $region,
        'interviewer' => $code,
        'date'        => $date,
    ];
}

echo json_encode(['success' => true, 'data' => $rows]);
