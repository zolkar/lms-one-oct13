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

// Export the entire course as AICC package
echo '<form action="export.php" method="post">';
echo '<input type="hidden" name="courseid" value="' . $course->id . '">';
echo '<button type="submit" class="btn btn-primary">Export Course as AICC Package</button>';
echo '</form>';

echo $OUTPUT->footer();
