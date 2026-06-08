<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: GET');


if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

$range          = $_GET['range']      ?? '30';
$regionFilter   = getStr('region');
$provinceFilter = getStr('province');

// Date filter
$dateFilter = '';
if ($range !== 'all') {
    $days = (int)$range ?: 30;
    $since = date('Y-m-d', strtotime("-{$days} days")) . 'T00:00:00';
    $dateFilter = '&created_at=gte.' . urlencode($since);
}

// ── Fetch core data ──────────────────────────────────────────
$assRes = supabaseRequest('GET', 'assessments?select=id,status,readiness_score,created_at,interviewer_id,interviewer_code&deleted_at=is.null&limit=100000' . $dateFilter);
$childRes = supabaseRequest('GET', 'children?select=assessment_id,region,province,city_municipality,date_of_birth,sex&limit=100000');
$disRes   = supabaseRequest('GET', 'child_education_health?select=assessment_id,disabilities&limit=100000');
$intRes   = supabaseRequest('GET', 'interviewers?select=id,full_name,interviewer_code,region,status&limit=10000');

$assessments = ($assRes['success'] && is_array($assRes['data'])) ? $assRes['data'] : [];
$children    = ($childRes['success'] && is_array($childRes['data'])) ? $childRes['data'] : [];
$disData     = ($disRes['success'] && is_array($disRes['data'])) ? $disRes['data'] : [];
$interviewers= ($intRes['success'] && is_array($intRes['data'])) ? $intRes['data'] : [];

// Build lookup maps
$childMap = [];
foreach ($children as $c) { $childMap[$c['assessment_id']] = $c; }

$disMap = [];
foreach ($disData as $d) {
    $dis = $d['disabilities'] ?? [];
    if (is_string($dis)) $dis = json_decode($dis, true) ?? [];
    $disMap[$d['assessment_id']] = is_array($dis) ? $dis : [];
}

function analyticsNormalizeRegion($r) {
    $r = trim($r ?? '');
    $map = [
        'NCR'=>'NCR (National Capital Region)','NCR – Metro Manila'=>'NCR (National Capital Region)',
        'NCR - Metro Manila'=>'NCR (National Capital Region)','National Capital Region'=>'NCR (National Capital Region)',
        'Region I – Ilocos Region'=>'Region I (Ilocos Region)','Region I - Ilocos Region'=>'Region I (Ilocos Region)',
        'Region II – Cagayan Valley'=>'Region II (Cagayan Valley)','Region II - Cagayan Valley'=>'Region II (Cagayan Valley)',
        'Region III – Central Luzon'=>'Region III (Central Luzon)','Region III - Central Luzon'=>'Region III (Central Luzon)',
        'Region IV-A – CALABARZON'=>'Region IV-A (CALABARZON)','Region IV-A - CALABARZON'=>'Region IV-A (CALABARZON)','CALABARZON'=>'Region IV-A (CALABARZON)',
        'Region IV-B – MIMAROPA'=>'Region IV-B (MIMAROPA)','Region IV-B - MIMAROPA'=>'Region IV-B (MIMAROPA)','MIMAROPA'=>'Region IV-B (MIMAROPA)',
        'Region V – Bicol Region'=>'Region V (Bicol Region)','Region V - Bicol Region'=>'Region V (Bicol Region)','Bicol Region'=>'Region V (Bicol Region)','Region V'=>'Region V (Bicol Region)','Region V (Bicol)'=>'Region V (Bicol Region)',
        'Region VI – Western Visayas'=>'Region VI (Western Visayas)','Region VI - Western Visayas'=>'Region VI (Western Visayas)','Region VI'=>'Region VI (Western Visayas)',
        'Region VII – Central Visayas'=>'Region VII (Central Visayas)','Region VII - Central Visayas'=>'Region VII (Central Visayas)','Region VII'=>'Region VII (Central Visayas)',
        'Region VIII – Eastern Visayas'=>'Region VIII (Eastern Visayas)','Region VIII - Eastern Visayas'=>'Region VIII (Eastern Visayas)','Region VIII'=>'Region VIII (Eastern Visayas)',
        'Region IX – Zamboanga Peninsula'=>'Region IX (Zamboanga Peninsula)','Region IX - Zamboanga Peninsula'=>'Region IX (Zamboanga Peninsula)','Region IX'=>'Region IX (Zamboanga Peninsula)',
        'Region X – Northern Mindanao'=>'Region X (Northern Mindanao)','Region X - Northern Mindanao'=>'Region X (Northern Mindanao)','Region X'=>'Region X (Northern Mindanao)',
        'Region XI – Davao Region'=>'Region XI (Davao Region)','Region XI - Davao Region'=>'Region XI (Davao Region)','Region XI'=>'Region XI (Davao Region)',
        'Region XII – SOCCSKSARGEN'=>'Region XII (SOCCSKSARGEN)','Region XII - SOCCSKSARGEN'=>'Region XII (SOCCSKSARGEN)','Region XII'=>'Region XII (SOCCSKSARGEN)',
        'Region XIII – Caraga'=>'Region XIII (Caraga)','Region XIII - Caraga'=>'Region XIII (Caraga)','Caraga'=>'Region XIII (Caraga)','Region XIII'=>'Region XIII (Caraga)',
        'CAR – Cordillera'=>'CAR (Cordillera Administrative Region)','CAR - Cordillera'=>'CAR (Cordillera Administrative Region)',
        'CAR'=>'CAR (Cordillera Administrative Region)','Cordillera'=>'CAR (Cordillera Administrative Region)',
        'BARMM'=>'BARMM (Bangsamoro)','Bangsamoro'=>'BARMM (Bangsamoro)',
    ];
    return $map[$r] ?? ($r ?: '—');
}

// Apply region filter
if ($regionFilter) {
    $normalizedFilter = analyticsNormalizeRegion($regionFilter);
    $filteredIds = [];
    foreach ($children as $c) {
        if (analyticsNormalizeRegion($c['region'] ?? '') === $normalizedFilter) {
            $filteredIds[$c['assessment_id']] = true;
        }
    }
    $assessments = array_values(array_filter($assessments, fn($a) => isset($filteredIds[$a['id']])));
}

// Apply province filter
if ($provinceFilter) {
    $filteredIds = [];
    foreach ($children as $c) {
        if (strcasecmp(trim($c['province'] ?? ''), $provinceFilter) === 0) {
            $filteredIds[$c['assessment_id']] = true;
        }
    }
    $assessments = array_values(array_filter($assessments, fn($a) => isset($filteredIds[$a['id']])));
}

$total     = count($assessments);
$completed = count(array_filter($assessments, fn($a) => ($a['status'] ?? '') === 'completed'));

// Completion rate = completed vs national target (sum of per-region targets)
$regionTargets = [
    'Region I (Ilocos Region)'    => 150,
    'Region II (Cagayan Valley)'  => 100,
    'Region III (Central Luzon)'  => 100,
    'Region IV-A (CALABARZON)'    => 100,
    'Region IV-B (MIMAROPA)'      => 150,
    'Region V (Bicol Region)'     => 100,
    'Region VI (Western Visayas)' => 150,
    'Region XI (Davao)'           => 150,
    'National Capital Region'     => 140,
];
$nationalTarget = array_sum($regionTargets);
$rate = $nationalTarget > 0 ? round(($completed / $nationalTarget) * 100, 1) : ($total > 0 ? round(($completed / $total) * 100, 1) : 0);

// Active interviewers (submitted at least once)
$activeIntIds = array_unique(array_filter(array_column($assessments, 'interviewer_code')));
$activeCount  = count($activeIntIds);

// Avg per day
$days = $range === 'all' ? 365 : (int)$range;
$avgPerDay = $days > 0 ? round($total / $days, 1) : 0;

// ── Trends ──────────────────────────────────────────────────
$daily = []; $weekly = []; $monthly = [];
foreach ($assessments as $a) {
    $ts = $a['created_at'] ?? null;
    if (!$ts) continue;
    $d = date('Y-m-d', strtotime($ts));
    $w = date('o-W',   strtotime($ts));
    $m = date('Y-m',   strtotime($ts));
    $daily[$d]  = ($daily[$d]  ?? 0) + 1;
    $weekly[$w] = ($weekly[$w] ?? 0) + 1;
    $monthly[$m]= ($monthly[$m]?? 0) + 1;
}
ksort($daily); ksort($weekly); ksort($monthly);

// Last 30 days for daily
$last30 = [];
for ($i = 29; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-{$i} days"));
    $last30[] = ['label' => date('M j', strtotime($day)), 'value' => $daily[$day] ?? 0];
}
// Last 12 weeks
$last12w = [];
for ($i = 11; $i >= 0; $i--) {
    $week = date('o-W', strtotime("-{$i} weeks"));
    $last12w[] = ['label' => 'W' . ltrim(explode('-', $week)[1], '0'), 'value' => $weekly[$week] ?? 0];
}
// Monthly current year
$monthlyArr = [];
$year = date('Y');
$mLabels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
for ($i = 1; $i <= 12; $i++) {
    $key = $year . '-' . str_pad($i, 2, '0', STR_PAD_LEFT);
    $monthlyArr[] = ['label' => $mLabels[$i-1], 'value' => $monthly[$key] ?? 0];
}

// ── Regions ──────────────────────────────────────────────────
$regionCounts = [];
foreach ($assessments as $a) {
    $region = $childMap[$a['id']]['region'] ?? '—';
    if ($region === '—') continue;
    $regionCounts[$region] = ($regionCounts[$region] ?? 0) + 1;
}
arsort($regionCounts);
$regions = array_map(fn($r, $c) => ['region' => $r, 'count' => $c], array_keys($regionCounts), array_values($regionCounts));

// ── Age Groups ────────────────────────────────────────────────
$ageGroups = ['0–5' => 0, '6–11' => 0, '12–17' => 0, '18+' => 0];
foreach ($assessments as $a) {
    $dob = $childMap[$a['id']]['date_of_birth'] ?? null;
    if (!$dob) continue;
    $age = (int)(new DateTime($dob))->diff(new DateTime())->y;
    if ($age <= 5) $ageGroups['0–5']++;
    elseif ($age <= 11) $ageGroups['6–11']++;
    elseif ($age <= 17) $ageGroups['12–17']++;
    else $ageGroups['18+']++;
}
$ageGroupsArr = array_map(fn($l, $v) => ['label' => $l, 'val' => $v], array_keys($ageGroups), array_values($ageGroups));

// ── Gender ────────────────────────────────────────────────────
$genderCounts = ['Male' => 0, 'Female' => 0, 'Other' => 0];
foreach ($assessments as $a) {
    $sex = $childMap[$a['id']]['sex'] ?? null;
    if (!$sex) continue;
    $key = ucfirst(strtolower($sex));
    if ($key === 'Male' || $key === 'Female') $genderCounts[$key]++;
    else $genderCounts['Other']++;
}
$gender = [
    ['label' => 'Male',   'val' => $genderCounts['Male'],   'color' => '#1152d4'],
    ['label' => 'Female', 'val' => $genderCounts['Female'], 'color' => '#93c5fd'],
];
if ($genderCounts['Other'] > 0) $gender[] = ['label' => 'Other', 'val' => $genderCounts['Other'], 'color' => '#bfdbfe'];

// ── Top Locations ─────────────────────────────────────────────
$locationCounts = [];
foreach ($assessments as $a) {
    $c = $childMap[$a['id']] ?? [];
    $city = trim(($c['city_municipality'] ?? '') . ', ' . ($c['region'] ?? ''), ', ');
    if (!$city || $city === ',') continue;
    $locationCounts[$city] = ($locationCounts[$city] ?? 0) + 1;
}
arsort($locationCounts);
$topLocations = array_map(fn($n, $c) => ['name' => $n, 'count' => $c],
    array_slice(array_keys($locationCounts), 0, 5),
    array_slice(array_values($locationCounts), 0, 5));

// ── Province breakdown ────────────────────────────────────────
$provinceCounts = [];
foreach ($assessments as $a) {
    $c = $childMap[$a['id']] ?? [];
    $prov = trim($c['province'] ?? '');
    if (!$prov) continue;
    $provinceCounts[$prov] = ($provinceCounts[$prov] ?? 0) + 1;
}
arsort($provinceCounts);
$topProvinces = array_map(fn($n, $c) => ['name' => $n, 'count' => $c],
    array_slice(array_keys($provinceCounts), 0, 15),
    array_slice(array_values($provinceCounts), 0, 15));

// ── City/Municipality breakdown ───────────────────────────────
$cityCounts = [];
$cityProvinceMap = [];
foreach ($assessments as $a) {
    $c = $childMap[$a['id']] ?? [];
    $city = trim($c['city_municipality'] ?? '');
    $prov = trim($c['province'] ?? '');
    if (!$city) continue;
    $cityCounts[$city] = ($cityCounts[$city] ?? 0) + 1;
    if ($prov && !isset($cityProvinceMap[$city])) $cityProvinceMap[$city] = $prov;
}
arsort($cityCounts);
$topCities = array_map(fn($n, $c) => ['name' => $n, 'count' => $c, 'province' => $cityProvinceMap[$n] ?? ''],
    array_slice(array_keys($cityCounts), 0, 15),
    array_slice(array_values($cityCounts), 0, 15));

// ── Disabilities ──────────────────────────────────────────────
$disCounts = [];
$multiCounts = [1 => 0, 2 => 0, 3 => 0];
$disAgeGroups = [];

foreach ($assessments as $a) {
    $dis = $disMap[$a['id']] ?? [];
    $dob = $childMap[$a['id']]['date_of_birth'] ?? null;
    $age = $dob ? (int)(new DateTime($dob))->diff(new DateTime())->y : null;
    $group = $age === null ? null : ($age <= 5 ? '0–5' : ($age <= 11 ? '6–11' : ($age <= 17 ? '12–17' : '18+')));

    foreach ($dis as $d) {
        $disCounts[$d] = ($disCounts[$d] ?? 0) + 1;
        if ($group) $disAgeGroups[$group][$d] = ($disAgeGroups[$group][$d] ?? 0) + 1;
    }
    $cnt = count($dis);
    if ($cnt >= 3) $multiCounts[3]++;
    elseif ($cnt === 2) $multiCounts[2]++;
    elseif ($cnt === 1) $multiCounts[1]++;
}
arsort($disCounts);
$disArr = array_map(fn($l, $v) => ['label' => $l, 'val' => $v],
    array_slice(array_keys($disCounts), 0, 10),
    array_slice(array_values($disCounts), 0, 10));

$totalDis = array_sum($multiCounts);
$multiArr = [
    ['label' => '1 disability',  'pct' => $totalDis > 0 ? round($multiCounts[1]/$totalDis*100) : 0],
    ['label' => '2 disabilities','pct' => $totalDis > 0 ? round($multiCounts[2]/$totalDis*100) : 0],
    ['label' => '3+ disabilities','pct'=> $totalDis > 0 ? round($multiCounts[3]/$totalDis*100) : 0],
];

$disAgeArr = [];
$topDisTypes = array_slice(array_keys($disCounts), 0, 5);
foreach (['0–5','6–11','12–17','18+'] as $grp) {
    $row = ['group' => $grp];
    foreach ($topDisTypes as $t) { $row[$t] = $disAgeGroups[$grp][$t] ?? 0; }
    $disAgeArr[] = $row;
}

// ── Interviewer Productivity ──────────────────────────────────
$intSubmissions = [];
foreach ($assessments as $a) {
    $code = $a['interviewer_code'] ?? '';
    if (!$code) continue;
    $intSubmissions[$code] = ($intSubmissions[$code] ?? 0) + 1;
}
$intLookup = [];
foreach ($interviewers as $iv) { $intLookup[$iv['interviewer_code']] = $iv; }

$intRows = [];
foreach ($intSubmissions as $code => $cnt) {
    $iv = $intLookup[$code] ?? [];
    $avg = $days > 0 ? round($cnt / $days, 1) : 0;
    $workload = $cnt > 80 ? 'overloaded' : ($cnt < 20 ? 'underutilized' : 'balanced');
    $intRows[] = [
        'name'      => $iv['full_name'] ?? $code,
        'code'      => $code,
        'region'    => $iv['region'] ?? '—',
        'completed' => $cnt,
        'avgDay'    => $avg,
        'workload'  => $workload,
    ];
}
usort($intRows, fn($a, $b) => $b['completed'] - $a['completed']);

// ── Data Quality ──────────────────────────────────────────────
// Completeness: check how many have all key sub-tables filled
$filledCount = 0;
foreach ($assessments as $a) {
    if (isset($childMap[$a['id']]) && isset($disMap[$a['id']])) $filledCount++;
}
$completeness = $total > 0 ? round(($filledCount / $total) * 100) : 0;

// Missing data: count nulls in child records
$missingFields = [
    'Date of Birth'    => 0,
    'City/Municipality'=> 0,
    'Sex'              => 0,
    'Province'         => 0,
    'Region'           => 0,
];
foreach ($assessments as $a) {
    $c = $childMap[$a['id']] ?? [];
    if (empty($c['date_of_birth']))       $missingFields['Date of Birth']++;
    if (empty($c['city_municipality']))    $missingFields['City/Municipality']++;
    if (empty($c['sex']))                 $missingFields['Sex']++;
    if (empty($c['province']))            $missingFields['Province']++;
    if (empty($c['region']))              $missingFields['Region']++;
}
$missingArr = [];
foreach ($missingFields as $f => $cnt) {
    $missingArr[] = ['field' => $f, 'pct' => $total > 0 ? round($cnt / $total * 100) : 0];
}
usort($missingArr, fn($a, $b) => $b['pct'] - $a['pct']);

// Flagged = severe readiness
$flagged = count(array_filter($assessments, fn($a) => ($a['readiness_score'] ?? '') === 'severe'));

echo json_encode([
    'success' => true,
    'summary' => [
        'total'             => $total,
        'completed'         => $completed,
        'completion_rate'   => $rate,
        'active_interviewers'=> $activeCount,
        'avg_per_day'       => $avgPerDay,
    ],
    'trends' => [
        'daily'   => $last30,
        'weekly'  => $last12w,
        'monthly' => $monthlyArr,
    ],
    'regions'       => $regions,
    'age_groups'    => $ageGroupsArr,
    'gender'        => $gender,
    'top_locations'  => $topLocations,
    'top_provinces'  => $topProvinces,
    'top_cities'     => $topCities,
    'disabilities'  => $disArr,
    'multi_dis'     => $multiArr,
    'dis_age'       => $disAgeArr,
    'dis_types'     => $topDisTypes,
    'interviewers'  => $intRows,
    'quality' => [
        'completeness'   => $completeness,
        'missing_fields' => $missingArr,
        'flagged_count'  => $flagged,
    ],
]);
