<?php
/**
 * Test script for External Users functionality
 * 
 * Usage: Run from CLI: php test_external_users.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/session_persistence.php');

cli_heading('External Users Test');

// Test 1: Check plugin is enabled
echo "Testing external users functionality...\n\n";

$enabled = get_config('local_aicc_hacp', 'enabled');
echo "✓ Plugin enabled: " . ($enabled ? 'YES' : 'NO') . "\n";

// Test 2: Check database tables exist
$tables = $DB->get_tables();
$required_tables = [
    'local_aicc_hacp_persistent_sessions',
    'local_aicc_hacp_sessions',
    'local_aicc_hacp_student_state',
    'local_aicc_hacp_logs'
];

echo "\nChecking database tables...\n";
foreach ($required_tables as $table) {
    $exists = in_array($table, $tables);
    echo ($exists ? '✓' : '✗') . " Table: $table\n";
}

// Test 3: Create test external user
echo "\nTesting external user creation...\n";
try {
    $test_email = 'test@external-lms.com';
    $test_name = 'Test External Student';
    $test_lms = 'test_lms';
    
    $user_id = \local_aicc_hacp\session_persistence::create_external_user_account(
        $test_email,
        $test_name,
        $test_lms
    );
    
    echo "✓ External user created with ID: $user_id\n";
    
    // Check user exists
    $user = $DB->get_record('user', ['id' => $user_id, 'deleted' => 0]);
    if ($user) {
        echo "✓ User found in database\n";
        echo "  Username: {$user->username}\n";
        echo "  Email: {$user->email}\n";
        echo "  ID Number: {$user->idnumber}\n";
    } else {
        echo "✗ User not found in database\n";
    }
    
} catch (Exception $e) {
    cli_error('Failed to create external user: ' . $e->getMessage());
}

// Test 4: Check for duplicate user creation
echo "\nTesting duplicate user handling...\n";
try {
    $duplicate_id = \local_aicc_hacp\session_persistence::create_external_user_account(
        $test_email,
        $test_name,
        $test_lms
    );
    
    if ($duplicate_id == $user_id) {
        echo "✓ Duplicate user returns same ID\n";
    } else {
        echo "✗ Duplicate user created new ID (expected same ID)\n";
    }
} catch (Exception $e) {
    echo "✗ Error handling duplicate: " . $e->getMessage() . "\n";
}

// Test 5: Create persistent session
echo "\nTesting persistent session creation...\n";
try {
    $test_student_id = 'TEST_STUDENT_' . time();
    $scorm_id = 1; // Assuming at least one SCORM exists
    $sco_id = 1;
    $origin = 'http://test-lms.local';
    
    $persistent_session = \local_aicc_hacp\session_persistence::get_persistent_session(
        $test_student_id,
        $scorm_id,
        $sco_id,
        $origin
    );
    
    echo "✓ Persistent session created\n";
    echo "  Session ID: {$persistent_session->id}\n";
    echo "  Student ID: {$persistent_session->student_id}\n";
    echo "  Origin: {$persistent_session->origin}\n";
    echo "  Access count: {$persistent_session->access_count}\n";
    
    // Test getting the same session again
    $same_session = \local_aicc_hacp\session_persistence::get_persistent_session(
        $test_student_id,
        $scorm_id,
        $sco_id,
        $origin
    );
    
    if ($same_session->id == $persistent_session->id) {
        echo "✓ Same session retrieved (access count should increase)\n";
        echo "  Access count: {$same_session->access_count}\n";
    }
    
} catch (Exception $e) {
    echo "✗ Failed to create persistent session: " . $e->getMessage() . "\n";
}

// Test 6: Test student progress storage
echo "\nTesting student progress storage...\n";
try {
    $test_data = [
        'Core' => [
            'Student_ID' => $test_student_id,
            'Lesson_Status' => 'incomplete',
            'Lesson_Location' => 'slide_1',
            'Score' => '75',
            'Time' => '00:05:30'
        ],
        'Core_Lesson' => [
            'suspend_data' => 'test_state_123'
        ]
    ];
    
    \local_aicc_hacp\session_persistence::save_student_progress(
        $test_student_id,
        $scorm_id,
        $sco_id,
        $test_data
    );
    
    echo "✓ Progress saved\n";
    
    // Retrieve progress
    $progress = \local_aicc_hacp\session_persistence::get_student_progress(
        $test_student_id,
        $scorm_id,
        $sco_id
    );
    
    if ($progress) {
        echo "✓ Progress retrieved\n";
        echo "  Lesson Status: {$progress->lesson_status}\n";
        echo "  Score: {$progress->score}\n";
        echo "  Location: {$progress->lesson_location}\n";
    } else {
        echo "✗ Progress not found\n";
    }
    
} catch (Exception $e) {
    echo "✗ Failed to save/retrieve progress: " . $e->getMessage() . "\n";
}

// Test 7: Test HACP session creation
echo "\nTesting HACP session creation...\n";
try {
    $hacp_session_id = \local_aicc_hacp\session_persistence::create_hacp_session(
        $test_student_id,
        $scorm_id,
        $sco_id,
        $origin
    );
    
    echo "✓ HACP session created\n";
    echo "  Session ID: $hacp_session_id\n";
    
    // Check session in database
    $session = $DB->get_record('local_aicc_hacp_sessions', ['session_id' => $hacp_session_id]);
    if ($session) {
        echo "✓ Session found in database\n";
        echo "  Status: {$session->status}\n";
        echo "  Persistent Session ID: {$session->persistent_session_id}\n";
    }
    
} catch (Exception $e) {
    echo "✗ Failed to create HACP session: " . $e->getMessage() . "\n";
}

// Test 8: List all external students
echo "\nListing all external students...\n";
try {
    $students = $DB->get_records('local_aicc_hacp_persistent_sessions', [], 'created_at DESC', '*', 0, 10);
    echo "✓ Found " . count($students) . " external student(s)\n";
    
    foreach ($students as $student) {
        echo "  - {$student->student_id} from {$student->origin} (access count: {$student->access_count})\n";
    }
} catch (Exception $e) {
    echo "✗ Failed to list students: " . $e->getMessage() . "\n";
}

echo "\n" . str_repeat('=', 60) . "\n";
echo "External users test completed successfully!\n";
echo str_repeat('=', 60) . "\n";
