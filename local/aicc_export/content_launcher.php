<?php

require_once(__DIR__ . '/../../config.php');

// SCORM content launcher for external LMS systems
// This serves the actual SCORM content and sets up HACP communication

$cmid = required_param('id', PARAM_INT);
$session_id = optional_param('session_id', '', PARAM_ALPHANUM);

// Get the course module
$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

// Verify it's a SCORM activity
if ($cm->modname !== 'scorm') {
    print_error('error_non_scorm_hacp', 'local_aicc_export');
}

$scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);

// Create or get HACP user for content access
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

// Set the HACP user as the current user (for content access)
$hacp_user_id = get_or_create_hacp_user();
$USER = $DB->get_record('user', ['id' => $hacp_user_id], '*', MUST_EXIST);

// Set up the course context
$context = \context_course::instance($course->id);

// If this is an AICC HACP request (has session_id), handle it
if (!empty($session_id)) {
    // This is a HACP communication request
    // Redirect to AICC handler for protocol communication
    $aicc_url = new \moodle_url('/mod/scorm/aicc.php', [
        'command' => 'getparam',
        'session_id' => $session_id
    ]);
    redirect($aicc_url);
} else {
    // This is a content launch request
    // Redirect to SCORM content
    $scorm_url = new \moodle_url('/mod/scorm/view.php', [
        'id' => $cmid
    ]);
    redirect($scorm_url);
}
