<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/token.php');
require_once($CFG->dirroot . '/local/aicc_hacp/classes/user_mapper.php');

$token = required_param('token', PARAM_RAW);
$aicc_sid = required_param('AICC_SID', PARAM_TEXT);
$aicc_url = required_param('AICC_URL', PARAM_URL);

$payload = \local_aicc_export\token::validate($token);
if (!$payload) {
    print_error('invalidtoken', 'local_aicc_export');
}

$courseid = $payload['courseid'];
$scormid = $payload['scormid'];
$scoid = $payload['scoid'];

$user = \local_aicc_hacp\user_mapper::get_or_create_user($aicc_sid, $courseid);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$scorm = $DB->get_record('scorm', ['id' => $scormid, 'course' => $courseid], '*', MUST_EXIST);
$cm = get_coursemodule_from_instance('scorm', $scorm->id, $course->id, false, MUST_EXIST);

$session = new \stdClass();
$session->aicc_sid = $aicc_sid;
$session->userid = $user->id;
$session->courseid = $courseid;
$session->scormid = $scormid;
$session->scoid = $scoid;
$session->timecreated = time();
$session->timemodified = time();
$DB->insert_record('local_aicc_hacp_sessions', $session);

complete_user_login($user);
\core\session\manager::set_user($user);

$scorm_url = new moodle_url('/mod/scorm/player.php', [
    'id' => $cm->id,
    'aicc_sid' => $aicc_sid,
    'aicc_url' => $aicc_url,
]);
redirect($scorm_url);
