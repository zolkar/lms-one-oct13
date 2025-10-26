<?php
require_once(__DIR__ . '/../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/formslib.php');

admin_externalpage_setup('local_aicc_exporter');

echo $OUTPUT->header();

class aicc_exporter_form extends moodleform {
    public function definition() {
        global $DB;
        $mform = &$this->_form;

        $courses = $DB->get_records('course', [], 'fullname', 'id, fullname');
        $courseoptions = [];
        foreach ($courses as $course) {
            $courseoptions[$course->id] = $course->fullname;
        }

        $mform->addElement('select', 'courseid', get_string('select_course', 'local_aicc_exporter'), $courseoptions);
        $mform->addRule('courseid', null, 'required', null, 'client');

        $this->add_action_buttons(true, get_string('export', 'local_aicc_exporter'));
    }
}

$mform = new aicc_exporter_form();

if ($mform->is_cancelled()) {
    redirect(new moodle_url('/local/aicc_exporter/'));
} else if ($fromform = $mform->get_data()) {
    require_once(__DIR__ . '/lib.php');
    $courseid = $fromform->courseid;
    $zip_path = local_aicc_exporter_export_course($courseid);
    if ($zip_path) {
        send_file($zip_path, basename($zip_path));
    } else {
        echo $OUTPUT->notification('Error exporting course.');
    }
} else {
    echo $OUTPUT->heading(get_string('pluginname', 'local_aicc_exporter'));
    $mform->display();
}

echo $OUTPUT->footer();
