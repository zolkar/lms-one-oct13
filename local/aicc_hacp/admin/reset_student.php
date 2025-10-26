<?php

require_once(__DIR__ . '/../../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$scormid = required_param('scormid', PARAM_INT);
$student_id = required_param('student_id', PARAM_ALPHANUMEXT);

require_login();

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$scorm = $DB->get_record('scorm', ['id' => $scormid], '*', MUST_EXIST);

$context = context_course::instance($course->id);
require_capability('moodle/course:manageactivities', $context);

// Confirm the action
$confirm = optional_param('confirm', 0, PARAM_INT);

if (!$confirm) {
    $PAGE->set_context($context);
    $PAGE->set_url('/local/aicc_hacp/admin/reset_student.php', [
        'courseid' => $courseid,
        'scormid' => $scormid,
        'student_id' => $student_id
    ]);
    $PAGE->set_title(get_string('reset_progress', 'local_aicc_hacp'));
    $PAGE->set_heading(get_string('reset_progress', 'local_aicc_hacp'));
    
    echo $OUTPUT->header();
    
    echo $OUTPUT->confirm(
        get_string('confirm_reset_progress', 'local_aicc_hacp') . '<br><br>' .
        get_string('student_id', 'local_aicc_hacp') . ': ' . $student_id . '<br>' .
        get_string('course', 'local_aicc_hacp') . ': ' . $course->shortname . '<br>' .
        get_string('scorm_activity', 'local_aicc_hacp') . ': ' . $scorm->name,
        new moodle_url('/local/aicc_hacp/admin/reset_student.php', [
            'courseid' => $courseid,
            'scormid' => $scormid,
            'student_id' => $student_id,
            'confirm' => 1
        ]),
        new moodle_url('/local/aicc_hacp/admin/student_details.php', [
            'courseid' => $courseid,
            'scormid' => $scormid
        ])
    );
    
    echo $OUTPUT->footer();
    exit;
}

// Perform the reset
try {
    // Delete student state
    $DB->delete_records('local_aicc_hacp_student_state', [
        'student_id' => $student_id,
        'scormid' => $scormid
    ]);
    
    // Delete persistent session
    $DB->delete_records('local_aicc_hacp_persistent_sessions', [
        'student_id' => $student_id,
        'scormid' => $scormid
    ]);
    
    // Delete all HACP sessions for this student
    $DB->delete_records('local_aicc_hacp_sessions', [
        'student_id' => $student_id,
        'scormid' => $scormid
    ]);
    
    // Delete SCORM tracking data for external user
    $external_user = $DB->get_record('user', ['idnumber' => $student_id, 'deleted' => 0]);
    if ($external_user) {
        $scoes = $DB->get_records('scorm_scoes', ['scorm' => $scormid]);
        foreach ($scoes as $sco) {
            $DB->delete_records('scorm_scoes_track', [
                'userid' => $external_user->id,
                'scoid' => $sco->id
            ]);
        }
    }
    
    // Log the reset action
    local_aicc_hacp_log(0, 'Student progress reset', '', 'RESET_PROGRESS', '', 1, [
        'student_id' => $student_id,
        'scormid' => $scormid,
        'courseid' => $courseid
    ]);
    
    redirect(
        new moodle_url('/local/aicc_hacp/admin/student_details.php', [
            'courseid' => $courseid,
            'scormid' => $scormid
        ]),
        get_string('progress_reset_success', 'local_aicc_hacp'),
        null,
        \core\output\notification::NOTIFY_SUCCESS
    );
    
} catch (Exception $e) {
    redirect(
        new moodle_url('/local/aicc_hacp/admin/student_details.php', [
            'courseid' => $courseid,
            'scormid' => $scormid
        ]),
        get_string('progress_reset_error', 'local_aicc_hacp') . ': ' . $e->getMessage(),
        null,
        \core\output\notification::NOTIFY_ERROR
    );
}
