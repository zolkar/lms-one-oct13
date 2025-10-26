<?php

namespace local_aicc_hacp;

defined('MOODLE_INTERNAL') || die();

class secure_session {
    
    /**
     * Create secure HACP session
     */
    public static function create_session(string $session_id, int $scormid, int $scoid, string $student_id, string $origin = ''): \stdClass {
        global $DB;
        
        // Validate inputs
        if (empty($session_id) || empty($student_id)) {
            throw new \moodle_exception('error_invalid_session_params', 'local_aicc_hacp');
        }
        
        // Check if session already exists
        $existing = $DB->get_record('local_aicc_hacp_sessions', ['session_id' => $session_id]);
        if ($existing) {
            // Update existing session
            $existing->last_activity_at = time();
            $existing->student_id = $student_id;
            $existing->origin = $origin;
            $DB->update_record('local_aicc_hacp_sessions', $existing);
            return $existing;
        }
        
        // Create new session
        $session = new \stdClass();
        $session->session_id = $session_id;
        $session->scormid = $scormid;
        $session->scoid = $scoid;
        $session->student_id = $student_id;
        $session->origin = $origin;
        $session->status = 'active';
        $session->created_at = time();
        $session->last_activity_at = time();
        $session->expires_at = time() + (get_config('local_aicc_hacp', 'session_timeout') ?: 7200);
        
        $session->id = $DB->insert_record('local_aicc_hacp_sessions', $session);
        
        // Log session creation
        local_aicc_hacp_log(0, 'Session created', $session_id, 'CREATE_SESSION', '', 1, [
            'scormid' => $scormid,
            'scoid' => $scoid,
            'student_id' => $student_id,
            'origin' => $origin
        ]);
        
        return $session;
    }
    
    /**
     * Validate and get session
     */
    public static function get_session(string $session_id): ?\stdClass {
        global $DB;
        
        $session = $DB->get_record('local_aicc_hacp_sessions', ['session_id' => $session_id]);
        
        if (!$session) {
            local_aicc_hacp_log(103, 'Session not found', $session_id, 'GET_SESSION', '', 0);
            return null;
        }
        
        // Check if session is expired
        if ($session->expires_at < time()) {
            local_aicc_hacp_log(104, 'Session expired', $session_id, 'GET_SESSION', '', 0);
            self::close_session($session_id);
            return null;
        }
        
        // Update last activity
        $session->last_activity_at = time();
        $DB->update_record('local_aicc_hacp_sessions', $session);
        
        return $session;
    }
    
    /**
     * Close session
     */
    public static function close_session(string $session_id): bool {
        global $DB;
        
        $session = $DB->get_record('local_aicc_hacp_sessions', ['session_id' => $session_id]);
        if (!$session) {
            return false;
        }
        
        $session->status = 'closed';
        $session->last_activity_at = time();
        $DB->update_record('local_aicc_hacp_sessions', $session);
        
        local_aicc_hacp_log(0, 'Session closed', $session_id, 'CLOSE_SESSION', '', 1);
        
        return true;
    }
    
    /**
     * Clean up expired sessions
     */
    public static function cleanup_expired_sessions(): int {
        global $DB;
        
        $expired_time = time() - (get_config('local_aicc_hacp', 'session_timeout') ?: 7200);
        
        $expired_sessions = $DB->get_records_select('local_aicc_hacp_sessions', 
            'expires_at < ? AND status = ?', [$expired_time, 'active']);
        
        $count = 0;
        foreach ($expired_sessions as $session) {
            $session->status = 'expired';
            $session->last_activity_at = time();
            $DB->update_record('local_aicc_hacp_sessions', $session);
            $count++;
        }
        
        if ($count > 0) {
            local_aicc_hacp_log(0, "Cleaned up {$count} expired sessions", '', 'CLEANUP', '', 1);
        }
        
        return $count;
    }
    
    /**
     * Generate secure session ID
     */
    public static function generate_session_id(int $courseid, int $scormid, string $student_id): string {
        $prefix = 'HACP_' . $courseid . '_' . $scormid . '_';
        $random = bin2hex(random_bytes(16));
        return $prefix . $random;
    }
    
    /**
     * Validate session ID format
     */
    public static function validate_session_id(string $session_id): bool {
        return preg_match('/^HACP_\d+_\d+_[a-f0-9]{32}$/', $session_id) === 1;
    }
}
