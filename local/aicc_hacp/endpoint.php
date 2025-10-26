<?php

define('NO_MOODLE_PAGE', true);
define('NO_OUTPUT_BUFFERING', true);

require_once(__DIR__ . '/../../config.php');

require_once(__DIR__ . '/classes/secure_auth.php');
require_once(__DIR__ . '/classes/secure_session.php');
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

// Get request parameters.
$command = required_param('command', PARAM_ALPHANUMEXT);
$session_id = required_param('session_id', PARAM_RAW);
$aicc_data = optional_param('aicc_data', '', PARAM_RAW);

// Validate session ID format
if (!\local_aicc_hacp\secure_session::validate_session_id($session_id)) {
    local_aicc_hacp_log(103, 'Invalid session ID format', $session_id, $command, http_build_query($_POST));
    local_aicc_hacp_respond(103, 'Invalid session ID format');
}

// Rate limiting by session ID
$rate_limit_key = getremoteaddr() . '_' . $session_id;
if (!\local_aicc_hacp\secure_auth::check_rate_limit($rate_limit_key)) {
    local_aicc_hacp_log(106, 'Rate limit exceeded', $session_id, $command, http_build_query($_POST));
    local_aicc_hacp_respond(106, 'Rate limit exceeded');
}

// Origin validation.
$origin = $_SERVER['HTTP_ORIGIN'] ?? $_SERVER['HTTP_REFERER'] ?? '';
if (!empty($origin) && !\local_aicc_hacp\secure_auth::validate_origin($origin)) {
    local_aicc_hacp_log(102, 'Invalid origin', $session_id, $command, http_build_query($_POST));
    local_aicc_hacp_respond(102, 'Invalid origin');
}

// Authenticate the request
$signature_valid = \local_aicc_hacp\secure_auth::validate_request($_POST, $_SERVER);
if (!$signature_valid) {
    local_aicc_hacp_log(102, 'Signature validation failed', $session_id, $command, http_build_query($_POST));
    local_aicc_hacp_respond(102, 'Signature validation failed');
}

// Get and validate session
$session = \local_aicc_hacp\secure_session::get_session($session_id);
if (!$session) {
    local_aicc_hacp_log(103, 'Session not found or expired', $session_id, $command, http_build_query($_POST));
    local_aicc_hacp_respond(103, 'Session not found or expired');
}

// Process the request.
$parsed = \local_aicc_hacp\parser::parse_aicc($aicc_data);
$response = \local_aicc_hacp\handler::process($command, $session_id, $parsed);

// Log and respond.
local_aicc_hacp_log($response['code'], $response['text'], $session_id, $command, http_build_query($_POST), 1, $parsed);
local_aicc_hacp_respond($response['code'], $response['text'], $response['data']);
