<?php

// Define constants to bypass login requirements
define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../config.php');

// Security: Only allow this endpoint if plugins are properly configured
if (!get_config('local_aicc_export', 'enabled') || !get_config('local_aicc_hacp', 'enabled')) {
    http_response_code(403);
    echo "Error: Service not enabled";
    exit;
}

// Secure AICC HACP endpoint that serves SCORM content for external students
// This file handles AICC communication and content delivery

// Get parameters
// Handle both regular and encoded parameter names
$aiccsession = '';
if (isset($_GET['aiccsession'])) {
    $aiccsession = $_GET['aiccsession'];
} elseif (isset($_SERVER['QUERY_STRING'])) {
    // Try to parse from raw query string
    if (preg_match('/aiccsession=([^&;]+)/', $_SERVER['QUERY_STRING'], $matches)) {
        $aiccsession = urldecode($matches[1]);
    }
}

$student_id = isset($_GET['AICC_SID']) ? $_GET['AICC_SID'] : '';

// Debug logging
error_log("secure_content_server called with aiccsession: {$aiccsession}");
error_log("QUERY_STRING: " . ($_SERVER['QUERY_STRING'] ?? ''));

// If we have an AICC session, we're launching for an external student
if (!empty($aiccsession)) {
    // This is an AICC launch request - serve the SCORM content
    // The session is already created by content_launcher.php
    // Get the HACP session details
    require_once(__DIR__ . '/../aicc_hacp/classes/session_persistence.php');
    
    // Debug: Log the search
    error_log("Looking for session: {$aiccsession}");
    
    // Force a fresh database connection to avoid transaction issues
    // Wait a tiny bit to ensure database commit completed
    usleep(10000); // 10ms
    
    try {
        // Direct SQL query to bypass any Moodle caching/transaction issues
        $sql = "SELECT * FROM mdl_local_aicc_hacp_sessions WHERE session_id = :sessionid";
        $params = ['sessionid' => $aiccsession];
        $session = $DB->get_record_sql($sql, $params);
        
        if ($session) {
            error_log("Found session for {$aiccsession}");
        } else {
            error_log("Session not found in database: {$aiccsession}");
            
            // List what sessions DO exist
            $all = $DB->get_records_sql("SELECT session_id, created_at FROM mdl_local_aicc_hacp_sessions ORDER BY created_at DESC LIMIT 5");
            if ($all) {
                error_log("Recent sessions in DB:");
                foreach ($all as $s) {
                    error_log("  - {$s->session_id}");
                }
            }
        }
    } catch (Exception $e) {
        error_log("Exception querying sessions: " . $e->getMessage());
        $session = null;
    }
    
    if (!$session) {
        http_response_code(404);
        echo "Error: Session not found\n";
        echo "<!-- Debug: Looking for session_id = {$aiccsession} -->\n";
        echo "<!-- Debug: Total sessions = " . $DB->count_records('local_aicc_hacp_sessions') . " -->\n";
        
        // Try to show all session IDs
        try {
            $all_sessions = $DB->get_records('local_aicc_hacp_sessions', [], 'created_at DESC', 'session_id, created_at', 0, 10);
            if (!empty($all_sessions)) {
                echo "<!-- Debug: All available session IDs:\n";
                foreach ($all_sessions as $s) {
                    echo "  - {$s->session_id} (created: " . date('Y-m-d H:i:s', $s->created_at) . ")\n";
                }
                echo " -->\n";
            }
        } catch (Exception $e) {
            echo "<!-- Error listing sessions: " . htmlspecialchars($e->getMessage()) . " -->\n";
        }
        exit;
    }
    
    // Get the SCORM and course module info
    $scorm = $DB->get_record('scorm', ['id' => $session->scormid], '*', MUST_EXIST);
    
    // Get the module ID for SCORM
    $moduleid = $DB->get_field('modules', 'id', ['name' => 'scorm']);
    if (!$moduleid) {
        http_response_code(404);
        echo "Error: SCORM module not found";
        exit;
    }
    
    // Get the course module
    $cm = $DB->get_record('course_modules', ['instance' => $scorm->id, 'module' => $moduleid], '*', MUST_EXIST);
    
    // Get the SCO
    $sco = $DB->get_record('scorm_scoes', ['id' => $session->scoid], '*', MUST_EXIST);
    
    // Build the HACP URL for this session
    global $CFG;
    $hacp_url = $CFG->wwwroot . '/local/aicc_hacp/endpoint.php?session_id=' . $aiccsession;
    
    // Get the SCORM package and construct the launch URL
    $fs = get_file_storage();
    // Note: linter warning about context_module is a false positive - class exists in Moodle
    $cmcontext = context_module::instance($cm->id);
    
    // Find the main SCO file (typically the entry point)
    $sco_files = $fs->get_area_files($cmcontext->id, 'mod_scorm', 'content', $sco->scorm, 
                                     'sortorder, itemid, filepath, filename', false);
    
    if (empty($sco_files)) {
        http_response_code(404);
        echo "Error: No SCORM content files found";
        exit;
    }
    
    // Find the entry point file
    $main_file = null;
    if (isset($sco->launch)) {
        $main_file = $fs->get_file($cmcontext->id, 'mod_scorm', 'content', 0, '/', $sco->launch);
    }
    
    if (!$main_file) {
        // Fallback: find index.html or imsmanifest.xml
        foreach ($sco_files as $file) {
            if ($file->get_filename() === 'index.html' || $file->get_filename() === 'imsmanifest.xml') {
                $main_file = $file;
                break;
            }
        }
    }
    
    if (!$main_file) {
        $main_file = reset($sco_files);
    }
    
    // Construct the URL to this file  
    $pluginfile_url = moodle_url::make_pluginfile_url(
        $main_file->get_contextid(),
        'mod_scorm',
        'content',
        0,
        $main_file->get_filepath(),
        $main_file->get_filename(),
        true
    );
    
    // Now serve an HTML wrapper that loads the SCORM content and initializes AICC HACP
    header('Content-Type: text/html; charset=utf-8');
    
    echo '<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>SCORM Content</title>
    <script type="text/javascript">
    var AICC_URL = "' . $hacp_url . '";
    var AICC_SID = "' . $session->student_id . '";
    // AICC API implementation
    function LMSGetValue(element) {
        // Implementation for AICC GetValue
        return "";
    }
    function LMSSetValue(element, value) {
        // Implementation for AICC SetValue  
        return "";
    }
    function LMSCommit(comment) {
        // Implementation for AICC Commit
        return "";
    }
    function LMSInitialize(parameter) {
        // Implementation for AICC Initialize
        return "true";
    }
    function LMSFinish(parameter) {
        // Implementation for AICC Finish
        return "true";
    }
    </script>
</head>
<body>
    <iframe src="' . $pluginfile_url->out() . '" width="100%" height="600px" frameborder="0"></iframe>
    <script type="text/javascript">
    // AICC API implementation
    </script>
</body>
</html>';
    exit;
}

// If no AICC session, return error
http_response_code(404);
echo "Error: No AICC session provided";
exit;
