<?php
/**
 * Shared region-coverage helpers used by get-region-coverage.php and get-dashboard-stats.php
 */

function normalizeRegion($r) {
    $r = trim($r ?? '');
    $map = [
        'NCR'                          => 'NCR (National Capital Region)',
        'NCR – Metro Manila'           => 'NCR (National Capital Region)',
        'NCR - Metro Manila'           => 'NCR (National Capital Region)',
        'National Capital Region'      => 'NCR (National Capital Region)',
        'Region I – Ilocos Region'     => 'Region I (Ilocos Region)',
        'Region I - Ilocos Region'     => 'Region I (Ilocos Region)',
        'Region II – Cagayan Valley'   => 'Region II (Cagayan Valley)',
        'Region II - Cagayan Valley'   => 'Region II (Cagayan Valley)',
        'Region III – Central Luzon'   => 'Region III (Central Luzon)',
        'Region III - Central Luzon'   => 'Region III (Central Luzon)',
        'Region IV-A – CALABARZON'     => 'Region IV-A (CALABARZON)',
        'Region IV-A - CALABARZON'     => 'Region IV-A (CALABARZON)',
        'CALABARZON'                   => 'Region IV-A (CALABARZON)',
        'Region IV-B – MIMAROPA'       => 'Region IV-B (MIMAROPA)',
        'Region IV-B - MIMAROPA'       => 'Region IV-B (MIMAROPA)',
        'MIMAROPA'                     => 'Region IV-B (MIMAROPA)',
        'Region V – Bicol Region'      => 'Region V (Bicol Region)',
        'Region V - Bicol Region'      => 'Region V (Bicol Region)',
        'Bicol Region'                 => 'Region V (Bicol Region)',
        'Region V'                     => 'Region V (Bicol Region)',
        'Region V (Bicol)'             => 'Region V (Bicol Region)',
        'Region VI – Western Visayas'  => 'Region VI (Western Visayas)',
        'Region VI - Western Visayas'  => 'Region VI (Western Visayas)',
        'Region VI'                    => 'Region VI (Western Visayas)',
        'Region VII – Central Visayas' => 'Region VII (Central Visayas)',
        'Region VII - Central Visayas' => 'Region VII (Central Visayas)',
        'Region VII'                   => 'Region VII (Central Visayas)',
        'Region VIII – Eastern Visayas'=> 'Region VIII (Eastern Visayas)',
        'Region VIII - Eastern Visayas'=> 'Region VIII (Eastern Visayas)',
        'Region VIII'                  => 'Region VIII (Eastern Visayas)',
        'Region IX – Zamboanga Peninsula'=> 'Region IX (Zamboanga Peninsula)',
        'Region IX - Zamboanga Peninsula'=> 'Region IX (Zamboanga Peninsula)',
        'Region IX'                    => 'Region IX (Zamboanga Peninsula)',
        'Region X – Northern Mindanao' => 'Region X (Northern Mindanao)',
        'Region X - Northern Mindanao' => 'Region X (Northern Mindanao)',
        'Region X'                     => 'Region X (Northern Mindanao)',
        'Region XI – Davao Region'     => 'Region XI (Davao Region)',
        'Region XI - Davao Region'     => 'Region XI (Davao Region)',
        'Region XI'                    => 'Region XI (Davao Region)',
        'Region XII – SOCCSKSARGEN'    => 'Region XII (SOCCSKSARGEN)',
        'Region XII - SOCCSKSARGEN'    => 'Region XII (SOCCSKSARGEN)',
        'Region XII'                   => 'Region XII (SOCCSKSARGEN)',
        'Region XIII – Caraga'         => 'Region XIII (Caraga)',
        'Region XIII - Caraga'         => 'Region XIII (Caraga)',
        'Caraga'                       => 'Region XIII (Caraga)',
        'Region XIII'                  => 'Region XIII (Caraga)',
        'CAR – Cordillera'             => 'CAR (Cordillera Administrative Region)',
        'CAR - Cordillera'             => 'CAR (Cordillera Administrative Region)',
        'CAR'                          => 'CAR (Cordillera Administrative Region)',
        'Cordillera'                   => 'CAR (Cordillera Administrative Region)',
        'BARMM'                        => 'BARMM (Bangsamoro)',
        'Bangsamoro'                   => 'BARMM (Bangsamoro)',
    ];
    if (isset($map[$r])) return $map[$r];
    // case-insensitive fallback so values like "region v (bicol region)" still match
    $rLower = mb_strtolower($r);
    foreach ($map as $key => $val) {
        if (mb_strtolower($key) === $rLower) return $val;
    }
    return $r ?: '';
}

// Supabase REST caps each response at 1000 rows regardless of ?limit=,
// so fetch in pages until a page comes back short.
function supabaseFetchAll($endpoint, $pageSize = 1000) {
    $all = [];
    $offset = 0;
    while (true) {
        $sep = (strpos($endpoint, '?') === false) ? '?' : '&';
        $page = supabaseRequest('GET', $endpoint . $sep . "limit={$pageSize}&offset={$offset}");
        if (!$page['success'] || !is_array($page['data']) || empty($page['data'])) break;
        $all = array_merge($all, $page['data']);
        if (count($page['data']) < $pageSize) break;
        $offset += $pageSize;
    }
    return $all;
}

/**
 * Fetch multiple endpoints in parallel, each paginated (Supabase caps a single
 * response at 1000 rows). First round fetches page 0 of every endpoint at once;
 * any endpoint that came back full (1000 rows) gets its next page fetched in the
 * next round, also in parallel, until every endpoint is exhausted.
 *
 * @param array $endpoints Map of key => base endpoint (no limit/offset params)
 * @return array Map of key => full row list for that endpoint
 */
function supabaseFetchAllMulti($endpoints, $pageSize = 1000) {
    $all = array_fill_keys(array_keys($endpoints), []);
    $offsets = array_fill_keys(array_keys($endpoints), 0);
    $pending = $endpoints;

    while (!empty($pending)) {
        $batch = [];
        foreach ($pending as $key => $endpoint) {
            $sep = (strpos($endpoint, '?') === false) ? '?' : '&';
            $batch[$key] = $endpoint . $sep . "limit={$pageSize}&offset={$offsets[$key]}";
        }
        $results = supabaseRequestMultiGet($batch);

        $pending = [];
        foreach ($results as $key => $page) {
            if (!$page['success'] || !is_array($page['data']) || empty($page['data'])) continue;
            $all[$key] = array_merge($all[$key], $page['data']);
            if (count($page['data']) >= $pageSize) {
                $offsets[$key] += $pageSize;
                $pending[$key] = $endpoints[$key];
            }
        }
    }

    return $all;
}

function getRegionTargets() {
    return [
        'Region I (Ilocos Region)'    => 150,
        'Region II (Cagayan Valley)'  => 100,
        'Region III (Central Luzon)'  => 100,
        'Region IV-A (CALABARZON)'    => 100,
        'Region IV-B (MIMAROPA)'      => 150,
        'Region V (Bicol Region)'     => 100,
        'Region VI (Western Visayas)' => 150,
        'Region XI (Davao Region)'      => 150,
        'NCR (National Capital Region)' => 140,
    ];
}

/**
 * Returns active children counts per region, keyed by normalized region name.
 */
function getActiveChildrenCountsByRegion() {
    $assData = supabaseFetchAll('assessments?select=id&deleted_at=is.null');
    $validIds = array_flip(array_column($assData, 'id'));

    $childData = supabaseFetchAll('children?select=region,assessment_id');

    $counts = [];
    foreach ($childData as $row) {
        if (!isset($validIds[$row['assessment_id'] ?? ''])) continue;
        $r = normalizeRegion($row['region'] ?? '');
        if ($r === '') continue;
        $counts[$r] = ($counts[$r] ?? 0) + 1;
    }
    return $counts;
}
