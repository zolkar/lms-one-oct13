<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/launcher.php');

$token = required_param('token', PARAM_RAW);

$payload = \local_aicc_export\launcher::validate_token($token);

if (!$payload) {
    print_error('error_invalid_token', 'local_aicc_export');
}

$course = $DB->get_record('course', ['id' => $payload['courseid']], '*', MUST_EXIST);
$scorm = $DB->get_record('scorm', ['id' => $payload['scormid'], 'course' => $payload['courseid']], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('scorm', $scorm->id, $course->id, false, MUST_EXIST);

require_login($course, true, $cm);

$session = new \stdClass();
$session->session_id = \core\uuid::generate();
$session->token_nonce = $payload['nonce'];
$session->scormid = $scorm->id;
$session->scoid = $payload['scoid'];
$session->courseid = $course->id;
$session->au = $payload['au'];
$session->status = 'active';
$session->created_at = $payload['issued_at'];
$session->expires_at = $payload['expires_at'];
$session->last_activity_at = time();
$session->origin = $_SERVER['HTTP_REFERER'] ?? '';

$DB->insert_record('local_aicc_export_sessions', $session);

$scorm_url = new moodle_url('/mod/scorm/player.php', ['id' => $cm->id, 'remote_session' => $session->session_id]);
redirect($scorm_url);
