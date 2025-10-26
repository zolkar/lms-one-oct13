<?php
/**
 * Direct Database Connection Check
 * Connects directly to MySQL to check tables
 */

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);
define('IGNORE_COMPONENT_CACHE', true);

// This bypasses Moodle's DB abstraction layer and connects directly
$dsn = "mysql:host=mariadb_moodle;dbname=lms_one;charset=utf8mb4";
$username = "root";
$password = "example";

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "DIRECT DATABASE CHECK - lms_one\n";
echo str_repeat('=', 70) . "\n\n";

try {
    $pdo = new PDO($dsn, $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "✓ Connected to lms_one database\n\n";
} catch (PDOException $e) {
    echo "✗ Failed to connect: " . $e->getMessage() . "\n\n";
    exit;
}

// Check 1: List all tables
echo "1. Listing all tables in lms_one...\n";
try {
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "   Total tables: " . count($tables) . "\n\n";
} catch (PDOException $e) {
    echo "   ✗ ERROR: " . $e->getMessage() . "\n\n";
}
echo "\n";

// Check 2: Core Moodle tables
echo "2. Checking core Moodle tables...\n";
$core_tables = [
    'mdl_course',
    'mdl_course_modules',
    'mdl_modules',
    'mdl_context',
    'mdl_course_sections',
    'mdl_scorm',
    'mdl_scorm_scoes',
    'mdl_user',
    'mdl_sections',
    'mdl_config'
];

foreach ($core_tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $result['cnt'];
        echo "   ✓ $table: $count records\n";
    } catch (PDOException $e) {
        echo "   ✗ $table: MISSING or ERROR\n";
    }
}
echo "\n";

// Check 3: AICC plugin tables
echo "3. Checking AICC plugin tables...\n";
$aicc_tables = [
    'mdl_local_aicc_export_sessions',
    'mdl_local_aicc_hacp_sessions',
    'mdl_local_aicc_hacp_persistent_sessions',
    'mdl_local_aicc_hacp_student_state',
    'mdl_local_aicc_hacp_logs',
    'mdl_local_aicc_hacp_usermap'
];

foreach ($aicc_tables as $table) {
    try {
        $stmt = $pdo->query("SELECT COUNT(*) as cnt FROM $table");
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $count = $result['cnt'];
        echo "   " . ($count >= 0 ? "✓" : "✗") . " $table: $count records\n";
    } catch (PDOException $e) {
        echo "   ✗ $table: MISSING or ERROR\n";
    }
}
echo "\n";

// Check 4: Course 9 specifically
echo "4. Checking course 9...\n";
try {
    $stmt = $pdo->query("SELECT * FROM mdl_course WHERE id = 9");
    $course = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($course) {
        echo "   ✓ Course 9 exists\n";
        echo "   Shortname: {$course['shortname']}\n";
        echo "   Fullname: {$course['fullname']}\n";
        echo "   Timemodified: " . date('Y-m-d H:i:s', $course['timemodified']) . "\n";
    } else {
        echo "   ✗ Course 9 does NOT exist\n";
    }
} catch (PDOException $e) {
    echo "   ✗ ERROR reading course 9: " . $e->getMessage() . "\n";
}
echo "\n";

// Check 5: Context for course 9
echo "5. Checking context for course 9...\n";
try {
    $stmt = $pdo->query("SELECT * FROM mdl_context WHERE instanceid = 9 AND contextlevel = 50");
    $context = $stmt->fetch(PDO::FETCH_ASSOC);
    if ($context) {
        echo "   ✓ Context exists for course 9\n";
        echo "   Context ID: {$context['id']}\n";
    } else {
        echo "   ✗ No context found for course 9 (PROBLEM!)\n";
    }
} catch (PDOException $e) {
    echo "   ✗ ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// Check 6: Course modules for course 9
echo "6. Checking course modules for course 9...\n";
try {
    $stmt = $pdo->query("
        SELECT cm.id, cm.instance, cm.section, cm.visible, cm.deletioninprogress, m.name as modname
        FROM mdl_course_modules cm
        LEFT JOIN mdl_modules m ON m.id = cm.module
        WHERE cm.course = 9
        ORDER BY cm.section, cm.id
    ");
    $modules = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (empty($modules)) {
        echo "   ⚠ No modules in course 9\n";
    } else {
        echo "   ✓ Found " . count($modules) . " module(s):\n";
        foreach ($modules as $mod) {
            $deleted = $mod['deletioninprogress'] ? " [DELETED]" : "";
            $visible = $mod['visible'] ? "visible" : "hidden";
            echo "     - {$mod['modname']} (CM ID: {$mod['id']}, Instance: {$mod['instance']}, Section: {$mod['section']}, $visible){$deleted}\n";
        }
    }
} catch (PDOException $e) {
    echo "   ✗ ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// Check 7: Course sections for course 9
echo "7. Checking course sections for course 9...\n";
try {
    $stmt = $pdo->query("SELECT id, section, name, visible FROM mdl_course_sections WHERE course = 9 ORDER BY section");
    $sections = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo "   ✓ Found " . count($sections) . " section(s)\n";
} catch (PDOException $e) {
    echo "   ✗ ERROR: " . $e->getMessage() . "\n";
}
echo "\n";

// Check 8: Look for problematic modules
echo "8. Looking for problematic modules...\n";
try {
    $stmt = $pdo->query("
        SELECT cm.id, cm.instance, m.name as modname, cm.deletioninprogress, cm.course
        FROM mdl_course_modules cm
        JOIN mdl_modules m ON m.id = cm.module
        WHERE cm.deletioninprogress = 1
        ORDER BY cm.id
        LIMIT 10
    ");
    $deleted = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    if (!empty($deleted)) {
        echo "   ⚠ Found " . count($deleted) . " module(s) marked for deletion:\n";
        foreach ($deleted as $del) {
            echo "     - Module {$del['modname']} (CM ID: {$del['id']}, Course: {$del['course']})\n";
        }
        echo "   These may need to be cleaned up.\n";
    } else {
        echo "   ✓ No deleted modules found\n";
    }
} catch (PDOException $e) {
    echo "   ⚠ Could not check: " . $e->getMessage() . "\n";
}
echo "\n";

// Summary
echo str_repeat('=', 70) . "\n";
echo "SUMMARY\n";
echo str_repeat('=', 70) . "\n\n";

echo "If course 9 exists but you're getting errors:\n";
echo "1. Purge caches: php admin/cli/purge_caches.php\n";
echo "2. Rebuild course cache: php admin/cli/rebuild_course_cache.php\n";
echo "3. Check for deleted modules and clean them up\n\n";

echo "Ready to export?\n";
echo "- Make sure course 9 has at least one SCORM activity\n";
echo "- Run: php local/setup_enable_plugins.php\n";
echo "- Then: http://localhost:8300/lms-one/local/aicc_export/index.php?courseid=9\n\n";

$pdo = null;
echo "Connection closed.\n";
