<?php
// Simple redirect handler for SCORM data files
// This catches requests to /lms-one/local/aicc_export/data/ and redirects them to the correct URLs

// Get the current URL
$current_url = $_SERVER['REQUEST_URI'];

// Extract the file path from the URL
$file_path = str_replace('/lms-one/local/aicc_export/data/', '', $current_url);
// Remove query string from file path
if (strpos($file_path, '?') !== false) {
    $file_path = substr($file_path, 0, strpos($file_path, '?'));
}

// Get the course module ID from the referer or query string
$cmid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if (!$cmid && isset($_SERVER['HTTP_REFERER'])) {
    // Try to extract cmid from referer
    if (preg_match('/id=(\d+)/', $_SERVER['HTTP_REFERER'], $matches)) {
        $cmid = (int)$matches[1];
    }
}

if (!$cmid) {
    http_response_code(400);
    echo "Error: Missing course module ID";
    exit;
}

// Get student_id from query string or referer
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
if (empty($student_id) && isset($_SERVER['HTTP_REFERER'])) {
    // Try to extract student_id from referer
    if (preg_match('/student_id=([^&]+)/', $_SERVER['HTTP_REFERER'], $matches)) {
        $student_id = $matches[1];
    }
}

// Construct the correct URL
$correct_url = '/lms-one/local/aicc_export/scorm_content_server.php?id=' . $cmid . '&file=res/data/' . $file_path;
if (!empty($student_id)) {
    $correct_url .= '&student_id=' . urlencode($student_id);
}

// Redirect to the correct URL
header('Location: ' . $correct_url);
exit;
?>
