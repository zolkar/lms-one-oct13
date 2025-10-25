<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/mod/scorm/locallib.php');

// Direct SCORM content launcher for external LMS systems
// This bypasses Moodle's player interface and serves content directly

define('NO_MOODLE_PAGE', true);
define('NO_OUTPUT_BUFFERING', true);

$cmid = required_param('id', PARAM_INT);
$session_id = optional_param('session_id', '', PARAM_ALPHANUM);
$command = optional_param('command', 'getparam', PARAM_ALPHA);
$student_id = optional_param('student_id', '', PARAM_RAW);

// Debug: Log incoming parameters
error_log("AICC Export - Incoming request parameters:");
error_log("cmid: " . $cmid);
error_log("session_id: " . $session_id);
error_log("command: " . $command);
error_log("student_id: " . $student_id);

// Get the course module
$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

// Verify it's a SCORM activity
if ($cm->modname !== 'scorm') {
    http_response_code(400);
    echo "Error: Not a SCORM activity";
    exit;
}

$scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);

// Create or get HACP user for content access
function get_or_create_hacp_user() {
    global $DB;
    
    // Look for existing HACP user
    $hacp_user = $DB->get_record('user', ['username' => 'hacp_user'], 'id');
    if ($hacp_user) {
        return $hacp_user->id;
    }
    
    // Create new HACP user
    $user = new \stdClass();
    $user->username = 'hacp_user';
    $user->firstname = 'HACP';
    $user->lastname = 'User';
    $user->email = 'hacp@localhost';
    $user->confirmed = 1;
    $user->mnethostid = 1;
    $user->timecreated = time();
    $user->timemodified = time();
    
    $userid = $DB->insert_record('user', $user);
    return $userid;
}

// Set the HACP user as the current user (for content access)
$hacp_user_id = get_or_create_hacp_user();
$USER = $DB->get_record('user', ['id' => $hacp_user_id], '*', MUST_EXIST);

// If this is an AICC HACP request (has session_id), handle it
if (!empty($session_id)) {
    // This is a HACP communication request
    // Redirect to HACP endpoint for protocol communication
    $hacp_url = new \moodle_url('/local/aicc_hacp/endpoint.php', [
        'command' => $command,
        'session_id' => $session_id
    ]);
    redirect($hacp_url);
}

// Get the first SCO for this SCORM activity
$scoes = $DB->get_records('scorm_scoes', ['scorm' => $scorm->id], 'id', 'id,identifier,title', 0, 1);
if (empty($scoes)) {
    http_response_code(404);
    echo "Error: No SCO found in SCORM activity";
    exit;
}
$sco = reset($scoes);

// Create or update AICC session record for HACP communication
$aicc_session = new \stdClass();
$aicc_session->session_id = 'DIRECT_' . $cmid . '_' . time();
$aicc_session->scormid = $scorm->id;
$aicc_session->scoid = $sco->id;
$aicc_session->userid = $hacp_user_id;
$aicc_session->student_id = $student_id;
$aicc_session->status = 'active';
$aicc_session->created_at = time();
$aicc_session->last_activity_at = time();

// Check if session already exists for this student and SCORM
$existing = null;
if (!empty($student_id)) {
    $existing = $DB->get_record('local_aicc_export_sessions', [
        'student_id' => $student_id,
        'scormid' => $scorm->id,
        'scoid' => $sco->id
    ]);
}

if ($existing) {
    $aicc_session->id = $existing->id;
    $aicc_session->last_activity_at = time();
    try {
        $DB->update_record('local_aicc_export_sessions', $aicc_session);
    } catch (Exception $e) {
        error_log("AICC Export DB Update Error: " . $e->getMessage());
        http_response_code(500);
        echo "Error: Database update failed - " . $e->getMessage();
        exit;
    }
} else {
    try {
        $id = $DB->insert_record('local_aicc_export_sessions', $aicc_session);
    } catch (Exception $e) {
        error_log("AICC Export DB Error: " . $e->getMessage());
        http_response_code(500);
        echo "Error: Database insertion failed - " . $e->getMessage();
        exit;
    }
}

// Serve a minimal SCORM content page without any Moodle interface
header('Content-Type: text/html; charset=UTF-8');
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo format_string($scorm->name); ?></title>
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
    </style>
</head>
<body>
    <div class="scorm-content">
        <div class="content-info">
            <h2><?php echo format_string($scorm->name); ?></h2>
            <p>SCORM Content Ready for External LMS</p>
            <?php if (!empty($student_id)): ?>
                <p>Student ID: <span class="student-id"><?php echo htmlspecialchars($student_id); ?></span></p>
            <?php endif; ?>
            <p>SCO: <?php echo htmlspecialchars($sco->title ?? $sco->identifier ?? 'Unknown'); ?></p>
        </div>
        
        <div class="hacp-info">
            <h3>HACP Communication Setup</h3>
            <p><strong>Session ID:</strong> <?php echo $aicc_session->session_id; ?></p>
            <p><strong>HACP Endpoint:</strong> /local/aicc_hacp/endpoint.php</p>
            <p><strong>Commands:</strong> getparam, putparam, exitau</p>
            <p><strong>Status:</strong> Ready for SCORM content communication</p>
        </div>
    </div>

    <script>
        // HACP communication functions for SCORM content
        var hacpEndpoint = '/local/aicc_hacp/endpoint.php';
        var sessionId = '<?php echo $aicc_session->session_id; ?>';
        var studentId = '<?php echo htmlspecialchars($student_id); ?>';
        
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
        
        console.log('HACP Communication Ready');
        console.log('Session ID:', sessionId);
        console.log('Student ID:', studentId);
        console.log('HACP Functions: hacpGetParam(), hacpPutParam(data), hacpExitAu()');
    </script>
</body>
</html>
