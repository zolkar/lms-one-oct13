<?php

require_once(__DIR__ . '/../../config.php');

// Script to disable HTTPS requirement for AICC HACP
require_login();
require_capability('moodle/site:config', \context_system::instance());

echo "<h2>Disable HTTPS Requirement for AICC HACP</h2>";

// Check current setting
$current_setting = get_config('local_aicc_hacp', 'require_https');
echo "<p>Current require_https setting: " . ($current_setting ? 'Enabled' : 'Disabled') . "</p>";

if ($current_setting) {
    // Disable HTTPS requirement
    set_config('require_https', 0, 'local_aicc_hacp');
    echo "<p style='color: green;'>✅ HTTPS requirement has been disabled!</p>";
    echo "<p>External LMS systems can now access the HACP endpoint via HTTP.</p>";
} else {
    echo "<p style='color: blue;'>ℹ️ HTTPS requirement is already disabled.</p>";
}

// Also check if HACP is enabled
$hacp_enabled = get_config('local_aicc_hacp', 'enabled');
echo "<p>HACP enabled: " . ($hacp_enabled ? 'Yes' : 'No') . "</p>";

if (!$hacp_enabled) {
    set_config('enabled', 1, 'local_aicc_hacp');
    echo "<p style='color: green;'>✅ HACP has been enabled!</p>";
}

echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li>Export your course as AICC package</li>";
echo "<li>Import the package into external LMS</li>";
echo "<li>Test HACP communication (should work with HTTP now)</li>";
echo "</ul>";

echo "<p><a href='" . $CFG->wwwroot . "'>Return to site</a></p>";
