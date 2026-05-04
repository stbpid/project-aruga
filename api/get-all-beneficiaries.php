<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

$limit  = isset($_GET['limit'])  ? (int)$_GET['limit']  : 50;
$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$search = isset($_GET['search']) ? trim($_GET['search']) : '';
$region = isset($_GET['region']) ? trim($_GET['region']) : '';

// Build query
$query = 'assessments?select=id,aruga_id,interviewer_code,created_at,children(first_name,last_name,date_of_birth,region),child_education_health(disabilities)&order=created_at.desc';

$res = supabaseRequest('GET', $query . '&limit=10000');

if (!$res['success']) {
    echo json_encode(['success' => false, 'data' => [], 'total' => 0]); exit;
}

$rows = [];
foreach ($res['data'] as $a) {
    $child = is_array($a['children']) ? (isset($a['children'][0]) ? $a['children'][0] : $a['children']) : null;
    $edu   = is_array($a['child_education_health']) ? (isset($a['child_education_health'][0]) ? $a['child_education_health'][0] : $a['child_education_health']) : null;

    $firstName = $child['first_name'] ?? '';
    $lastName  = $child['last_name'] ?? '';
    $fullName  = trim($firstName . ' ' . $lastName) ?: 'Unknown';

    $dob = $child['date_of_birth'] ?? null;
    $age = '—';
    if ($dob) {
        $birth = new DateTime($dob);
        $age   = (int)$birth->diff(new DateTime())->y;
    }

    $disabilities = $edu['disabilities'] ?? [];
    if (is_string($disabilities)) $disabilities = json_decode($disabilities, true) ?? [];
    $disability = !empty($disabilities) ? $disabilities[0] : '—';

    $childRegion = $child['region'] ?? '—';
    $arugaId     = $a['aruga_id'] ?? '—';
    $code        = $a['interviewer_code'] ?? '—';
    $date        = $a['created_at'] ? date('M j, Y', strtotime($a['created_at'])) : '—';

    // Filter by region
    if ($region !== '' && stripos($childRegion, $region) === false) continue;

    // Filter by search (name or aruga_id or interviewer code)
    if ($search !== '') {
        $hay = strtolower($fullName . ' ' . $arugaId . ' ' . $code . ' ' . $childRegion);
        if (strpos($hay, strtolower($search)) === false) continue;
    }

    $rows[] = [
        'name'        => $fullName,
        'aruga_id'    => $arugaId,
        'age'         => $age,
        'disability'  => $disability,
        'region'      => $childRegion,
        'interviewer' => $code,
        'date'        => $date,
    ];
}

$total   = count($rows);
$paged   = array_slice($rows, $offset, $limit);

echo json_encode(['success' => true, 'data' => $paged, 'total' => $total]);
