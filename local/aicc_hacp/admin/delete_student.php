<?php
require_once('../../../config.php');
require_once(__DIR__ . '/../lib.php');

require_login();

// Get parameters
$courseid = required_param('courseid', PARAM_INT);
$scormid = required_param('scormid', PARAM_INT); // This is the SCORM instance ID
$student_id = required_param('student_id', PARAM_TEXT);
$returnurl = optional_param('returnurl', '', PARAM_URL);

// Get course and context
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
require_login($course);

$context = context_course::instance($courseid);
require_capability('moodle/course:view', $context);

// Delete student data
try {
    // Delete all records for this student
    $DB->delete_records('local_aicc_hacp_sessions', ['student_id' => $student_id]);
    $DB->delete_records('local_aicc_hacp_student_state', ['student_id' => $student_id]);
    $DB->delete_records('local_aicc_hacp_persistent_sessions', ['student_id' => $student_id]);
    
    // Delete user mapping
    $DB->delete_records('local_aicc_hacp_usermap', ['external_id' => $student_id]);
    
    // Log
    local_aicc_hacp_log(0, "Student deleted by admin: {$student_id}", '', 'DELETE', '');
    
    // Redirect
    if (!empty($returnurl)) {
        redirect(new moodle_url($returnurl), get_string('student_deleted', 'local_aicc_hacp'), null, \core\output\notification::NOTIFY_SUCCESS);
    } else {
        redirect(new moodle_url('/local/aicc_hacp/admin/student_details.php', [
            'courseid' => $courseid,
            'scormid' => $scormid
        ]), get_string('student_deleted', 'local_aicc_hacp'), null, \core\output\notification::NOTIFY_SUCCESS);
    }
} catch (Exception $e) {
    error_log("Error deleting student {$student_id}: " . $e->getMessage());
    error_log("Stack trace: " . $e->getTraceAsString());
    local_aicc_hacp_log(1, "Error deleting student: " . $e->getMessage(), '', 'DELETE', '');
    
    if (!empty($returnurl)) {
        redirect(new moodle_url($returnurl), get_string('error_deleting_student', 'local_aicc_hacp') . ': ' . $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    } else {
        redirect(new moodle_url('/local/aicc_hacp/admin/student_details.php', [
            'courseid' => $courseid,
            'scormid' => $scormid
        ]), get_string('error_deleting_student', 'local_aicc_hacp') . ': ' . $e->getMessage(), null, \core\output\notification::NOTIFY_ERROR);
    }
}
