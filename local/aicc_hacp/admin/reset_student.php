<?php
/**
 * Reset student progress
 */

require_once(__DIR__ . '/../../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$scormid = required_param('scormid', PARAM_INT);
$student_id = required_param('student_id', PARAM_RAW);
$confirm = optional_param('confirm', 0, PARAM_INT);

require_login();

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$scorm = $DB->get_record('scorm', ['id' => $scormid], '*', MUST_EXIST);

$context = context_course::instance($course->id);
require_capability('moodle/course:view', $context);

if ($confirm && confirm_sesskey()) {
    // Get SCO
    $scoes = $DB->get_records('scorm_scoes', ['scorm' => $scormid], 'id', 'id', 0, 1);
    if (empty($scoes)) {
        throw new moodle_exception('error_no_sco', 'local_aicc_hacp');
    }
    $sco = reset($scoes);
    
    // Delete student state
    $DB->delete_records('local_aicc_hacp_student_state', [
        'student_id' => $student_id,
        'scormid' => $scormid,
        'scoid' => $sco->id
    ]);
    
    // Close all sessions for this student
    $DB->set_field('local_aicc_hacp_sessions', 'status', 'closed', [
        'student_id' => $student_id,
        'scormid' => $scormid
    ]);
    
    redirect(new moodle_url('/local/aicc_hacp/admin/student_details.php', [
        'courseid' => $courseid,
        'scormid' => $scormid
    ]), get_string('progress_reset_success', 'local_aicc_hacp'));
}

// Show confirmation
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
    get_string('confirm_reset_progress', 'local_aicc_hacp'),
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