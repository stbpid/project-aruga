<?php
/**
 * Analytics Router — consolidates:
 *   - get-analytics.php           (action=analytics)
 *   - get-analytics-extended.php  (action=analytics-extended)
 *   - get-dashboard-stats.php     (action=dashboard-stats)
 *   - get-monthly-assessments.php (action=monthly-assessments)
 *   - get-top-issues.php          (action=top-issues)
 *   - get-regions-stats.php       (action=regions-stats)
 *   - get-region-coverage.php     (action=region-coverage)
 *
 * NOTE: api/get-recent-sessions.php is NOT included here — grepped public/ and found
 * zero references to it from any frontend code, so it is dead/unused. Left untouched
 * in place per the out-of-scope instruction (not moved, not routed).
 *
 * All source files require auth.php + region-coverage-helper.php unconditionally.
 */
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';
require_once __DIR__ . '/lib/region-coverage-helper.php';

$action = $_GET['action'] ?? '';

switch ($action) {

    // ================================================================
    // action=analytics  (was api/get-analytics.php)
    // ================================================================
    case 'analytics': {
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

        // ── Fetch core data in parallel (paginated — Supabase caps each response at 1000 rows) ──
        $fetched = supabaseFetchAllMulti([
            'assessments'   => 'assessments?select=id,status,readiness_score,created_at,interviewer_id,interviewer_code&deleted_at=is.null' . $dateFilter,
            'children'      => 'children?select=assessment_id,region,province,city_municipality,date_of_birth,sex',
            'disabilities'  => 'child_education_health?select=assessment_id,disabilities',
            'interviewers'  => 'interviewers?select=id,full_name,interviewer_code,region,status',
        ]);
        $assessments  = $fetched['assessments'];
        $children     = $fetched['children'];
        $disData      = $fetched['disabilities'];
        $interviewers = $fetched['interviewers'];

        // Build lookup maps
        $childMap = [];
        foreach ($children as $c) { $childMap[$c['assessment_id']] = $c; }

        $disMap = [];
        foreach ($disData as $d) {
            $dis = $d['disabilities'] ?? [];
            if (is_string($dis)) $dis = json_decode($dis, true) ?? [];
            $disMap[$d['assessment_id']] = is_array($dis) ? $dis : [];
        }

        if (!function_exists('analyticsNormalizeRegion')) {
            function analyticsNormalizeRegion($r) {
                return normalizeRegion($r) ?: '—';
            }
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

        // Completion rate = active (non-deleted) children vs target, same logic as Overview tab
        $regionTargets = getRegionTargets();
        $activeCountsByRegion = getActiveChildrenCountsByRegion();
        if ($regionFilter) {
            $normalizedFilter = analyticsNormalizeRegion($regionFilter);
            $totalActive = $activeCountsByRegion[$normalizedFilter] ?? 0;
            $totalTarget = $regionTargets[$normalizedFilter] ?? 0;
        } else {
            $totalActive = 0;
            $totalTarget = 0;
            foreach ($regionTargets as $region => $target) {
                $totalActive += $activeCountsByRegion[$region] ?? 0;
                $totalTarget += $target;
            }
        }
        $rate = $totalTarget > 0 ? round(($totalActive / $totalTarget) * 100, 1) : 0;

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
        break;
    }

    // ================================================================
    // action=analytics-extended  (was api/get-analytics-extended.php)
    // ================================================================
    case 'analytics-extended': {
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
            $days  = (int)$range ?: 30;
            $since = date('Y-m-d', strtotime("-{$days} days")) . 'T00:00:00';
            $dateFilter = '&created_at=gte.' . urlencode($since);
        }

        // ── Fetch all needed tables in parallel (paginated — Supabase caps each response at 1000 rows) ──
        $fetched = supabaseFetchAllMulti([
            'assessments' => 'assessments?select=id,readiness_score,created_at&deleted_at=is.null' . $dateFilter,
            'children'    => 'children?select=assessment_id,region,sex,religion,religion_other,ip_membership',
            'preq'        => 'pre_qualification?select=assessment_id,is_4ps_member',
            'family'      => 'family_members?select=assessment_id',
            'education'   => 'education_info?select=assessment_id,is_currently_enrolled,not_enrolled_reason',
            'cedu'        => 'child_education_health?select=assessment_id,highest_education,disabilities',
            'economic'    => 'economic_capacity?select=assessment_id,income_classification,monthly_income,primary_income_source',
            'health'      => 'health_info?select=assessment_id,has_all_vaccinations,has_ongoing_health_conditions,availed_services_6months,has_barriers_to_healthcare,healthcare_barriers_details,expense_food,expense_medication,expense_therapy,expense_hygiene,expense_assistive_device,expense_other',
            'services'    => 'service_availment?select=assessment_id,is_aware_of_social_services,has_availed_services,receives_financial_assistance',
            'socio'       => 'socio_economic?select=assessment_id,housing_materials,tenure_status,has_accessibility_modifications,water_source,electricity_source,toilet_type,garbage_disposal',
        ]);
        $rawAss    = $fetched['assessments'];
        $rawChild  = $fetched['children'];
        $rawPreq   = $fetched['preq'];
        $rawFam    = $fetched['family'];
        $rawEdus   = $fetched['education'];
        $rawCedu   = $fetched['cedu'];
        $rawEcons  = $fetched['economic'];
        $rawHealth = $fetched['health'];
        $rawSvcs   = $fetched['services'];
        $rawSocio  = $fetched['socio'];

        if (!function_exists('extNormalizeRegion')) {
            function extNormalizeRegion($r) {
                return normalizeRegion($r) ?: '—';
            }
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

        if (!function_exists('filterByAss')) {
            function filterByAss(array $rows, array $assIdSet, string $key = 'assessment_id'): array {
                return array_values(array_filter($rows, fn($r) => isset($assIdSet[$r[$key] ?? ''])));
            }
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
        break;
    }

    // ================================================================
    // action=dashboard-stats  (was api/get-dashboard-stats.php)
    // ================================================================
    case 'dashboard-stats': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }

        if (!function_exists('supabaseCountDS')) {
            function supabaseCountDS($endpoint) {
                $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
                $ch = curl_init();
                curl_setopt($ch, CURLOPT_URL, $url);
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, [
                    'Content-Type: application/json',
                    'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
                    'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
                    'Prefer: count=exact',
                    'Range: 0-0',
                ]);
                curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'GET');
                curl_setopt($ch, CURLOPT_HEADER, true);
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                $resp = curl_exec($ch);
                curl_close($ch);

                // Content-Range: 0-0/14  or  Content-Range: */14
                if (preg_match('/Content-Range:\s*[\d\*]+-?[\d\*]*\/(\d+)/i', $resp, $m)) {
                    return (int)$m[1];
                }
                return 0;
            }
        }

        // Total beneficiaries = total rows in assessments table
        $totalBeneficiaries = supabaseCountDS('assessments?select=id&deleted_at=is.null');

        // Active interviewers — no status filter first, count all, then try with active
        $activeInterviewers = supabaseCountDS('interviewers?select=id&status=eq.active');
        // Fallback: if 0, count all interviewers (status column may not exist or have different value)
        if ($activeInterviewers === 0) {
            $activeInterviewers = supabaseCountDS('interviewers?select=id');
        }

        // Regions covered = number of top-level regions in the locations dropdown data
        // (originally parsed from get-locations.php's $locations array via regex; that file
        // was consolidated into admin-router.php's 'locations' action during the router
        // refactor, so the same top-level region key count is inlined directly here to
        // avoid depending on file-parsing a path that no longer exists at runtime).
        $regionsCovered = 9;

        // Completion rate = total active children across all regions / total region targets * 100
        $regionCounts = getActiveChildrenCountsByRegion();
        $regionTargets = getRegionTargets();
        $totalActive = 0;
        $totalTarget = 0;
        foreach ($regionTargets as $region => $target) {
            $totalActive += $regionCounts[$region] ?? 0;
            $totalTarget += $target;
        }
        $completionRate = $totalTarget > 0
            ? round(($totalActive / $totalTarget) * 100, 1)
            : 0;

        echo json_encode([
            'success' => true,
            'data' => [
                'total_beneficiaries' => $totalBeneficiaries,
                'active_interviewers' => $activeInterviewers,
                'regions_covered'     => $regionsCovered,
                'completion_rate'     => $completionRate,
            ]
        ]);
        break;
    }

    // ================================================================
    // action=monthly-assessments  (was api/get-monthly-assessments.php)
    // ================================================================
    case 'monthly-assessments': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        $year = isset($_GET['year']) ? (int)$_GET['year'] : (int)date('Y');

        // Fetch all assessments for the given year — just created_at
        $res = supabaseRequest('GET', 'assessments?select=created_at&deleted_at=is.null&created_at=gte.' . $year . '-01-01T00:00:00&created_at=lt.' . ($year + 1) . '-01-01T00:00:00&limit=10000');

        $months = array_fill(1, 12, 0);

        if ($res['success'] && is_array($res['data'])) {
            foreach ($res['data'] as $row) {
                if (!empty($row['created_at'])) {
                    $m = (int)date('n', strtotime($row['created_at']));
                    if ($m >= 1 && $m <= 12) $months[$m]++;
                }
            }
        }

        $labels = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        $data = [];
        for ($i = 1; $i <= 12; $i++) {
            $data[] = ['month' => $i, 'label' => $labels[$i - 1], 'value' => $months[$i]];
        }

        echo json_encode(['success' => true, 'year' => $year, 'data' => $data]);
        break;
    }

    // ================================================================
    // action=top-issues  (was api/get-top-issues.php)
    // ================================================================
    case 'top-issues': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }

        // System-wide (all non-deleted assessments, not scoped to a month)
        $assData    = supabaseFetchAll('assessments?select=id,readiness_score&deleted_at=is.null');
        $healthData = supabaseFetchAll('health_info?select=assessment_id,has_ongoing_health_conditions,has_barriers_to_healthcare');
        $eduData    = supabaseFetchAll('education_info?select=assessment_id,is_currently_enrolled');
        $disData    = supabaseFetchAll('child_education_health?select=assessment_id,disabilities');
        $econData   = supabaseFetchAll('economic_capacity?select=assessment_id,income_classification');

        $assessmentIds = [];
        $severeIds     = [];
        foreach ($assData as $a) {
            $assessmentIds[] = $a['id'];
            if (($a['readiness_score'] ?? '') === 'severe') $severeIds[] = $a['id'];
        }
        $totalAssessments = count($assessmentIds);
        $idSet = array_flip($assessmentIds);

        // Count: severe cases
        $severeCases = count($severeIds);

        // Count: healthcare barriers / ongoing health conditions
        $healthBarriers = 0;
        $ongoingHealth  = 0;
        foreach ($healthData as $h) {
            if (!isset($idSet[$h['assessment_id']])) continue;
            if (!empty($h['has_ongoing_health_conditions'])) $ongoingHealth++;
            if (!empty($h['has_barriers_to_healthcare']))    $healthBarriers++;
        }

        // Count: not enrolled in school
        $notEnrolled = 0;
        foreach ($eduData as $e) {
            if (!isset($idSet[$e['assessment_id']])) continue;
            if (isset($e['is_currently_enrolled']) && $e['is_currently_enrolled'] === false) $notEnrolled++;
        }

        // Count: multi-disability (2+ disabilities)
        $multiDisability = 0;
        foreach ($disData as $d) {
            if (!isset($idSet[$d['assessment_id']])) continue;
            $dis = $d['disabilities'] ?? [];
            if (is_string($dis)) $dis = json_decode($dis, true) ?? [];
            if (count($dis) >= 2) $multiDisability++;
        }

        // Count: subsistence/poor income classification
        $extremePoverty = 0;
        foreach ($econData as $e) {
            if (!isset($idSet[$e['assessment_id']])) continue;
            $cls = trim($e['income_classification'] ?? '');
            if ($cls === 'Below Minimum / Low Income') {
                $extremePoverty++;
            }
        }

        // Build issues list, sorted by count desc
        $issues = [
            [
                'key'         => 'severe',
                'title'       => 'Severe Readiness Cases',
                'icon'        => 'emergency',
                'count'       => $severeCases,
                'severity'    => 'critical',
                'description' => 'Children flagged as needing immediate intervention.',
                'link_tab'    => 'beneficiaries',
            ],
            [
                'key'         => 'health_barriers',
                'title'       => 'Healthcare Access Barriers',
                'icon'        => 'local_hospital',
                'count'       => $healthBarriers,
                'severity'    => 'high',
                'description' => 'Cases reporting barriers to accessing healthcare services.',
                'link_tab'    => 'beneficiaries',
            ],
            [
                'key'         => 'ongoing_health',
                'title'       => 'Ongoing Health Conditions',
                'icon'        => 'monitor_heart',
                'count'       => $ongoingHealth,
                'severity'    => 'high',
                'description' => 'Children with active health conditions requiring monitoring.',
                'link_tab'    => 'beneficiaries',
            ],
            [
                'key'         => 'not_enrolled',
                'title'       => 'Out-of-School Children',
                'icon'        => 'school',
                'count'       => $notEnrolled,
                'severity'    => 'moderate',
                'description' => 'Children not currently enrolled in any educational program.',
                'link_tab'    => 'beneficiaries',
            ],
            [
                'key'         => 'multi_disability',
                'title'       => 'Multiple Disabilities',
                'icon'        => 'accessibility_new',
                'count'       => $multiDisability,
                'severity'    => 'moderate',
                'description' => 'Children with two or more reported disabilities.',
                'link_tab'    => 'beneficiaries',
            ],
            [
                'key'         => 'extreme_poverty',
                'title'       => 'Extreme Poverty Cases',
                'icon'        => 'attach_money',
                'count'       => $extremePoverty,
                'severity'    => 'high',
                'description' => 'Households classified as subsistence poor.',
                'link_tab'    => 'beneficiaries',
            ],
        ];

        // Sort by count desc, show all 6
        usort($issues, fn($a, $b) => $b['count'] - $a['count']);

        echo json_encode([
            'success'          => true,
            'total_assessments'=> $totalAssessments,
            'issues'           => $issues,
        ]);
        break;
    }

    // ================================================================
    // action=region-coverage  (was api/get-region-coverage.php)
    // ================================================================
    case 'region-coverage': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET');

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']);
            exit;
        }

        $counts = getActiveChildrenCountsByRegion();
        $regionTargets = getRegionTargets();

        // Sort descending by count
        arsort($counts);

        $data = [];
        foreach ($counts as $region => $count) {
            $target = $regionTargets[$region] ?? null;
            $data[] = ['region' => $region, 'count' => $count, 'target' => $target];
        }

        echo json_encode(['success' => true, 'data' => $data]);
        break;
    }

    // ================================================================
    // action=regions-stats  (was api/get-regions-stats.php)
    // ================================================================
    case 'regions-stats': {
        header('Content-Type: application/json');
        header('Access-Control-Allow-Methods: GET, OPTIONS');
        header('Access-Control-Allow-Headers: Content-Type, Authorization');

        if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') { http_response_code(204); exit; }

        if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
            echo json_encode(['success' => false, 'message' => 'Method not allowed']); exit;
        }

        $filterRegion = isset($_GET['region']) ? trim($_GET['region']) : '';
        $filterProvince = isset($_GET['province']) ? trim($_GET['province']) : '';

        // Pull all needed tables in parallel (paginated — Supabase caps each response at 1000 rows)
        $fetched = supabaseFetchAllMulti([
            'children'     => 'children?select=assessment_id,region,province,city_municipality,barangay,sex,date_of_birth',
            'assessments'  => 'assessments?select=id,status,readiness_score,created_at,interviewer_code&deleted_at=is.null',
            'interviewers' => 'interviewers?select=region,status',
            'disabilities' => 'child_education_health?select=assessment_id,disabilities',
        ]);
        $childRows = $fetched['children'];
        $assRows   = $fetched['assessments'];
        $intRows2  = $fetched['interviewers'];
        $disRows   = $fetched['disabilities'];

        if (empty($childRows)) {
            echo json_encode(['success' => false, 'data' => [], 'regions' => [], 'provinces' => [], 'summary' => []]); exit;
        }

        // Build assessment lookup
        $assMap = [];
        foreach ($assRows as $a) {
            $assMap[$a['id']] = [
                'status'         => $a['status'] ?? 'in_progress',
                'readiness_score'=> $a['readiness_score'] ?? null,
                'created_at'     => $a['created_at'] ?? null,
                'interviewer_code'=> $a['interviewer_code'] ?? null,
            ];
        }

        // Build disability lookup
        $disMap = [];
        foreach ($disRows as $d) {
            $dis = $d['disabilities'] ?? [];
            if (is_string($dis)) $dis = json_decode($dis, true) ?? [];
            $disMap[$d['assessment_id']] = $dis;
        }

        // Build interviewer counts per region
        $intRegionCount = [];
        foreach ($intRows2 as $iv) {
            $r = normalizeRegion($iv['region'] ?? '');
            if ($r === '') continue;
            $intRegionCount[$r] = ($intRegionCount[$r] ?? 0) + 1;
        }

        // --- Aggregate ---
        $regionMap   = [];
        $provinceMap = [];
        $cityMap     = [];
        $disCount    = [];
        $monthlyMap  = [];
        $totalProfiled = 0;

        foreach ($childRows as $row) {
            $region   = normalizeRegion(trim($row['region']   ?? ''));
            $province = trim($row['province']          ?? '');
            $city     = trim($row['city_municipality'] ?? '');
            $barangay = trim($row['barangay']          ?? '');
            $aid      = $row['assessment_id']          ?? null;
            $sex      = trim($row['sex']               ?? '');
            $dob      = $row['date_of_birth']          ?? null;

            if ($region === '') continue;
            if ($filterRegion  !== '' && $region   !== normalizeRegion($filterRegion))  continue;
            if ($filterProvince !== '' && $province !== $filterProvince) continue;

            if (!isset($assMap[$aid])) continue; // skip children of soft-deleted assessments

            $ass    = $assMap[$aid];
            $disArr = $disMap[$aid]    ?? [];
            $status = $ass['status'];
            $month  = $ass['created_at'] ? substr($ass['created_at'],0,7) : null;

            // Age
            $age = null;
            if ($dob) {
                try { $age = (int)(new DateTime($dob))->diff(new DateTime())->y; } catch(Exception $e){}
            }

            $totalProfiled++;

            // Region
            if (!isset($regionMap[$region])) {
                $regionMap[$region] = ['region'=>$region,'total'=>0,'completed'=>0,'in_progress'=>0,'abandoned'=>0,'male'=>0,'female'=>0,'interviewers'=>$intRegionCount[$region]??0,'provinces'=>[]];
            }
            $regionMap[$region]['total']++;
            if ($status === 'completed')  $regionMap[$region]['completed']++;
            elseif ($status === 'abandoned') $regionMap[$region]['abandoned']++;
            else $regionMap[$region]['in_progress']++;
            if ($sex === 'Male')   $regionMap[$region]['male']++;
            if ($sex === 'Female') $regionMap[$region]['female']++;
            if ($province !== '') $regionMap[$region]['provinces'][$province] = true;

            // Province
            $pKey = $region . '||' . $province;
            if (!isset($provinceMap[$pKey])) {
                $provinceMap[$pKey] = ['region'=>$region,'province'=>$province,'total'=>0,'completed'=>0,'in_progress'=>0,'cities'=>[]];
            }
            $provinceMap[$pKey]['total']++;
            if ($status === 'completed') $provinceMap[$pKey]['completed']++;
            else $provinceMap[$pKey]['in_progress']++;
            if ($city !== '') $provinceMap[$pKey]['cities'][$city] = true;

            // City
            $cKey = $region . '||' . $province . '||' . $city;
            if (!isset($cityMap[$cKey])) {
                $cityMap[$cKey] = ['region'=>$region,'province'=>$province,'city'=>$city,'total'=>0,'completed'=>0];
            }
            $cityMap[$cKey]['total']++;
            if ($status === 'completed') $cityMap[$cKey]['completed']++;

            // Disabilities
            foreach ($disArr as $d) {
                if ($d) $disCount[$d] = ($disCount[$d] ?? 0) + 1;
            }

            // Monthly
            if ($month) $monthlyMap[$month] = ($monthlyMap[$month] ?? 0) + 1;
        }

        // Finalize regions
        $regions = [];
        foreach ($regionMap as $r) {
            $r['province_count'] = count($r['provinces']);
            unset($r['provinces']);
            $r['rate'] = $r['total'] > 0 ? round(($r['completed'] / $r['total']) * 100) : 0;
            $regions[] = $r;
        }
        usort($regions, fn($a,$b) => $b['total'] - $a['total']);

        // Finalize provinces
        $provinces = [];
        foreach ($provinceMap as $p) {
            $p['city_count'] = count($p['cities']);
            unset($p['cities']);
            $p['rate'] = $p['total'] > 0 ? round(($p['completed'] / $p['total']) * 100) : 0;
            $provinces[] = $p;
        }
        usort($provinces, fn($a,$b) => $b['total'] - $a['total']);

        // Finalize cities
        $cities = [];
        foreach ($cityMap as $c) {
            $c['rate'] = $c['total'] > 0 ? round(($c['completed'] / $c['total']) * 100) : 0;
            $cities[] = $c;
        }
        usort($cities, fn($a,$b) => $b['total'] - $a['total']);

        // Top disabilities
        arsort($disCount);
        $topDisabilities = array_slice(array_map(fn($k,$v)=>['label'=>$k,'count'=>$v], array_keys($disCount), array_values($disCount)), 0, 10);

        // Monthly trend (last 12)
        ksort($monthlyMap);
        $monthlyTrend = [];
        foreach (array_slice($monthlyMap, -12, 12, true) as $m => $cnt) {
            $monthlyTrend[] = ['month'=>$m,'count'=>$cnt];
        }

        // Summary
        $totalCompleted   = array_sum(array_column($regions,'completed'));
        $totalInProgress  = array_sum(array_column($regions,'in_progress'));
        $totalAbandoned   = array_sum(array_column($regions,'abandoned'));
        $totalInterviewers= array_sum(array_column($regions,'interviewers'));

        echo json_encode([
            'success'       => true,
            'summary'       => [
                'total_profiled'  => $totalProfiled,
                'completed'       => $totalCompleted,
                'in_progress'     => $totalInProgress,
                'abandoned'       => $totalAbandoned,
                'regions_count'   => count($regions),
                'interviewers'    => $totalInterviewers,
            ],
            'regions'       => $regions,
            'provinces'     => $provinces,
            'cities'        => array_slice($cities, 0, 100),
            'disabilities'  => $topDisabilities,
            'monthly_trend' => $monthlyTrend,
        ]);
        break;
    }

    default: {
        http_response_code(400);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Unknown action']);
        exit;
    }
}
