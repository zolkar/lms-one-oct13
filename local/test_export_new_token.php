<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../config.php');

require_once($CFG->dirroot . '/local/aicc_hacp/classes/secure_auth.php');

echo "Testing token generation...\n\n";

// Generate a token
$token1 = \local_aicc_hacp\secure_auth::generate_launch_token(9, 34, 'external_lms');
echo "Token 1: " . substr($token1, 0, 50) . "...\n";

// Wait 1 second and generate another
sleep(1);
$token2 = \local_aicc_hacp\secure_auth::generate_launch_token(9, 34, 'external_lms');
echo "Token 2: " . substr($token2, 0, 50) . "...\n";

echo "\nTokens are " . ($token1 === $token2 ? "IDENTICAL" : "DIFFERENT") . "\n";

// Decode both tokens
$data1 = \local_aicc_hacp\secure_auth::validate_launch_token($token1);
$data2 = \local_aicc_hacp\secure_auth::validate_launch_token($token2);

if ($data1 && $data2) {
    echo "\nToken 1 issued at: " . date('Y-m-d H:i:s', $data1['issued_at']) . "\n";
    echo "Token 2 issued at: " . date('Y-m-d H:i:s', $data2['issued_at']) . "\n";
    echo "Current time: " . date('Y-m-d H:i:s', time()) . "\n";
    echo "TTL: " . get_config('local_aicc_hacp', 'launch_token_ttl') . " seconds\n";
}
