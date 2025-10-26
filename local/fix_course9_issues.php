<?php
/**
 * Fix common issues with course 9
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../config.php');

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "COURSE 9 DIAGNOSTIC AND FIX\n";
echo str_repeat('=', 70) . "\n\n";

// Step 1: Check if course exists
echo "Step 1: Checking if course 9 exists...\n";
$course = $DB->get_record('course', ['id' => 9]);

if (!$course) {
    echo "✗ Course 9 does NOT exist\n";
    echo "\nAvailable courses:\n";
    $courses = $DB->get_records_sql("SELECT id, shortname, fullname FROM {course} WHERE id > 1 ORDER BY id LIMIT 20");
    foreach ($courses as $c) {
        echo "  - Course {$c->id}: {$c->shortname}\n";
    }
    exit;
}

echo "✓ Course 9 exists: {$course->shortname} - {$course->fullname}\n\n";

// Step 2: Check course modules
echo "Step 2: Checking course modules...\n";
try {
    $sql = "SELECT cm.*, m.name as modname 
            FROM {course_modules} cm
            JOIN {modules} m ON m.id = cm.module
            WHERE cm.course = ? AND cm.deletioninprogress = 0
            ORDER BY cm.section, cm.id";
    
    $modules = $DB->get_records_sql($sql, [9]);
    
    if (empty($modules)) {
        echo "⚠ No modules in course 9 (this is OK if course is empty)\n";
    } else {
        echo "✓ Found " . count($modules) . " module(s):\n";
        foreach ($modules as $mod) {
            echo "  - {$mod->modname} (CM ID: {$mod->id}, Instance: {$mod->instance})\n";
        }
    }
} catch (Exception $e) {
    echo "✗ ERROR: " . $e->getMessage() . "\n";
    echo "  Stack trace:\n" . $e->getTraceAsString() . "\n";
}
echo "\n";

// Step 3: Check for deleted modules
echo "Step 3: Checking for modules marked for deletion...\n";
try {
    $deleted = $DB->get_records_sql("
        SELECT cm.id, cm.instance, m.name as modname, cm.deletioninprogress
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module
        WHERE cm.course = ? AND cm.deletioninprogress = 1
    ", [9]);
    
    if (!empty($deleted)) {
        echo "⚠ Found " . count($deleted) . " module(s) marked for deletion:\n";
        foreach ($deleted as $del) {
            echo "  - {$del->modname} (CM ID: {$del->id}) - Want to clean these up? (y/n): ";
            // In production, you'd need user input, but for automation, skip
            echo "Skipping automatic cleanup\n";
        }
    } else {
        echo "✓ No deleted modules found\n";
    }
} catch (Exception $e) {
    echo "⚠ Could not check deleted modules: " . $e->getMessage() . "\n";
}
echo "\n";

// Step 4: Test specific queries that might fail
echo "Step 4: Testing specific database queries...\n";

$queries = [
    "Basic course query" => "SELECT * FROM {course} WHERE id = ?",
    "Course modules query" => "SELECT * FROM {course_modules} WHERE course = ?",
    "Sections query" => "SELECT * FROM {course_sections} WHERE course = ?",
    "Context query" => "SELECT * FROM {context} WHERE instanceid = ? AND contextlevel = ?",
];

foreach ($queries as $name => $sql) {
    try {
        if ($name == "Context query") {
            $result = $DB->get_records_sql($sql, [9, CONTEXT_COURSE]);
        } else {
            $result = $DB->get_records_sql($sql, [9]);
        }
        echo "  ✓ {$name}: " . count($result) . " record(s)\n";
    } catch (Exception $e) {
        echo "  ✗ {$name}: ERROR - " . $e->getMessage() . "\n";
    }
}
echo "\n";

// Step 5: Check context
echo "Step 5: Checking context...\n";
try {
    $context = $DB->get_record('context', ['instanceid' => 9, 'contextlevel' => CONTEXT_COURSE]);
    if ($context) {
        echo "✓ Context exists for course 9\n";
    } else {
        echo "⚠ No context found for course 9 (this might be the problem!)\n";
    }
} catch (Exception $e) {
    echo "✗ ERROR checking context: " . $e->getMessage() . "\n";
}
echo "\n";

// Step 6: Check sections
echo "Step 6: Checking course sections...\n";
try {
    $sections = $DB->get_records('course_sections', ['course' => 9]);
    echo "✓ Found " . count($sections) . " section(s)\n";
} catch (Exception $e) {
    echo "✗ ERROR checking sections: " . $e->getMessage() . "\n";
}
echo "\n";

// Summary and recommendations
echo str_repeat('=', 70) . "\n";
echo "RECOMMENDATIONS\n";
echo str_repeat('=', 70) . "\n\n";

echo "If you're getting 'Error reading from database' when accessing course 9:\n\n";
echo "Option 1: Purge caches\n";
echo "  Run: php admin/cli/purge_caches.php\n\n";

echo "Option 2: Run database schema check\n";
echo "  Run: php admin/cli/check_database_schema.php\n\n";

echo "Option 3: Rebuild course cache\n";
echo "  Run: php admin/cli/rebuild_course_cache.php --help\n\n";

echo "Option 4: Check if the course was corrupted\n";
echo "  Check the database directly:\n";
echo "  docker exec -it mariadb_moodle bash\n";
echo "  mysql -u root -pexample lms_one\n";
echo "  SELECT * FROM mdl_course WHERE id = 9;\n";
echo "  SELECT * FROM mdl_context WHERE instanceid = 9;\n\n";

echo "Try accessing course 9 again:\n";
echo "  http://localhost:8300/lms-one/course/view.php?id=9\n\n";
