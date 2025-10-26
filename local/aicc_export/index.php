<?php

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);
require_capability('local/aicc_export:export', context_course::instance($course->id));

$PAGE->set_url(new moodle_url('/local/aicc_export/index.php', ['courseid' => $course->id]));
$PAGE->set_title('Export AICC Package');
$PAGE->set_heading($course->fullname);
$PAGE->set_context(context_course::instance($course->id));

echo $OUTPUT->header();

echo '<h2>Export AICC Package</h2>';
echo '<div style="margin-bottom:1em;color:#666;font-size:0.95em;">'
    . 'This will export the course as an AICC package containing descriptor files with URLs pointing back to this Moodle site. The content remains hosted here and is accessed via HACP (HTTP AICC Communication Protocol).'
    . '</div>';

// Check plugin enabled
if (!get_config('local_aicc_export', 'enabled')) {
    echo $OUTPUT->notification('AICC Export plugin is not enabled. Please enable it in Site administration → Plugins → Local plugins → AICC Export.', 'error');
    echo $OUTPUT->footer();
    exit;
}

// Check for SCORM activities
$scorm_sql = "
    SELECT cm.id, cm.instance, s.name, s.id as scormid
    FROM {course_modules} cm
    JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
    JOIN {scorm} s ON s.id = cm.instance
    WHERE cm.course = ? AND cm.deletioninprogress = 0
    ORDER BY cm.section, cm.id
";

try {
    $scorm_activities = $DB->get_records_sql($scorm_sql, [$course->id]);
    
    if (empty($scorm_activities)) {
        echo $OUTPUT->notification('No SCORM activities found in this course. Please add a SCORM activity before exporting.', 'warning');
        echo '<p><a href="' . new moodle_url('/course/view.php', ['id' => $course->id]) . '" class="btn btn-secondary">Back to Course</a></p>';
    } else {
        echo '<div class="alert alert-info">Found ' . count($scorm_activities) . ' SCORM activity(ies) in this course:</div>';
        
        echo '<ul>';
        foreach ($scorm_activities as $activity) {
            echo '<li>' . $activity->name . ' (ID: ' . $activity->scormid . ')</li>';
        }
        echo '</ul>';
        
        echo '<p class="mt-3">Click the button below to export this course as an AICC package:</p>';
        echo '<form action="export.php" method="get" style="margin-top:1em;">';
        echo '<input type="hidden" name="courseid" value="' . $course->id . '">';
        echo '<button type="submit" class="btn btn-primary btn-lg"><i class="fa fa-download"></i> Export Course as AICC Package</button>';
        echo '</form>';
    }
} catch (Exception $e) {
    echo $OUTPUT->notification('Error: ' . $e->getMessage(), 'error');
    error_log('AICC Export error: ' . $e->getMessage());
    
    echo '<div class="alert alert-danger mt-3">';
    echo '<h4>Troubleshooting:</h4>';
    echo '<ol>';
    echo '<li>Check if SCORM module is installed: <code>Site administration → Plugins → Activity modules</code></li>';
    echo '<li>Verify database integrity: <code>Site administration → Reports → Maintenance mode → Database checks</code></li>';
    echo '<li>Check error logs for more details</li>';
    echo '</ol>';
    echo '</div>';
    
    echo '<p><a href="' . new moodle_url('/course/view.php', ['id' => $course->id]) . '" class="btn btn-secondary">Back to Course</a></p>';
}

echo $OUTPUT->footer();
