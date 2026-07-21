<?php
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../auth.php';
require_once __DIR__ . '/../region-coverage-helper.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

requireRole(['stu_head', 'admin']);
$region  = getStr('region');
requireRegion($region);
$search  = trim($_GET['search']  ?? '');
$status  = trim($_GET['status']  ?? '');
$limit   = max(1, min(10000, (int)($_GET['limit']  ?? 50)));
$offset  = getInt('offset', 0, 0);

if (!$region) {
    echo json_encode(['success' => false, 'message' => 'region required']); exit;
}

function stuNormalizeRegion($r) {
    return normalizeRegion($r) ?: '';
}

$normalizedTarget = stuNormalizeRegion($region);

// Fetch all assessments with child region — filter by children.region match (paginated)
$assessments = supabaseFetchAll(
    'assessments?select=id,aruga_id,interviewer_code,status,created_at,readiness_score,children(first_name,last_name,date_of_birth,sex,barangay,region),child_education_health(disabilities)&deleted_at=is.null&order=created_at.desc'
);

$rows = [];
foreach ($assessments as $a) {
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
