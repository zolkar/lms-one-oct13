<?php

// Completely standalone SCORM file server
// This serves SCORM files without any Moodle dependencies

// Get parameters
$cmid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$scoid = isset($_GET['scoid']) ? (int)$_GET['scoid'] : 0;
$file = isset($_GET['file']) ? $_GET['file'] : '';

if (!$cmid || !$scoid) {
    http_response_code(400);
    echo "Error: Missing required parameters";
    exit;
}

// Database connection parameters (hardcoded to avoid Moodle config issues)
$dbhost = 'moodle_db';
$dbname = 'lms_one';
$dbuser = 'root';
$dbpass = 'example';

try {
    $db = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo "Database connection error: " . $e->getMessage();
    exit;
}

// Get the SCO
$stmt = $db->prepare("SELECT * FROM mdl_scorm_scoes WHERE id = ?");
$stmt->execute([$scoid]);
$sco = $stmt->fetch(PDO::FETCH_OBJ);

if (!$sco) {
    http_response_code(404);
    echo "Error: SCO not found";
    exit;
}

// Get the SCORM activity
$stmt = $db->prepare("SELECT * FROM mdl_scorm WHERE id = ?");
$stmt->execute([$sco->scorm]);
$scorm = $stmt->fetch(PDO::FETCH_OBJ);

if (!$scorm) {
    http_response_code(404);
    echo "Error: SCORM activity not found";
    exit;
}

// Get the course module
$stmt = $db->prepare("SELECT * FROM mdl_course_modules WHERE id = ?");
$stmt->execute([$cmid]);
$cm = $stmt->fetch(PDO::FETCH_OBJ);

if (!$cm) {
    http_response_code(404);
    echo "Error: Course module not found";
    exit;
}

// Determine which file to serve
$target_file = $file ?: $sco->launch;
if (!$target_file) {
    $target_file = 'index.html'; // Default fallback
}

// If no specific file requested, serve the main launch file
if (empty($file)) {
    $file = $sco->launch;
}

// Get the context ID for the course module
$stmt = $db->prepare("SELECT * FROM mdl_context WHERE contextlevel = 70 AND instanceid = ?");
$stmt->execute([$cmid]);
$context = $stmt->fetch(PDO::FETCH_OBJ);

if (!$context) {
    http_response_code(404);
    echo "Error: Context not found for course module";
    exit;
}

// Get the SCORM package file
$stmt = $db->prepare("SELECT * FROM mdl_files WHERE component = 'mod_scorm' AND filearea = 'package' AND itemid = 0 AND contextid = ?");
$stmt->execute([$context->id]);
$package_file = $stmt->fetch(PDO::FETCH_OBJ);

if (!$package_file) {
    http_response_code(404);
    echo "Error: SCORM package not found";
    exit;
}

// Extract the ZIP file to a temporary directory
$temp_dir = sys_get_temp_dir() . '/scorm_' . $cmid . '_' . time();
if (!mkdir($temp_dir, 0755, true)) {
    http_response_code(500);
    echo "Error: Could not create temporary directory";
    exit;
}

// Extract ZIP file - Moodle stores files using content hash
$content_hash = $package_file->contenthash;
$zip_path = '/var/www/lms-one-data/filedir/' . substr($content_hash, 0, 2) . '/' . substr($content_hash, 2, 2) . '/' . $content_hash;
if (!file_exists($zip_path)) {
    http_response_code(404);
    echo "Error: Package file not found on disk. Path: " . $zip_path;
    exit;
}

$zip = new ZipArchive();
if ($zip->open($zip_path) !== TRUE) {
    http_response_code(500);
    echo "Error: Could not open package file";
    exit;
}

$zip->extractTo($temp_dir);
$zip->close();

// Find the target file in the extracted content
$target_path = $temp_dir . '/' . $target_file;
if (!file_exists($target_path)) {
    // Try to find the file in subdirectories
    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($temp_dir));
    foreach ($iterator as $file) {
        if ($file->isFile() && basename($file->getPathname()) === basename($target_file)) {
            $target_path = $file->getPathname();
            break;
        }
    }
}

if (!file_exists($target_path)) {
    http_response_code(404);
    echo "Error: File not found in package: " . $target_file;
    exit;
}

// Determine content type
$extension = strtolower(pathinfo($target_path, PATHINFO_EXTENSION));
$content_types = [
    'html' => 'text/html',
    'htm' => 'text/html',
    'js' => 'application/javascript',
    'css' => 'text/css',
    'json' => 'application/json',
    'xml' => 'application/xml',
    'png' => 'image/png',
    'jpg' => 'image/jpeg',
    'jpeg' => 'image/jpeg',
    'gif' => 'image/gif',
    'svg' => 'image/svg+xml',
    'swf' => 'application/x-shockwave-flash',
    'mp4' => 'video/mp4',
    'mp3' => 'audio/mpeg',
    'wav' => 'audio/wav'
];

$content_type = isset($content_types[$extension]) ? $content_types[$extension] : 'application/octet-stream';

// Set headers
header('Content-Type: ' . $content_type);
header('Content-Length: ' . filesize($target_path));

// Serve the file
readfile($target_path);

// Clean up temporary directory
function cleanup_temp_dir($dir) {
    if (is_dir($dir)) {
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                cleanup_temp_dir($path);
            } else {
                unlink($path);
            }
        }
        rmdir($dir);
    }
}

// Clean up after serving (in background)
register_shutdown_function(function() use ($temp_dir) {
    cleanup_temp_dir($temp_dir);
});