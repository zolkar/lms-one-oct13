<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/hacp_handler.php');

// Security checks.
$allowed_origins = get_config('local_aicc_hacp', 'allowed_origins');
if (!empty($allowed_origins)) {
    $allowed_origins = explode("\n", trim($allowed_origins));
    $referer = $_SERVER['HTTP_REFERER'] ?? '';
    $is_allowed = false;
    foreach ($allowed_origins as $origin) {
        if (strpos($referer, trim($origin)) === 0) {
            $is_allowed = true;
            break;
        }
    }
    if (!$is_allowed) {
        header('HTTP/1.1 403 Forbidden');
        exit;
    }
}

$requests_per_minute = get_config('local_aicc_hacp', 'requests_per_minute');
if (!empty($requests_per_minute)) {
    $cache = \cache::make('local_aicc_hacp', 'ratelimit');
    $ip = getremoteaddr();
    $key = "ratelimit_{$ip}";
    $count = $cache->get($key);
    if ($count === false) {
        $count = 0;
    }
    if ($count >= $requests_per_minute) {
        header('HTTP/1.1 429 Too Many Requests');
        exit;
    }
    $cache->set($key, $count + 1, 60);
}

$aicc_sid = required_param('AICC_SID', PARAM_TEXT);
$command = required_param('command', PARAM_TEXT);
$aiccdata = optional_param('aicc_data', '', PARAM_RAW);

$handler = new \local_aicc_hacp\hacp_handler($aicc_sid);
$response = $handler->process_request($command, $aiccdata);

header('Content-Type: text/plain');
echo $response;
exit;
