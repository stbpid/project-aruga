<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: https://project-aruga.vercel.app');
header('Access-Control-Allow-Methods: GET');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

function supabaseCount($endpoint) {
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

    if (preg_match('/Content-Range:\s*[\d\*]+-?[\d\*]*\/(\d+)/i', $resp, $m)) {
        return (int)$m[1];
    }
    return 0;
}

$severe   = supabaseCount('assessments?select=id&readiness_score=eq.severe');
$moderate = supabaseCount('assessments?select=id&readiness_score=eq.moderate');
$low      = supabaseCount('assessments?select=id&readiness_score=eq.low');
$stable   = supabaseCount('assessments?select=id&readiness_score=eq.stable');

echo json_encode([
    'success' => true,
    'data' => [
        'severe'   => $severe,
        'moderate' => $moderate,
        'low'      => $low,
        'stable'   => $stable,
        'total'    => $severe + $moderate + $low + $stable,
    ]
]);
