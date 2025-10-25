<?php
define('CLI_SCRIPT', true);
require_once('config.php');

echo "Testing database insertion logic directly...\n";

// Simulate the exact logic from direct_content.php
$cmid = 36; // Use the course module ID we found
$student_id = 'test_student_123';

echo "Testing with cmid: $cmid, student_id: $student_id\n";

// Get the course module
$cm = get_coursemodule_from_id('', $cmid, 0, false, MUST_EXIST);
$course = $DB->get_record('course', ['id' => $cm->course], '*', MUST_EXIST);

echo "Found course module: {$cm->id}, course: {$course->id}\n";

// Verify it's a SCORM activity
if ($cm->modname !== 'scorm') {
    echo "ERROR: Not a SCORM activity\n";
    exit;
}

$scorm = $DB->get_record('scorm', ['id' => $cm->instance], '*', MUST_EXIST);
echo "Found SCORM: {$scorm->id}\n";

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
echo "HACP user ID: $hacp_user_id\n";

// Get the first SCO for this SCORM activity
$scoes = $DB->get_records('scorm_scoes', ['scorm' => $scorm->id], 'id', 'id,identifier,title', 0, 1);
if (empty($scoes)) {
    echo "ERROR: No SCO found in SCORM activity\n";
    exit;
}
$sco = reset($scoes);
echo "Found SCO: {$sco->id}, identifier: " . ($sco->identifier ?? 'NULL') . "\n";

// Create or update AICC session record for HACP communication
$aicc_session = new \stdClass();
$aicc_session->hacpsession = 'DIRECT_' . $cmid . '_' . time();
$aicc_session->scormid = $scorm->id;
$aicc_session->scoid = $sco->id;
$aicc_session->userid = $hacp_user_id;
$aicc_session->student_id = $student_id;
$aicc_session->timecreated = time();
$aicc_session->timemodified = time();

echo "Created session object:\n";
print_r($aicc_session);

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
    echo "Found existing session: {$existing->id}\n";
    $aicc_session->id = $existing->id;
    $aicc_session->timemodified = time();
    try {
        echo "Attempting to update session...\n";
        $DB->update_record('local_aicc_export_sessions', $aicc_session);
        echo "SUCCESS: Session updated\n";
    } catch (Exception $e) {
        echo "ERROR updating session: " . $e->getMessage() . "\n";
    }
} else {
    echo "No existing session found, creating new one...\n";
    $aicc_session->session_id = $aicc_session->hacpsession;
    $aicc_session->token_nonce = uniqid();
    $aicc_session->courseid = $course->id;
    $aicc_session->au = !empty($sco->identifier) ? $sco->identifier : 'sco_' . $sco->id;
    $aicc_session->expires_at = time() + (24 * 60 * 60); // 24 hours
    $aicc_session->last_activity_at = time();
    $aicc_session->origin = 'test_origin';
    
    echo "Final session object for insert:\n";
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
}
