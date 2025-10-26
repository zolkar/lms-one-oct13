<?php
/**
 * Test script for AICC Token functionality
 * 
 * Usage: Run from CLI: php test_token.php
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/secure_auth.php');

cli_heading('AICC Token Test');

// Test 1: Check secrets are configured
echo "Testing token secrets...\n\n";

$shared_secret = get_config('local_aicc_hacp', 'shared_secret');
echo "✓ Shared secret configured: " . (!empty($shared_secret) ? 'YES (length: ' . strlen($shared_secret) . ')' : 'NO') . "\n";

$launch_secret = get_config('local_aicc_hacp', 'launch_token_secret');
echo "✓ Launch token secret configured: " . (!empty($launch_secret) ? 'YES (length: ' . strlen($launch_secret) . ')' : 'NO') . "\n";

if (empty($launch_secret)) {
    cli_error('Launch token secret is not configured!');
}

// Test 2: Generate a token
echo "\nGenerating test token...\n";
try {
    $token = \local_aicc_hacp\secure_auth::generate_launch_token(
        1, // Course ID
        1, // SCORM ID  
        'test_lms'
    );
    echo "✓ Token generated: " . substr($token, 0, 50) . "...\n";
    echo "  Full token: $token\n";
} catch (Exception $e) {
    cli_error('Failed to generate token: ' . $e->getMessage());
}

// Test 3: Validate the token immediately
echo "\nValidating token...\n";
try {
    $payload = \local_aicc_hacp\secure_auth::validate_launch_token($token);
    if ($payload) {
        echo "✓ Token validated successfully\n";
        echo "  Course ID: " . ($payload['courseid'] ?? 'N/A') . "\n";
        echo "  SCORM ID: " . ($payload['scormid'] ?? 'N/A') . "\n";
        echo "  External LMS: " . ($payload['external_lms'] ?? 'N/A') . "\n";
        echo "  Issued at: " . date('Y-m-d H:i:s', $payload['issued_at'] ?? 0) . "\n";
        echo "  Expires at: " . date('Y-m-d H:i:s', $payload['expires_at'] ?? 0) . "\n";
    } else {
        echo "✗ Token validation failed\n";
    }
} catch (Exception $e) {
    cli_error('Failed to validate token: ' . $e->getMessage());
}

// Test 4: Test with invalid token
echo "\nTesting invalid token...\n";
$invalid_payload = \local_aicc_hacp\secure_auth::validate_launch_token('invalid.token.here');
if (!$invalid_payload) {
    echo "✓ Invalid token correctly rejected\n";
} else {
    echo "✗ Invalid token was accepted (this is a problem)\n";
}

// Test 5: Test with expired token (simulate by setting short TTL)
echo "\nTesting expired token...\n";
set_config('launch_token_ttl', 1, 'local_aicc_hacp'); // 1 second TTL
$expiring_token = \local_aicc_hacp\secure_auth::generate_launch_token(1, 1, 'test');
sleep(2); // Wait for token to expire
$expired_payload = \local_aicc_hacp\secure_auth::validate_launch_token($expiring_token);
if (!$expired_payload) {
    echo "✓ Expired token correctly rejected\n";
} else {
    echo "✗ Expired token was accepted (this is a problem)\n";
}

// Reset TTL to default
set_config('launch_token_ttl', 3600, 'local_aicc_hacp');

// Test 6: Generate and validate multiple tokens
echo "\nTesting multiple tokens...\n";
$success_count = 0;
for ($i = 0; $i < 5; $i++) {
    $test_token = \local_aicc_hacp\secure_auth::generate_launch_token(rand(1, 100), rand(1, 50), 'test_lms_' . $i);
    $test_payload = \local_aicc_hacp\secure_auth::validate_launch_token($test_token);
    if ($test_payload) {
        $success_count++;
    }
}
echo "✓ Generated and validated $success_count/5 tokens successfully\n";

echo "\n" . str_repeat('=', 60) . "\n";
echo "Token test completed successfully!\n";
echo str_repeat('=', 60) . "\n";
