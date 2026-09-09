<?php
/**
 * AI Agent Chatbot Router
 *
 * action=ask — accepts a question, answers using Gemini 2.5 Flash-Lite
 * with function calling over two tools:
 *   - search_documents: keyword search over extracted reference docs
 *   - get_my_assessment_counts: counts of the logged-in interviewer's own assessments
 */
require_once __DIR__ . '/lib/config.php';
require_once __DIR__ . '/lib/auth.php';

header('Content-Type: application/json');
header('Access-Control-Allow-Methods: POST, OPTIONS');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    exit;
}

$action = $_GET['action'] ?? '';

if ($action !== 'ask') {
    echo json_encode(['success' => false, 'message' => 'Unknown action.']);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

requireRole(['admin']);

// Read raw — the question goes into a JSON API body, not HTML, so htmlspecialchars() would corrupt apostrophes.
$requestBody = json_decode(file_get_contents('php://input'), true);
$question = trim($requestBody['question'] ?? '');
if (mb_strlen($question) > 2000) {
    $question = mb_substr($question, 0, 2000);
}
if (empty($question)) {
    echo json_encode(['success' => false, 'message' => 'Question is required.']);
    exit;
}

$geminiApiKey = getenv('GEMINI_API_KEY') ?: ($_ENV['GEMINI_API_KEY'] ?? $_SERVER['GEMINI_API_KEY'] ?? '');
if (empty($geminiApiKey)) {
    echo json_encode(['success' => false, 'message' => 'AI Agent is not configured.']);
    exit;
}

// ================================================================
// TOOLS
// ================================================================

function chatbotSearchDocuments($query) {
    $extractedDir = __DIR__ . '/../chatbot-sources/extracted';
    $matches = [];

    if (!is_dir($extractedDir)) {
        return 'No reference documents are available.';
    }

    $needle = mb_strtolower(trim($query));
    if ($needle === '') {
        return 'No search query provided.';
    }

    foreach (glob($extractedDir . '/*.txt') as $file) {
        $text = file_get_contents($file);
        $lines = explode("\n", $text);
        foreach ($lines as $i => $line) {
            if (mb_stripos($line, $needle) !== false) {
                $start = max(0, $i - 2);
                $end = min(count($lines) - 1, $i + 2);
                $excerpt = trim(implode("\n", array_slice($lines, $start, $end - $start + 1)));
                if ($excerpt !== '') {
                    $matches[] = '[' . basename($file, '.txt') . '] ' . $excerpt;
                }
            }
            if (count($matches) >= 8) break;
        }
        if (count($matches) >= 8) break;
    }

    if (empty($matches)) {
        return 'No matching content found in the reference documents.';
    }

    return implode("\n---\n", $matches);
}

function chatbotGetMyAssessmentCounts() {
    global $authInterviewer;

    $endpoint = 'assessments?interviewer_id=eq.' . urlencode($authInterviewer['id'])
              . '&deleted_at=is.null'
              . '&select=status';

    $result = supabaseRequest('GET', $endpoint);
    if (!$result['success']) {
        return 'Unable to retrieve assessment counts right now.';
    }

    $rows = $result['data'] ?? [];
    $counts = ['in_progress' => 0, 'completed' => 0, 'abandoned' => 0];
    foreach ($rows as $row) {
        $status = $row['status'] ?? '';
        if (isset($counts[$status])) {
            $counts[$status]++;
        }
    }

    return json_encode([
        'total' => count($rows),
        'in_progress' => $counts['in_progress'],
        'completed' => $counts['completed'],
        'abandoned' => $counts['abandoned'],
    ]);
}

// ================================================================
// GEMINI CALL
// ================================================================

$toolDeclarations = [
    [
        'name' => 'search_documents',
        'description' => 'Search the reference documents (manual, guidelines) for content relevant to a query.',
        'parameters' => [
            'type' => 'object',
            'properties' => [
                'query' => ['type' => 'string', 'description' => 'Keywords to search for'],
            ],
            'required' => ['query'],
        ],
    ],
    [
        'name' => 'get_my_assessment_counts',
        'description' => 'Get the count of assessments created by the current logged-in interviewer, broken down by status (in_progress, completed, abandoned).',
        'parameters' => [
            'type' => 'object',
            'properties' => (object)[],
        ],
    ],
];

function callGemini($apiKey, $contents, $tools) {
    $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-3.5-flash-lite:generateContent?key=' . urlencode($apiKey);

    $payload = [
        'contents' => $contents,
        'tools' => [['functionDeclarations' => $tools]],
    ];

    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    return ['httpCode' => $httpCode, 'data' => json_decode($response, true)];
}

$contents = [
    ['role' => 'user', 'parts' => [['text' => $question]]],
];

$result = callGemini($geminiApiKey, $contents, $toolDeclarations);

if ($result['httpCode'] === 429) {
    echo json_encode(['success' => false, 'message' => 'The assistant is busy right now, please try again in a moment.', 'debug' => $result]);
    exit;
}

if ($result['httpCode'] < 200 || $result['httpCode'] >= 300) {
    echo json_encode(['success' => false, 'message' => 'The assistant is temporarily unavailable.', 'debug' => $result]);
    exit;
}

$candidate = $result['data']['candidates'][0] ?? null;
$parts = $candidate['content']['parts'] ?? [];

$functionCall = null;
foreach ($parts as $part) {
    if (isset($part['functionCall'])) {
        $functionCall = $part['functionCall'];
        break;
    }
}

if ($functionCall !== null) {
    $fnName = $functionCall['name'] ?? '';
    $fnArgs = $functionCall['args'] ?? [];

    if ($fnName === 'search_documents') {
        $toolResult = chatbotSearchDocuments($fnArgs['query'] ?? '');
    } elseif ($fnName === 'get_my_assessment_counts') {
        $toolResult = chatbotGetMyAssessmentCounts();
    } else {
        $toolResult = 'Unknown tool.';
    }

    // Send the tool result back to Gemini for the final answer
    $contents[] = ['role' => 'model', 'parts' => [['functionCall' => $functionCall]]];
    $contents[] = [
        'role' => 'user',
        'parts' => [[
            'functionResponse' => [
                'name' => $fnName,
                'response' => ['result' => $toolResult],
            ],
        ]],
    ];

    $result = callGemini($geminiApiKey, $contents, $toolDeclarations);

    if ($result['httpCode'] === 429) {
        echo json_encode(['success' => false, 'message' => 'The assistant is busy right now, please try again in a moment.', 'debug' => $result]);
        exit;
    }

    if ($result['httpCode'] < 200 || $result['httpCode'] >= 300) {
        echo json_encode(['success' => false, 'message' => 'The assistant is temporarily unavailable.', 'debug' => $result]);
        exit;
    }

    $candidate = $result['data']['candidates'][0] ?? null;
    $parts = $candidate['content']['parts'] ?? [];
}

$answerText = '';
foreach ($parts as $part) {
    if (isset($part['text'])) {
        $answerText .= $part['text'];
    }
}

if (trim($answerText) === '') {
    $answerText = "I don't have enough information to answer that.";
}

echo json_encode(['success' => true, 'answer' => $answerText]);
