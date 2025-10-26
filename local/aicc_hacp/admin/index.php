<?php

require_once(__DIR__ . '/../../../config.php');
require_admin();

$PAGE->set_context(context_system::instance());
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('manage', 'local_aicc_hacp'));
$PAGE->set_heading(get_string('manage', 'local_aicc_hacp'));

echo $OUTPUT->header();

$usermappings = $DB->get_records('local_aicc_hacp_users');
$table = new html_table();
$table->head = ['External SID', 'Internal User', 'Course', 'Tracking Data'];

foreach ($usermappings as $mapping) {
    $user = $DB->get_record('user', ['id' => $mapping->internaluserid]);
    $course = $DB->get_record('course', ['id' => $mapping->courseid]);
    $trackingdata = $DB->get_records('local_aicc_hacp_tracking', ['userid' => $mapping->internaluserid]);

    $tracking_html = '<ul>';
    foreach ($trackingdata as $data) {
        $tracking_html .= '<li>' . $data->element . ': ' . $data->value . '</li>';
    }
    $tracking_html .= '</ul>';

    $table->data[] = [
        $mapping->externalsid,
        fullname($user),
        $course->fullname,
        $tracking_html,
    ];
}

echo html_writer::table($table);

echo $OUTPUT->footer();
