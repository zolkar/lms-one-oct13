<?php

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../lib.php');

$courseid = required_param('courseid', PARAM_INT);
$scormid = required_param('scormid', PARAM_INT);

require_login();

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$scorm = $DB->get_record('scorm', ['id' => $scormid], '*', MUST_EXIST);

// Get the course module for breadcrumb
$cm = $DB->get_record('course_modules', ['instance' => $scormid, 'course' => $courseid], '*', MUST_EXIST);
$actual_cmid = $cm->id;

$context = context_course::instance($course->id);
require_capability('moodle/course:view', $context);

$PAGE->set_context($context);
$PAGE->set_url('/local/aicc_hacp/admin/student_details.php', ['courseid' => $courseid, 'scormid' => $scormid]);
$PAGE->set_title(get_string('student_details', 'local_aicc_hacp'));
$PAGE->set_heading(get_string('student_details', 'local_aicc_hacp'));

// Breadcrumb
$PAGE->navbar->add($course->shortname, new moodle_url('/course/view.php', ['id' => $courseid]));
$PAGE->navbar->add($scorm->name, new moodle_url('/mod/scorm/view.php', ['id' => $actual_cmid]));
$PAGE->navbar->add(get_string('external_students', 'local_aicc_hacp'));

echo $OUTPUT->header();

// Get external students and their progress
$sql = "
    SELECT DISTINCT 
        hs.student_id,
        hs.origin,
        hs.status as session_status,
        hs.created_at as session_created,
        hs.last_activity_at,
        ss.lesson_status,
        ss.lesson_location,
        ss.score,
        ss.session_time,
        ss.updated_at as last_progress_update,
        ps.student_name,
        ps.student_email
    FROM {local_aicc_hacp_sessions} hs
    LEFT JOIN {local_aicc_hacp_student_state} ss ON (
        ss.student_id COLLATE utf8mb4_unicode_ci = hs.student_id AND 
        ss.scormid = hs.scormid AND 
        ss.scoid = hs.scoid
    )
    LEFT JOIN {local_aicc_hacp_persistent_sessions} ps ON ps.student_id COLLATE utf8mb4_unicode_ci = hs.student_id
    WHERE hs.scormid = ? AND hs.scoid = ?
    ORDER BY hs.last_activity_at DESC
";

$scoes = $DB->get_records('scorm_scoes', ['scorm' => $scormid], 'id', 'id', 0, 1);
if (empty($scoes)) {
    echo $OUTPUT->notification(get_string('no_scoes', 'local_aicc_hacp'), 'error');
    echo $OUTPUT->footer();
    exit;
}

$sco = reset($scoes);
$students = $DB->get_records_sql($sql, [$scormid, $sco->id]);

if (empty($students)) {
    echo $OUTPUT->notification(get_string('no_external_students', 'local_aicc_hacp'), 'info');
    echo $OUTPUT->footer();
    exit;
}

// Display students table
$table = new html_table();
$table->head = [
    'Student Name',
    'Email',
    'Student ID',
    'Origin LMS',
    'Lesson Status',
    'Score',
    'Session Time',
    'Last Activity',
    'Session Status',
    'Actions'
];

foreach ($students as $student) {
    $last_activity = $student->last_activity_at ? userdate($student->last_activity_at) : '-';
    $session_created = $student->session_created ? userdate($student->session_created) : '-';
    
    // Status badge
    $status_class = $student->session_status === 'active' ? 'badge-success' : 'badge-secondary';
    $status_badge = html_writer::span($student->session_status, "badge {$status_class}");
    
    // Lesson status badge
    $lesson_status_class = 'badge-secondary';
    if ($student->lesson_status === 'completed') {
        $lesson_status_class = 'badge-success';
    } elseif ($student->lesson_status === 'incomplete') {
        $lesson_status_class = 'badge-warning';
    } elseif ($student->lesson_status === 'passed') {
        $lesson_status_class = 'badge-success';
    } elseif ($student->lesson_status === 'failed') {
        $lesson_status_class = 'badge-danger';
    }
    $lesson_status_badge = html_writer::span($student->lesson_status ?: 'not attempted', "badge {$lesson_status_class}");
    
    // Actions
    $reset_url = new moodle_url('/local/aicc_hacp/admin/reset_student.php', [
        'courseid' => $courseid,
        'scormid' => $scormid,
        'student_id' => $student->student_id
    ]);
    $reset_link = html_writer::link($reset_url, get_string('reset_progress', 'local_aicc_hacp'), [
        'class' => 'btn btn-warning btn-sm',
        'onclick' => 'return confirm("' . get_string('confirm_reset_progress', 'local_aicc_hacp') . '")'
    ]);
    
    $delete_url = new moodle_url('/local/aicc_hacp/admin/delete_student.php', [
        'courseid' => $courseid,
        'scormid' => $scormid,
        'student_id' => $student->student_id,
        'returnurl' => $PAGE->url->out(false)
    ]);
    $delete_link = html_writer::link($delete_url, get_string('delete', 'local_aicc_hacp'), [
        'class' => 'btn btn-danger btn-sm',
        'onclick' => 'return confirm("' . get_string('confirm_delete_student', 'local_aicc_hacp') . '")'
    ]);
    
    $actions = $reset_link . ' ' . $delete_link;
    
    // Extract domain name and base path from origin URL
    $origin_display = '-';
    if (!empty($student->origin)) {
        $parsed = parse_url($student->origin);
        if (isset($parsed['host'])) {
            $origin_display = $parsed['scheme'] . '://' . $parsed['host'];
            if (isset($parsed['port'])) {
                $origin_display .= ':' . $parsed['port'];
            }
            // Add the base path (e.g., /lms-two) but skip the query string
            if (isset($parsed['scheme'], $parsed['host'], $parsed['path'])) {
                // Split path by /
                $pathparts = explode('/', trim($parsed['path'], '/'));
                // Grab the first part for the "base" (if exists)
                $base = !empty($pathparts) ? '/' . $pathparts[0] : '';
                $origin_display = $parsed['scheme'] . '://' . $parsed['host'];
                if (isset($parsed['port'])) {
                    $origin_display .= ':' . $parsed['port'];
                }
                $origin_display .= $base;
            }
        } else {
            $origin_display = $student->origin;
        }
    }
    
    $table->data[] = [
        $student->student_name ?: 'External Student',
        $student->student_email ?: '-',
        $student->student_id,
        $origin_display,
        $lesson_status_badge,
        $student->score ?: '-',
        $student->session_time ?: '-',
        $last_activity,
        $status_badge,
        $actions
    ];
}

echo html_writer::table($table);

// Summary statistics
$total_students = count($students);
$active_sessions = count(array_filter($students, function($s) { return $s->session_status === 'active'; }));
$completed_students = count(array_filter($students, function($s) { return $s->lesson_status === 'completed'; }));
$passed_students = count(array_filter($students, function($s) { return $s->lesson_status === 'passed'; }));

echo html_writer::div(
    html_writer::tag('h3', get_string('summary_statistics', 'local_aicc_hacp')) .
    html_writer::tag('p', get_string('total_external_students', 'local_aicc_hacp') . ': ' . $total_students) .
    html_writer::tag('p', get_string('active_sessions', 'local_aicc_hacp') . ': ' . $active_sessions) .
    html_writer::tag('p', get_string('completed_students', 'local_aicc_hacp') . ': ' . $completed_students) .
    html_writer::tag('p', get_string('passed_students', 'local_aicc_hacp') . ': ' . $passed_students),
    'card'
);

echo $OUTPUT->footer();
