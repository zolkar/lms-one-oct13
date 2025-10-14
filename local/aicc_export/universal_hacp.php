<?php

require_once(__DIR__ . '/../../config.php');

// Universal HACP session handler
// This creates sessions for any session ID that external LMS systems might generate

$session_id = required_param('session_id', PARAM_ALPHANUM);
$command = optional_param('command', 'getparam', PARAM_ALPHA);
$aicc_sid = optional_param('aicc_sid', '', PARAM_ALPHANUM);
$aicc_url = optional_param('aicc_url', '', PARAM_URL);

// Debug information
error_log("HACP Universal Handler called with:");
error_log("session_id: " . $session_id);
error_log("command: " . $command);
error_log("aicc_sid: " . $aicc_sid);
error_log("aicc_url: " . $aicc_url);

// Extract course and activity info from session ID if possible
// Handle different session ID formats
$courseid = null;
$activityid = null;

if (strpos($session_id, 'HACP') === 0) {
    // Try to extract info from session ID
    $parts = explode('_', $session_id);
    if (count($parts) >= 3) {
        $courseid = $parts[1] ?? null;
        $activityid = $parts[2] ?? null;
    }
}

// If we can't extract from session ID, try to find a default SCORM activity
if (!$courseid || !$activityid) {
    // Find the first SCORM activity in the system (fallback)
    $scorm = $DB->get_record('scorm', [], 'id,course', 'id,course');
    if ($scorm) {
        $courseid = $scorm->course;
        $cm = get_coursemodule_from_instance('scorm', $scorm->id, $courseid, false, MUST_EXIST);
        $activityid = $cm->id;
    } else {
        print_error('error_no_scorm_activity', 'local_aicc_export');
    }
}

// Get the course and activity
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$cm = get_coursemodule_from_id('', $activityid, 0, false, MUST_EXIST);

// Verify it's a SCORM activity
if ($cm->modname !== 'scorm') {
    print_error('error_non_scorm_hacp', 'local_aicc_export');
}

$scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);

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

// Get the first SCO for this SCORM activity
$scoes = $DB->get_records('scorm_scoes', ['scorm' => $scorm->id], 'id', 'id', 0, 1);
if (empty($scoes)) {
    print_error('error_no_sco', 'local_aicc_export');
}
$sco = reset($scoes); // Get the first (and only) record

// Create or update AICC session record for HACP communication
$aicc_session = new \stdClass();
$aicc_session->hacpsession = $session_id;
$aicc_session->scormid = $scorm->id;
$aicc_session->scoid = $sco->id; // Add SCO ID - this is required!
$aicc_session->userid = get_or_create_hacp_user(); // Create a dedicated HACP user
$aicc_session->timecreated = time();
$aicc_session->timemodified = time();

// Check if session already exists
$existing = $DB->get_record('scorm_aicc_session', ['hacpsession' => $session_id]);
if ($existing) {
    $aicc_session->id = $existing->id;
    $aicc_session->timemodified = time();
    $DB->update_record('scorm_aicc_session', $aicc_session);
} else {
    $DB->insert_record('scorm_aicc_session', $aicc_session);
}

// Redirect to Moodle's AICC handler with the session
$aicc_url = new \moodle_url('/mod/scorm/aicc.php', [
    'command' => $command,
    'session_id' => $session_id
]);

redirect($aicc_url);
