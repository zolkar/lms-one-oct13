<?php
/**
 * Full workflow test for AICC Export and HACP
 * 
 * Usage: Run from CLI: php test_full_workflow.php
 */

require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/secure_auth.php');
require_once(__DIR__ . '/../classes/session_persistence.php');
require_once(__DIR__ . '/../../aicc_export/classes/exporter.php');

cli_heading('Full AICC Workflow Test');

echo "This test simulates the complete workflow:\n";
echo "1. LMS-1 exports course as AICC package\n";
echo "2. LMS-2 imports package\n";
echo "3. Student from LMS-2 accesses content\n";
echo "4. Progress is tracked via HACP\n\n";

// Test 1: Find a course with SCORM
echo "Step 1: Finding course with SCORM activities...\n";
$sql = "
    SELECT DISTINCT c.id, c.shortname, c.fullname
    FROM {course} c
    JOIN {course_modules} cm ON cm.course = c.id
    JOIN {modules} m ON m.id = cm.module
    WHERE m.name = 'scorm' AND c.id > 1
    ORDER BY c.id
    LIMIT 1
";

$course = $DB->get_record_sql($sql);

if (empty($course)) {
    cli_error('No course with SCORM activities found. Please create a course with SCORM first.');
}

echo "✓ Found course: {$course->shortname} (ID: {$course->id})\n\n";

// Test 2: Generate export token
echo "Step 2: Generating launch token...\n";
try {
    // Get first SCORM activity
    $scorm_sql = "
        SELECT s.id, cm.id as cmid
        FROM {scorm} s
        JOIN {course_modules} cm ON cm.instance = s.id
        JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
        WHERE s.course = ?
        ORDER BY s.id
        LIMIT 1
    ";
    $scorm = $DB->get_record_sql($scorm_sql, [$course->id]);
    
    if (empty($scorm)) {
        cli_error('No SCORM activities found in course');
    }
    
    $token = \local_aicc_hacp\secure_auth::generate_launch_token(
        $course->id,
        $scorm->id,
        'lms_two'
    );
    
    echo "✓ Token generated\n";
    echo "  Token preview: " . substr($token, 0, 50) . "...\n\n";
    
} catch (Exception $e) {
    cli_error('Failed to generate token: ' . $e->getMessage());
}

// Test 3: Validate token
echo "Step 3: Validating token...\n";
try {
    $payload = \local_aicc_hacp\secure_auth::validate_launch_token($token);
    if ($payload) {
        echo "✓ Token validated\n";
        echo "  Course ID: {$payload['courseid']}\n";
        echo "  SCORM ID: {$payload['scormid']}\n";
        echo "  External LMS: {$payload['external_lms']}\n";
        echo "  Expires: " . date('Y-m-d H:i:s', $payload['expires_at']) . "\n\n";
    } else {
        cli_error('Token validation failed');
    }
} catch (Exception $e) {
    cli_error('Token validation error: ' . $e->getMessage());
}

// Test 4: Create external user (simulate student from LMS-2)
echo "Step 4: Creating external student account...\n";
try {
    $student_email = 'student' . time() . '@external-lms.com';
    $student_name = 'External Test Student';
    
    $user_id = \local_aicc_hacp\session_persistence::create_external_user_account(
        $student_email,
        $student_name,
        'lms_two'
    );
    
    echo "✓ External user created\n";
    echo "  User ID: $user_id\n";
    echo "  Email: $student_email\n\n";
    
} catch (Exception $e) {
    cli_error('Failed to create external user: ' . $e->getMessage());
}

// Test 5: Create persistent session
echo "Step 5: Creating persistent session...\n";
try {
    $student_id = 'EXTERNAL_' . md5($student_email);
    
    $persistent_session = \local_aicc_hacp\session_persistence::get_persistent_session(
        $student_id,
        $scorm->id,
        1, // Assuming SCO ID 1
        'http://localhost:8300/lms-two'
    );
    
    echo "✓ Persistent session created\n";
    echo "  Session ID: {$persistent_session->id}\n";
    echo "  Access count: {$persistent_session->access_count}\n\n";
    
} catch (Exception $e) {
    cli_error('Failed to create persistent session: ' . $e->getMessage());
}

// Test 6: Create HACP session
echo "Step 6: Creating HACP session...\n";
try {
    $hacp_session_id = \local_aicc_hacp\session_persistence::create_hacp_session(
        $student_id,
        $scorm->id,
        1,
        'http://localhost:8300/lms-two'
    );
    
    echo "✓ HACP session created\n";
    echo "  Session ID: $hacp_session_id\n\n";
    
} catch (Exception $e) {
    cli_error('Failed to create HACP session: ' . $e->getMessage());
}

// Test 7: Simulate progress tracking
echo "Step 7: Simulating progress tracking...\n";
try {
    $progress_data = [
        'Core' => [
            'Student_ID' => $student_id,
            'Lesson_Status' => 'incomplete',
            'Lesson_Location' => 'slide_1',
            'Score' => '85',
            'Time' => '00:10:30'
        ]
    ];
    
    \local_aicc_hacp\session_persistence::save_student_progress(
        $student_id,
        $scorm->id,
        1,
        $progress_data
    );
    
    echo "✓ Progress saved\n";
    echo "  Lesson Status: incomplete\n";
    echo "  Score: 85\n";
    echo "  Time: 00:10:30\n\n";
    
} catch (Exception $e) {
    cli_error('Failed to save progress: ' . $e->getMessage());
}

// Test 8: Retrieve progress
echo "Step 8: Retrieving saved progress...\n";
try {
    $progress = \local_aicc_hacp\session_persistence::get_student_progress(
        $student_id,
        $scorm->id,
        1
    );
    
    if ($progress) {
        echo "✓ Progress retrieved\n";
        echo "  Lesson Status: {$progress->lesson_status}\n";
        echo "  Score: {$progress->score}\n";
        echo "  Location: {$progress->lesson_location}\n\n";
    } else {
        cli_error('Progress not found');
    }
} catch (Exception $e) {
    cli_error('Failed to retrieve progress: ' . $e->getMessage());
}

// Summary
echo str_repeat('=', 60) . "\n";
echo "✓ Full workflow test completed successfully!\n";
echo "\nSimulated workflow:\n";
echo "  [LMS-1] Course '{$course->shortname}' exported with token\n";
echo "  [LMS-2] Package imported, student accesses activity\n";
echo "  [LMS-1] External user created (ID: $user_id)\n";
echo "  [LMS-1] HACP session created for tracking\n";
echo "  [LMS-1] Progress saved and retrieved\n";
echo "\nNext steps:\n";
echo "  1. Export course via web interface\n";
echo "  2. Import on LMS-2\n";
echo "  3. Access as student from LMS-2\n";
echo "  4. Check progress in admin reports\n";
echo str_repeat('=', 60) . "\n";
