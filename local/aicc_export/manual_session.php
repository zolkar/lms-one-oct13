<?php

require_once(__DIR__ . '/../../config.php');

// Manual session creator for testing
require_login();
require_capability('moodle/site:config', \context_system::instance());

echo "<h2>Manual Session Creator</h2>";

$session_id = 'HACP221760418235';

// Find the first SCORM activity
$scorm = $DB->get_record('scorm', [], 'id,course', 'id,course');
if (!$scorm) {
    echo "<p style='color: red;'>No SCORM activities found!</p>";
    exit;
}

echo "<p>Using SCORM activity: {$scorm->id} in course: {$scorm->course}</p>";

// Get the first SCO for this SCORM activity
$scoes = $DB->get_records('scorm_scoes', ['scorm' => $scorm->id], 'id', 'id', 0, 1);
if (empty($scoes)) {
    echo "<p style='color: red;'>No SCO found for SCORM activity!</p>";
    exit;
}
$sco = reset($scoes); // Get the first (and only) record
echo "<p>Using SCO: {$sco->id}</p>";

// Function to get or create a dedicated HACP user
function get_or_create_hacp_user() {
    global $DB;
    
    // Look for existing HACP user
    $hacp_user = $DB->get_record('user', ['username' => 'hacp_user'], 'id');
    if ($hacp_user) {
        return $hacp_user->id;
    }
    
    // Create new HACP user
    $user = new \stdClass();
    $user->username = 'hacp_user';
    $user->firstname = 'HACP';
    $user->lastname = 'User';
    $user->email = 'hacp@localhost';
    $user->confirmed = 1;
    $user->mnethostid = 1;
    $user->timecreated = time();
    $user->timemodified = time();
    
    $userid = $DB->insert_record('user', $user);
    return $userid;
}

// Create AICC session record
$aicc_session = new \stdClass();
$aicc_session->hacpsession = $session_id;
$aicc_session->scormid = $scorm->id;
$aicc_session->scoid = $sco->id; // Add SCO ID - this is required!
$aicc_session->userid = get_or_create_hacp_user();
$aicc_session->timecreated = time();
$aicc_session->timemodified = time();

// Check if session already exists
$existing = $DB->get_record('scorm_aicc_session', ['hacpsession' => $session_id]);
if ($existing) {
    $aicc_session->id = $existing->id;
    $aicc_session->timemodified = time();
    $DB->update_record('scorm_aicc_session', $aicc_session);
    echo "<p style='color: blue;'>Updated existing session: {$session_id}</p>";
} else {
    $DB->insert_record('scorm_aicc_session', $aicc_session);
    echo "<p style='color: green;'>Created new session: {$session_id}</p>";
}

// Test the session
echo "<p><strong>Testing session validation:</strong></p>";
$cfgscorm = get_config('scorm');
$time = time() - (($cfgscorm->aicchacptimeout ?? 60) * 60);
$sql = "hacpsession = ? AND timemodified > ?";
$result = $DB->get_record_select('scorm_aicc_session', $sql, array($session_id, $time));

if ($result) {
    echo "<p style='color: green;'>✅ Session is now valid!</p>";
    echo "<pre>" . print_r($result, true) . "</pre>";
} else {
    echo "<p style='color: red;'>❌ Session still not valid.</p>";
}

echo "<p><strong>Test URL:</strong></p>";
$test_url = new \moodle_url('/mod/scorm/aicc.php', [
    'command' => 'getparam',
    'session_id' => $session_id
]);
echo "<p><a href='{$test_url}' target='_blank'>Test AICC Handler</a></p>";

echo "<p><a href='" . $CFG->wwwroot . "'>Return to site</a></p>";
