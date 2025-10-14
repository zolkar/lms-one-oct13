<?php

require_once(__DIR__ . '/../../config.php');
require_once($CFG->dirroot.'/mod/scorm/locallib.php');

// Custom SCORM file server for embedded content
// This bypasses normal Moodle authentication for AICC/HACP access

define('NO_MOODLE_PAGE', true);

$cmid = required_param('id', PARAM_INT);
$file = optional_param('file', '', PARAM_RAW);

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

// If a specific file is requested, serve it
if (!empty($file)) {
    // Get the file from the SCORM package
    $fs = get_file_storage();
    $file_record = [
        'contextid' => \context_module::instance($cmid)->id,
        'component' => 'mod_scorm',
        'filearea' => 'content',
        'itemid' => 0,
        'filepath' => '/',
        'filename' => $file
    ];
    
    $stored_file = $fs->get_file($file_record['contextid'], $file_record['component'], 
                                 $file_record['filearea'], $file_record['itemid'], 
                                 $file_record['filepath'], $file_record['filename']);
    
    if ($stored_file) {
        // Serve the file
        send_stored_file($stored_file, 0, 0, false);
    } else {
        http_response_code(404);
        echo "File not found: " . htmlspecialchars($file);
    }
    exit;
}

// No specific file requested, serve the main SCORM content
// Get the first SCO for this SCORM activity
$scoes = $DB->get_records('scorm_scoes', ['scorm' => $scorm->id], 'id', 'id', 0, 1);
if (empty($scoes)) {
    http_response_code(404);
    echo "Error: No SCO found in SCORM activity";
    exit;
}
$sco = reset($scoes); // Get the first (and only) record

// Instead of using complex SCORM functions, create a simple embedded launcher
// that loads the SCORM player in popup mode (which has minimal navigation)
$player_url = new \moodle_url('/mod/scorm/player.php', [
    'id' => $cmid,
    'scoid' => $sco->id,
    'display' => 'popup',
    'mode' => 'normal'
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
            src="<?php echo $player_url; ?>" 
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
