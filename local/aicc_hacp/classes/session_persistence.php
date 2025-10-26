<?php

namespace local_aicc_hacp;

defined('MOODLE_INTERNAL') || die();

class session_persistence {
    
    /**
     * Get or create persistent user session for external student
     */
    public static function get_persistent_session(string $student_id, int $scormid, int $scoid, string $origin = ''): \stdClass {
        global $DB;
        
        // Look for existing persistent session
        $persistent_session = $DB->get_record('local_aicc_hacp_persistent_sessions', [
            'student_id' => $student_id,
            'scormid' => $scormid,
            'scoid' => $scoid
        ]);
        
        if ($persistent_session) {
            // Update last access time
            $persistent_session->last_access_at = time();
            $persistent_session->access_count = $persistent_session->access_count + 1;
            $DB->update_record('local_aicc_hacp_persistent_sessions', $persistent_session);
            
            return $persistent_session;
        }
        
        // Create new persistent session
        $persistent_session = new \stdClass();
        $persistent_session->student_id = $student_id;
        $persistent_session->student_name = 'External Student';
        $persistent_session->student_email = '';
        $persistent_session->scormid = $scormid;
        $persistent_session->scoid = $scoid;
        $persistent_session->origin = $origin;
        $persistent_session->created_at = time();
        $persistent_session->last_access_at = time();
        $persistent_session->access_count = 1;
        $persistent_session->status = 'active';
        
        $persistent_session->id = $DB->insert_record('local_aicc_hacp_persistent_sessions', $persistent_session);
        
        return $persistent_session;
    }
    
    /**
     * Create or update user mapping with proper name and email
     * Store name and email in persistent sessions table for now
     */
    public static function create_user_mapping(string $student_id, string $name = '', string $email = '', string $origin = ''): \stdClass {
        global $DB;
        
        // For now, we'll store the name and email in the persistent sessions table
        // This is a simple solution until we can modify the database schema
        $persistent_session = $DB->get_record('local_aicc_hacp_persistent_sessions', [
            'student_id' => $student_id
        ], '*', IGNORE_MULTIPLE);
        
        if ($persistent_session) {
            // Update existing session with name and email
            $persistent_session->student_name = $name ?: 'External Student';
            $persistent_session->student_email = $email ?: '';
            $persistent_session->origin = $origin ?: $persistent_session->origin;
            $DB->update_record('local_aicc_hacp_persistent_sessions', $persistent_session);
            return $persistent_session;
        } else {
            // Create a simple mapping object
            $mapping = new \stdClass();
            $mapping->student_id = $student_id;
            $mapping->student_name = $name ?: 'External Student';
            $mapping->student_email = $email ?: '';
            $mapping->origin = $origin;
            return $mapping;
        }
    }
    
    /**
     * Get user mapping with proper name and email
     */
    public static function get_user_mapping(string $student_id): ?\stdClass {
        global $DB;
        
        // Get from persistent sessions table
        $persistent_session = $DB->get_record('local_aicc_hacp_persistent_sessions', [
            'student_id' => $student_id
        ], '*', IGNORE_MULTIPLE);
        
        if ($persistent_session) {
            $mapping = new \stdClass();
            $mapping->student_id = $student_id;
            $mapping->student_name = $persistent_session->student_name ?? 'External Student';
            $mapping->student_email = $persistent_session->student_email ?? '';
            $mapping->origin = $persistent_session->origin ?? '';
            return $mapping;
        }
        
        return null;
    }
    
    /**
     * Get student's progress from persistent session
     */
    public static function get_student_progress(string $student_id, int $scormid, int $scoid): ?\stdClass {
        global $DB;
        
        return $DB->get_record('local_aicc_hacp_student_state', [
            'student_id' => $student_id,
            'scormid' => $scormid,
            'scoid' => $scoid
        ]);
    }
    
    /**
     * Save student progress to persistent storage
     */
    public static function save_student_progress(string $student_id, int $scormid, int $scoid, array $aicc_data): void {
        global $DB;
        
        $state = $DB->get_record('local_aicc_hacp_student_state', [
            'student_id' => $student_id,
            'scormid' => $scormid,
            'scoid' => $scoid
        ]);
        
        if ($state) {
            // Update existing state
            $state->state_data = json_encode($aicc_data);
            $state->lesson_status = $aicc_data['Core']['Lesson_Status'] ?? $state->lesson_status;
            $state->lesson_location = $aicc_data['Core']['Lesson_Location'] ?? $state->lesson_location;
            $state->score = $aicc_data['Core']['Score'] ?? $state->score;
            $state->session_time = $aicc_data['Core']['Time'] ?? $state->session_time;
            $state->updated_at = time();
            $DB->update_record('local_aicc_hacp_student_state', $state);
        } else {
            // Create new state record
            $state = new \stdClass();
            $state->student_id = $student_id;
            $state->scormid = $scormid;
            $state->scoid = $scoid;
            $state->state_data = json_encode($aicc_data);
            $state->lesson_status = $aicc_data['Core']['Lesson_Status'] ?? '';
            $state->lesson_location = $aicc_data['Core']['Lesson_Location'] ?? '';
            $state->score = $aicc_data['Core']['Score'] ?? '';
            $state->session_time = $aicc_data['Core']['Time'] ?? '';
            $state->created_at = time();
            $state->updated_at = time();
            $DB->insert_record('local_aicc_hacp_student_state', $state);
        }
    }
    
    /**
     * Create temporary HACP session linked to persistent session
     */
    public static function create_hacp_session(string $student_id, int $scormid, int $scoid, string $origin = ''): string {
        global $DB;
        
        // Get or create persistent session
        $persistent_session = self::get_persistent_session($student_id, $scormid, $scoid, $origin);
        
        // Generate temporary HACP session ID
        $hacp_session_id = 'HACP_' . $scormid . '_' . $scoid . '_' . bin2hex(random_bytes(16));
        
        // Create temporary HACP session
        $hacp_session = new \stdClass();
        $hacp_session->session_id = $hacp_session_id;
        $hacp_session->scormid = $scormid;
        $hacp_session->scoid = $scoid;
        $hacp_session->student_id = $student_id;
        $hacp_session->origin = $origin;
        $hacp_session->persistent_session_id = $persistent_session->id;
        $hacp_session->status = 'active';
        $hacp_session->created_at = time();
        $hacp_session->last_activity_at = time();
        $hacp_session->expires_at = time() + (get_config('local_aicc_hacp', 'session_timeout') ?: 7200);
        
        $DB->insert_record('local_aicc_hacp_sessions', $hacp_session);
        
        return $hacp_session_id;
    }
    
    /**
     * Restore student progress when creating new session
     */
    public static function restore_progress_to_session(string $hacp_session_id): void {
        global $DB;
        
        $session = $DB->get_record('local_aicc_hacp_sessions', ['session_id' => $hacp_session_id]);
        if (!$session) {
            return;
        }
        
        // Get persistent progress
        $progress = self::get_student_progress($session->student_id, $session->scormid, $session->scoid);
        if (!$progress) {
            return;
        }
        
        // Create SCORM tracking records for this session
        $scorm = $DB->get_record('scorm', ['id' => $session->scormid], '*', MUST_EXIST);
        
        // Get or create a dedicated user for this external student
        $user_id = self::get_or_create_external_user($session->student_id, $session->origin);
        
        // Create SCORM attempt
        $attempt = $DB->get_record('scorm_scoes_track', [
            'scoid' => $session->scoid,
            'userid' => $user_id,
            'element' => 'cmi.core.lesson_status'
        ]);
        
        if (!$attempt) {
            // Create new attempt
            $attempt_data = new \stdClass();
            $attempt_data->scoid = $session->scoid;
            $attempt_data->userid = $user_id;
            $attempt_data->attempt = 1;
            $attempt_data->element = 'cmi.core.lesson_status';
            $attempt_data->value = $progress->lesson_status ?: 'not attempted';
            $attempt_data->timemodified = time();
            $DB->insert_record('scorm_scoes_track', $attempt_data);
        }
        
        // Restore other tracking data
        if ($progress->lesson_location) {
            $location_data = new \stdClass();
            $location_data->scoid = $session->scoid;
            $location_data->userid = $user_id;
            $location_data->attempt = 1;
            $location_data->element = 'cmi.core.lesson_location';
            $location_data->value = $progress->lesson_location;
            $location_data->timemodified = time();
            $DB->insert_record('scorm_scoes_track', $location_data);
        }
        
        if ($progress->score) {
            $score_data = new \stdClass();
            $score_data->scoid = $session->scoid;
            $score_data->userid = $user_id;
            $score_data->attempt = 1;
            $score_data->element = 'cmi.core.score.raw';
            $score_data->value = $progress->score;
            $score_data->timemodified = time();
            $DB->insert_record('scorm_scoes_track', $score_data);
        }
        
        if ($progress->session_time) {
            $time_data = new \stdClass();
            $time_data->scoid = $session->scoid;
            $time_data->userid = $user_id;
            $time_data->attempt = 1;
            $time_data->element = 'cmi.core.session_time';
            $time_data->value = $progress->session_time;
            $time_data->timemodified = time();
            $DB->insert_record('scorm_scoes_track', $time_data);
        }
        
        // Restore suspend data if available
        if ($progress->state_data) {
            $aicc_data = json_decode($progress->state_data, true);
            if (isset($aicc_data['Core_Lesson'])) {
                $suspend_data = new \stdClass();
                $suspend_data->scoid = $session->scoid;
                $suspend_data->userid = $user_id;
                $suspend_data->attempt = 1;
                $suspend_data->element = 'cmi.suspend_data';
                $suspend_data->value = json_encode($aicc_data['Core_Lesson']);
                $suspend_data->timemodified = time();
                $DB->insert_record('scorm_scoes_track', $suspend_data);
            }
        }
    }
    
    /**
     * Get or create external user for tracking
     */
    private static function get_or_create_external_user(string $student_id, string $origin = ''): int {
        global $DB;
        
        // Try to find existing user by idnumber
        $user = $DB->get_record('user', ['idnumber' => $student_id, 'deleted' => 0]);
        if ($user) {
            return $user->id;
        }
        
        // Create new external user
        $user = new \stdClass();
        $user->username = 'external_' . $student_id;
        $user->firstname = 'External';
        $user->lastname = 'Student';
        $user->email = $student_id . '@external.local';
        $user->idnumber = $student_id;
        $user->confirmed = 1;
        $user->mnethostid = 1;
        $user->timecreated = time();
        $user->timemodified = time();
        $user->lastnamephonetic = '';
        $user->firstnamephonetic = '';
        $user->middlename = '';
        $user->alternatename = '';
        
        $user_id = $DB->insert_record('user', $user);
        
        return $user_id;
    }
    
    /**
     * Clean up expired persistent sessions
     */
    public static function cleanup_expired_sessions(): int {
        global $DB;
        
        $expired_time = time() - (30 * 24 * 60 * 60); // 30 days
        
        $expired_sessions = $DB->get_records_select('local_aicc_hacp_persistent_sessions', 
            'last_access_at < ? AND status = ?', [$expired_time, 'active']);
        
        $count = 0;
        foreach ($expired_sessions as $session) {
            $session->status = 'expired';
            $DB->update_record('local_aicc_hacp_persistent_sessions', $session);
            $count++;
        }
        
        return $count;
    }
}
