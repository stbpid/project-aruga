<?php
header('Access-Control-Allow-Origin: *');
header('Content-Type: application/json');

$assets = [
    'navLogo' => 'https://lh3.googleusercontent.com/d/1PXQxz3l0uxl0zEo1Lw5R4Qrd41yXbQ6V',
    'heroImage' => 'https://lh3.googleusercontent.com/d/1m7NYCL9E3bDRR1XKcrPtV4RPLg9BKW2a',
    'formLogo' => 'https://lh3.googleusercontent.com/d/1q7Qgj0pJp6CtGVbMvOdQTQM3BKAuy4mw'
];

echo json_encode($assets, JSON_PRETTY_PRINT);
?>