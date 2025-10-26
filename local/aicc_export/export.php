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
    // Check for SCORM activities
    $scorm_activities = $DB->get_records_sql("
        SELECT cm.id, cm.instance, s.id as scormid, s.name
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
        JOIN {scorm} s ON s.id = cm.instance
        WHERE cm.course = ? AND cm.deletioninprogress = 0
        ORDER BY cm.section, cm.id
    ", [$course->id]);
    
    if (empty($scorm_activities)) {
        print_error('no_scorms_in_course', 'local_aicc_export');
    }
    
    $exporter = new \local_aicc_export\course_exporter($course);
    $zipfilepath = $exporter->generate_package();
    $filename = clean_filename($course->shortname) . '_aicc_' . time() . '.zip';
    
    // Prevent caching to ensure fresh token on each export
    header('Cache-Control: no-cache, no-store, must-revalidate');
    header('Pragma: no-cache');
    header('Expires: 0');
    
    send_file($zipfilepath, $filename);
    unlink($zipfilepath);
    
    // If we reach here, something went wrong
    throw new Exception('File generation failed');
} catch (Exception $e) {
    error_log('AICC Export failed: ' . $e->getMessage());
    print_error('export_failed', 'local_aicc_export', '', $e->getMessage());
}
