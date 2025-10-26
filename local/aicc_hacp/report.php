<?php

require_once(__DIR__ . '/../../config.php');

$courseid = required_param('courseid', PARAM_INT);
$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
$context = context_course::instance($courseid);

require_login($course);
require_capability('local/aicc_hacp:viewreport', $context);

$PAGE->set_context($context);
$PAGE->set_pagelayout('report');
$PAGE->set_title(get_string('remote_learner_report', 'local_aicc_hacp'));
$PAGE->set_heading(get_string('remote_learner_report', 'local_aicc_hacp'));

echo $OUTPUT->header();

$usermappings = $DB->get_records('local_aicc_hacp_users', ['courseid' => $courseid]);
$table = new html_table();
$table->head = [
    get_string('remote_user', 'local_aicc_hacp'),
    get_string('lesson_status', 'local_aicc_hacp'),
    get_string('score', 'local_aicc_hacp'),
];

foreach ($usermappings as $mapping) {
    $user = $DB->get_record('user', ['id' => $mapping->internaluserid]);
    $trackingdata = $DB->get_records_menu('local_aicc_hacp_tracking', ['userid' => $mapping->internaluserid], '', 'element, value');

    $lesson_status = $trackingdata['cmi.core.lesson_status'] ?? 'not attempted';
    $score = $trackingdata['cmi.core.score.raw'] ?? '0';

    $table->data[] = [
        fullname($user),
        $lesson_status,
        $score,
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
