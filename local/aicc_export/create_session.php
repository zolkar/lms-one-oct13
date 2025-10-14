<?php

require_once(__DIR__ . '/../../config.php');

// Direct HACP session creator
// This can be called directly by external LMS systems with any session ID

$session_id = required_param('session_id', PARAM_ALPHANUM);
$command = optional_param('command', 'getparam', PARAM_ALPHA);

// Find the first SCORM activity in the system to use as default
$scorm = $DB->get_record('scorm', [], 'id,course', 'id,course');
if (!$scorm) {
    print_error('error_no_scorm_activity', 'local_aicc_export');
}

$courseid = $scorm->course;
$cm = get_coursemodule_from_instance('scorm', $scorm->id, $courseid, false, MUST_EXIST);
$activityid = $cm->id;

// Get the course and activity
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

// Get the first SCO for this SCORM activity
$scoes = $DB->get_records('scorm_scoes', ['scorm' => $scorm->id], 'id', 'id', 0, 1);
if (empty($scoes)) {
    print_error('error_no_sco', 'local_aicc_export');
}
$sco = reset($scoes); // Get the first (and only) record

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

// Create or update AICC session record for HACP communication
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
    echo "Updated existing session: {$session_id}\n";
} else {
    $DB->insert_record('scorm_aicc_session', $aicc_session);
    echo "Created new session: {$session_id}\n";
}

// Redirect to Moodle's AICC handler with the session
$aicc_url = new \moodle_url('/mod/scorm/aicc.php', [
    'command' => $command,
    'session_id' => $session_id
]);

redirect($aicc_url);
