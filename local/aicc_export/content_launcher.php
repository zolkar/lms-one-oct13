<?php

require_once(__DIR__ . '/../../config.php');

// AICC content launcher for external LMS systems
// This launches SCORM content for external students via AICC HACP

// Get parameters
$cmid = required_param('id', PARAM_INT);
$student_id = optional_param('AICC_SID', '', PARAM_ALPHANUMEXT);
if (empty($student_id)) {
    $student_id = optional_param('student_id', '', PARAM_ALPHANUMEXT);
}

// Get course module and SCORM info
$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
if ($cm->modname !== 'scorm') {
    throw new \moodle_exception('error_non_scorm_hacp', 'local_aicc_export');
}

$scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

// Enable AICC HACP for this SCORM activity if not already enabled
if (!$scorm->allowaicchacp) {
    $scorm->allowaicchacp = 1;
    $DB->update_record('scorm', $scorm);
}

// Get the first SCO for this SCORM activity
$scoes = $DB->get_records('scorm_scoes', ['scorm' => $scorm->id], 'id', 'id', 0, 1);
if (empty($scoes)) {
    throw new \moodle_exception('error_no_sco', 'local_aicc_export');
}
$sco = reset($scoes);

// Generate a student ID if not provided
if (empty($student_id)) {
    $student_id = 'external_student_' . time() . '_' . rand(1000, 9999);
}

// Try to extract name and email from AICC parameters
$student_name = optional_param('student_name', '', PARAM_TEXT);
$student_email = optional_param('student_email', '', PARAM_EMAIL);

// Create persistent session for this external student
require_once(__DIR__ . '/../aicc_hacp/classes/session_persistence.php');
$persistent_session = \local_aicc_hacp\session_persistence::get_persistent_session(
    $student_id, 
    $scorm->id, 
    $sco->id, 
    $_SERVER['HTTP_REFERER'] ?? ''
);

// Update the persistent session with name and email
if ($student_name || $student_email) {
    $persistent_session->student_name = $student_name ?: ($persistent_session->student_name ?? 'External Student');
    $persistent_session->student_email = $student_email ?: ($persistent_session->student_email ?? '');
    $DB->update_record('local_aicc_hacp_persistent_sessions', $persistent_session);
}

// Create HACP session
$hacp_session_id = \local_aicc_hacp\session_persistence::create_hacp_session(
    $student_id, 
    $scorm->id, 
    $sco->id, 
    $_SERVER['HTTP_REFERER'] ?? ''
);

// Build the launch URL - use Moodle's built-in SCORM player
$launch_url = new moodle_url('/mod/scorm/view.php', ['id' => $cmid, 'aiccsession' => $hacp_session_id]);
redirect($launch_url);