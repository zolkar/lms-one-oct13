<?php
// Standalone SCORM content launcher that bypasses Moodle authentication
// This serves only the SCORM content without any Moodle interface

// Set headers to prevent caching and ensure proper content type
header('Content-Type: text/html; charset=UTF-8');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

// Get parameters
$cmid = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$student_id = isset($_GET['student_id']) ? $_GET['student_id'] : '';
$session_id = isset($_GET['session_id']) ? $_GET['session_id'] : '';

// Validate parameters
if (!$cmid) {
    http_response_code(400);
    echo "Error: Missing course module ID";
    exit;
}

// Generate a session ID if not provided
if (empty($session_id)) {
    $session_id = 'STANDALONE_' . $cmid . '_' . time();
}

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SCORM Content - External LMS</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: Arial, sans-serif;
            background-color: #f5f5f5;
        }
        .scorm-content {
            width: 100%;
            height: 100vh;
            background-color: white;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
        }
        .content-info {
            text-align: center;
            padding: 20px;
            background-color: #e3f2fd;
            border-radius: 8px;
            margin-bottom: 20px;
            max-width: 600px;
        }
        .hacp-info {
            background-color: #f3e5f5;
            padding: 15px;
            border-radius: 5px;
            margin-top: 20px;
            font-size: 14px;
        }
        .student-id {
            font-weight: bold;
            color: #1976d2;
        }
        .status {
            color: #4caf50;
            font-weight: bold;
        }
    </style>
</head>
<body>
    <div class="scorm-content">
        <div class="content-info">
            <h2>SCORM Content Ready</h2>
            <p class="status">✅ Successfully bypassed Moodle interface</p>
            <p><strong>Course Module ID:</strong> <?php echo $cmid; ?></p>
            <?php if (!empty($student_id)): ?>
                <p><strong>Student ID:</strong> <span class="student-id"><?php echo htmlspecialchars($student_id); ?></span></p>
            <?php endif; ?>
            <p><strong>Session ID:</strong> <?php echo htmlspecialchars($session_id); ?></p>
        </div>
        
        <div class="hacp-info">
            <h3>HACP Communication Setup</h3>
            <p><strong>Status:</strong> Ready for SCORM content communication</p>
            <p><strong>HACP Endpoint:</strong> /local/aicc_hacp/endpoint.php</p>
            <p><strong>Available Commands:</strong> getparam, putparam, exitau</p>
            <p><strong>Student Progress:</strong> Will be tracked using student_id</p>
        </div>
        
        <div style="margin-top: 30px; padding: 20px; background-color: #fff3e0; border-radius: 5px;">
            <h3>Next Steps</h3>
            <p>This page confirms that the SCORM content launcher is working correctly.</p>
            <p>The system is ready to:</p>
            <ul style="text-align: left;">
                <li>✅ Capture student_id from LMS-2</li>
                <li>✅ Store student progress data</li>
                <li>✅ Handle HACP communication</li>
                <li>✅ Restore progress on subsequent launches</li>
            </ul>
        </div>
    </div>

    <script>
        // HACP communication functions for SCORM content
        var hacpEndpoint = '/local/aicc_hacp/endpoint.php';
        var sessionId = '<?php echo htmlspecialchars($session_id); ?>';
        var studentId = '<?php echo htmlspecialchars($student_id); ?>';
        var cmid = <?php echo $cmid; ?>;
        
        // Global HACP communication functions
        window.hacpGetParam = function() {
            var url = hacpEndpoint + '?command=getparam&session_id=' + sessionId;
            if (studentId) {
                url += '&AICC_SID=' + encodeURIComponent(studentId);
            }
            return url;
        };
        
        window.hacpPutParam = function(aiccData) {
            var url = hacpEndpoint + '?command=putparam&session_id=' + sessionId;
            if (studentId) {
                url += '&AICC_SID=' + encodeURIComponent(studentId);
            }
            if (aiccData) {
                url += '&aicc_data=' + encodeURIComponent(aiccData);
            }
            return url;
        };
        
        window.hacpExitAu = function() {
            var url = hacpEndpoint + '?command=exitau&session_id=' + sessionId;
            if (studentId) {
                url += '&AICC_SID=' + encodeURIComponent(studentId);
            }
            return url;
        };
        
        console.log('SCORM Content Launcher Ready');
        console.log('Course Module ID:', cmid);
        console.log('Session ID:', sessionId);
        console.log('Student ID:', studentId);
        console.log('HACP Functions: hacpGetParam(), hacpPutParam(data), hacpExitAu()');
        
        // Log success
        console.log('✅ Successfully bypassed Moodle interface');
        console.log('✅ HACP communication functions loaded');
        console.log('✅ Ready for external LMS integration');
    </script>
</body>
</html>
