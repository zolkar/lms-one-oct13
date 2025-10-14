<?php
// Debug script to check HACP sessions
require_once(__DIR__ . '/../../config.php');

// Check if we have admin access
require_login();
require_capability('moodle/site:config', context_system::instance());

echo "<h2>HACP Session Debug</h2>";

// Check SCORM config
$cfgscorm = get_config('scorm');
echo "<p><strong>SCORM Config:</strong></p>";
echo "<ul>";
echo "<li>allowaicchacp: " . ($cfgscorm->allowaicchacp ? 'Enabled' : 'Disabled') . "</li>";
echo "<li>aicchacptimeout: " . ($cfgscorm->aicchacptimeout ?? 'Not set') . " minutes</li>";
echo "</ul>";

// Check existing sessions
echo "<p><strong>Existing HACP Sessions:</strong></p>";
$sessions = $DB->get_records('scorm_aicc_session', [], 'timemodified DESC', 'id,hacpsession,scormid,userid,timecreated,timemodified', 0, 10);

if (empty($sessions)) {
    echo "<p style='color: red;'>No HACP sessions found in database.</p>";
} else {
    echo "<table border='1' style='border-collapse: collapse;'>";
    echo "<tr><th>ID</th><th>Session ID</th><th>SCORM ID</th><th>User ID</th><th>Created</th><th>Modified</th></tr>";
    foreach ($sessions as $session) {
        echo "<tr>";
        echo "<td>{$session->id}</td>";
        echo "<td>{$session->hacpsession}</td>";
        echo "<td>{$session->scormid}</td>";
        echo "<td>{$session->userid}</td>";
        echo "<td>" . date('Y-m-d H:i:s', $session->timecreated) . "</td>";
        echo "<td>" . date('Y-m-d H:i:s', $session->timemodified) . "</td>";
        echo "</tr>";
    }
    echo "</table>";
}

// Test session validation
echo "<p><strong>Test Session Validation:</strong></p>";
$test_session = 'HACP221760418235';
echo "<p>Testing session: {$test_session}</p>";

$time = time() - (($cfgscorm->aicchacptimeout ?? 60) * 60);
$sql = "hacpsession = ? AND timemodified > ?";
$result = $DB->get_record_select('scorm_aicc_session', $sql, array($test_session, $time));

if ($result) {
    echo "<p style='color: green;'>✅ Session found and valid!</p>";
    echo "<pre>" . print_r($result, true) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ Session not found or expired.</p>";
    echo "<p>Looking for sessions with hacpsession = '{$test_session}' and timemodified > " . date('Y-m-d H:i:s', $time) . "</p>";
}

echo "<p><a href='" . $CFG->wwwroot . "'>Return to site</a></p>";
