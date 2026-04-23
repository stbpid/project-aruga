<?php
/**
 * Supabase Configuration
 * Project Aruga - DSWD STB
 */

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Supabase credentials (replace with your actual values)
define('SUPABASE_URL', getenv('SUPABASE_URL') ?: 'https://YOUR_PROJECT_ID.supabase.co');
define('SUPABASE_SERVICE_ROLE_KEY', getenv('SUPABASE_SERVICE_ROLE_KEY') ?: 'YOUR_SERVICE_ROLE_KEY_HERE');

/**
 * Get headers for Supabase API requests
 */
function getSupabaseHeaders() {
    return [
        'Content-Type: application/json',
        'apikey: ' . SUPABASE_SERVICE_ROLE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
        'Prefer: return=representation'
    ];
}

/**
 * Make a request to Supabase REST API
 */
function supabaseRequest($method, $endpoint, $data = null) {
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    
    $ch = curl_init();
    
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, getSupabaseHeaders());
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    
    if ($data !== null && ($method === 'POST' || $method === 'PATCH')) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'success' => false,
            'error' => 'cURL Error: ' . $error,
            'httpCode' => 0
        ];
    }
    
    curl_close($ch);
    
    $decoded = json_decode($response, true);
    
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'data' => $decoded,
        'httpCode' => $httpCode
    ];
}

/**
 * Get user's IP address
 */
function getUserIP() {
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        return $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        return $_SERVER['HTTP_X_FORWARDED_FOR'];
    } else {
        return $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
    }
}

/**
 * Get user agent
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Send JSON response
 */
function sendResponse($success, $message, $data = null, $httpCode = 200) {
    http_response_code($httpCode);
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type');
    
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('c')
    ]);
    exit;
}
?>