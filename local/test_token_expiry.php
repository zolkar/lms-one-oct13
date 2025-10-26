<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../config.php');

require_once($CFG->dirroot . '/local/aicc_hacp/classes/secure_auth.php');

$token = 'eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJjb3Vyc2VpZCI6OSwic2Nvcm1pZCI6MzQsImV4dGVybmFsX2xtcyI6ImV4dGVybmFsX2xtcyIsImlzc3VlZF9hdCI6MTc2MTQ2MzAxNywiZXhwaXJlc19hdCI6MTc2MTQ2NjYxN30.Xe9DWdzDtRkQhc3kQBH5cvMvKwX7mtXiRJss9bj0qgw';

echo "Validating token...\n";

$data = \local_aicc_hacp\secure_auth::validate_launch_token($token);

if ($data) {
    echo "Token is VALID\n";
    echo "Issued at: " . date('Y-m-d H:i:s', $data['issued_at']) . "\n";
    echo "Expires at: " . date('Y-m-d H:i:s', $data['expires_at']) . "\n";
    echo "Current time: " . date('Y-m-d H:i:s', time()) . "\n";
} else {
    echo "Token is INVALID or EXPIRED\n";
}

// Check config value
echo "\nToken TTL setting: " . get_config('local_aicc_hacp', 'launch_token_ttl') . " seconds\n";
echo "Expected TTL: 86400 (24 hours)\n";
