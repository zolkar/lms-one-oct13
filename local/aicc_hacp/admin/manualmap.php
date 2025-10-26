<?php
/**
 * Manual user mapping
 */

require_once(__DIR__ . '/../../../config.php');

require_login();
require_capability('local/aicc_hacp:manualmap', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/aicc_hacp/admin/manualmap.php');
$PAGE->set_title('Manual User Mapping');
$PAGE->set_heading('Manual User Mapping');

echo $OUTPUT->header();

echo html_writer::tag('p', 'This feature is not yet implemented. External users are automatically created when they access content.');

// Show current mappings
$mappings = $DB->get_records('local_aicc_hacp_usermap', [], 'created_at DESC', '*', 0, 50);

if (!empty($mappings)) {
    $table = new html_table();
    $table->head = ['External ID', 'Moodle User ID', 'Trusted Origin', 'Created'];
    
    foreach ($mappings as $mapping) {
        $user = $DB->get_record('user', ['id' => $mapping->userid]);
        $user_name = $user ? fullname($user) : 'Unknown';
        
        $table->data[] = [
            $mapping->external_id,
            html_writer::link(new moodle_url('/user/view.php', ['id' => $mapping->userid]), $user_name),
            $mapping->trusted_origin,
            userdate($mapping->created_at)
        ];
    }
    
    echo html_writer::table($table);
}

echo $OUTPUT->footer();