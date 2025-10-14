<?php

require_once(__DIR__ . '/../../config.php');
require_once(__DIR__ . '/classes/exporter.php');

// Debug script to see what URLs are being generated
require_login();
require_capability('moodle/site:config', context_system::instance());

echo "<h2>AICC Package URL Debug</h2>";

$courseid = optional_param('courseid', 0, PARAM_INT);
if (!$courseid) {
    echo "<p>Select a course:</p>";
    $courses = $DB->get_records('course', [], 'shortname', 'id,shortname,fullname', 0, 10);
    foreach ($courses as $course) {
        echo "<p><a href='?courseid={$course->id}'>{$course->shortname} - {$course->fullname}</a></p>";
    }
    exit;
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
echo "<h3>Course: {$course->fullname}</h3>";

// Get activities
$activities = $DB->get_records_sql("
    SELECT cm.id, cm.instance, m.name as modname, m.id as moduleid
    FROM {course_modules} cm
    JOIN {modules} m ON m.id = cm.module
    WHERE cm.course = ? AND m.name IN ('scorm', 'resource', 'page', 'lesson', 'quiz')
    ORDER BY cm.section, cm.id
", [$courseid]);

echo "<p><strong>Activities found:</strong></p>";
echo "<ul>";
foreach ($activities as $activity) {
    echo "<li>{$activity->modname} - ID: {$activity->id}, Instance: {$activity->instance}</li>";
}
echo "</ul>";

// Create exporter and get URLs
$exporter = new \local_aicc_export\course_exporter($course);

// Get SCORM activities
$scorm_activities = array_filter($activities, function($activity) {
    return $activity->modname === 'scorm';
});

if (!empty($scorm_activities)) {
    $scorm_activity = reset($scorm_activities);
    echo "<p><strong>Using SCORM activity:</strong> {$scorm_activity->id}</p>";
    
    // Get the launch URL
    $reflection = new ReflectionClass($exporter);
    $method = $reflection->getMethod('get_hacp_launch_url');
    $method->setAccessible(true);
    $launch_url = $method->invoke($exporter, $scorm_activity);
    
    echo "<p><strong>Generated HACP URL:</strong></p>";
    echo "<p><code>{$launch_url}</code></p>";
    
    // Test the URL
    echo "<p><strong>Test the URL:</strong></p>";
    echo "<p><a href='{$launch_url}' target='_blank'>Test HACP Handler</a></p>";
} else {
    echo "<p style='color: red;'>No SCORM activities found!</p>";
}

echo "<p><a href='" . $CFG->wwwroot . "'>Return to site</a></p>";
