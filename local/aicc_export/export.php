<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/exporter.php');

$courseid = required_param('courseid', PARAM_INT);

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);

require_login($course);
require_capability('local/aicc_export:export', context_course::instance($course->id));

if (!get_config('local_aicc_export', 'enabled')) {
    throw new \moodle_exception('error_plugin_disabled', 'local_aicc_export');
}

try {
    $exporter = new \local_aicc_export\course_exporter($course);
    $zipfilepath = $exporter->generate_package();
    $filename = clean_filename($course->shortname) . '_aicc.zip';
    send_file($zipfilepath, $filename);
    unlink($zipfilepath);
} catch (Exception $e) {
    print_error('export_failed', 'local_aicc_export', '', $e->getMessage());
}
