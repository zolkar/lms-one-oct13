<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);
require_capability('local/aicc_export:export', context_course::instance($course->id));

$PAGE->set_url(new moodle_url('/local/aicc_export/index.php', ['courseid' => $course->id]));
$PAGE->set_title(get_string('export_aicc_package', 'local_aicc_export'));
$PAGE->set_heading($course->fullname);
$PAGE->set_context(context_course::instance($course->id));

echo $OUTPUT->header();

$scorms = $DB->get_records('scorm', ['course' => $course->id]);

if (empty($scorms)) {
    echo $OUTPUT->notification(get_string('no_scorms_in_course', 'local_aicc_export'));
    echo $OUTPUT->footer();
    exit;
}


echo '<h2>' . get_string('select_scorm', 'local_aicc_export') . '</h2>';
echo '<div style="margin-bottom:1em;color:#666;font-size:0.95em;">'
    . get_string('export_description', 'local_aicc_export')
    . '</div>';
echo '<form action="export.php" method="post">';
echo '<input type="hidden" name="courseid" value="' . $course->id . '">';
echo '<select name="scormid">';
foreach ($scorms as $scorm) {
    echo '<option value="' . $scorm->id . '">' . $scorm->name . '</option>';
}
echo '</select>';
echo '<button type="submit">' . get_string('export', 'local_aicc_export') . '</button>';
echo '</form>';

echo $OUTPUT->footer();
