<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

// ── Core fetches ─────────────────────────────────────────────
$assRes    = supabaseRequest('GET', 'assessments?select=id,readiness_score,created_at&limit=100000');
$eduRes    = supabaseRequest('GET', 'education_info?select=assessment_id,is_currently_enrolled,not_enrolled_reason&limit=100000');
$econRes   = supabaseRequest('GET', 'economic_capacity?select=assessment_id,income_classification,monthly_income&limit=100000');
$healthRes = supabaseRequest('GET', 'health_info?select=assessment_id,has_all_vaccinations,has_barriers_to_healthcare,healthcare_barriers_details,expense_food,expense_medication,expense_therapy,expense_hygiene,expense_assistive_device,expense_other&limit=100000');
$svcRes    = supabaseRequest('GET', 'service_availment?select=assessment_id,is_aware_of_social_services,has_availed_services&limit=100000');
$childRes  = supabaseRequest('GET', 'children?select=assessment_id,region&limit=100000');

$assessments = ($assRes['success']    && is_array($assRes['data']))    ? $assRes['data']    : [];
$edus        = ($eduRes['success']    && is_array($eduRes['data']))    ? $eduRes['data']    : [];
$econs       = ($econRes['success']   && is_array($econRes['data']))   ? $econRes['data']   : [];
$healths     = ($healthRes['success'] && is_array($healthRes['data'])) ? $healthRes['data'] : [];
$svcs        = ($svcRes['success']    && is_array($svcRes['data']))    ? $svcRes['data']    : [];
$children    = ($childRes['success']  && is_array($childRes['data']))  ? $childRes['data']  : [];

// region lookup
$regionMap = [];
foreach ($children as $c) { $regionMap[$c['assessment_id']] = $c['region'] ?? '—'; }

// ── 1. Readiness Score Trends (monthly, last 12 months) ──────
$readinessMonths = [];
$now = new DateTime();
for ($i = 11; $i >= 0; $i--) {
    $dt  = (clone $now)->modify("-{$i} months");
    $key = $dt->format('Y-m');
    $readinessMonths[$key] = ['label' => $dt->format('M Y'), 'severe'=>0,'moderate'=>0,'low'=>0,'stable'=>0];
}
foreach ($assessments as $a) {
    $ts  = $a['created_at'] ?? null;
    $rs  = strtolower($a['readiness_score'] ?? '');
    if (!$ts || !in_array($rs, ['severe','moderate','low','stable'])) continue;
    $key = date('Y-m', strtotime($ts));
    if (isset($readinessMonths[$key])) $readinessMonths[$key][$rs]++;
}
$readinessTrends = array_values($readinessMonths);

// ── 2. School Enrollment ─────────────────────────────────────
$enrolled = 0; $notEnrolled = 0; $reasonCounts = [];
foreach ($edus as $e) {
    if (!empty($e['is_currently_enrolled'])) { $enrolled++; }
    else {
        $notEnrolled++;
        $reason = trim($e['not_enrolled_reason'] ?? '');
        if ($reason) $reasonCounts[$reason] = ($reasonCounts[$reason] ?? 0) + 1;
    }
}
arsort($reasonCounts);
$topReasons = array_map(fn($r,$c)=>['reason'=>$r,'count'=>$c],
    array_slice(array_keys($reasonCounts),0,5),
    array_slice(array_values($reasonCounts),0,5));

// ── 3. Income Classification ─────────────────────────────────
$incomeCounts = [];
foreach ($econs as $e) {
    $cls = trim($e['income_classification'] ?? '');
    if (!$cls) continue;
    $incomeCounts[$cls] = ($incomeCounts[$cls] ?? 0) + 1;
}
arsort($incomeCounts);
$incomeTotal = array_sum($incomeCounts);
$incomeBreakdown = array_map(fn($l,$c)=>['label'=>$l,'count'=>$c,'pct'=>$incomeTotal>0?round($c/$incomeTotal*100):0],
    array_keys($incomeCounts), array_values($incomeCounts));

// ── 4. Service Awareness vs Availment Gap ────────────────────
$regionSvc = [];
foreach ($svcs as $s) {
    $region = $regionMap[$s['assessment_id']] ?? '—';
    if ($region === '—') continue;
    if (!isset($regionSvc[$region])) $regionSvc[$region] = ['aware'=>0,'availed'=>0,'total'=>0];
    $regionSvc[$region]['total']++;
    if (!empty($s['is_aware_of_social_services'])) $regionSvc[$region]['aware']++;
    if (!empty($s['has_availed_services']))         $regionSvc[$region]['availed']++;
}
$serviceGap = [];
foreach ($regionSvc as $region => $d) {
    $serviceGap[] = [
        'region'      => $region,
        'aware_pct'   => $d['total']>0 ? round($d['aware']/$d['total']*100)   : 0,
        'availed_pct' => $d['total']>0 ? round($d['availed']/$d['total']*100) : 0,
        'total'       => $d['total'],
    ];
}
usort($serviceGap, fn($a,$b)=>$b['aware_pct']-$a['aware_pct']);

// ── 5. Healthcare Barriers ───────────────────────────────────
$barrierCounts = [];
$withBarriers  = 0;
foreach ($healths as $h) {
    if (empty($h['has_barriers_to_healthcare'])) continue;
    $withBarriers++;
    $detail = trim($h['healthcare_barriers_details'] ?? '');
    if (!$detail) { $barrierCounts['Not specified'] = ($barrierCounts['Not specified']??0)+1; continue; }
    // Split by comma or semicolon for multi-barriers
    $parts = preg_split('/[,;]+/', $detail);
    foreach ($parts as $p) {
        $p = trim($p);
        if ($p) $barrierCounts[$p] = ($barrierCounts[$p]??0)+1;
    }
}
arsort($barrierCounts);
$barriers = array_map(fn($l,$c)=>['label'=>$l,'count'=>$c],
    array_slice(array_keys($barrierCounts),0,8),
    array_slice(array_values($barrierCounts),0,8));

// ── 6. Vaccination Status ────────────────────────────────────
$vaccTotal = count($healths);
$vaccYes   = count(array_filter($healths, fn($h)=>!empty($h['has_all_vaccinations'])));
$vaccRate  = $vaccTotal>0 ? round($vaccYes/$vaccTotal*100) : 0;

$regionVacc = [];
foreach ($healths as $h) {
    $region = $regionMap[$h['assessment_id']] ?? '—';
    if ($region==='—') continue;
    if (!isset($regionVacc[$region])) $regionVacc[$region]=['yes'=>0,'total'=>0];
    $regionVacc[$region]['total']++;
    if (!empty($h['has_all_vaccinations'])) $regionVacc[$region]['yes']++;
}
$vaccByRegion = [];
foreach ($regionVacc as $region=>$d) {
    $vaccByRegion[] = ['region'=>$region,'rate'=>$d['total']>0?round($d['yes']/$d['total']*100):0,'total'=>$d['total']];
}
usort($vaccByRegion, fn($a,$b)=>$b['rate']-$a['rate']);

// ── 7. Health Expenses ───────────────────────────────────────
$expKeys   = ['expense_medication','expense_therapy','expense_hygiene','expense_assistive_device','expense_other'];
$expLabels = ['Medication','Therapy','Hygiene','Assistive Device','Other'];
$expSums   = array_fill(0, count($expKeys), 0);
$expCount  = 0;
foreach ($healths as $h) {
    $hasAny = false;
    foreach ($expKeys as $idx=>$key) {
        $val = (float)($h[$key]??0);
        if ($val>0) { $expSums[$idx]+=$val; $hasAny=true; }
    }
    if ($hasAny) $expCount++;
}
$expenses = [];
$totalAvg = 0;
foreach ($expKeys as $idx=>$key) {
    $avg = $expCount>0 ? round($expSums[$idx]/$expCount,2) : 0;
    $totalAvg += $avg;
    $expenses[] = ['label'=>$expLabels[$idx],'avg'=>$avg];
}
usort($expenses, fn($a,$b)=>$b['avg']<=>$a['avg']);

echo json_encode([
    'success'          => true,
    'readiness_trends' => $readinessTrends,
    'enrollment'       => ['enrolled'=>$enrolled,'not_enrolled'=>$notEnrolled,'top_reasons'=>$topReasons],
    'income'           => $incomeBreakdown,
    'service_gap'      => $serviceGap,
    'barriers'         => $barriers,
    'vaccination'      => ['national_rate'=>$vaccRate,'total'=>$vaccTotal,'vaccinated'=>$vaccYes,'by_region'=>$vaccByRegion],
    'expenses'         => ['total_avg'=>round($totalAvg,2),'breakdown'=>$expenses],
]);
