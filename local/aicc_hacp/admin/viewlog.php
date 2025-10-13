<?php

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

admin_externalpage_setup('local_aicc_hacp_viewlog');

$PAGE->set_title(get_string('view_logs', 'local_aicc_hacp'));
$PAGE->set_heading(get_string('view_logs', 'local_aicc_hacp'));

echo $OUTPUT->header();

$sort = optional_param('sort', 'created_at', PARAM_ALPHA);
$dir = optional_param('dir', 'DESC', PARAM_ALPHA);
$page = optional_param('page', 0, PARAM_INT);
$perpage = 20;

$table = new \html_table();
$table->head = [
    new \html_table_cell(get_string('log_time', 'local_aicc_hacp')),
    new \html_table_cell(get_string('log_session_id', 'local_aicc_hacp')),
    new \html_table_cell(get_string('log_command', 'local_aicc_hacp')),
    new \html_table_cell(get_string('log_result', 'local_aicc_hacp')),
    new \html_table_cell(get_string('log_remote_ip', 'local_aicc_hacp')),
];
$table->attributes['class'] = 'admintable';

$totalcount = $DB->count_records('local_aicc_hacp_logs');
$logs = $DB->get_records('local_aicc_hacp_logs', null, "$sort $dir", '*', $page * $perpage, $perpage);

echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $PAGE->url);

foreach ($logs as $log) {
    $row = [];
    $row[] = userdate($log->created_at);
    $row[] = $log->session_id;
    $row[] = $log->command;
    $row[] = "{$log->result_code} - {$log->result_message}";
    $row[] = $log->remote_ip;
    $table->data[] = $row;
}

echo html_writer::table($table);

echo $OUTPUT->paging_bar($totalcount, $page, $perpage, $PAGE->url);

echo $OUTPUT->footer();
