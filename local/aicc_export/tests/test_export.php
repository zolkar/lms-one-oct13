<?php
/**
 * Test script for AICC Export
 * 
 * Usage: Run from CLI: php test_export.php
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once(__DIR__ . '/../classes/exporter.php');

cli_heading('AICC Export Test');

// Get a course with SCORM activities
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
    cli_error('No course with SCORM activities found');
}

echo "Testing export for course: {$course->shortname} ({$course->id})\n\n";

// Test 1: Check plugin is enabled
$enabled = get_config('local_aicc_export', 'enabled');
echo "✓ Plugin enabled: " . ($enabled ? 'YES' : 'NO') . "\n";

// Test 2: Check token secret is configured
$token_secret = get_config('local_aicc_export', 'launch_token_secret');
echo "✓ Launch token secret configured: " . (!empty($token_secret) ? 'YES' : 'NO') . "\n";

// Test 3: Check database table exists
$tables = $DB->get_tables();
$table_exists = in_array('local_aicc_export_sessions', $tables);
echo "✓ Database table exists: " . ($table_exists ? 'YES' : 'NO') . "\n";

// Test 4: Try to create exporter instance
try {
    $exporter = new \local_aicc_export\course_exporter($course);
    echo "✓ Exporter instance created successfully\n";
    
    // Check if course has activities
    $reflection = new ReflectionClass($exporter);
    $property = $reflection->getProperty('activities');
    $property->setAccessible(true);
    $activities = $property->getValue($exporter);
    
    echo "✓ Activities found: " . count($activities) . "\n";
    
    if (!empty($activities)) {
        echo "\nActivities:\n";
        foreach ($activities as $activity) {
            echo "  - {$activity->modname}: ID {$activity->id}\n";
        }
    }
    
} catch (Exception $e) {
    cli_error('Failed to create exporter: ' . $e->getMessage());
}

// Test 5: Test CRS generation
try {
    $reflection = new ReflectionClass($exporter);
    $method = $reflection->getMethod('get_crs_content');
    $method->setAccessible(true);
    $crs_content = $method->invoke($exporter);
    
    echo "\n✓ CRS file generated:\n";
    echo substr($crs_content, 0, 200) . "...\n";
    
} catch (Exception $e) {
    cli_error('Failed to generate CRS content: ' . $e->getMessage());
}

// Test 6: Test launch URL generation
try {
    $reflection = new ReflectionClass($exporter);
    $method = $reflection->getMethod('get_course_launch_url');
    $method->setAccessible(true);
    $launch_url = $method->invoke($exporter);
    
    echo "\n✓ Launch URL generated:\n";
    echo "  " . $launch_url . "\n";
    
    // Check if URL contains token
    if (strpos($launch_url, 'token=') !== false) {
        echo "✓ Launch URL contains token\n";
    } else {
        echo "✗ Launch URL missing token\n";
    }
    
} catch (Exception $e) {
    cli_error('Failed to generate launch URL: ' . $e->getMessage());
}

// Summary
echo "\n" . str_repeat('=', 60) . "\n";
echo "Export test completed successfully!\n";
echo "To export: Visit /local/aicc_export/export.php?courseid={$course->id}\n";
echo str_repeat('=', 60) . "\n";
