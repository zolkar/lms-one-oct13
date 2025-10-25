<?php
define('CLI_SCRIPT', true);
require_once('config.php');

echo "Simulating LMS-2 request...\n";

// Find a SCORM activity
$scorm = $DB->get_record('scorm', [], 'id,course', 'id,course');
if (!$scorm) {
    echo "No SCORM activities found!\n";
    exit;
}

// Get course module
$cm = $DB->get_record('course_modules', ['instance' => $scorm->id, 'module' => $DB->get_field('modules', 'id', ['name' => 'scorm'])]);
if (!$cm) {
    echo "No course module found for SCORM {$scorm->id}!\n";
    exit;
}

echo "Testing with SCORM {$scorm->id}, Course Module {$cm->id}\n";

// Test URLs that LMS-2 might use
$base_url = 'http://localhost/lms-one/local/aicc_export/direct_content.php';
$test_urls = [
    "Basic launch: {$base_url}?id={$cm->id}",
    "With student_id: {$base_url}?id={$cm->id}&student_id=test_student_123",
    "With AICC_SID: {$base_url}?id={$cm->id}&AICC_SID=test_student_456",
    "With session_id: {$base_url}?id={$cm->id}&session_id=test_session_789",
];

foreach ($test_urls as $url) {
    echo "\n" . $url . "\n";
}

echo "\nTo test, try accessing one of these URLs in your browser or from LMS-2.\n";
echo "Check the Moodle error logs for detailed debugging information.\n";
