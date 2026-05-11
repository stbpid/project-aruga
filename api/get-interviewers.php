<?php
require_once __DIR__ . '/config.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false]); exit;
}

$res = supabaseRequest('GET',
    'interviewers?select=id,full_name,interviewer_code,email,region,province,position,office,status,dashboard_role&order=full_name.asc&limit=10000'
);

if (!$res['success']) {
    echo json_encode(['success' => false, 'data' => []]); exit;
}

// Fetch all assessments for submission counts
$monthStart = date('Y-m-01T00:00:00');
$monthEnd   = date('Y-m-t') . 'T23:59:59';

$allRes = supabaseRequest('GET',
    'assessments?select=interviewer_code,status,created_at&limit=100000'
);

// Build per-code totals, monthly counts, and last active date
$totalMap       = [];
$monthMap       = [];
$completedTotal = [];
$completedMonth = [];
$lastActiveMap  = [];

if ($allRes['success'] && is_array($allRes['data'])) {
    foreach ($allRes['data'] as $a) {
        $code = $a['interviewer_code'] ?? '';
        if (!$code) continue;
        $totalMap[$code] = ($totalMap[$code] ?? 0) + 1;
        if ($a['status'] === 'completed') $completedTotal[$code] = ($completedTotal[$code] ?? 0) + 1;
        $createdAt = $a['created_at'] ?? '';
        if ($createdAt >= $monthStart && $createdAt <= $monthEnd) {
            $monthMap[$code] = ($monthMap[$code] ?? 0) + 1;
            if ($a['status'] === 'completed') $completedMonth[$code] = ($completedMonth[$code] ?? 0) + 1;
        }
        if ($createdAt && (!isset($lastActiveMap[$code]) || $createdAt > $lastActiveMap[$code])) {
            $lastActiveMap[$code] = $createdAt;
        }
    }
}

// Also check sessions for last login activity
$sessRes = supabaseRequest('GET', 'sessions?select=interviewer_code,created_at&limit=100000');
if ($sessRes['success'] && is_array($sessRes['data'])) {
    foreach ($sessRes['data'] as $s) {
        $code = $s['interviewer_code'] ?? '';
        $createdAt = $s['created_at'] ?? '';
        if (!$code || !$createdAt) continue;
        if (!isset($lastActiveMap[$code]) || $createdAt > $lastActiveMap[$code]) {
            $lastActiveMap[$code] = $createdAt;
        }
    }
}

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
        'Region VI – Western Visayas'  => 'Region VI (Western Visayas)',
        'Region VI - Western Visayas'  => 'Region VI (Western Visayas)',
        'Region VII – Central Visayas' => 'Region VII (Central Visayas)',
        'Region VII - Central Visayas' => 'Region VII (Central Visayas)',
        'Region VIII – Eastern Visayas'=> 'Region VIII (Eastern Visayas)',
        'Region VIII - Eastern Visayas'=> 'Region VIII (Eastern Visayas)',
        'Region IX – Zamboanga Peninsula'=> 'Region IX (Zamboanga Peninsula)',
        'Region IX - Zamboanga Peninsula'=> 'Region IX (Zamboanga Peninsula)',
        'Region X – Northern Mindanao' => 'Region X (Northern Mindanao)',
        'Region X - Northern Mindanao' => 'Region X (Northern Mindanao)',
        'Region XI – Davao Region'     => 'Region XI (Davao Region)',
        'Region XI - Davao Region'     => 'Region XI (Davao Region)',
        'Region XII – SOCCSKSARGEN'    => 'Region XII (SOCCSKSARGEN)',
        'Region XII - SOCCSKSARGEN'    => 'Region XII (SOCCSKSARGEN)',
        'Region XIII – Caraga'         => 'Region XIII (Caraga)',
        'Region XIII - Caraga'         => 'Region XIII (Caraga)',
        'Caraga'                       => 'Region XIII (Caraga)',
        'CAR – Cordillera'             => 'CAR (Cordillera Administrative Region)',
        'CAR - Cordillera'             => 'CAR (Cordillera Administrative Region)',
        'CAR'                          => 'CAR (Cordillera Administrative Region)',
        'Cordillera'                   => 'CAR (Cordillera Administrative Region)',
        'BARMM'                        => 'BARMM (Bangsamoro)',
        'Bangsamoro'                   => 'BARMM (Bangsamoro)',
    ];
    return $map[$r] ?? ($r ?: '—');
}

$rows = array_map(function($r) use ($totalMap, $monthMap, $completedTotal, $completedMonth, $lastActiveMap) {
    $code  = $r['interviewer_code'] ?? '—';
    $total = $totalMap[$code] ?? 0;
    $month = $monthMap[$code] ?? 0;
    $cTotal = $completedTotal[$code] ?? 0;
    $cMonth = $completedMonth[$code] ?? 0;
    $lastActive = $lastActiveMap[$code] ?? null;
    return [
        'id'               => $r['id'] ?? null,
        'name'             => $r['full_name'] ?? '—',
        'code'             => $code,
        'email'            => $r['email'] ?? '—',
        'dashboard_role'   => $r['dashboard_role'] ?? '—',
        'region'           => normalizeRegion($r['region'] ?? ''),
        'province'         => $r['province'] ?? '—',
        'position'         => $r['position'] ?? '—',
        'office'           => $r['office'] ?? '—',
        'status'           => $r['status'] ?? 'active',
        'submissions_month'=> $month,
        'submissions_total'=> $total,
        'completed_month'  => $cMonth,
        'completed_total'  => $cTotal,
        'last_active'      => $lastActive,
    ];
}, $res['data']);

echo json_encode(['success' => true, 'data' => $rows]);
