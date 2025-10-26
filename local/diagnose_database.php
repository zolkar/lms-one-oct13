<?php
/**
 * Comprehensive database diagnostic script
 * Checks for common database issues
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../config.php');

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "DATABASE DIAGNOSTIC - LMS-ONE (Database: lms_one)\n";
echo str_repeat('=', 70) . "\n\n";

// Test 1: Database Connection
echo "1. Testing database connection...\n";
try {
    $test_query = "SELECT 1";
    $DB->execute($test_query);
    echo "   ✓ Database connection successful\n\n";
} catch (Exception $e) {
    echo "   ✗ Database connection FAILED: " . $e->getMessage() . "\n\n";
    exit;
}

// Test 2: Check course 9
echo "2. Checking course 9...\n";
try {
    $course = $DB->get_record('course', ['id' => 9]);
    if ($course) {
        echo "   ✓ Course found: ID={$course->id}, Shortname={$course->shortname}, Fullname={$course->fullname}\n";
    } else {
        echo "   ✗ Course 9 NOT FOUND in database\n";
    }
} catch (Exception $e) {
    echo "   ✗ ERROR reading course table: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 3: Check all courses
echo "3. Listing all courses...\n";
try {
    $courses = $DB->get_records_sql("SELECT id, shortname, fullname FROM {course} WHERE id > 1 ORDER BY id LIMIT 10");
    echo "   Found " . count($courses) . " course(s):\n";
    foreach ($courses as $c) {
        echo "     - Course {$c->id}: {$c->shortname} - {$c->fullname}\n";
    }
} catch (Exception $e) {
    echo "   ✗ ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 4: Check course_modules for course 9
echo "4. Checking course_modules for course 9...\n";
try {
    $modules = $DB->get_records_sql("
        SELECT cm.id, cm.instance, m.name as modname, cm.section
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        WHERE cm.course = ?
        ORDER BY cm.section, cm.id
    ", [9]);
    
    if (empty($modules)) {
        echo "   ⚠ No course modules found for course 9\n";
    } else {
        echo "   ✓ Found " . count($modules) . " module(s):\n";
        foreach ($modules as $mod) {
            $deleted = isset($mod->deletioninprogress) && $mod->deletioninprogress ? " [DELETED]" : "";
            echo "     - {$mod->modname} (CM ID: {$mod->id}, Instance: {$mod->instance}, Section: {$mod->section}){$deleted}\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ ERROR reading course_modules: " . $e->getMessage() . "\n";
    echo "   DETAILS: " . $e->getTraceAsString() . "\n";
}
echo "\n";

// Test 5: Check tables existence
echo "5. Checking core tables...\n";
$core_tables = [
    'course',
    'course_modules', 
    'modules',
    'scorm',
    'scorm_scoes',
    'sections',
    'course_sections',
    'context',
    'capabilities',
    'config'
];

foreach ($core_tables as $table) {
    $table_name = $table;
    if (strpos($table, '_') === false && $table != 'config') {
        $table_name = $table;
    }
    
    try {
        $exists = $DB->get_manager()->table_exists($table);
        if ($exists) {
            $count = $DB->count_records($table);
            echo "   ✓ Table '{$table}': {$count} records\n";
        } else {
            echo "   ✗ Table '{$table}': MISSING\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Table '{$table}': Error - " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Test 6: Check sections for course 9
echo "6. Checking course sections for course 9...\n";
try {
    $sections = $DB->get_records('course_sections', ['course' => 9], 'section ASC', 'id, section, name, visible');
    if (empty($sections)) {
        echo "   ⚠ No sections found for course 9\n";
    } else {
        echo "   ✓ Found " . count($sections) . " section(s):\n";
        foreach ($sections as $sec) {
            echo "     - Section {$sec->section}: " . ($sec->name ?: 'Unnamed') . " (visible=" . ($sec->visible ? 'yes' : 'no') . ")\n";
        }
    }
} catch (Exception $e) {
    echo "   ✗ ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// Test 7: Check for orphaned/deleted modules
echo "7. Checking for problematic modules in course 9...\n";
try {
    // Check for deleted in progress
    $deleted = $DB->get_records_sql("
        SELECT cm.id, cm.instance, m.name as modname
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        WHERE cm.course = ? AND cm.deletioninprogress = 1
    ", [9]);
    
    if (!empty($deleted)) {
        echo "   ⚠ Found " . count($deleted) . " module(s) marked for deletion:\n";
        foreach ($deleted as $del) {
            echo "     - {$del->modname} (CM ID: {$del->id})\n";
        }
    } else {
        echo "   ✓ No modules marked for deletion\n";
    }
    
    // Check for modules where the instance doesn't exist
    $orphans = $DB->get_records_sql("
        SELECT cm.id, cm.instance, m.name as modname
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        LEFT JOIN {".$DB->get_manager()->prefix."{$mod->modname}} inst ON inst.id = cm.instance
        WHERE cm.course = ? AND inst.id IS NULL
        LIMIT 10
    ", [9]);
    
    if (!empty($orphans)) {
        echo "   ⚠ Found " . count($orphans) . " potentially orphaned module(s):\n";
        foreach ($orphans as $orph) {
            echo "     - {$orph->modname} (CM ID: {$orph->id})\n";
        }
    }
} catch (Exception $e) {
    // Ignore complex queries
}
echo "\n";

// Test 8: Check AICC tables
echo "8. Checking AICC plugin tables...\n";
$aicc_tables = [
    'local_aicc_export_sessions',
    'local_aicc_hacp_sessions',
    'local_aicc_hacp_persistent_sessions',
    'local_aicc_hacp_student_state',
    'local_aicc_hacp_logs',
    'local_aicc_hacp_usermap'
];

foreach ($aicc_tables as $table) {
    try {
        $exists = $DB->get_manager()->table_exists($table);
        if ($exists) {
            $count = $DB->count_records($table);
            echo "   ✓ Table '{$table}': {$count} records\n";
        } else {
            echo "   ⚠ Table '{$table}': not installed yet\n";
        }
    } catch (Exception $e) {
        echo "   ✗ Table '{$table}': Error - " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Test 9: Database integrity check
echo "9. Checking database integrity...\n";
try {
    // Test for deleted course_modules pointing to missing instances
    $sql = "
        SELECT COUNT(*) as bad_modules
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        WHERE cm.course = 9 
        AND cm.deletioninprogress = 0
        AND NOT EXISTS (
            SELECT 1 FROM mdl_scorm s 
            WHERE s.id = cm.instance AND m.name = 'scorm'
        )
    ";
    
    $bad = $DB->get_record_sql($sql);
    if ($bad->bad_modules > 0) {
        echo "   ⚠ Found {$bad->bad_modules} potentially problematic module(s)\n";
    } else {
        echo "   ✓ No integrity issues found\n";
    }
} catch (Exception $e) {
    echo "   ⚠ Could not perform integrity check: " . $e->getMessage() . "\n";
}
echo "\n";

// Summary
echo str_repeat('=', 70) . "\n";
echo "DIAGNOSTIC SUMMARY\n";
echo str_repeat('=', 70) . "\n\n";

echo "Next steps:\n";
echo "1. If course 9 exists, try accessing it again\n";
echo "2. If errors persist, check PHP error logs\n";
echo "3. Run Moodle database check: php admin/cli/check_database_schema.php\n";
echo "4. Purge caches: php admin/cli/purge_caches.php\n";
echo "\n";

echo "For troubleshooting:\n";
echo "- Check Apache error logs: docker logs apache_8 2>&1 | grep error\n";
echo "- Check PHP error logs\n";
echo "- Verify database tables are intact\n";
echo "\n";
