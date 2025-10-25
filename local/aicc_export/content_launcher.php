<?php

// Standalone SCORM content launcher for external LMS systems
// This serves the actual SCORM content and sets up HACP communication

// Get parameters
$cmid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$session_id = isset($_GET['session_id']) ? $_GET['session_id'] : '';
$command = isset($_GET['command']) ? $_GET['command'] : 'getparam';
$student_id = isset($_GET['AICC_SID']) ? $_GET['AICC_SID'] : '';
if (empty($student_id)) {
    $student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
}

if (!$cmid) {
    http_response_code(400);
    echo "Error: Missing course module ID";
    exit;
}

// If this is a HACP communication request (has session_id), redirect to HACP endpoint
if (!empty($session_id)) {
    $hacp_url = '/lms-one/local/aicc_hacp/endpoint.php?command=' . urlencode($command) . '&session_id=' . urlencode($session_id);
    if (!empty($student_id)) {
        $hacp_url .= '&AICC_SID=' . urlencode($student_id);
    }
    header('Location: ' . $hacp_url);
    exit;
}

// This is a content launch request - redirect to SCORM content server
$scorm_content_url = '/lms-one/local/aicc_export/scorm_content_server.php?id=' . $cmid;
if (!empty($student_id)) {
    $scorm_content_url .= '&student_id=' . urlencode($student_id);
}
header('Location: ' . $scorm_content_url);
exit;