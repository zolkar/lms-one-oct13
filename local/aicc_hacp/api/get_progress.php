<?php
/**
 * Simple API to get progress by student email
 * Called by LMS-2 to display progress on LMS-2's SCORM view
 */

define('NO_MOODLE_COOKIES', true);

require_once(__DIR__ . '/../../config.php');

header('Content-Type: application/json');

// Get parameters
$student_email = optional_param('email', '', PARAM_EMAIL);
$scormid = optional_param('scormid', PARAM_INT);

if (empty($student_email) || empty($scormid)) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}

global $DB;

// Find the student's latest progress
$sql = "
    SELECT 
        ps.student_email,
        ss.lesson_status,
        ss.score,
        ss.session_time,
        ss.updated_at,
        hs.last_activity_at
    FROM {local_aicc_hacp_persistent_sessions} ps
    LEFT JOIN {local_aicc_hacp_student_state} ss ON ss.student_id = ps.student_id AND ss.scormid = ?
    LEFT JOIN {local_aicc_hacp_sessions} hs ON hs.student_id = ps.student_id AND hs.scormid = ?
    WHERE ps.student_email = ? AND ps.scormid = ?
    ORDER BY ss.updated_at DESC
    LIMIT 1
";

$progress = $DB->get_record_sql($sql, [$scormid, $scormid, $student_email, $scormid]);

if ($progress) {
    echo json_encode([
        'lesson_status' => $progress->lesson_status ?? '',
        'score' => $progress->score ?? '',
        'session_time' => $progress->session_time ?? '',
        'last_activity' => $progress->updated_at ?? $progress->last_activity_at ?? ''
    ]);
} else {
    echo json_encode([
        'lesson_status' => '',
        'score' => '',
        'session_time' => '',
        'last_activity' => ''
    ]);
}

