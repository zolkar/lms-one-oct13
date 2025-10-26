<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Add external progress link to course navigation
 */
function local_aicc_hacp_extend_navigation_course($navigation, $course, $coursecontext) {
    global $DB;
    
    // Check if user has permission to view course
    if (!has_capability('moodle/course:view', $coursecontext)) {
        return;
    }
    
    // Check if this course has SCORM activities with external sessions
    $sql = "
        SELECT COUNT(DISTINCT hs.student_id) as external_students
        FROM {scorm} s
        LEFT JOIN {local_aicc_hacp_sessions} hs ON hs.scormid = s.id
        WHERE s.course = ?
        HAVING external_students > 0
    ";
    
    $has_external_students = $DB->record_exists_sql($sql, [$course->id]);
    
    if ($has_external_students) {
        $external_progress_url = new moodle_url('/local/aicc_hacp/admin/external_progress.php', [
            'courseid' => $course->id
        ]);
        
        $navigation->add(
            'External Students Progress',
            $external_progress_url,
            navigation_node::TYPE_CUSTOM,
            null,
            'external_progress',
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Add external progress link to site administration
 */
function local_aicc_hacp_extend_navigation_user_settings($navigation, $user, $usercontext, $course, $coursecontext) {
    // Add to site administration if user is admin
    if (has_capability('moodle/site:config', \context_system::instance())) {
        $external_progress_url = new moodle_url('/local/aicc_hacp/admin/external_progress.php');
        
        $navigation->add(
            'External Students Progress',
            $external_progress_url,
            navigation_node::TYPE_CUSTOM,
            null,
            'external_progress_admin',
            new pix_icon('i/report', '')
        );
    }
}

/**
 * Log HACP request for debugging and audit
 */
function local_aicc_hacp_log(int $code, string $message, string $session_id, string $command, string $request_body, int $signature_valid = 0, array $parsed_data = []): void {
    global $DB;
    
    $log_level = get_config('local_aicc_hacp', 'log_level') ?: 'error';
    
    // Don't log if logging is disabled
    if ($log_level === 'none') {
        return;
    }
    
    // Only log errors if log level is error
    if ($log_level === 'error' && $code < 100) {
        return;
    }
    
    // Only log info level and above if log level is info
    if ($log_level === 'info' && $code < 100 && $code > 0) {
        return;
    }
    
    try {
        $log_entry = new \stdClass();
        $log_entry->session_id = $session_id;
        $log_entry->command = $command;
        $log_entry->request_body = substr($request_body, 0, 1000); // Limit to 1000 chars
        $log_entry->signature_valid = $signature_valid;
        $log_entry->remote_ip = getremoteaddr();
        $log_entry->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
        $log_entry->parsed_data_json = json_encode($parsed_data);
        $log_entry->result_code = $code;
        $log_entry->result_message = substr($message, 0, 255);
        $log_entry->created_at = time();
        
        $DB->insert_record('local_aicc_hacp_logs', $log_entry);
    } catch (\Exception $e) {
        // Silently fail logging to avoid breaking the request
        error_log('AICC HACP logging failed: ' . $e->getMessage());
    }
}

/**
 * Respond to HACP request with proper AICC format
 */
function local_aicc_hacp_respond(int $code, string $text, string $data = ''): void {
    $response = [
        'error' => false,
        'error_text' => '',
        'version' => '1.0'
    ];
    
    if ($code === 0) {
        $response['error'] = false;
        $response['error_text'] = $text;
        if (!empty($data)) {
            $response['aicc_data'] = $data;
        }
    } else {
        $response['error'] = true;
        $response['error_text'] = $text;
    }
    
    header('Content-Type: text/plain');
    echo "error=" . ($code === 0 ? "false" : "true") . "\r\n";
    echo "error_text=" . $text . "\r\n";
    echo "version=1.0\r\n";
    if ($code === 0 && !empty($data)) {
        echo "\r\n" . $data;
    }
    exit;
}