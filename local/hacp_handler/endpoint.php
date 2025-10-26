<?php
require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/lib.php');

$command = required_param('command', PARAM_TEXT);
$sessionid = required_param('session_id', PARAM_ALPHANUM); // This is the AICC_SID
$aicc_data = optional_param('aicc_data', '', PARAM_RAW);

// The AICC standard specifies that the course ID is sent in the [Course] block
// of the AICC data. However, for simplicity, we'll pass it as a URL parameter.
$courseid = required_param('course_id', PARAM_INT);
$token = required_param('token', PARAM_TEXT);
$remote_user_id = required_param('student_id', PARAM_TEXT); // This is the AICC_Student_ID

if (!local_hacp_handler_validate_token($token, $courseid)) {
    die(get_string('invalid_token', 'local_hacp_handler'));
}

switch (strtoupper($command)) {
    case 'GETPARAM':
        $response = local_hacp_handler_get_param($remote_user_id, $courseid);
        break;
    case 'PUTPARAM':
        $response = local_hacp_handler_put_param($remote_user_id, $courseid, $aicc_data);
        break;
    case 'EXITAU':
        $response = local_hacp_handler_exit_au($remote_user_id, $courseid);
        break;
    default:
        $response = "error=1\nerror_text=Invalid command\n";
}

echo $response;
