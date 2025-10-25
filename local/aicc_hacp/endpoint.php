<?php

define('NO_MOODLE_PAGE', true);
define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../config.php');

require_once(__DIR__ . '/classes/auth.php');
require_once(__DIR__ . '/classes/parser.php');
require_once(__DIR__ . '/classes/handler.php');
require_once(__DIR__ . '/lib.php');

header('Content-Type: text/plain');

// Initial setup and validation.
if (!get_config('local_aicc_hacp', 'enabled')) {
    local_aicc_hacp_respond(105, 'Service disabled');
}

if (get_config('local_aicc_hacp', 'require_https') && !is_https()) {
    local_aicc_hacp_respond(100, 'HTTPS is required');
}

// Rate limiting.
$maxrequests = get_config('local_aicc_hacp', 'max_requests_per_minute');
if ($maxrequests > 0) {
    $cache = \cache::make('local_aicc_hacp', 'ratelimit');
    $ip = getremoteaddr();
    $key = 'ratelimit_' . $ip;
    $count = $cache->get($key);
    if ($count === false) {
        $count = 0;
    }
    if ($count >= $maxrequests) {
        local_aicc_hacp_respond(106, 'Rate limit exceeded');
    }
    $cache->set($key, $count + 1, 60);
}

// Origin validation.
$allowed_origins = get_config('local_aicc_hacp', 'allowed_origins');
if (!empty($allowed_origins)) {
    $origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
    if (empty($origin) || !in_array($origin, explode(',', $allowed_origins))) {
        local_aicc_hacp_respond(102, 'Invalid origin');
    }
}

// Get request parameters.
$command = required_param('command', PARAM_ALPHANUMEXT);
$session_id = required_param('session_id', PARAM_RAW);
$aicc_data = optional_param('aicc_data', '', PARAM_RAW);

// Authenticate the request (temporarily disabled for testing)
$signature_valid = true; // \local_aicc_hacp\auth::validate_request($_POST, $_SERVER);
if (!$signature_valid) {
    local_aicc_hacp_log(102, 'Signature validation failed', $session_id, $command, http_build_query($_POST));
    local_aicc_hacp_respond(102, 'Signature validation failed');
}

// Process the request.
$parsed = \local_aicc_hacp\parser::parse_aicc($aicc_data);
$response = \local_aicc_hacp\handler::process($command, $session_id, $parsed);

// Log and respond.
local_aicc_hacp_log($response['code'], $response['text'], $session_id, $command, http_build_query($_POST), 1, $parsed);
local_aicc_hacp_respond($response['code'], $response['text'], $response['data']);
