<?php
/**
 * API endpoint for LMS-2 to query student progress from LMS-1
 * 
 * This allows LMS-2 to display attempt counts, scores, and progress
 * that are tracked on LMS-1
 */

define('NO_MOODLE_COOKIES', true);
define('WS_SERVER', true);

require_once(__DIR__ . '/../../config.php');

// Get parameters
$student_email = required_param('student_email', PARAM_EMAIL);
$scormid = required_param('scormid', PARAM_INT);

// Validate token or API key
// For now, we'll use a simple API key stored in config
$api_key = get_config('local_aicc_hacp', 'api_key');
$received_key = optional_param('api_key', '', PARAM_TEXT);

if (empty($api_key) || $api_key !== $received_key) {
    http_response_code(401);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// Get student data from LMS-1 database
global $DB;

// Find student by email
$student_state = $DB->get_records_sql("
    SELECT 
        ss.student_id,
        ss.lesson_status,
        ss.score,
        ss.session_time,
        ss.updated_at,
        COUNT(DISTINCT ss.id) as attempts
    FROM {local_aicc_hacp_student_state} ss
    WHERE ss.scormid = ? 
    AND EXISTS (
        SELECT 1 FROM {local_aicc_hacp_persistent_sessions} ps 
        WHERE ps.student_id = ss.student_id 
        AND ps.student_email = ?
    )
    GROUP BY ss.student_id, ss.lesson_status, ss.score, ss.session_time, ss.updated_at
    ORDER BY ss.updated_at DESC
", [$scormid, $student_email]);

// Get attempts count
$attempts_count = $DB->get_records_sql("
    SELECT 
        COUNT(DISTINCT session_id) as total_attempts,
        MAX(created_at) as last_attempt
    FROM {local_aicc_hacp_sessions} hs
    WHERE hs.scormid = ? 
    AND EXISTS (
        SELECT 1 FROM {local_aicc_hacp_persistent_sessions} ps 
        WHERE ps.student_id = hs.student_id 
        AND ps.student_email = ?
    )
", [$scormid, $student_email]);

$result = [
    'student_email' => $student_email,
    'scormid' => $scormid,
    'lesson_status' => '',
    'score' => '',
    'session_time' => '',
    'attempts' => 0,
    'last_activity' => ''
];

if (!empty($student_state)) {
    $first = reset($student_state);
    $result['lesson_status'] = $first->lesson_status ?? '';
    $result['score'] = $first->score ?? '';
    $result['session_time'] = $first->session_time ?? '';
    $result['last_activity'] = $first->updated_at ?? '';
}

if (!empty($attempts_count)) {
    $first_attempt = reset($attempts_count);
    $result['attempts'] = (int)$first_attempt->total_attempts;
}

header('Content-Type: application/json');
echo json_encode($result);

