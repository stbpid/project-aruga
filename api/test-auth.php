<?php
require_once __DIR__ . '/config.php';
require_once __DIR__ . '/auth.php';
header('Content-Type: application/json');
echo json_encode(['success' => true, 'message' => 'Auth passed']);
?>
