<?php
/**
 * Quick setup script to enable AICC plugins
 * Run this to enable both plugins with default settings
 */

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);
require_once(__DIR__ . '/../config.php');

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "AICC Plugins - Quick Enable\n";
echo str_repeat('=', 70) . "\n\n";

// Enable AICC Export plugin
echo "1. Enabling AICC Export plugin...\n";
set_config('enabled', 1, 'local_aicc_export');
echo "   ✓ AICC Export enabled\n";

// Ensure token secret is set
if (empty(get_config('local_aicc_export', 'launch_token_secret'))) {
    set_config('launch_token_secret', \core\uuid::generate(), 'local_aicc_export');
    echo "   ✓ Generated launch token secret\n";
}

if (empty(get_config('local_aicc_export', 'launch_token_ttl'))) {
    set_config('launch_token_ttl', 3600, 'local_aicc_export');
    echo "   ✓ Set token TTL to 3600 seconds\n";
}

// Enable AICC HACP plugin
echo "\n2. Enabling AICC HACP plugin...\n";
set_config('enabled', 1, 'local_aicc_hacp');
echo "   ✓ AICC HACP enabled\n";

// Configure HACP settings
if (empty(get_config('local_aicc_hacp', 'shared_secret'))) {
    set_config('shared_secret', \core\uuid::generate(), 'local_aicc_hacp');
    echo "   ✓ Generated shared secret\n";
}

if (empty(get_config('local_aicc_hacp', 'launch_token_secret'))) {
    set_config('launch_token_secret', \core\uuid::generate(), 'local_aicc_hacp');
    echo "   ✓ Generated launch token secret\n";
}

if (empty(get_config('local_aicc_hacp', 'launch_token_ttl'))) {
    set_config('launch_token_ttl', 3600, 'local_aicc_hacp');
    echo "   ✓ Set token TTL to 3600 seconds\n";
}

set_config('log_level', 'debug', 'local_aicc_hacp');
echo "   ✓ Set log level to debug\n";

set_config('max_requests_per_minute', 60, 'local_aicc_hacp');
echo "   ✓ Set max requests to 60/min\n";

set_config('session_timeout', 7200, 'local_aicc_hacp');
echo "   ✓ Set session timeout to 7200 seconds\n";

set_config('require_https', 0, 'local_aicc_hacp');
echo "   ✓ HTTPS requirement disabled (for testing)\n";

set_config('allowed_origins', 'http://localhost:8300', 'local_aicc_hacp');
echo "   ✓ Set allowed origins to http://localhost:8300\n";

echo "\n3. Verifying settings...\n";
$export_enabled = get_config('local_aicc_export', 'enabled');
$hacp_enabled = get_config('local_aicc_hacp', 'enabled');

echo "   AICC Export enabled: " . ($export_enabled ? 'YES' : 'NO') . "\n";
echo "   AICC HACP enabled: " . ($hacp_enabled ? 'YES' : 'NO') . "\n";

if ($export_enabled && $hacp_enabled) {
    echo "\n✓ Both plugins are now enabled!\n";
} else {
    echo "\n✗ Some plugins are not enabled. Please check the output above.\n";
}

echo "\n4. Purging caches...\n";
purge_all_caches();
echo "   ✓ Caches purged\n";

echo "\n" . str_repeat('=', 70) . "\n";
echo "Setup Complete!\n";
echo "\nNext steps:\n";
echo "1. Run tests: php local/run_all_tests.php\n";
echo "2. Export a course and try importing on LMS-2\n";
echo "3. Access the SCORM activity as a student\n";
echo str_repeat('=', 70) . "\n\n";
