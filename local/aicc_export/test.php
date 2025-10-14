<?php
/**
 * Test script for AICC Export functionality
 * This script can be run from the command line to test the export functionality
 */

require_once(__DIR__ . '/../../config.php');

// Only allow this to run from command line or admin
if (!CLI_SCRIPT && !is_siteadmin()) {
    die('This script can only be run from command line or by site administrators');
}

echo "AICC Export Test Script\n";
echo "======================\n\n";

// Test 1: Check if plugin is installed
echo "1. Checking plugin installation...\n";
$plugin_installed = $DB->record_exists('config_plugins', ['plugin' => 'local_aicc_export']);
if ($plugin_installed) {
    echo "   ✓ Plugin is installed\n";
} else {
    echo "   ✗ Plugin is not installed\n";
    exit(1);
}

// Test 2: Check if plugin is enabled
echo "2. Checking plugin status...\n";
$plugin_enabled = get_config('local_aicc_export', 'enabled');
if ($plugin_enabled) {
    echo "   ✓ Plugin is enabled\n";
} else {
    echo "   ⚠ Plugin is disabled (this is normal for new installations)\n";
}

// Test 3: Check for SCORM activities
echo "3. Checking for SCORM activities...\n";
$scorm_count = $DB->count_records('scorm');
if ($scorm_count > 0) {
    echo "   ✓ Found {$scorm_count} SCORM activities\n";
    
    // Get a sample SCORM activity
    $scorm = $DB->get_record('scorm', [], '*', IGNORE_MULTIPLE);
    $course = $DB->get_record('course', ['id' => $scorm->course], '*', MUST_EXIST);
    
    echo "   Sample SCORM: {$scorm->name} (Course: {$course->shortname})\n";
    
    // Test 4: Check SCORM content files
    echo "4. Checking SCORM content files...\n";
    $cm = get_coursemodule_from_instance('scorm', $scorm->id, $course->id, false, MUST_EXIST);
    $context = context_module::instance($cm->id);
    $fs = get_file_storage();
    $files = $fs->get_area_files($context->id, 'mod_scorm', 'content', 0, 'sortorder, itemid, filepath, filename', false);
    
    $file_count = count($files);
    if ($file_count > 0) {
        echo "   ✓ Found {$file_count} content files\n";
        
        // List some files
        $file_list = array_slice($files, 0, 5);
        foreach ($file_list as $file) {
            if (!$file->is_directory()) {
                echo "     - {$file->get_filename()}\n";
            }
        }
        if ($file_count > 5) {
            echo "     ... and " . ($file_count - 5) . " more files\n";
        }
    } else {
        echo "   ⚠ No content files found in SCORM activity\n";
    }
    
    // Test 5: Test exporter class
    echo "5. Testing exporter class...\n";
    try {
        require_once(__DIR__ . '/classes/exporter.php');
        $exporter = new \local_aicc_export\course_exporter($course, $scorm);
        echo "   ✓ Exporter class instantiated successfully\n";
        
        // Test AICC file generation
        echo "6. Testing AICC file generation...\n";
        try {
            $reflection = new ReflectionClass($exporter);
            $get_crs_method = $reflection->getMethod('get_crs_content');
            $get_crs_method->setAccessible(true);
            $crs_content = $get_crs_method->invoke($exporter);
            
            if (!empty($crs_content) && strpos($crs_content, '[Course]') !== false) {
                echo "   ✓ CRS file generation works\n";
            } else {
                echo "   ✗ CRS file generation failed\n";
            }
            
            $get_cst_method = $reflection->getMethod('get_cst_content');
            $get_cst_method->setAccessible(true);
            $cst_content = $get_cst_method->invoke($exporter);
            
            if (!empty($cst_content) && strpos($cst_content, 'Block,Title,Type,Parent,AU') !== false) {
                echo "   ✓ CST file generation works\n";
            } else {
                echo "   ✗ CST file generation failed\n";
            }
        } catch (Exception $e) {
            echo "   ✗ AICC file generation test failed: " . $e->getMessage() . "\n";
        }
        
    } catch (Exception $e) {
        echo "   ✗ Exporter class test failed: " . $e->getMessage() . "\n";
    }
    
} else {
    echo "   ⚠ No SCORM activities found\n";
}

echo "\nTest completed!\n";
echo "\nTo test the full export functionality:\n";
echo "1. Enable the plugin in Site Administration\n";
echo "2. Navigate to a course with SCORM activities\n";
echo "3. Access the AICC Export page\n";
echo "4. Select a SCORM activity and export it\n";
echo "5. Try importing the exported package back into Moodle\n";
