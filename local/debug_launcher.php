<?php
/**
 * Debug script for content launcher issues
 */

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);
require_once(__DIR__ . '/../config.php');

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "Content Launcher Debug\n";
echo str_repeat('=', 70) . "\n\n";

// Get parameters from AICC export
$cmid = 42; // Course module ID
$token = 'YOUR_TOKEN_HERE'; // Replace with actual token from AICC package

echo "Parameters:\n";
echo "  Course Module ID: {$cmid}\n";
echo "  Token: " . substr($token, 0, 50) . "...\n\n";

// Check if token is set
if (empty($token) || $token === 'YOUR_TOKEN_HERE') {
    echo "⚠ No token provided. Get it from the AICC .au file:\n";
    echo "  1. Extract the ZIP file\n";
    echo "  2. Open the .au file\n";
    echo "  3. Look for the token in the URL\n\n";
    
    // Try to generate a test token
    require_once(__DIR__ . '/../aicc_hacp/classes/secure_auth.php');
    $test_token = \local_aicc_hacp\secure_auth::generate_launch_token(9, 34, 'test');
    echo "Generated test token: {$test_token}\n\n";
} else {
    echo "Validating token...\n";
    require_once(__DIR__ . '/../aicc_hacp/classes/secure_auth.php');
    $token_data = \local_aicc_hacp\secure_auth::validate_launch_token($token);
    
    if ($token_data) {
        echo "✓ Token is valid\n";
        echo "  Course ID: {$token_data['courseid']}\n";
        echo "  SCORM ID: {$token_data['scormid']}\n";
        echo "  External LMS: {$token_data['external_lms']}\n";
        echo "  Expires: " . date('Y-m-d H:i:s', $token_data['expires_at']) . "\n";
    } else {
        echo "✗ Token validation failed\n";
        echo "  Possible reasons:\n";
        echo "  1. Token expired (check expiration time)\n";
        echo "  2. Secret doesn't match\n";
        echo "  3. Invalid token format\n";
    }
}

echo "\n";

// Check course module
echo "Checking course module 42...\n";
$cm = $DB->get_record('course_modules', ['id' => $cmid]);

if (!$cm) {
    echo "✗ Course module not found\n";
} else {
    echo "✓ Course module found\n";
    echo "  Course: {$cm->course}\n";
    echo "  Instance: {$cm->instance}\n";
    echo "  Visible: {$cm->visible}\n";
    echo "  Deletion in progress: {$cm->deletioninprogress}\n";
}

echo "\n";

// Check what happens when content launcher is called
echo "Simulating content_launcher.php call...\n";
echo "URL would be:\n";
echo "  http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id={$cmid}&token=" . substr($token, 0, 20) . "...\n\n";

// Check if plugins are enabled
echo "Checking plugin status...\n";
$export_enabled = get_config('local_aicc_export', 'enabled');
$hacp_enabled = get_config('local_aicc_hacp', 'enabled');

echo "  AICC Export enabled: " . ($export_enabled ? 'YES' : 'NO') . "\n";
echo "  AICC HACP enabled: " . ($hacp_enabled ? 'YES' : 'NO') . "\n";

if (!$export_enabled || !$hacp_enabled) {
    echo "\n⚠ One or both plugins are DISABLED!\n";
    echo "  Run: php local/setup_enable_plugins.php\n";
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "TROUBLESHOOTING\n";
echo str_repeat('=', 70) . "\n\n";

echo "Common issues:\n\n";

echo "1. 'Service disabled' error:\n";
echo "   - Run: php local/setup_enable_plugins.php\n\n";

echo "2. 'Invalid or expired access token' error:\n";
echo "   - Re-export the course from LMS-1\n";
echo "   - Tokens expire after 1 hour by default\n\n";

echo "3. 'Missing access token' error:\n";
echo "   - Check the URL in your AICC package contains &token=...\n";
echo "   - Make sure LMS-2 isn't stripping parameters\n\n";

echo "4. Content not loading:\n";
echo "   - Check browser console for CORS errors\n";
echo "   - Verify LMS-2 can reach LMS-1 (http://localhost:8300/lms-one)\n\n";

echo "To get the actual error:\n";
echo "1. Open browser console (F12)\n";
echo "2. Try accessing the SCORM activity\n";
echo "3. Look at Network tab for the failing request\n";
echo "4. Check the response body for error details\n\n";

echo "Test the launcher directly:\n";
echo "  http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=YOUR_TOKEN\n";
echo "(Replace YOUR_TOKEN with the token from your .au file)\n\n";
