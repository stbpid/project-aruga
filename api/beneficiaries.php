<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

$interviewerCode = trim($_GET['interviewerCode'] ?? '');
$search          = trim($_GET['search'] ?? '');
$page            = max(1, (int)($_GET['page'] ?? 1));
$limit           = 20;
$offset          = ($page - 1) * $limit;

if (empty($interviewerCode)) {
    echo json_encode(['success' => false, 'message' => 'interviewerCode required']);
    exit;
}

$code = urlencode($interviewerCode);

// Fetch all matching assessments for this interviewer (with child data)
$res = supabaseRequest('GET',
    "assessments?select=id,aruga_id,status,created_at,readiness_score,children(first_name,last_name,date_of_birth,sex,barangay,region),child_education_health(disabilities)&interviewer_code=eq.$code&order=created_at.desc&limit=10000"
);

if (!$res['success']) {
    echo json_encode(['success' => false, 'message' => 'Failed to fetch data']);
    exit;
}

$rows = [];
foreach ($res['data'] as $a) {
    $child = is_array($a['children'])
        ? (isset($a['children'][0]) ? $a['children'][0] : $a['children'])
        : null;

    $firstName = $child['first_name'] ?? '';
    $lastName  = $child['last_name']  ?? '';
    $fullName  = trim($firstName . ' ' . $lastName) ?: 'Unknown';

    $dob = $child['date_of_birth'] ?? null;
    $age = '—';
    if ($dob) {
        $age = (int)(new DateTime($dob))->diff(new DateTime())->y;
    }

    $arugaId  = $a['aruga_id']        ?? '—';
    $region   = $child['region']      ?? '—';
    $readiness= $a['readiness_score'] ?? '—';
    $date     = $a['created_at'] ? date('M j, Y', strtotime($a['created_at'])) : '—';

    $cehRaw = $a['child_education_health'] ?? [];
    $ceh = is_array($cehRaw) ? (isset($cehRaw[0]) ? $cehRaw[0] : $cehRaw) : [];
    $disabilities = $ceh['disabilities'] ?? [];
    if (is_string($disabilities)) $disabilities = json_decode($disabilities, true) ?? [];
    if (!is_array($disabilities)) $disabilities = [];
    $disability = !empty($disabilities) ? $disabilities[0] : '—';

    // Search filter
    if ($search !== '') {
        $hay = strtolower($fullName . ' ' . $arugaId . ' ' . $region . ' ' . implode(' ', $disabilities));
        if (strpos($hay, strtolower($search)) === false) continue;
    }

    $rows[] = [
        'id'            => $a['id']   ?? '',
        'arugaId'       => $arugaId,
        'name'          => $fullName,
        'age'           => $age,
        'disability'    => $disability,
        'disabilities'  => $disabilities,
        'region'        => $region,
        'interviewer'   => $interviewerCode,
        'readinessScore'=> $readiness,
        'dateAssessed'  => $date,
        'status'        => $a['status'] ?? 'pending',
    ];
}

$total      = count($rows);
$totalPages = $limit > 0 ? (int)ceil($total / $limit) : 1;
$paged      = array_slice($rows, $offset, $limit);

echo json_encode([
    'success' => true,
    'data'    => $paged,
    'pagination' => [
        'page'       => $page,
        'limit'      => $limit,
        'total'      => $total,
        'totalPages' => $totalPages,
    ]
]);
