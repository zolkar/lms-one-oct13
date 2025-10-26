<?php
/**
 * View HACP logs
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
require_capability('local/aicc_hacp:viewlog', context_system::instance());

$perpage = optional_param('perpage', 50, PARAM_INT);
$page = optional_param('page', 0, PARAM_INT);

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/aicc_hacp/admin/viewlog.php');
$PAGE->set_title('AICC HACP Logs');
$PAGE->set_heading('AICC HACP Logs');

echo $OUTPUT->header();

// Get total count
$total = $DB->count_records('local_aicc_hacp_logs');

if ($total == 0) {
    echo $OUTPUT->notification('No logs found', 'info');
    echo $OUTPUT->footer();
    exit;
}

// Get logs with pagination
$logs = $DB->get_records('local_aicc_hacp_logs', [], 'created_at DESC', '*', $page * $perpage, $perpage);

// Create table
$table = new html_table();
$table->head = [
    'Time',
    'Session ID',
    'Command',
    'Result Code',
    'Message',
    'IP Address',
    'Signature Valid'
];

foreach ($logs as $log) {
    $valid_badge = $log->signature_valid ? 
        html_writer::span('Valid', 'badge badge-success') : 
        html_writer::span('Invalid', 'badge badge-danger');
    
    $table->data[] = [
        userdate($log->created_at),
        substr($log->session_id, 0, 20) . '...',
        $log->command,
        $log->result_code,
        $log->result_message,
        $log->remote_ip,
        $valid_badge
    ];
}

echo html_writer::table($table);

// Pagination
$pagingbar = new paging_bar($total, $page, $perpage, $PAGE->url);
echo $OUTPUT->render($pagingbar);

// Summary
echo html_writer::div(
    html_writer::tag('strong', "Total logs: $total") .
    html_writer::tag('p', "Showing page " . ($page + 1) . " of " . ceil($total / $perpage)),
    'card'
);

echo $OUTPUT->footer();