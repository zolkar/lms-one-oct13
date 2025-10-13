<?php

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');

admin_externalpage_setup('local_aicc_hacp_manualmap');

$PAGE->set_title(get_string('manual_mapping', 'local_aicc_hacp'));
$PAGE->set_heading(get_string('manual_mapping', 'local_aicc_hacp'));

$action = optional_param('action', '', PARAM_ALPHA);

if ($action == 'delete' && confirm_sesskey()) {
    $id = required_param('id', PARAM_INT);
    $DB->delete_records('local_aicc_hacp_usermap', ['id' => $id]);
    redirect($PAGE->url);
}

if ($_POST && confirm_sesskey()) {
    $externalid = required_param('externalid', PARAM_TEXT);
    $userid = required_param('userid', PARAM_INT);
    $origin = optional_param('origin', '', PARAM_TEXT);

    $map = new \stdClass();
    $map->external_id = $externalid;
    $map->userid = $userid;
    $map->trusted_origin = $origin;
    $DB->insert_record('local_aicc_hacp_usermap', $map);
    redirect($PAGE->url);
}

echo $OUTPUT->header();

echo '<form method="post">';
echo '<fieldset>';
echo '<legend>' . get_string('add_mapping', 'local_aicc_hacp') . '</legend>';
echo '<label for="externalid">' . get_string('external_id', 'local_aicc_hacp') . '</label>';
echo '<input type="text" name="externalid" id="externalid">';
echo '<label for="userid">' . get_string('moodle_user_id', 'local_aicc_hacp') . '</label>';
echo '<input type="number" name="userid" id="userid">';
echo '<label for="origin">' . get_string('trusted_origin', 'local_aicc_hacp') . '</label>';
echo '<input type="text" name="origin" id="origin">';
echo '<input type="hidden" name="sesskey" value="' . sesskey() . '">';
echo '<button type="submit">' . get_string('add', 'local_aicc_hacp') . '</button>';
echo '</fieldset>';
echo '</form>';

$table = new \html_table();
$table->head = [
    get_string('external_id', 'local_aicc_hacp'),
    get_string('moodle_user', 'local_aicc_hacp'),
    get_string('trusted_origin', 'local_aicc_hacp'),
    get_string('action', 'local_aicc_hacp'),
];

$mappings = $DB->get_records('local_aicc_hacp_usermap');
foreach ($mappings as $map) {
    $user = $DB->get_record('user', ['id' => $map->userid]);
    $deleteurl = new moodle_url($PAGE->url, ['action' => 'delete', 'id' => $map->id, 'sesskey' => sesskey()]);
    $row = [];
    $row[] = $map->external_id;
    $row[] = fullname($user);
    $row[] = $map->trusted_origin;
    $row[] = html_writer::link($deleteurl, get_string('delete', 'moodle'));
    $table->data[] = $row;
}

echo html_writer::table($table);

echo $OUTPUT->footer();
