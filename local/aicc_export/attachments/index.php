<?php
// Attachment handler for SCORM content
// This serves PDF and other attachment files from SCORM packages

// Get parameters
$cmid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$file = isset($_GET['file']) ? $_GET['file'] : '';

if (!$cmid) {
    http_response_code(400);
    echo "Error: Missing course module ID";
    exit;
}

if (empty($file)) {
    http_response_code(400);
    echo "Error: Missing file parameter";
    exit;
}

// Database connection parameters
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

// Get the course module
$stmt = $db->prepare("SELECT * FROM mdl_course_modules WHERE id = ?");
$stmt->execute([$cmid]);
$cm = $stmt->fetch(PDO::FETCH_OBJ);

if (!$cm) {
    http_response_code(404);
    echo "Error: Course module not found";
    exit;
}

// Get the SCORM activity
$stmt = $db->prepare("SELECT * FROM mdl_scorm WHERE id = ?");
$stmt->execute([$cm->instance]);
$scorm = $stmt->fetch(PDO::FETCH_OBJ);

if (!$scorm) {
    http_response_code(404);
    echo "Error: SCORM activity not found";
    exit;
}

// Get the context for file storage
$stmt = $db->prepare("SELECT * FROM mdl_context WHERE contextlevel = 70 AND instanceid = ?");
$stmt->execute([$cmid]);
$context = $stmt->fetch(PDO::FETCH_OBJ);

if (!$context) {
    http_response_code(404);
    echo "Error: Context not found";
    exit;
}

// Get SCORM package files
$stmt = $db->prepare("SELECT * FROM mdl_files WHERE contextid = ? AND component = 'mod_scorm' AND filearea = 'package' AND filename != '.' ORDER BY id DESC LIMIT 1");
$stmt->execute([$context->id]);
$package_file = $stmt->fetch(PDO::FETCH_OBJ);

if (!$package_file) {
    http_response_code(404);
    echo "Error: SCORM package not found";
    exit;
}

// Extract the ZIP file to a temporary directory
$zip_path = '/var/www/lms-one-data/filedir/' . substr($package_file->contenthash, 0, 2) . '/' . substr($package_file->contenthash, 2, 2) . '/' . $package_file->contenthash;
$extract_dir = sys_get_temp_dir() . '/scorm_package_' . $cmid;

if (!file_exists($extract_dir)) {
    if (!mkdir($extract_dir, 0755, true)) {
        http_response_code(500);
        echo "Error: Failed to create extraction directory";
        exit;
    }
    
    $zip = new ZipArchive();
    if ($zip->open($zip_path) === TRUE) {
        $zip->extractTo($extract_dir);
        $zip->close();
    } else {
        http_response_code(500);
        echo "Error: Failed to extract SCORM package";
        exit;
    }
}

// Look for the attachment file in various possible locations
$possible_paths = [
    $extract_dir . '/' . $file,
    $extract_dir . '/attachments/' . $file,
    $extract_dir . '/res/attachments/' . $file,
    $extract_dir . '/files/' . $file,
    $extract_dir . '/documents/' . $file,
    $extract_dir . '/resources/' . $file,
    $extract_dir . '/res/' . $file
];

$attachment_path = null;
foreach ($possible_paths as $path) {
    if (file_exists($path)) {
        $attachment_path = $path;
        break;
    }
}

if (!$attachment_path || !file_exists($attachment_path)) {
    http_response_code(404);
    echo "Error: Attachment file not found: " . $file;
    exit;
}

// Get file info
$file_info = pathinfo($attachment_path);
$file_extension = strtolower($file_info['extension']);

// Set appropriate content type
$content_types = [
    'pdf' => 'application/pdf',
    'doc' => 'application/msword',
    'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
    'ppt' => 'application/vnd.ms-powerpoint',
    'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
    'txt' => 'text/plain',
    'rtf' => 'application/rtf'
];

$content_type = $content_types[$file_extension] ?? 'application/octet-stream';

// Set headers
header('Content-Type: ' . $content_type);
header('Content-Length: ' . filesize($attachment_path));
header('Content-Disposition: inline; filename="' . basename($file) . '"');
header('Cache-Control: public, max-age=3600');

// Serve the file
readfile($attachment_path);
?>
