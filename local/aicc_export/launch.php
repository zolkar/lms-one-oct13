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

// For exported AICC packages, we redirect directly to the SCORM player
// The AICC package should contain all necessary content files
$scorm_url = new moodle_url('/mod/scorm/player.php', ['id' => $cm->id]);
redirect($scorm_url);
