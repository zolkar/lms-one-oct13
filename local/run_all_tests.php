<?php
/**
 * Master test runner for all AICC tests
 * 
 * Usage: php run_all_tests.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../config.php');

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "AICC IMPLEMENTATION - COMPREHENSIVE TEST SUITE\n";
echo str_repeat('=', 70) . "\n\n";

$tests_passed = 0;
$tests_failed = 0;
$total_tests = 6;

// Test 1: Plugin Installation Check
echo "TEST 1: Plugin Installation Check\n";
echo str_repeat('-', 70) . "\n";
try {
    $plugins = ['aicc_export', 'aicc_hacp'];
    foreach ($plugins as $plugin) {
        $plugin_name = 'local_' . $plugin;
        $plugin_path = __DIR__ . '/' . $plugin;
        
        if (file_exists($plugin_path)) {
            echo "✓ Plugin '$plugin' found\n";
            $version = $plugin_name . '_version';
            echo "  Path: $plugin_path\n";
        } else {
            throw new Exception("Plugin '$plugin' not found");
        }
    }
    echo "✓ TEST 1 PASSED\n\n";
    $tests_passed++;
} catch (Exception $e) {
    echo "✗ TEST 1 FAILED: " . $e->getMessage() . "\n\n";
    $tests_failed++;
}

// Test 2: Database Tables
echo "TEST 2: Database Tables Check\n";
echo str_repeat('-', 70) . "\n";
try {
    $tables = [
        'local_aicc_export_sessions',
        'local_aicc_hacp_sessions',
        'local_aicc_hacp_persistent_sessions',
        'local_aicc_hacp_student_state',
        'local_aicc_hacp_logs',
        'local_aicc_hacp_usermap'
    ];
    
    global $DB;
    $existing_tables = $DB->get_tables();
    
    foreach ($tables as $table) {
        if (in_array($table, $existing_tables)) {
            echo "✓ Table '$table' exists\n";
        } else {
            throw new Exception("Table '$table' not found");
        }
    }
    echo "✓ TEST 2 PASSED\n\n";
    $tests_passed++;
} catch (Exception $e) {
    echo "✗ TEST 2 FAILED: " . $e->getMessage() . "\n\n";
    $tests_failed++;
}

// Test 3: Configuration Check
echo "TEST 3: Configuration Check\n";
echo str_repeat('-', 70) . "\n";
try {
    // Check AICC Export settings
    $export_enabled = get_config('local_aicc_export', 'enabled');
    echo "  AICC Export enabled: " . ($export_enabled ? 'YES' : 'NO') . "\n";
    
    $export_secret = get_config('local_aicc_export', 'launch_token_secret');
    echo "  AICC Export token secret configured: " . (!empty($export_secret) ? 'YES' : 'NO') . "\n";
    
    // Check AICC HACP settings
    $hacp_enabled = get_config('local_aicc_hacp', 'enabled');
    echo "  AICC HACP enabled: " . ($hacp_enabled ? 'YES' : 'NO') . "\n";
    
    $hacp_secret = get_config('local_aicc_hacp', 'shared_secret');
    echo "  AICC HACP shared secret configured: " . (!empty($hacp_secret) ? 'YES' : 'NO') . "\n";
    
    $hacp_token = get_config('local_aicc_hacp', 'launch_token_secret');
    echo "  AICC HACP token secret configured: " . (!empty($hacp_token) ? 'YES' : 'NO') . "\n";
    
    if (empty($export_secret) || empty($hacp_secret) || empty($hacp_token)) {
        throw new Exception("One or more secrets not configured");
    }
    
    echo "✓ TEST 3 PASSED\n\n";
    $tests_passed++;
} catch (Exception $e) {
    echo "✗ TEST 3 FAILED: " . $e->getMessage() . "\n\n";
    $tests_failed++;
}

// Test 4: SCORM Activity Check
echo "TEST 4: SCORM Activity Check\n";
echo str_repeat('-', 70) . "\n";
try {
    $sql = "
        SELECT COUNT(*) as count
        FROM {scorm}
        WHERE id > 1
    ";
    $result = $DB->get_record_sql($sql);
    
    if ($result->count == 0) {
        echo "⚠ No SCORM activities found (create one for full testing)\n";
    } else {
        echo "✓ Found {$result->count} SCORM activity(ies)\n";
    }
    
    $sql = "
        SELECT c.id, c.shortname, s.id as scormid, s.name
        FROM {course} c
        JOIN {scorm} s ON s.course = c.id
        WHERE c.id > 1
        ORDER BY c.id
        LIMIT 3
    ";
    $courses = $DB->get_records_sql($sql);
    if (!empty($courses)) {
        echo "  Example courses:\n";
        foreach ($courses as $course) {
            echo "    - {$course->shortname}: {$course->name}\n";
        }
    }
    
    echo "✓ TEST 4 PASSED\n\n";
    $tests_passed++;
} catch (Exception $e) {
    echo "✗ TEST 4 FAILED: " . $e->getMessage() . "\n\n";
    $tests_failed++;
}

// Test 5: Class Loading Check
echo "TEST 5: Class Loading Check\n";
echo str_repeat('-', 70) . "\n";
try {
    $classes = [
        'local_aicc_export\\course_exporter',
        'local_aicc_hacp\\secure_auth',
        'local_aicc_hacp\\session_persistence',
        'local_aicc_hacp\\secure_session',
        'local_aicc_hacp\\handler',
        'local_aicc_hacp\\parser'
    ];
    
    foreach ($classes as $class) {
        if (!class_exists($class)) {
            throw new Exception("Class '$class' not found");
        }
        echo "✓ Class '$class' loaded\n";
    }
    
    echo "✓ TEST 5 PASSED\n\n";
    $tests_passed++;
} catch (Exception $e) {
    echo "✗ TEST 5 FAILED: " . $e->getMessage() . "\n\n";
    $tests_failed++;
}

// Test 6: Token Generation/Validation Check
echo "TEST 6: Token Generation/Validation Check\n";
echo str_repeat('-', 70) . "\n";
try {
    // Test token generation
    $test_token = \local_aicc_hacp\secure_auth::generate_launch_token(1, 1, 'test_lms');
    
    if (empty($test_token)) {
        throw new Exception("Failed to generate token");
    }
    echo "✓ Token generated: " . substr($test_token, 0, 50) . "...\n";
    
    // Test token validation
    $payload = \local_aicc_hacp\secure_auth::validate_launch_token($test_token);
    
    if (!$payload) {
        throw new Exception("Failed to validate token");
    }
    echo "✓ Token validated\n";
    echo "  Course ID: {$payload['courseid']}\n";
    echo "  SCORM ID: {$payload['scormid']}\n";
    echo "  Expires: " . date('Y-m-d H:i:s', $payload['expires_at']) . "\n";
    
    // Test with invalid token
    $invalid = \local_aicc_hacp\secure_auth::validate_launch_token('invalid.token.here');
    if ($invalid) {
        throw new Exception("Invalid token was accepted");
    }
    echo "✓ Invalid token correctly rejected\n";
    
    echo "✓ TEST 6 PASSED\n\n";
    $tests_passed++;
} catch (Exception $e) {
    echo "✗ TEST 6 FAILED: " . $e->getMessage() . "\n\n";
    $tests_failed++;
}

// Summary
echo str_repeat('=', 70) . "\n";
echo "TEST SUMMARY\n";
echo str_repeat('=', 70) . "\n";
echo "Tests Passed: $tests_passed / $total_tests\n";
echo "Tests Failed: $tests_failed / $total_tests\n\n";

if ($tests_failed == 0) {
    echo "✓ ALL TESTS PASSED! System is ready.\n\n";
    
    echo "Next steps:\n";
    echo "1. Create a course with SCORM activity\n";
    echo "2. Run: php local/aicc_export/tests/test_export.php\n";
    echo "3. Run: php local/aicc_hacp/tests/test_full_workflow.php\n";
    echo "4. Export course via web interface\n";
    echo "5. Import on LMS-2\n";
    echo "6. Test student access\n";
} else {
    echo "✗ SOME TESTS FAILED. Please fix issues and re-run.\n\n";
    
    echo "Troubleshooting:\n";
    if ($tests_failed >= 1) {
        echo "1. Run: php admin/cli/upgrade.php --non-interactive\n";
    }
    echo "2. Check plugin settings in Site administration\n";
    echo "3. Verify database tables exist\n";
    echo "4. Review error logs\n";
}

echo str_repeat('=', 70) . "\n\n";
