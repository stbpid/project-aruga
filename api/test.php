<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$response = [
    'status' => 'success',
    'message' => 'PHP API is working on Vercel!',
    'timestamp' => date('Y-m-d H:i:s'),
    'php_version' => phpversion()
];

echo json_encode($response, JSON_PRETTY_PRINT);