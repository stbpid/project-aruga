<?php
// TEMPORARY - DELETE AFTER DEBUGGING
header('Content-Type: application/json');
echo json_encode([
    'getenv_url' => getenv('SUPABASE_URL') ? 'SET len=' . strlen(getenv('SUPABASE_URL')) : 'EMPTY',
    'getenv_key' => getenv('SUPABASE_SERVICE_ROLE_KEY') ? 'SET len=' . strlen(getenv('SUPABASE_SERVICE_ROLE_KEY')) : 'EMPTY',
    'env_url'    => isset($_ENV['SUPABASE_URL']) ? 'SET len=' . strlen($_ENV['SUPABASE_URL']) : 'EMPTY',
    'env_key'    => isset($_ENV['SUPABASE_SERVICE_ROLE_KEY']) ? 'SET len=' . strlen($_ENV['SUPABASE_SERVICE_ROLE_KEY']) : 'EMPTY',
    'server_url' => isset($_SERVER['SUPABASE_URL']) ? 'SET len=' . strlen($_SERVER['SUPABASE_URL']) : 'EMPTY',
    'server_key' => isset($_SERVER['SUPABASE_SERVICE_ROLE_KEY']) ? 'SET len=' . strlen($_SERVER['SUPABASE_SERVICE_ROLE_KEY']) : 'EMPTY',
]);
?>
