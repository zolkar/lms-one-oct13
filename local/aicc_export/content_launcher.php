<?php

// Define constants to bypass login requirements for external access
define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../config.php');

// Security: Only allow this endpoint if plugins are properly configured
if (!get_config('local_aicc_export', 'enabled') || !get_config('local_aicc_hacp', 'enabled')) {
    http_response_code(403);
    echo "Error: Service not enabled";
    exit;
}

// AICC content launcher for external LMS systems
// This launches SCORM content for external students via AICC HACP

// Security: Validate token
// Handle &amp; encoding issue by decoding HTML entities in the query string
$token = '';
if (isset($_GET['token'])) {
    $token = $_GET['token'];
} elseif (isset($_GET['amp;token'])) {
    // Handle &amp; being converted to &amp;token key
    $token = $_GET['amp;token'];
} else {
    // Try parsing from raw query string
    $query_string = $_SERVER['QUERY_STRING'] ?? '';
    if (preg_match('/[&;]token=([^&;]+)/', $query_string, $matches)) {
        $token = urldecode($matches[1]);
    } elseif (preg_match('/amp;token=([^&]+)/', $query_string, $matches)) {
        $token = urldecode($matches[1]);
    }
}

if (empty($token) && isset($_POST['token'])) {
    $token = $_POST['token'];
}

if (empty($token)) {
    http_response_code(401);
    echo "Error: Missing access token\n";
    echo "<!-- Debug: REQUEST_URI = " . htmlspecialchars($_SERVER['REQUEST_URI'] ?? '') . " -->\n";
    echo "<!-- Debug: QUERY_STRING = " . htmlspecialchars($_SERVER['QUERY_STRING'] ?? '') . " -->\n";
    echo "<!-- Debug: GET = " . print_r($_GET, true) . " -->\n";
    exit;
}

// Log for debugging
error_log("Content launcher called with token: " . substr($token, 0, 50) . "...");

require_once($CFG->dirroot . '/local/aicc_hacp/classes/secure_auth.php');
$token_data = \local_aicc_hacp\secure_auth::validate_launch_token($token);
if (!$token_data) {
    http_response_code(401);
    echo "Error: Invalid or expired access token";
    exit;
}

// Get parameters
$cmid = required_param('id', PARAM_INT);
$student_id = optional_param('AICC_SID', '', PARAM_ALPHANUMEXT);
if (empty($student_id)) {
    $student_id = optional_param('student_id', '', PARAM_ALPHANUMEXT);
}

// Get course module and SCORM info
$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
if ($cm->modname !== 'scorm') {
    throw new \moodle_exception('error_non_scorm_hacp', 'local_aicc_export');
}

$scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);

// Validate token matches the requested SCORM activity
if (isset($token_data['scormid']) && $token_data['scormid'] > 0) {
    if ($token_data['scormid'] != $scorm->id) {
        http_response_code(403);
        echo "Error: Token does not match requested SCORM activity";
        exit;
    }
}
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

// Enable AICC HACP for this SCORM activity if not already enabled
// Note: allowaicchacp property may not exist in all Moodle versions
if (property_exists($scorm, 'allowaicchacp') && !$scorm->allowaicchacp) {
    $scorm->allowaicchacp = 1;
    $DB->update_record('scorm', $scorm);
}

// Get the first SCO for this SCORM activity
$scoes = $DB->get_records('scorm_scoes', ['scorm' => $scorm->id], 'id', 'id', 0, 1);
if (empty($scoes)) {
    throw new \moodle_exception('error_no_sco', 'local_aicc_export');
}
$sco = reset($scoes);

// Try to get student_id from session or generate one
if (empty($student_id)) {
    // Try to get from session
    session_start();
    if (isset($_SESSION['aicc_student_id'])) {
        $student_id = $_SESSION['aicc_student_id'];
    } else {
        $student_id = 'external_student_' . time() . '_' . rand(1000, 9999);
        $_SESSION['aicc_student_id'] = $student_id;
        session_write_close();
    }
}

// Try to extract name and email from AICC parameters
$student_name = optional_param('student_name', '', PARAM_TEXT);
$student_email = optional_param('student_email', '', PARAM_EMAIL);

// If no email provided, generate one from student_id
if (empty($student_email)) {
    // Generate email from student_id or use a default
    if (!empty($student_id)) {
        // Use the student_id from AICC to create a unique email
        $student_email = str_replace([' ', '_'], '.', $student_id) . '@external-lms.local';
    } else {
        $student_email = 'external_student_' . time() . '@external-lms.local';
    }
}

// Generate name if not provided
if (empty($student_name)) {
    $student_name = 'External Student ' . substr($student_id, 0, 10);
}

// Create or get external user account
require_once($CFG->dirroot . '/local/aicc_hacp/classes/session_persistence.php');
$external_user_id = \local_aicc_hacp\session_persistence::create_external_user_account(
    $student_email,
    $student_name ?: 'External Student',
    $token_data['external_lms'] ?? 'Unknown LMS'
);

// Create persistent session for this external student
$persistent_session = \local_aicc_hacp\session_persistence::get_persistent_session(
    $student_id, 
    $scorm->id, 
    $sco->id, 
    $_SERVER['HTTP_REFERER'] ?? ''
);

// Update the persistent session with name and email
$persistent_session->student_name = $student_name ?: 'External Student';
$persistent_session->student_email = $student_email;
$persistent_session->userid = $external_user_id;
$DB->update_record('local_aicc_hacp_persistent_sessions', $persistent_session);

// Create HACP session
try {
    $hacp_session_id = \local_aicc_hacp\session_persistence::create_hacp_session(
        $student_id, 
        $scorm->id, 
        $sco->id, 
        $_SERVER['HTTP_REFERER'] ?? ''
    );
    
    error_log("Created HACP session: {$hacp_session_id} for student {$student_id}");
    
} catch (Exception $e) {
    error_log("Error creating HACP session: " . $e->getMessage());
    http_response_code(500);
    echo "Error creating session: " . $e->getMessage();
    exit;
}

// Serve the content directly without redirect
// Get the SCORM package and construct the launch URL
$fs = get_file_storage();
$cmcontext = context_module::instance($cm->id);

// Find the main SCO file (typically the entry point)
// Get all files for this SCORM activity
$sco_files = $fs->get_area_files($cmcontext->id, 'mod_scorm', 'content', 0);

if (empty($sco_files)) {
    http_response_code(404);
    echo "Error: No SCORM content files found";
    error_log("No SCORM files found for context {$cmcontext->id}");
    exit;
}

// Find the entry point file
$main_file = null;
if (isset($sco->launch) && !empty($sco->launch)) {
    // Look for file matching the launch path
    $launch_file = trim($sco->launch, '/');
    $launch_path = dirname($launch_file) . '/';
    $launch_filename = basename($launch_file);
    
    error_log("Looking for launch file: path={$launch_path}, filename={$launch_filename}");
    
    foreach ($sco_files as $file) {
        if ($file->get_filepath() === $launch_path && $file->get_filename() === $launch_filename) {
            $main_file = $file;
            error_log("Found launch file: " . $file->get_filepath() . $file->get_filename());
            break;
        }
    }
    
    if (!$main_file) {
        // Try just filename match
        foreach ($sco_files as $file) {
            if ($file->get_filename() === $launch_filename) {
                $main_file = $file;
                error_log("Found by filename only: " . $file->get_filepath() . $file->get_filename());
                break;
            }
        }
    }
}

if (!$main_file) {
    // Fallback: find any index.html
    foreach ($sco_files as $file) {
        if ($file->get_filename() === 'index.html') {
            $main_file = $file;
            error_log("Found index.html: " . $file->get_filepath());
            break;
        }
    }
}

if (!$main_file && !empty($sco_files)) {
    // Last resort: use first file
    $main_file = reset($sco_files);
    error_log("Using first file: " . $main_file->get_filepath() . $main_file->get_filename());
}

if (!$main_file) {
    http_response_code(404);
    echo "Error: Could not find entry point file";
    error_log("ERROR: No main file found!");
    exit;
}

// Instead of using pluginfile which requires login, serve the file content directly
// This allows external access without requiring authentication

// Build the HACP URL for this session  
$hacp_url = $CFG->wwwroot . '/local/aicc_hacp/endpoint.php?session_id=' . $hacp_session_id;

// Get the file content
$file_content = $main_file->get_content();

// If it's an HTML file, we need to wrap it with AICC API and rewrite URLs
if (strpos($main_file->get_filename(), '.html') !== false) {
    // Serve the HTML file directly with AICC API embedded
    header('Content-Type: text/html; charset=utf-8');
    
    // Inject AICC API into the HTML
    $html = $file_content;
    
    // Rewrite relative URLs to point to our file server
    $file_server_url = $CFG->wwwroot . '/local/aicc_export/file_server.php?id=' . $cm->id . '&file=';
    
    // Get the base path of the main file
    $base_path = $main_file->get_filepath();
    error_log("Base path: {$base_path}");
    
    // Replace relative URLs (handle paths like "res/data/player.js" or just "player.js")
    // Patterns: src="res/data/file.js" or src="/res/data/file.js" or src="./file.js"
    $html = preg_replace_callback(
        '/(src|href)=["\']([^"\']+\.(js|css|png|jpg|gif|mp4|mp3|swf|xml|json|pdf))["\']/i',
        function($matches) use ($file_server_url, $base_path) {
            $url = $matches[2];
            // Remove leading ./
            $url = ltrim($url, './');
            // If absolute path, remove leading /
            if (strpos($url, '/') === 0) {
                $url = ltrim($url, '/');
            } else {
                // Relative to current file
                $url = $base_path . $url;
            }
            return $matches[1] . '="' . $file_server_url . $url . '"';
        },
        $html
    );
    
    error_log("HTML with rewritten URLs (first 500 chars): " . substr($html, 0, 500));
    
    // Find the closing </head> tag and inject AICC API
    $aicc_js = '
    <script type="text/javascript">
        var AICC_URL = "' . $hacp_url . '";
        var AICC_SID = "' . $student_id . '";
        
        // Enhanced AICC API implementation
        function LMSInitialize(parameter) {
            try {
                var xhr = new XMLHttpRequest();
                xhr.open("POST", AICC_URL, false);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                
                var data = "command=GetParam&AICC_SID=" + encodeURIComponent(AICC_SID);
                xhr.send(data);
                
                if (xhr.status === 200) {
                    console.log("LMSInitialize: " + xhr.responseText);
                    return "true";
                }
            } catch (e) {
                console.error("LMSInitialize error:", e);
            }
            return "true";
        }
        
        function LMSFinish(parameter) {
            try {
                var xhr = new XMLHttpRequest();
                xhr.open("POST", AICC_URL, false);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                
                var data = "command=ExitAU&AICC_SID=" + encodeURIComponent(AICC_SID);
                xhr.send(data);
                
                console.log("LMSFinish: " + xhr.responseText);
                return "true";
            } catch (e) {
                console.error("LMSFinish error:", e);
            }
            return "true";
        }
        
        function LMSGetValue(element) {
            try {
                var xhr = new XMLHttpRequest();
                xhr.open("POST", AICC_URL, false);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                
                var data = "command=GetParam&AICC_SID=" + encodeURIComponent(AICC_SID);
                xhr.send(data);
                
                if (xhr.status === 200) {
                    var response = xhr.responseText;
                    // Parse the response to extract the value
                    var regex = new RegExp("\\\\|" + element + "\\\\|?([^\\\\|]+)", "i");
                    var match = response.match(regex);
                    return match ? match[1] : "";
                }
            } catch (e) {
                console.error("LMSGetValue error:", e);
            }
            return "";
        }
        
        function LMSSetValue(element, value) {
            try {
                var xhr = new XMLHttpRequest();
                xhr.open("POST", AICC_URL, false);
                xhr.setRequestHeader("Content-Type", "application/x-www-form-urlencoded");
                
                // Build AICC data
                var aiccData = "Core\\\\";
                aiccData += "Student_ID=" + AICC_SID + "\\\\";
                aiccData += element + "=" + value + "\\\\";
                
                var data = "command=PutParam&AICC_SID=" + encodeURIComponent(AICC_SID) + "&AICC_DATA=" + encodeURIComponent(aiccData);
                xhr.send(data);
                
                console.log("LMSSetValue: " + element + "=" + value);
                return "true";
            } catch (e) {
                console.error("LMSSetValue error:", e);
            }
            return "true";
        }
        
        function LMSCommit(comment) {
            // Same as LMSSetValue but for committing all changes
            return "true";
        }
        
        // Make API available globally
        window.LMSInitialize = LMSInitialize;
        window.LMSFinish = LMSFinish;
        window.LMSGetValue = LMSGetValue;
        window.LMSSetValue = LMSSetValue;
        window.LMSCommit = LMSCommit;
    </script>
    ';
    
    if (strpos($html, '</head>') !== false) {
        $html = str_replace('</head>', $aicc_js . '</head>', $html);
    } else {
        $html = $aicc_js . $html;
    }
    
    echo $html;
} else {
    // For non-HTML files, serve as-is
    header('Content-Type: ' . $main_file->get_mimetype());
    echo $file_content;
}

exit;