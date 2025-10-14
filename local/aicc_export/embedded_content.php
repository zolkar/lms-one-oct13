<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/mod/scorm/locallib.php');

// Embedded SCORM content launcher for external LMS systems
// This serves SCORM content in a minimal, iframe-friendly format without Moodle navigation

define('NO_MOODLE_PAGE', true);

$cmid = required_param('id', PARAM_INT);
$session_id = optional_param('session_id', '', PARAM_ALPHANUM);
$command = optional_param('command', 'getparam', PARAM_ALPHA);

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

// Set up the course context
$context = \context_course::instance($course->id);

// If this is an AICC HACP request (has session_id), handle it
if (!empty($session_id)) {
    // This is a HACP communication request
    // Redirect to AICC handler for protocol communication
    $aicc_url = new \moodle_url('/mod/scorm/aicc.php', [
        'command' => $command,
        'session_id' => $session_id
    ]);
    redirect($aicc_url);
}

// This is a content launch request - serve embedded SCORM content
// Get the first SCO for this SCORM activity
$scoes = $DB->get_records('scorm_scoes', ['scorm' => $scorm->id], 'id', 'id', 0, 1);
if (empty($scoes)) {
    http_response_code(404);
    echo "Error: No SCO found in SCORM activity";
    exit;
}
$sco = reset($scoes); // Get the first (and only) record

// Create or update AICC session record for HACP communication
$aicc_session = new \stdClass();
$aicc_session->hacpsession = 'EMBEDDED_' . $cmid . '_' . time();
$aicc_session->scormid = $scorm->id;
$aicc_session->scoid = $sco->id;
$aicc_session->userid = $hacp_user_id;
$aicc_session->timecreated = time();
$aicc_session->timemodified = time();

// Check if session already exists
$existing = $DB->get_record('scorm_aicc_session', ['hacpsession' => $aicc_session->hacpsession]);
if ($existing) {
    $aicc_session->id = $existing->id;
    $aicc_session->timemodified = time();
    $DB->update_record('scorm_aicc_session', $aicc_session);
} else {
    $DB->insert_record('scorm_aicc_session', $aicc_session);
}

// Create a custom embedded SCORM launcher that bypasses Moodle navigation
// We'll create a minimal page that loads the SCORM content directly

// Use our custom file server to bypass authentication issues
$custom_launch_url = new \moodle_url('/local/aicc_export/scorm_file_server.php', [
    'id' => $cmid
]);

// Serve minimal HTML page with SCORM content
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
        .scorm-container {
            width: 100%;
            height: 100vh;
            border: none;
            background-color: white;
        }
        .loading {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: white;
        }
        .loading-text {
            font-size: 16px;
            color: #666;
        }
        .error {
            display: flex;
            justify-content: center;
            align-items: center;
            height: 100vh;
            background-color: white;
            color: #d32f2f;
        }
    </style>
</head>
<body>
    <div class="loading" id="loading">
        <div class="loading-text">Loading SCORM content...</div>
    </div>
    
    <div class="error" id="error" style="display: none;">
        <div>Unable to load SCORM content. Please check that the SCORM package is properly configured.</div>
    </div>
    
    <iframe id="scorm-frame" 
            class="scorm-container" 
            src="<?php echo $custom_launch_url; ?>" 
            frameborder="0"
            style="display: none;"
            onerror="showError()">
    </iframe>

    <script>
        function showError() {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('scorm-frame').style.display = 'none';
            document.getElementById('error').style.display = 'flex';
        }
        
        // Hide loading and show content when iframe loads
        document.getElementById('scorm-frame').onload = function() {
            document.getElementById('loading').style.display = 'none';
            document.getElementById('scorm-frame').style.display = 'block';
        };
        
        // Fallback: show content after 5 seconds even if onload doesn't fire
        setTimeout(function() {
            if (document.getElementById('loading').style.display !== 'none') {
                showError();
            }
        }, 5000);
    </script>
</body>
</html>
