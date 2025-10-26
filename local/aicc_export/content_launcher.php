<?php

// Define constants to bypass login requirements for external access
define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../config.php');

// Security: Only allow this endpoint if plugins are properly configured
if (!get_config('local_aicc_export', 'enabled') || !get_config('local_aicc_hacp', 'enabled')) {
    http_response_code(403);
    echo "Error: Service not enabled";
    exit;
}

// AICC content launcher for external LMS systems
// This launches SCORM content for external students via AICC HACP

// Security: Validate token
$token = optional_param('token', '', PARAM_ALPHANUMEXT);
if (empty($token)) {
    http_response_code(401);
    echo "Error: Missing access token";
    exit;
}

require_once($CFG->dirroot . '/local/aicc_hacp/classes/secure_auth.php');
$token_data = \local_aicc_hacp\secure_auth::validate_launch_token($token);
if (!$token_data) {
    http_response_code(401);
    echo "Error: Invalid or expired access token";
    exit;
}

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

// Validate token matches the requested SCORM activity
if (isset($token_data['scormid']) && $token_data['scormid'] > 0) {
    if ($token_data['scormid'] != $scorm->id) {
        http_response_code(403);
        echo "Error: Token does not match requested SCORM activity";
        exit;
    }
}
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

// Validate required student information
if (empty($student_email)) {
    http_response_code(400);
    echo "Error: Student email is required for tracking";
    exit;
}

// Create or get external user account
require_once($CFG->dirroot . '/local/aicc_hacp/classes/session_persistence.php');
$external_user_id = \local_aicc_hacp\session_persistence::create_external_user_account(
    $student_email,
    $student_name ?: 'External Student',
    $token_data['external_lms'] ?? 'Unknown LMS'
);

// Create persistent session for this external student
$persistent_session = \local_aicc_hacp\session_persistence::get_persistent_session(
    $student_id, 
    $scorm->id, 
    $sco->id, 
    $_SERVER['HTTP_REFERER'] ?? ''
);

// Update the persistent session with name and email
$persistent_session->student_name = $student_name ?: 'External Student';
$persistent_session->student_email = $student_email;
$persistent_session->userid = $external_user_id;
$DB->update_record('local_aicc_hacp_persistent_sessions', $persistent_session);

// Create HACP session
$hacp_session_id = \local_aicc_hacp\session_persistence::create_hacp_session(
    $student_id, 
    $scorm->id, 
    $sco->id, 
    $_SERVER['HTTP_REFERER'] ?? ''
);

// Instead of redirecting to Moodle's viewer (which requires login),
// serve the content directly using our secure content server
$secure_url = new moodle_url('/local/aicc_export/secure_content_server.php', [
    'aiccsession' => $hacp_session_id
]);
redirect($secure_url);