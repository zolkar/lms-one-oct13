<?php

require_once(__DIR__ . '/../../../config.php');

$courseid = optional_param('courseid', 0, PARAM_INT);

if ($courseid) {
    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $context = context_course::instance($course->id);
    require_login($course);
    require_capability('moodle/course:view', $context);
    
    $PAGE->set_context($context);
    $PAGE->set_url('/local/aicc_hacp/admin/external_progress.php', ['courseid' => $courseid]);
    $PAGE->set_title('External Students Progress');
    $PAGE->set_heading('External Students Progress');
    
    // Add to course navigation
    $PAGE->navbar->add(get_string('external_progress_report', 'local_aicc_hacp'));
} else {
    require_login();
    require_capability('moodle/site:config', context_system::instance());
    
    $PAGE->set_context(context_system::instance());
    $PAGE->set_url('/local/aicc_hacp/admin/external_progress.php');
    $PAGE->set_title('External Students Progress');
    $PAGE->set_heading('External Students Progress');
}

echo $OUTPUT->header();

// Get courses with SCORM activities that have external sessions
$sql = "
    SELECT DISTINCT c.id, c.shortname, c.fullname, s.id as scormid, s.name as scormname,
           cm.id as cmid,
           COUNT(DISTINCT hs.student_id) as external_students,
           COUNT(DISTINCT CASE WHEN hs.status = 'active' THEN hs.student_id END) as active_students
    FROM {course} c
    JOIN {scorm} s ON s.course = c.id
    JOIN {course_modules} cm ON cm.instance = s.id AND cm.module = (
        SELECT id FROM {modules} WHERE name = 'scorm' LIMIT 1
    )
    LEFT JOIN {local_aicc_hacp_sessions} hs ON hs.scormid = s.id
    GROUP BY c.id, c.shortname, c.fullname, s.id, s.name, cm.id
    HAVING external_students > 0
    ORDER BY c.shortname, s.name
";

if ($courseid) {
    $sql = str_replace('GROUP BY', 'WHERE c.id = ? GROUP BY', $sql);
    $courses = $DB->get_records_sql($sql, [$courseid]);
} else {
    $courses = $DB->get_records_sql($sql);
}

if (empty($courses)) {
    echo $OUTPUT->notification('No external students found', 'info');
    
    // Show SCORM activities that could have external students
    echo html_writer::tag('h3', 'SCORM Activities Available for External Access');
    $scorm_sql = "
        SELECT c.id, c.shortname, c.fullname, s.id as scormid, s.name as scormname, cm.id as cmid
        FROM {course} c
        JOIN {scorm} s ON s.course = c.id
        JOIN {course_modules} cm ON cm.instance = s.id AND cm.module = (
            SELECT id FROM {modules} WHERE name = 'scorm' LIMIT 1
        )
        ORDER BY c.shortname, s.name
    ";
    
    if ($courseid) {
        $scorm_sql = str_replace('ORDER BY', 'WHERE c.id = ? ORDER BY', $scorm_sql);
        $scorm_activities = $DB->get_records_sql($scorm_sql, [$courseid]);
    } else {
        $scorm_activities = $DB->get_records_sql($scorm_sql);
    }
    
    if (!empty($scorm_activities)) {
        $table = new html_table();
        $table->head = [
            'Course',
            'SCORM Activity',
            'Status'
        ];
        
        foreach ($scorm_activities as $activity) {
            $course_url = new moodle_url('/course/view.php', ['id' => $activity->id]);
            $scorm_url = new moodle_url('/mod/scorm/view.php', ['id' => $activity->cmid]);
            
            $table->data[] = [
                html_writer::link($course_url, $activity->shortname),
                html_writer::link($scorm_url, $activity->scormname),
                html_writer::span('Ready for external access', 'badge badge-info')
            ];
        }
        
        echo html_writer::table($table);
    }
    
    echo $OUTPUT->footer();
    exit;
}

// Course selector (only show if not filtering by course)
if (!$courseid) {
    $course_options = [0 => 'All Courses'];
    foreach ($courses as $course) {
        $course_options[$course->id] = $course->shortname . ' - ' . $course->fullname;
    }

    $course_select = new single_select(
        new moodle_url('/local/aicc_hacp/admin/external_progress.php'),
        'courseid',
        $course_options,
        $courseid
    );

    echo $OUTPUT->render($course_select);
}

// Display courses table
$table = new html_table();
$table->head = [
    'Course',
    'SCORM Activity', 
    'External Students',
    'Active Sessions',
    'Actions'
];

foreach ($courses as $course) {
    $course_url = new moodle_url('/course/view.php', ['id' => $course->id]);
    $scorm_url = new moodle_url('/mod/scorm/view.php', ['id' => $course->cmid]);
    $details_url = new moodle_url('/local/aicc_hacp/admin/student_details.php', [
        'courseid' => $course->id,
        'scormid' => $course->scormid
    ]);
    
    $table->data[] = [
        html_writer::link($course_url, $course->shortname),
        html_writer::link($scorm_url, $course->scormname),
        $course->external_students,
        $course->active_students,
        html_writer::link($details_url, 'View Details', [
            'class' => 'btn btn-primary btn-sm'
        ])
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();