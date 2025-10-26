<?php
/**
 * Debug script for testing HACP endpoint
 * Run this script to simulate HACP requests
 * 
 * Usage: php debug_endpoint.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

cli_heading('HACP Endpoint Debug');

// Configuration
$base_url = get_config('local_aicc_hacp', 'base_url') ?: $CFG->wwwroot;
$endpoint_url = $base_url . '/local/aicc_hacp/endpoint.php';

echo "HACP Endpoint: $endpoint_url\n";
echo str_repeat('=', 60) . "\n\n";

// Test 1: Check plugin enabled
echo "Test 1: Plugin Status\n";
$enabled = get_config('local_aicc_hacp', 'enabled');
echo "  Plugin enabled: " . ($enabled ? 'YES' : 'NO') . "\n";
echo "  HTTPS required: " . (get_config('local_aicc_hacp', 'require_https') ? 'YES' : 'NO') . "\n";
echo "  Log level: " . (get_config('local_aicc_hacp', 'log_level') ?: 'not set') . "\n";
echo "  Max requests/min: " . (get_config('local_aicc_hacp', 'max_requests_per_minute') ?: 60) . "\n\n";

// Test 2: Check shared secret
echo "Test 2: Security Configuration\n";
$shared_secret = get_config('local_aicc_hacp', 'shared_secret');
echo "  Shared secret configured: " . (!empty($shared_secret) ? 'YES' : 'NO') . "\n\n";

// Test 3: Check database tables
echo "Test 3: Database Tables\n";
$tables = $DB->get_tables();
$required_tables = [
    'local_aicc_hacp_sessions',
    'local_aicc_hacp_persistent_sessions',
    'local_aicc_hacp_student_state',
    'local_aicc_hacp_logs'
];

foreach ($required_tables as $table) {
    $exists = in_array($table, $tables);
    echo "  " . ($exists ? '✓' : '✗') . " $table\n";
}
echo "\n";

// Test 4: List recent HACP requests
echo "Test 4: Recent HACP Requests\n";
try {
    $logs = $DB->get_records('local_aicc_hacp_logs', [], 'created_at DESC', '*', 0, 10);
    
    if (empty($logs)) {
        echo "  No logs found\n\n";
    } else {
        echo "  Found " . count($logs) . " recent requests:\n";
        foreach ($logs as $log) {
            echo "    - Command: {$log->command}\n";
            echo "      Session: {$log->session_id}\n";
            echo "      Result: {$log->result_code} - {$log->result_message}\n";
            echo "      Time: " . date('Y-m-d H:i:s', $log->created_at) . "\n\n";
        }
    }
} catch (Exception $e) {
    echo "  Error reading logs: " . $e->getMessage() . "\n\n";
}

// Test 5: List active sessions
echo "Test 5: Active Sessions\n";
try {
    $sessions = $DB->get_records_select(
        'local_aicc_hacp_sessions',
        'status = ? AND expires_at > ?',
        ['active', time()],
        'created_at DESC',
        '*',
        0,
        10
    );
    
    if (empty($sessions)) {
        echo "  No active sessions\n\n";
    } else {
        echo "  Found " . count($sessions) . " active sessions:\n";
        foreach ($sessions as $session) {
            echo "    - Session: {$session->session_id}\n";
            echo "      Student: {$session->student_id}\n";
            echo "      SCORM: {$session->scormid}\n";
            echo "      Origin: {$session->origin}\n";
            echo "      Created: " . date('Y-m-d H:i:s', $session->created_at) . "\n\n";
        }
    }
} catch (Exception $e) {
    echo "  Error reading sessions: " . $e->getMessage() . "\n\n";
}

// Test 6: List external students
echo "Test 6: External Students\n";
try {
    $students = $DB->get_records('local_aicc_hacp_persistent_sessions', [], 'last_access_at DESC', '*', 0, 10);
    
    if (empty($students)) {
        echo "  No external students found\n\n";
    } else {
        echo "  Found " . count($students) . " external student(s):\n";
        foreach ($students as $student) {
            echo "    - Student: {$student->student_name} ({$student->student_email})\n";
            echo "      ID: {$student->student_id}\n";
            echo "      Origin: {$student->origin}\n";
            echo "      Access count: {$student->access_count}\n";
            echo "      Last access: " . date('Y-m-d H:i:s', $student->last_access_at) . "\n\n";
        }
    }
} catch (Exception $e) {
    echo "  Error reading students: " . $e->getMessage() . "\n\n";
}

// Test 7: Generate sample HACP request
echo "Test 7: Sample HACP Request\n";
echo "  Sample curl command:\n";
echo "  curl -X POST \"$endpoint_url\" \\\n";
echo "    -d 'command=GETPARAM' \\\n";
echo "    -d 'session_id=TEST_SESSION' \\\n";
echo "    -d 'aicc_data=[Core]\r\nStudent_ID=TEST_STUDENT' \\\n";
echo "    -H 'Content-Type: application/x-www-form-urlencoded'\n\n";

echo str_repeat('=', 60) . "\n";
echo "Debug information complete!\n";
echo str_repeat('=', 60) . "\n";
