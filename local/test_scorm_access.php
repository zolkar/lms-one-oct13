<?php
/**
 * Test SCORM access for debugging
 */

define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../config.php');

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "SCORM Access Test\n";
echo str_repeat('=', 70) . "\n\n";

// Check SCORM ID 34
echo "Checking SCORM instance ID 34...\n";
$scorm = $DB->get_record('scorm', ['id' => 34]);
if ($scorm) {
    echo "✓ SCORM found: {$scorm->name}\n";
    echo "  Course: {$scorm->course}\n";
    echo "  Reference: {$scorm->reference}\n";
} else {
    echo "✗ SCORM 34 not found\n";
    exit;
}

echo "\n";

// Check course module
echo "Checking course module ID 42...\n";
$cm = $DB->get_record_sql("
    SELECT cm.*, m.name as modname
    FROM {course_modules} cm
    JOIN {modules} m ON m.id = cm.module
    WHERE cm.id = 42
");

if ($cm) {
    echo "✓ Course module found\n";
    echo "  Module: {$cm->modname}\n";
    echo "  Course: {$cm->course}\n";
    echo "  Instance: {$cm->instance}\n";
    echo "  Visible: {$cm->visible}\n";
    echo "  Deletion in progress: {$cm->deletioninprogress}\n";
} else {
    echo "✗ Course module 42 not found\n";
}

echo "\n";

// Check SCOes
echo "Checking SCOes for SCORM 34...\n";
$scoes = $DB->get_records('scorm_scoes', ['scorm' => 34]);
if (empty($scoes)) {
    echo "✗ No SCOes found! This is a problem.\n";
    echo "  The SCORM activity exists but has no SCO data.\n";
    echo "  This usually means the SCORM package wasn't uploaded properly.\n";
} else {
    echo "✓ Found " . count($scoes) . " SCOe(s):\n";
    foreach ($scoes as $sco) {
        echo "  - SCO ID: {$sco->id}, Title: {$sco->title}, Launch: {$sco->launch}\n";
    }
}

echo "\n";

// Check if content files exist
echo "Checking content files...\n";
$context = $DB->get_record_sql("
    SELECT c.*
    FROM {context} c
    JOIN {course_modules} cm ON cm.id = c.instanceid AND c.contextlevel = 70
    WHERE cm.id = 42
");

if ($context) {
    echo "✓ Context found: {$context->id}\n";
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_scorm', 'content', 0, 'id', false);
    echo "  Files in content area: " . count($files) . "\n";
    
    if (count($files) == 0) {
        echo "  ⚠ WARNING: No files found! SCORM content is missing.\n";
    }
} else {
    echo "✗ Context not found\n";
}

echo "\n";
echo str_repeat('=', 70) . "\n";
echo "SUMMARY\n";
echo str_repeat('=', 70) . "\n\n";

if (empty($scoes)) {
    echo "ISSUE: The SCORM activity exists but has no SCO data.\n";
    echo "\nSOLUTION:\n";
    echo "1. Go to: http://localhost:8300/lms-one/course/view.php?id=9\n";
    echo "2. Edit the SCORM activity (socrm)\n";
    echo "3. Upload a proper SCORM package\n";
    echo "4. Or delete and recreate the SCORM activity\n";
} else {
    echo "✓ SCORM appears to be set up correctly.\n";
    echo "You should be able to export it.\n";
}

echo "\n";
