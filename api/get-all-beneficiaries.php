<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
require_once __DIR__ . '/region-coverage-helper.php';

header('Content-Type: application/json');


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

$limit          = isset($_GET['limit'])          ? (int)$_GET['limit']              : 50;
$offset         = isset($_GET['offset'])         ? (int)$_GET['offset']             : 0;
$search         = isset($_GET['search'])         ? trim($_GET['search'])            : '';
$region         = isset($_GET['region'])         ? trim($_GET['region'])            : '';
$readinessScore = isset($_GET['readiness_score'])? trim($_GET['readiness_score'])   : '';
$disabilityType = isset($_GET['disability_type'])? trim($_GET['disability_type'])   : '';
$only4ps        = isset($_GET['is_4ps_member'])  && $_GET['is_4ps_member'] === '1';

// Fetch assessments with readiness_score (paginated — Supabase caps each response at 1000 rows)
$assessments = supabaseFetchAll(
    'assessments?select=id,aruga_id,interviewer_code,created_at,readiness_score,children(first_name,last_name,date_of_birth,region),child_education_health(disabilities)&deleted_at=is.null&order=created_at.desc'
);

// Fetch 4Ps data
$pqData = supabaseFetchAll('pre_qualification?select=assessment_id,is_4ps_member');
$pqMap = [];
foreach ($pqData as $pq) {
    $pqMap[$pq['assessment_id']] = (bool)($pq['is_4ps_member'] ?? false);
}

$rows = [];
foreach ($assessments as $a) {
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

    $childRegion   = normalizeRegion($child['region'] ?? '') ?: '—';
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
