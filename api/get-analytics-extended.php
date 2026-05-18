<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://project-aruga.vercel.app');
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
    $days  = (int)$range ?: 30;
    $since = date('Y-m-d', strtotime("-{$days} days")) . 'T00:00:00';
    $dateFilter = '&created_at=gte.' . urlencode($since);
}

// ── Fetch all needed tables ──────────────────────────────────
$assRes    = supabaseRequest('GET', 'assessments?select=id,readiness_score,created_at&limit=100000' . $dateFilter);
$childRes  = supabaseRequest('GET', 'children?select=assessment_id,region,sex,religion,religion_other,ip_membership&limit=100000');
$preqRes   = supabaseRequest('GET', 'pre_qualification?select=assessment_id,is_4ps_member&limit=100000');
$famRes    = supabaseRequest('GET', 'family_members?select=assessment_id&limit=100000');
$eduRes    = supabaseRequest('GET', 'education_info?select=assessment_id,is_currently_enrolled,not_enrolled_reason&limit=100000');
$ceduRes   = supabaseRequest('GET', 'child_education_health?select=assessment_id,highest_education,disabilities&limit=100000');
$econRes   = supabaseRequest('GET', 'economic_capacity?select=assessment_id,income_classification,monthly_income,primary_income_source&limit=100000');
$healthRes = supabaseRequest('GET', 'health_info?select=assessment_id,has_all_vaccinations,has_ongoing_health_conditions,availed_services_6months,has_barriers_to_healthcare,healthcare_barriers_details,expense_food,expense_medication,expense_therapy,expense_hygiene,expense_assistive_device,expense_other&limit=100000');
$svcRes    = supabaseRequest('GET', 'service_availment?select=assessment_id,is_aware_of_social_services,has_availed_services,receives_financial_assistance&limit=100000');
$socioRes  = supabaseRequest('GET', 'socio_economic?select=assessment_id,housing_materials,tenure_status,has_accessibility_modifications,water_source,electricity_source,toilet_type,garbage_disposal&limit=100000');

$rawAss    = ($assRes['success']    && is_array($assRes['data']))    ? $assRes['data']    : [];
$rawChild  = ($childRes['success']  && is_array($childRes['data']))  ? $childRes['data']  : [];
$rawPreq   = ($preqRes['success']   && is_array($preqRes['data']))   ? $preqRes['data']   : [];
$rawFam    = ($famRes['success']    && is_array($famRes['data']))    ? $famRes['data']    : [];
$rawEdus   = ($eduRes['success']    && is_array($eduRes['data']))    ? $eduRes['data']    : [];
$rawCedu   = ($ceduRes['success']   && is_array($ceduRes['data']))   ? $ceduRes['data']   : [];
$rawEcons  = ($econRes['success']   && is_array($econRes['data']))   ? $econRes['data']   : [];
$rawHealth = ($healthRes['success'] && is_array($healthRes['data'])) ? $healthRes['data'] : [];
$rawSvcs   = ($svcRes['success']    && is_array($svcRes['data']))    ? $svcRes['data']    : [];
$rawSocio  = ($socioRes['success']  && is_array($socioRes['data']))  ? $socioRes['data']  : [];

function extNormalizeRegion($r) {
    $r = trim($r ?? '');
    $map = [
        'NCR'=>'NCR (National Capital Region)','NCR – Metro Manila'=>'NCR (National Capital Region)',
        'NCR - Metro Manila'=>'NCR (National Capital Region)','National Capital Region'=>'NCR (National Capital Region)',
        'Region I – Ilocos Region'=>'Region I (Ilocos Region)','Region I - Ilocos Region'=>'Region I (Ilocos Region)',
        'Region II – Cagayan Valley'=>'Region II (Cagayan Valley)','Region II - Cagayan Valley'=>'Region II (Cagayan Valley)',
        'Region III – Central Luzon'=>'Region III (Central Luzon)','Region III - Central Luzon'=>'Region III (Central Luzon)',
        'Region IV-A – CALABARZON'=>'Region IV-A (CALABARZON)','Region IV-A - CALABARZON'=>'Region IV-A (CALABARZON)','CALABARZON'=>'Region IV-A (CALABARZON)',
        'Region IV-B – MIMAROPA'=>'Region IV-B (MIMAROPA)','Region IV-B - MIMAROPA'=>'Region IV-B (MIMAROPA)','MIMAROPA'=>'Region IV-B (MIMAROPA)',
        'Region V – Bicol Region'=>'Region V (Bicol Region)','Region V - Bicol Region'=>'Region V (Bicol Region)','Bicol Region'=>'Region V (Bicol Region)',
        'Region VI – Western Visayas'=>'Region VI (Western Visayas)','Region VI - Western Visayas'=>'Region VI (Western Visayas)',
        'Region VII – Central Visayas'=>'Region VII (Central Visayas)','Region VII - Central Visayas'=>'Region VII (Central Visayas)',
        'Region VIII – Eastern Visayas'=>'Region VIII (Eastern Visayas)','Region VIII - Eastern Visayas'=>'Region VIII (Eastern Visayas)',
        'Region IX – Zamboanga Peninsula'=>'Region IX (Zamboanga Peninsula)','Region IX - Zamboanga Peninsula'=>'Region IX (Zamboanga Peninsula)',
        'Region X – Northern Mindanao'=>'Region X (Northern Mindanao)','Region X - Northern Mindanao'=>'Region X (Northern Mindanao)',
        'Region XI – Davao Region'=>'Region XI (Davao Region)','Region XI - Davao Region'=>'Region XI (Davao Region)',
        'Region XII – SOCCSKSARGEN'=>'Region XII (SOCCSKSARGEN)','Region XII - SOCCSKSARGEN'=>'Region XII (SOCCSKSARGEN)',
        'Region XIII – Caraga'=>'Region XIII (Caraga)','Region XIII - Caraga'=>'Region XIII (Caraga)','Caraga'=>'Region XIII (Caraga)',
        'CAR – Cordillera'=>'CAR (Cordillera Administrative Region)','CAR - Cordillera'=>'CAR (Cordillera Administrative Region)',
        'CAR'=>'CAR (Cordillera Administrative Region)','Cordillera'=>'CAR (Cordillera Administrative Region)',
        'BARMM'=>'BARMM (Bangsamoro)','Bangsamoro'=>'BARMM (Bangsamoro)',
    ];
    return $map[$r] ?? ($r ?: '—');
}

// Region filter — normalize both sides to handle inconsistent stored values
$regionMap = [];
foreach ($rawChild as $c) $regionMap[$c['assessment_id']] = extNormalizeRegion($c['region'] ?? '—');

$normalizedFilter = $regionFilter ? extNormalizeRegion($regionFilter) : '';
$filteredAssIds = [];
if ($normalizedFilter) {
    foreach ($rawChild as $c) {
        if (extNormalizeRegion($c['region'] ?? '') === $normalizedFilter)
            $filteredAssIds[$c['assessment_id']] = true;
    }
}
$hasRegion = !empty($normalizedFilter);

$assessments = $hasRegion
    ? array_values(array_filter($rawAss, fn($a) => isset($filteredAssIds[$a['id']])))
    : $rawAss;

// Province filter
if ($provinceFilter) {
    $provIds = [];
    foreach ($rawChild as $c) {
        if (strcasecmp(trim($c['province'] ?? ''), $provinceFilter) === 0)
            $provIds[$c['assessment_id']] = true;
    }
    $assessments = array_values(array_filter($assessments, fn($a) => isset($provIds[$a['id']])));
}

$assIdSet = array_flip(array_column($assessments, 'id'));

function filterByAss(array $rows, array $assIdSet, string $key = 'assessment_id'): array {
    return array_values(array_filter($rows, fn($r) => isset($assIdSet[$r[$key] ?? ''])));
}

$children = filterByAss($rawChild,  $assIdSet);
$preqs    = filterByAss($rawPreq,   $assIdSet);
$fams     = filterByAss($rawFam,    $assIdSet);
$edus     = filterByAss($rawEdus,   $assIdSet);
$cedus    = filterByAss($rawCedu,   $assIdSet);
$econs    = filterByAss($rawEcons,  $assIdSet);
$healths  = filterByAss($rawHealth, $assIdSet);
$svcs     = filterByAss($rawSvcs,   $assIdSet);
$socios   = filterByAss($rawSocio,  $assIdSet);

// ── 1. Readiness Score Trends (Jan–Dec of current year) ──────
$readinessMonths = [];
$year = date('Y');
for ($m = 1; $m <= 12; $m++) {
    $key = $year . '-' . str_pad($m, 2, '0', STR_PAD_LEFT);
    $dt  = new DateTime($key . '-01');
    $readinessMonths[$key] = ['label' => $dt->format('M Y'), 'severe'=>0,'moderate'=>0,'low'=>0,'stable'=>0];
}
foreach ($assessments as $a) {
    $ts = $a['created_at'] ?? null;
    $rs = strtolower($a['readiness_score'] ?? '');
    if (!$ts || !in_array($rs, ['severe','moderate','low','stable'])) continue;
    $key = date('Y-m', strtotime($ts));
    if (isset($readinessMonths[$key])) $readinessMonths[$key][$rs]++;
}
$readinessTrends = array_values($readinessMonths);

// ── 2. Enrollment ────────────────────────────────────────────
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

// ── 4. Minimum Wage Level ─────────────────────────────────────
// Philippine daily minimum wage ~₱610 (NCR); monthly ~₱13,000 as reference
$minWageMonthly = 13000;
$mwBelow = 0; $mwAt = 0; $mwAbove = 0;
foreach ($econs as $e) {
    $inc = (float)($e['monthly_income'] ?? 0);
    if ($inc <= 0) continue;
    if ($inc < $minWageMonthly * 0.9)      $mwBelow++;
    elseif ($inc <= $minWageMonthly * 1.1) $mwAt++;
    else                                    $mwAbove++;
}
$minWage = ['below' => $mwBelow, 'at' => $mwAt, 'above' => $mwAbove];

// ── 5. Service Awareness vs Availment Gap ────────────────────
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

// ── 6. Financial Assistance ───────────────────────────────────
$faYes = 0; $faNo = 0;
foreach ($svcs as $s) {
    if (!empty($s['receives_financial_assistance'])) $faYes++;
    else $faNo++;
}
$financialAssist = ['yes'=>$faYes, 'no'=>$faNo, 'total'=>count($svcs)];

// ── 7. Healthcare Barriers ───────────────────────────────────
$barrierCounts = [];
foreach ($healths as $h) {
    if (empty($h['has_barriers_to_healthcare'])) continue;
    $detail = trim($h['healthcare_barriers_details'] ?? '');
    if (!$detail) { $barrierCounts['Not specified'] = ($barrierCounts['Not specified']??0)+1; continue; }
    $parts = preg_split('/[,;]+/', $detail);
    foreach ($parts as $p) { $p = trim($p); if ($p) $barrierCounts[$p] = ($barrierCounts[$p]??0)+1; }
}
arsort($barrierCounts);
$barriers = array_map(fn($l,$c)=>['label'=>$l,'count'=>$c],
    array_slice(array_keys($barrierCounts),0,8),
    array_slice(array_values($barrierCounts),0,8));

// ── 8. Vaccination / Immunization ────────────────────────────
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

// ── 9. Ongoing Health Issues ─────────────────────────────────
$hiYes = 0; $hiNo = 0;
foreach ($healths as $h) {
    if (!empty($h['has_ongoing_health_conditions'])) $hiYes++;
    else $hiNo++;
}
$healthIssues = ['yes'=>$hiYes, 'no'=>$hiNo, 'total'=>count($healths)];

// ── 10. Availed Health Services (last 6 months) ──────────────
$haYes = 0; $haNo = 0;
foreach ($healths as $h) {
    if (!empty($h['availed_services_6months'])) $haYes++;
    else $haNo++;
}
$healthAvailed = ['yes'=>$haYes, 'no'=>$haNo, 'total'=>count($healths)];

// ── 11. Health Expenses ──────────────────────────────────────
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
$expenses = []; $totalAvg = 0;
foreach ($expKeys as $idx=>$key) {
    $avg = $expCount>0 ? round($expSums[$idx]/$expCount,2) : 0;
    $totalAvg += $avg;
    $expenses[] = ['label'=>$expLabels[$idx],'avg'=>$avg];
}
usort($expenses, fn($a,$b)=>$b['avg']<=>$a['avg']);

// ── 12. Family Size — count rows in family_members per assessment ─
$famCountMap = [];
foreach ($fams as $f) {
    $aid = $f['assessment_id'] ?? '';
    if ($aid) $famCountMap[$aid] = ($famCountMap[$aid] ?? 0) + 1;
}
$fsSmall = 0; $fsMed = 0; $fsLarge = 0; $fsTotal = 0; $fsSum = 0;
foreach ($famCountMap as $aid => $cnt) {
    if (!isset($assIdSet[$aid])) continue;
    $fsTotal++; $fsSum += $cnt;
    if ($cnt <= 4)      $fsSmall++;
    elseif ($cnt <= 7)  $fsMed++;
    else                $fsLarge++;
}
$familySize = [
    'small'  => $fsSmall,
    'medium' => $fsMed,
    'large'  => $fsLarge,
    'total'  => $fsTotal,
    'avg'    => $fsTotal > 0 ? round($fsSum / $fsTotal, 1) : 0,
];

// ── 13. 4Ps Membership ───────────────────────────────────────
$psYes = 0; $psNo = 0;
foreach ($preqs as $p) {
    if (!empty($p['is_4ps_member'])) $psYes++;
    else $psNo++;
}
$fourps = ['yes'=>$psYes, 'no'=>$psNo, 'total'=>count($preqs)];

// ── 14. Religion ─────────────────────────────────────────────
$relCounts = [];
foreach ($children as $c) {
    $rel = trim($c['religion'] ?? '');
    if ($rel === 'Other' || $rel === '') $rel = trim($c['religion_other'] ?? '') ?: 'Other';
    if ($rel) $relCounts[$rel] = ($relCounts[$rel] ?? 0) + 1;
}
arsort($relCounts);
$religion = array_map(fn($l,$c)=>['label'=>$l,'count'=>$c],
    array_slice(array_keys($relCounts),0,6),
    array_slice(array_values($relCounts),0,6));

// ── 15. Disability Types (ranked) ────────────────────────────
$disTypeCounts = [];
foreach ($cedus as $c) {
    $dis = $c['disabilities'] ?? [];
    if (is_string($dis)) $dis = json_decode($dis, true) ?? [];
    if (!is_array($dis)) continue;
    foreach ($dis as $d) {
        $d = trim($d);
        if ($d) $disTypeCounts[$d] = ($disTypeCounts[$d] ?? 0) + 1;
    }
}
arsort($disTypeCounts);
$disTypesRanked = array_map(fn($l,$c)=>['label'=>$l,'count'=>$c],
    array_keys($disTypeCounts), array_values($disTypeCounts));

// ── 16. Housing ──────────────────────────────────────────────
$matCounts = []; $tenureCounts = []; $waterCounts = []; $elecCounts = []; $toiletCounts = []; $garbageCounts = []; $modYes = 0; $modNo = 0;
foreach ($socios as $s) {
    $mat = trim($s['housing_materials'] ?? '');
    if ($mat) $matCounts[$mat] = ($matCounts[$mat] ?? 0) + 1;

    $tenure = trim($s['tenure_status'] ?? '');
    if ($tenure) $tenureCounts[$tenure] = ($tenureCounts[$tenure] ?? 0) + 1;

    $water = trim($s['water_source'] ?? '');
    if ($water) $waterCounts[$water] = ($waterCounts[$water] ?? 0) + 1;

    $elec = trim($s['electricity_source'] ?? '');
    if ($elec) $elecCounts[$elec] = ($elecCounts[$elec] ?? 0) + 1;

    $toilet = trim($s['toilet_type'] ?? '');
    if ($toilet) $toiletCounts[$toilet] = ($toiletCounts[$toilet] ?? 0) + 1;

    $garbage = trim($s['garbage_disposal'] ?? '');
    if ($garbage) $garbageCounts[$garbage] = ($garbageCounts[$garbage] ?? 0) + 1;

    if (!empty($s['has_accessibility_modifications'])) $modYes++;
    else $modNo++;
}
arsort($matCounts); arsort($tenureCounts); arsort($waterCounts); arsort($elecCounts); arsort($toiletCounts); arsort($garbageCounts);
$housing = [
    'materials'         => array_map(fn($l,$c)=>['label'=>$l,'val'=>$c], array_keys($matCounts), array_values($matCounts)),
    'tenure'            => array_map(fn($l,$c)=>['label'=>$l,'val'=>$c], array_keys($tenureCounts), array_values($tenureCounts)),
    'water_source'      => array_map(fn($l,$c)=>['label'=>$l,'val'=>$c], array_keys($waterCounts), array_values($waterCounts)),
    'electricity_source'=> array_map(fn($l,$c)=>['label'=>$l,'val'=>$c], array_keys($elecCounts), array_values($elecCounts)),
    'toilet_type'       => array_map(fn($l,$c)=>['label'=>$l,'val'=>$c], array_keys($toiletCounts), array_values($toiletCounts)),
    'garbage_disposal'  => array_map(fn($l,$c)=>['label'=>$l,'val'=>$c], array_keys($garbageCounts), array_values($garbageCounts)),
    'mod_yes'           => $modYes,
    'mod_no'            => $modNo,
    'mod_total'         => $modYes + $modNo,
];

// ── 17b. IP Membership ───────────────────────────────────────
$ipCounts = [];
foreach ($children as $c) {
    $ip = trim($c['ip_membership'] ?? '');
    if ($ip) $ipCounts[$ip] = ($ipCounts[$ip] ?? 0) + 1;
}
arsort($ipCounts);
$ipMembership = array_map(fn($l,$c)=>['label'=>$l,'val'=>$c], array_keys($ipCounts), array_values($ipCounts));

// ── 17c. Primary Income Source ───────────────────────────────
$incSrcCounts = [];
foreach ($econs as $e) {
    $src = trim($e['primary_income_source'] ?? '');
    if ($src) $incSrcCounts[$src] = ($incSrcCounts[$src] ?? 0) + 1;
}
arsort($incSrcCounts);
$primaryIncomeSrc = array_map(fn($l,$c)=>['label'=>$l,'val'=>$c], array_keys($incSrcCounts), array_values($incSrcCounts));

// ── 17d. Financial Assistance & Social Services Awareness ────
$faAwareYes = 0; $faAwareNo = 0;
foreach ($svcs as $s) {
    if (!empty($s['is_aware_of_social_services'])) $faAwareYes++;
    else $faAwareNo++;
}
$socialAwareness = ['yes'=>$faAwareYes, 'no'=>$faAwareNo, 'total'=>count($svcs)];

// ── 17e. Readiness Score Distribution ────────────────────────
$rdCounts = ['severe'=>0,'moderate'=>0,'low'=>0,'stable'=>0];
foreach ($assessments as $a) {
    $rs = strtolower(trim($a['readiness_score'] ?? ''));
    if (isset($rdCounts[$rs])) $rdCounts[$rs]++;
}
$readinessDist = [
    ['label'=>'Severe',   'val'=>$rdCounts['severe'],   'color'=>'#ef4444'],
    ['label'=>'Moderate', 'val'=>$rdCounts['moderate'], 'color'=>'#f59e0b'],
    ['label'=>'Low',      'val'=>$rdCounts['low'],      'color'=>'#3b82f6'],
    ['label'=>'Stable',   'val'=>$rdCounts['stable'],   'color'=>'#22c55e'],
];

// ── 17. Educational Attainment ───────────────────────────────
$attCounts = [];
foreach ($cedus as $c) {
    $att = trim($c['highest_education'] ?? '');
    if ($att) $attCounts[$att] = ($attCounts[$att] ?? 0) + 1;
}
// Order by typical school progression
$attOrder = ['No Formal Education','Kindergarten','Elementary','Junior High School','Senior High School','Vocational/Technical','College','Post-Graduate'];
$attOrdered = [];
foreach ($attOrder as $level) {
    if (isset($attCounts[$level])) {
        $attOrdered[] = ['label'=>$level,'val'=>$attCounts[$level]];
        unset($attCounts[$level]);
    }
}
// Append any remaining levels not in the order list
arsort($attCounts);
foreach ($attCounts as $l=>$v) $attOrdered[] = ['label'=>$l,'val'=>$v];

echo json_encode([
    'success'             => true,
    'readiness_trends'    => $readinessTrends,
    'readiness_dist'      => $readinessDist,
    'enrollment'          => ['enrolled'=>$enrolled,'not_enrolled'=>$notEnrolled,'top_reasons'=>$topReasons],
    'income'              => $incomeBreakdown,
    'min_wage'            => $minWage,
    'primary_income_src'  => $primaryIncomeSrc,
    'service_gap'         => $serviceGap,
    'financial_assist'    => $financialAssist,
    'social_awareness'    => $socialAwareness,
    'barriers'            => $barriers,
    'vaccination'         => ['national_rate'=>$vaccRate,'total'=>$vaccTotal,'vaccinated'=>$vaccYes,'by_region'=>$vaccByRegion],
    'health_issues'       => $healthIssues,
    'health_availed'      => $healthAvailed,
    'expenses'            => ['total_avg'=>round($totalAvg,2),'breakdown'=>$expenses],
    'family_size'         => $familySize,
    'fourps'              => $fourps,
    'religion'            => $religion,
    'ip_membership'       => $ipMembership,
    'dis_types_ranked'    => $disTypesRanked,
    'housing'             => $housing,
    'educ_attainment'     => $attOrdered,
]);
