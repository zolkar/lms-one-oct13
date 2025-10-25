<?php
define('CLI_SCRIPT', true);
require_once('config.php');

echo "Testing fixed database insertion logic...\n";

// Simulate the exact logic from direct_content.php with correct field names
$cmid = 36;
$student_id = 'test_student_123';

echo "Testing with cmid: $cmid, student_id: $student_id\n";

// Get the course module
$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

// Verify it's a SCORM activity
if ($cm->modname !== 'scorm') {
    echo "ERROR: Not a SCORM activity\n";
    exit;
}

$scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);

// Create or get HACP user for content access
function get_or_create_hacp_user() {
    global $DB;
    
    $hacp_user = $DB->get_record('user', ['username' => 'hacp_user'], 'id');
    if ($hacp_user) {
        return $hacp_user->id;
    }
    
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

$hacp_user_id = get_or_create_hacp_user();

// Get the first SCO for this SCORM activity
$scoes = $DB->get_records('scorm_scoes', ['scorm' => $scorm->id], 'id', 'id,identifier,title', 0, 1);
if (empty($scoes)) {
    echo "ERROR: No SCO found in SCORM activity\n";
    exit;
}
$sco = reset($scoes);

// Create session record with correct field names
$aicc_session = new \stdClass();
$aicc_session->session_id = 'DIRECT_' . $cmid . '_' . time();
$aicc_session->scormid = $scorm->id;
$aicc_session->scoid = $sco->id;
$aicc_session->userid = $hacp_user_id;
$aicc_session->student_id = $student_id;
$aicc_session->status = 'active';
$aicc_session->created_at = time();
$aicc_session->last_activity_at = time();

echo "Session object with correct field names:\n";
print_r($aicc_session);

try {
    echo "Attempting to insert session...\n";
    $id = $DB->insert_record('local_aicc_export_sessions', $aicc_session);
    echo "SUCCESS: Session inserted with ID: $id\n";
    
    // Clean up
    $DB->delete_records('local_aicc_export_sessions', ['id' => $id]);
    echo "Test session cleaned up\n";
    
} catch (Exception $e) {
    echo "ERROR inserting session: " . $e->getMessage() . "\n";
    echo "Error details: " . print_r($e, true) . "\n";
}
