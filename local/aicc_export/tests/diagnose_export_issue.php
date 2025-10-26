<?php
/**
 * Diagnostic script for export issues
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "AICC Export Diagnosis\n";
echo str_repeat('=', 70) . "\n\n";

// Check 1: Plugin enabled
echo "1. Checking if plugin is enabled...\n";
$enabled = get_config('local_aicc_export', 'enabled');
echo "   " . ($enabled ? "✓ Plugin is enabled" : "✗ Plugin is DISABLED - Run: php local/setup_enable_plugins.php") . "\n\n";

if (!$enabled) {
    echo "SOLUTION: Run 'php local/setup_enable_plugins.php' to enable the plugin\n\n";
    exit;
}

// Check 2: Course ID 8
echo "2. Checking course ID 8...\n";
$course = $DB->get_record('course', ['id' => 8]);
if (!$course) {
    echo "   ✗ Course 8 not found in database\n";
    echo "   Available courses:\n";
    $courses = $DB->get_records_sql("SELECT id, shortname, fullname FROM {course} WHERE id > 1 ORDER BY id LIMIT 10");
    foreach ($courses as $c) {
        echo "     - Course {$c->id}: {$c->shortname} ({$c->fullname})\n";
    }
    exit;
}
echo "   ✓ Course found: {$course->shortname} - {$course->fullname}\n\n";

// Check 3: SCORM module installed
echo "3. Checking if SCORM module is installed...\n";
$scorm_module = $DB->get_record('modules', ['name' => 'scorm']);
if (!$scorm_module) {
    echo "   ✗ SCORM module NOT installed\n";
    echo "   SOLUTION: Install SCORM module from Site administration → Plugins → Activity modules\n";
    exit;
}
echo "   ✓ SCORM module installed (ID: {$scorm_module->id})\n\n";

// Check 4: Course has SCORM activities
echo "4. Checking for SCORM activities in course 8...\n";
$scorm_activities = $DB->get_records_sql("
    SELECT cm.id, cm.instance, s.name, s.id as scormid
    FROM {course_modules} cm
    JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
    JOIN {scorm} s ON s.id = cm.instance
    WHERE cm.course = ? AND cm.deletioninprogress = 0
    ORDER BY cm.section, cm.id
", [8]);

if (empty($scorm_activities)) {
    echo "   ✗ No SCORM activities found in course 8\n";
    echo "   SOLUTION: Add a SCORM activity to this course before exporting\n";
    exit;
}

echo "   ✓ Found " . count($scorm_activities) . " SCORM activity(ies):\n";
foreach ($scorm_activities as $activity) {
    echo "     - {$activity->name} (SCORM ID: {$activity->scormid}, CM ID: {$activity->id})\n";
}
echo "\n";

// Check 5: Check course modules table
echo "5. Checking course_modules table...\n";
$all_activities = $DB->get_records_sql("
    SELECT cm.id, cm.instance, m.name as modname
    FROM {course_modules} cm
    JOIN {modules} m ON m.id = cm.module
    WHERE cm.course = 8 AND cm.deletioninprogress = 0
    ORDER BY cm.section, cm.id
", [8]);

echo "   Found " . count($all_activities) . " total activities:\n";
foreach ($all_activities as $act) {
    echo "     - {$act->modname} (ID: {$act->id})\n";
}
echo "\n";

// Check 6: Database tables
echo "6. Checking database tables...\n";
$tables = ['course', 'course_modules', 'modules', 'scorm'];
foreach ($tables as $table) {
    $exists = $DB->get_manager()->table_exists($table);
    echo "   " . ($exists ? "✓" : "✗") . " Table: {$table}\n";
}
echo "\n";

// Check 7: Test SQL query
echo "7. Testing SQL query...\n";
try {
    $test_query = "
        SELECT cm.id, cm.instance, m.name as modname, m.id as moduleid
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
        WHERE cm.course = ? AND cm.deletioninprogress = 0
        ORDER BY cm.section, cm.id
    ";
    $result = $DB->get_records_sql($test_query, [8]);
    echo "   ✓ SQL query executed successfully\n";
    echo "   ✓ Returned " . count($result) . " record(s)\n";
} catch (Exception $e) {
    echo "   ✗ SQL query failed: " . $e->getMessage() . "\n";
    echo "   ERROR: " . $e->getTraceAsString() . "\n";
}
echo "\n";

// Summary
echo str_repeat('=', 70) . "\n";
echo "DIAGNOSIS COMPLETE\n";
echo str_repeat('=', 70) . "\n\n";

if (!empty($scorm_activities)) {
    echo "✓ Course 8 has SCORM activities\n";
    echo "✓ Plugin is enabled\n";
    echo "✓ Ready to export!\n\n";
    echo "Try accessing: http://localhost:8300/lms-one/local/aicc_export/index.php?courseid=8\n";
} else {
    echo "✗ Course 8 does not have any SCORM activities\n";
    echo "Please add a SCORM activity to the course first.\n";
}
echo "\n";
