<?php
/**
 * Test script to manually test progress sync
 * This simulates what would happen when tracking data is received
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../config.php');

echo "=== Testing Progress Sync ===\n\n";

// Get a student
$students = $DB->get_records('local_aicc_hacp_sessions', [], 'id DESC', '*', 0, 1);

if (empty($students)) {
    echo "No students found.\n";
    exit;
}

$student = reset($students);
echo "Testing with student: {$student->student_id}\n\n";

// Test save progress
echo "1. Simulating PutParam command...\n";
$aicc_data = [
    'Core' => [
        'Student_ID' => $student->student_id,
        'Lesson_Status' => 'completed',
        'Lesson_Location' => 'Slide 10',
        'Score' => '85',
        'Time' => '00:15:30'
    ]
];

require_once($CFG->dirroot . '/local/aicc_hacp/classes/session_persistence.php');
\local_aicc_hacp\session_persistence::save_student_progress(
    $student->student_id,
    $student->scormid,
    $student->scoid,
    $aicc_data
);

echo "   ✓ Progress saved\n\n";

// Verify
echo "2. Verifying saved data...\n";
$progress = $DB->get_record('local_aicc_hacp_student_state', [
    'student_id' => $student->student_id,
    'scormid' => $student->scormid,
    'scoid' => $student->scoid
]);

if ($progress) {
    echo "   Lesson Status: " . ($progress->lesson_status ?? 'N/A') . "\n";
    echo "   Score: " . ($progress->score ?? 'N/A') . "\n";
    echo "   Session Time: " . ($progress->session_time ?? 'N/A') . "\n";
    echo "   ✓ Data verified\n";
} else {
    echo "   ✗ No data found\n";
}

echo "\nDone!\n";
