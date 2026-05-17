<?php
/**
 * Supabase Configuration
 * Project Aruga - DSWD STB
 */

// Enable error reporting for development
error_reporting(E_ALL);
ini_set('display_errors', 1);

// ================================================================
// SUPABASE CREDENTIALS
// ================================================================
// 
// FOR PRODUCTION (Vercel):
// Set these as Environment Variables in Vercel Dashboard:
// - SUPABASE_URL
// - SUPABASE_SERVICE_ROLE_KEY
//
// FOR LOCAL TESTING:
// Replace the values below with your actual credentials
// ================================================================

$supabaseUrl = getenv('SUPABASE_URL');
$supabaseKey = getenv('SUPABASE_SERVICE_ROLE_KEY');

// For local development, create a .env file (see .env.example) and load it here,
// or set the environment variables in your local server configuration.
// NEVER hardcode credentials in this file.
if (empty($supabaseUrl) || empty($supabaseKey)) {
    error_log('FATAL: SUPABASE_URL or SUPABASE_SERVICE_ROLE_KEY environment variable is not set.');
    http_response_code(500);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Server configuration error.']);
    exit;
}

define('SUPABASE_URL', $supabaseUrl);
define('SUPABASE_SERVICE_ROLE_KEY', $supabaseKey);

// ================================================================
// HELPER FUNCTIONS
// ================================================================

/**
 * Get headers for Supabase API requests
 * @return array Headers for cURL requests
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
 * @param string $method HTTP method (GET, POST, PATCH, DELETE)
 * @param string $endpoint API endpoint (e.g., 'interviewers?select=*')
 * @param array|null $data Data to send (for POST/PATCH)
 * @return array Response with success status, data, and HTTP code
 */
function supabaseRequest($method, $endpoint, $data = null) {
    // Build full URL
    $url = SUPABASE_URL . '/rest/v1/' . $endpoint;
    
    // Initialize cURL
    $ch = curl_init();
    
    // Set cURL options
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, getSupabaseHeaders());
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    
    // Add data for POST/PATCH requests
    if ($data !== null && ($method === 'POST' || $method === 'PATCH')) {
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($data));
    }
    
    // Execute request
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    // Check for cURL errors
    if (curl_errno($ch)) {
        $error = curl_error($ch);
        curl_close($ch);
        return [
            'success' => false,
            'error' => 'cURL Error: ' . $error,
            'data' => null,
            'httpCode' => 0
        ];
    }
    
    curl_close($ch);
    
    // Decode JSON response
    $decoded = json_decode($response, true);
    
    // Check for JSON decode errors
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [
            'success' => false,
            'error' => 'JSON Decode Error: ' . json_last_error_msg(),
            'data' => $response,
            'httpCode' => $httpCode
        ];
    }
    
    // Return formatted response
    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'data' => $decoded,
        'error' => ($httpCode >= 400) ? ($decoded['message'] ?? 'Unknown error') : null,
        'httpCode' => $httpCode
    ];
}

/**
 * Debug version — returns response body and HTTP code for troubleshooting
 */
function logAuditDebug($action, $tableName, $recordId = null, $oldValues = null, $newValues = null, $interviewerId = null, $assessmentId = null) {
    $payload = [
        'action'     => $action,
        'table_name' => $tableName,
        'ip_address' => getUserIP(),
        'user_agent' => getUserAgent(),
    ];
    if (!empty($recordId))      $payload['record_id']      = $recordId;
    if (!empty($interviewerId)) $payload['interviewer_id'] = $interviewerId;
    if (!empty($assessmentId))  $payload['assessment_id']  = $assessmentId;
    if ($oldValues !== null && is_array($oldValues)) $payload['old_values'] = $oldValues;
    if ($newValues !== null && is_array($newValues)) $payload['new_values'] = $newValues;

    $url = SUPABASE_URL . '/rest/v1/audit_logs';
    $ch  = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: '       . SUPABASE_SERVICE_ROLE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
        'Prefer: return=representation',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    $body = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $err  = curl_error($ch);
    curl_close($ch);

    return ['http_code' => $code, 'body' => $body, 'curl_error' => $err, 'payload_sent' => $payload];
}

/**
 * Write an entry to the audit_logs table
 */
function logAudit($action, $tableName, $recordId = null, $oldValues = null, $newValues = null, $interviewerId = null, $assessmentId = null) {
    $payload = [
        'action'     => $action,
        'table_name' => $tableName,
        'ip_address' => getUserIP(),
        'user_agent' => getUserAgent(),
    ];

    // UUIDs — only include if non-empty string
    if (!empty($recordId))      $payload['record_id']     = $recordId;
    if (!empty($interviewerId)) $payload['interviewer_id'] = $interviewerId;
    if (!empty($assessmentId))  $payload['assessment_id']  = $assessmentId;

    // jsonb columns must be sent as JSON-encoded strings with explicit cast header
    // Supabase REST accepts plain objects — send as PHP arrays, let json_encode handle it
    if ($oldValues !== null && is_array($oldValues)) $payload['old_values'] = $oldValues;
    if ($newValues !== null && is_array($newValues)) $payload['new_values'] = $newValues;

    // Use a dedicated curl call so we can set Prefer: return=minimal (faster, no body parse)
    $url = SUPABASE_URL . '/rest/v1/audit_logs';
    $ch  = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Content-Type: application/json',
        'apikey: '       . SUPABASE_SERVICE_ROLE_KEY,
        'Authorization: Bearer ' . SUPABASE_SERVICE_ROLE_KEY,
        'Prefer: return=minimal',
    ]);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_exec($ch);
    curl_close($ch);
}

/**
 * Call a Supabase RPC function
 */
function supabaseRPC($functionName, $params = []) {
    $url = SUPABASE_URL . '/rest/v1/rpc/' . $functionName;

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, getSupabaseHeaders());
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, 'POST');
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    $decoded = json_decode($response, true);

    return [
        'success' => $httpCode >= 200 && $httpCode < 300,
        'data'    => $decoded,
        'httpCode'=> $httpCode
    ];
}

/**
 * Get client's IP address
 * @return string IP address
 */
function getUserIP() {
    // Check for various forwarded IP headers
    $headers = [
        'HTTP_CLIENT_IP',
        'HTTP_X_FORWARDED_FOR',
        'HTTP_X_FORWARDED',
        'HTTP_X_CLUSTER_CLIENT_IP',
        'HTTP_FORWARDED_FOR',
        'HTTP_FORWARDED',
        'REMOTE_ADDR'
    ];
    
    foreach ($headers as $header) {
        if (!empty($_SERVER[$header])) {
            $ip = $_SERVER[$header];
            // Take first IP if multiple IPs are present
            if (strpos($ip, ',') !== false) {
                $ip = explode(',', $ip)[0];
            }
            return trim($ip);
        }
    }
    
    return 'Unknown';
}

/**
 * Get user agent string
 * @return string User agent
 */
function getUserAgent() {
    return $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown';
}

/**
 * Send standardized JSON response
 * @param bool $success Success status
 * @param string $message Response message
 * @param mixed $data Response data (optional)
 * @param int $httpCode HTTP status code
 */
function sendResponse($success, $message, $data = null, $httpCode = 200) {
    // Set HTTP status code
    http_response_code($httpCode);
    
    // Set headers
    header('Content-Type: application/json');
    header('Access-Control-Allow-Origin: *');
    header('Access-Control-Allow-Methods: GET, POST, PATCH, DELETE, OPTIONS');
    header('Access-Control-Allow-Headers: Content-Type, Authorization');
    
    // Build response
    $response = [
        'success' => $success,
        'message' => $message,
        'timestamp' => date('c')
    ];
    
    // Add data if provided
    if ($data !== null) {
        $response['data'] = $data;
    }
    
    // Send response and exit
    echo json_encode($response, JSON_PRETTY_PRINT);
    exit;
}

/**
 * Validate required fields in request data
 * @param array $data Request data
 * @param array $requiredFields List of required field names
 * @return array|null Returns null if valid, or array with missing fields
 */
function validateRequiredFields($data, $requiredFields) {
    $missing = [];
    
    foreach ($requiredFields as $field) {
        if (!isset($data[$field]) || empty(trim($data[$field]))) {
            $missing[] = $field;
        }
    }
    
    return empty($missing) ? null : $missing;
}

/**
 * Sanitize string input
 * @param string $input Input string
 * @return string Sanitized string
 */
function sanitizeInput($input) {
    $input = trim($input);
    $input = stripslashes($input);
    $input = htmlspecialchars($input, ENT_QUOTES, 'UTF-8');
    return $input;
}

/**
 * Generate UUID v4
 * @return string UUID
 */
function generateUUID() {
    $data = random_bytes(16);
    $data[6] = chr(ord($data[6]) & 0x0f | 0x40);
    $data[8] = chr(ord($data[8]) & 0x3f | 0x80);
    return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($data), 4));
}

/**
 * Log error to file (for debugging)
 * @param string $message Error message
 * @param mixed $context Additional context
 */
function logError($message, $context = null) {
    $logFile = __DIR__ . '/../logs/error.log';
    $logDir = dirname($logFile);
    
    // Create logs directory if it doesn't exist
    if (!file_exists($logDir)) {
        mkdir($logDir, 0755, true);
    }
    
    $timestamp = date('Y-m-d H:i:s');
    $contextStr = $context ? ' | Context: ' . json_encode($context) : '';
    $logMessage = "[$timestamp] $message$contextStr\n";
    
    file_put_contents($logFile, $logMessage, FILE_APPEND);
}

// ================================================================
// VERIFY CONFIGURATION
// ================================================================

// Check if credentials are set (only for debugging, remove in production)
if (SUPABASE_URL === 'https://YOUR_PROJECT_ID.supabase.co') {
    // Credentials not configured - log warning
    error_log('WARNING: Supabase credentials not configured in api/config.php');
}

?>