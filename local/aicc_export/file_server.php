<?php
/**
 * File server for SCORM content
 * Serves files without authentication for external LMS access
 */

// Define constants to bypass login requirements
define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../config.php');

// Security: Only allow this endpoint if plugins are properly configured
if (!get_config('local_aicc_export', 'enabled') || !get_config('local_aicc_hacp', 'enabled')) {
    http_response_code(403);
    echo "Error: Service not enabled";
    exit;
}

// Get parameters
$cmid = required_param('id', PARAM_INT);
$filepath = required_param('file', PARAM_RAW);

// Get course module and SCORM info
$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
$scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);

// Get context
$fs = get_file_storage();
$cmcontext = context_module::instance($cm->id);

// Parse the requested file path
$filepath = '/' . ltrim($filepath, '/');
$dir = dirname($filepath) . '/';
$filename = basename($filepath);

error_log("File server request: path={$dir}, filename={$filename}");

// Try to find the file
$file = $fs->get_file($cmcontext->id, 'mod_scorm', 'content', 0, $dir, $filename);

if (!$file) {
    // Try parent directory
    $file = $fs->get_file($cmcontext->id, 'mod_scorm', 'content', 0, '/res/', $filename);
}

if (!$file) {
    // Try root
    $file = $fs->get_file($cmcontext->id, 'mod_scorm', 'content', 0, '/', $filename);
}

if (!$file) {
    http_response_code(404);
    echo "Error: File not found: {$filepath}";
    error_log("File not found: {$filepath} in context {$cmcontext->id}");
    
    // Debug: list available files
    $all_files = $fs->get_area_files($cmcontext->id, 'mod_scorm', 'content', 0);
    error_log("Available files in context {$cmcontext->id}:");
    foreach ($all_files as $f) {
        error_log("  - " . $f->get_filepath() . $f->get_filename());
    }
    
    exit;
}

// Serve the file
$mimetype = $file->get_mimetype();
$filesize = $file->get_filesize();

header('Content-Type: ' . $mimetype);
header('Content-Length: ' . $filesize);
header('Cache-Control: public, max-age=3600');

echo $file->get_content();
exit;
