<?php

require_once(__DIR__ . '/../../config.php');

// HACP handler that creates sessions dynamically and redirects to AICC handler
// This handles the session creation that external LMS systems need

$courseid = required_param('courseid', PARAM_INT);
$activityid = required_param('activityid', PARAM_INT);
$command = optional_param('command', 'getparam', PARAM_ALPHA);
$session_id = optional_param('session_id', '', PARAM_ALPHANUM);

// Get the course and activity
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$cm = get_coursemodule_from_id('', $activityid, 0, false, MUST_EXIST);

// Verify it's a SCORM activity
if ($cm->modname !== 'scorm') {
    print_error('error_non_scorm_hacp', 'local_aicc_export');
}

$scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);

// If no session ID provided, generate one
if (empty($session_id)) {
    $session_id = 'HACP' . time() . rand(1000, 9999);
}

// Create or update AICC session record for HACP communication
$aicc_session = new \stdClass();
$aicc_session->hacpsession = $session_id;
$aicc_session->scormid = $scorm->id;
$aicc_session->userid = 0; // External user - will be set by HACP
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
