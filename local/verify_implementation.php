<?php
/**
 * Verification script for AICC implementation
 * Checks all three plugins are correctly configured and working together
 */

define('CLI_SCRIPT', true);
define('CACHE_DISABLE_ALL', true);

require_once(__DIR__ . '/../config.php');

echo "==========================================\n";
echo "AICC Implementation Verification\n";
echo "==========================================\n\n";

$errors = [];
$warnings = [];

// Check Plugin 1: aicc_export
echo "1. Checking local/aicc_export...\n";
$files_required = [
    'version.php',
    'classes/exporter.php',
    'content_launcher.php',
    'export.php',
    'file_server.php',
    'index.php',
    'settings.php',
    'lang/en/local_aicc_export.php',
    'db/install.php',
    'db/access.php'
];

foreach ($files_required as $file) {
    $path = __DIR__ . '/aicc_export/' . $file;
    if (!file_exists($path)) {
        $errors[] = "Missing: aicc_export/{$file}";
    }
}

if (empty($errors)) {
    echo "   ✓ All required files present\n";
}

// Check Plugin 2: aicc_hacp
echo "\n2. Checking local/aicc_hacp...\n";
$files_required = [
    'version.php',
    'endpoint.php',
    'lib.php',
    'settings.php',
    'lang/en/local_aicc_hacp.php',
    'classes/handler.php',
    'classes/parser.php',
    'classes/secure_auth.php',
    'classes/secure_session.php',
    'classes/session_persistence.php',
    'classes/mapper.php',
    'admin/student_details.php',
    'admin/delete_student.php',
    'admin/reset_student.php',
    'db/install.php',
    'db/access.php',
    'db/caches.php'
];

foreach ($files_required as $file) {
    $path = __DIR__ . '/aicc_hacp/' . $file;
    if (!file_exists($path)) {
        $errors[] = "Missing: aicc_hacp/{$file}";
    }
}

if (empty($errors)) {
    echo "   ✓ All required files present\n";
}

// Check Plugin 3: aicc_use
echo "\n3. Checking local/aicc_use...\n";
$files_required = [
    'version.php',
    'launcher.php',
    'lang/en/local_aicc_use.php',
    'README.txt',
    'INSTALL_GUIDE.md'
];

foreach ($files_required as $file) {
    $path = __DIR__ . '/aicc_use/' . $file;
    if (!file_exists($path)) {
        $errors[] = "Missing: aicc_use/{$file}";
    }
}

if (empty($errors)) {
    echo "   ✓ All required files present\n";
}

// Check dependencies
echo "\n4. Checking dependencies...\n";

// Check if exporter uses secure_auth correctly
$exporter_content = file_get_contents(__DIR__ . '/aicc_export/classes/exporter.php');
if (strpos($exporter_content, 'secure_auth') === false) {
    $errors[] = "exporter.php doesn't use secure_auth";
} else {
    echo "   ✓ exporter.php uses secure_auth from aicc_hacp\n";
}

if (strpos($exporter_content, "require_once(__DIR__ . '/launcher.php')") !== false) {
    $errors[] = "exporter.php still references deleted launcher.php";
} else {
    echo "   ✓ No references to deleted launcher.php\n";
}

// Check if content_launcher uses aicc_hacp correctly
$launcher_content = file_get_contents(__DIR__ . '/aicc_export/content_launcher.php');
if (strpos($launcher_content, 'session_persistence') === false) {
    $errors[] = "content_launcher.php doesn't use session_persistence";
} else {
    echo "   ✓ content_launcher.php uses session_persistence from aicc_hacp\n";
}

// Check database tables
echo "\n5. Checking database tables...\n";
global $DB;
$tables_required = [
    'local_aicc_exports',
    'local_aicc_hacp_sessions',
    'local_aicc_hacp_student_state',
    'local_aicc_hacp_persistent_sessions',
    'local_aicc_hacp_usermap',
    'local_aicc_hacp_logs'
];

foreach ($tables_required as $table) {
    if ($DB->get_manager()->table_exists($table)) {
        echo "   ✓ Table exists: {$table}\n";
    } else {
        $errors[] = "Table missing: {$table}";
    }
}

// Check plugin configuration
echo "\n6. Checking plugin configuration...\n";
$export_enabled = get_config('local_aicc_export', 'enabled');
$hacp_enabled = get_config('local_aicc_hacp', 'enabled');

if ($export_enabled) {
    echo "   ✓ aicc_export is enabled\n";
} else {
    $warnings[] = "aicc_export is not enabled";
}

if ($hacp_enabled) {
    echo "   ✓ aicc_hacp is enabled\n";
} else {
    $warnings[] = "aicc_hacp is not enabled";
}

// Summary
echo "\n==========================================\n";
echo "SUMMARY\n";
echo "==========================================\n";

if (empty($errors) && empty($warnings)) {
    echo "✓ All checks passed!\n";
    echo "\nThe implementation is ready to use.\n";
    exit(0);
}

if (!empty($errors)) {
    echo "ERRORS FOUND:\n";
    foreach ($errors as $error) {
        echo "  ✗ {$error}\n";
    }
}

if (!empty($warnings)) {
    echo "\nWARNINGS:\n";
    foreach ($warnings as $warning) {
        echo "  ⚠ {$warning}\n";
    }
}

exit(count($errors));

