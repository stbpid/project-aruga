<?php
header('Content-Type: application/json');

require_once __DIR__ . '/config.php';

// Only allow POST requests for safety
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST only']);
    exit;
}

$migrations = [
    ['old' => 'Region V (Bicol)',       'new' => 'Region V (Bicol Region)'],
    ['old' => 'Region IV-A (MIMAROPA)', 'new' => 'Region IV-B (MIMAROPA)'],
];

$results = [];

foreach ($migrations as $m) {
    // Update children table
    $url = SUPABASE_URL . '/rest/v1/children?region=eq.' . urlencode($m['old']);
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ],
        CURLOPT_POSTFIELDS => json_encode(['region' => $m['new']]),
    ]);
    $resp = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $results[] = [
        'table'    => 'children',
        'old'      => $m['old'],
        'new'      => $m['new'],
        'http'     => $httpCode,
        'response' => json_decode($resp),
    ];

    // Update interviewers table
    $url2 = SUPABASE_URL . '/rest/v1/interviewers?region=eq.' . urlencode($m['old']);
    $ch2 = curl_init($url2);
    curl_setopt_array($ch2, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST  => 'PATCH',
        CURLOPT_HTTPHEADER     => [
            'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
            'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
            'Content-Type: application/json',
            'Prefer: return=representation',
        ],
        CURLOPT_POSTFIELDS => json_encode(['region' => $m['new']]),
    ]);
    $resp2 = curl_exec($ch2);
    $httpCode2 = curl_getinfo($ch2, CURLINFO_HTTP_CODE);
    curl_close($ch2);

    $results[] = [
        'table'    => 'interviewers',
        'old'      => $m['old'],
        'new'      => $m['new'],
        'http'     => $httpCode2,
        'response' => json_decode($resp2),
    ];
}

echo json_encode(['success' => true, 'results' => $results], JSON_PRETTY_PRINT);
?>
