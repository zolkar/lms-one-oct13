<?php
// Script to enable AICC HACP in SCORM settings
// Run this once to enable HACP communication

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

// Check if we have admin access
require_login();
require_capability('moodle/site:config', context_system::instance());

echo "<h2>Enabling AICC HACP Support</h2>";

// Get current SCORM config
$cfgscorm = get_config('scorm');

echo "<p>Current allowaicchacp setting: " . ($cfgscorm->allowaicchacp ? 'Enabled' : 'Disabled') . "</p>";

if (empty($cfgscorm->allowaicchacp)) {
    // Enable AICC HACP
    set_config('allowaicchacp', 1, 'scorm');
    echo "<p style='color: green;'>✅ AICC HACP has been enabled!</p>";
} else {
    echo "<p style='color: blue;'>ℹ️ AICC HACP is already enabled.</p>";
}

// Check timeout setting
$timeout = $cfgscorm->aicchacptimeout ?? 60;
echo "<p>Current HACP timeout: {$timeout} minutes</p>";

if ($timeout < 120) {
    set_config('aicchacptimeout', 120, 'scorm');
    echo "<p style='color: green;'>✅ HACP timeout increased to 120 minutes!</p>";
}

echo "<p><strong>Next steps:</strong></p>";
echo "<ul>";
echo "<li>Export your course as AICC package</li>";
echo "<li>Import the package into external LMS</li>";
echo "<li>Test HACP communication</li>";
echo "</ul>";

echo "<p><a href='" . $CFG->wwwroot . "'>Return to site</a></p>";
