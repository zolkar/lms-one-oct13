<?php

namespace local_aicc_hacp;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/mapper.php');
require_once($GLOBALS['CFG']->dirroot . '/mod/scorm/lib.php');

class handler {

    public static function process(string $command, string $session_id, array $parsed_data): array {
        switch (strtoupper($command)) {
            case 'GETPARAM':
                return self::get_param($session_id, $parsed_data);
            case 'PUTPARAM':
                return self::put_param($session_id, $parsed_data);
            case 'EXITAU':
                return self::exit_au($session_id, $parsed_data);
            default:
                return ['code' => 101, 'text' => 'Unknown command', 'data' => ''];
        }
    }

    protected static function get_param(string $session_id, array $parsed_data): array {
        global $DB;
        $session = $DB->get_record('local_aicc_export_sessions', ['session_id' => $session_id]);
        if (!$session) {
            return ['code' => 103, 'text' => 'Session not found', 'data' => ''];
        }

        $student_id = $parsed_data['Core']['Student_ID'] ?? $session->student_id ?? '';
        if (empty($student_id)) {
            return ['code' => 100, 'text' => 'Missing Student_ID', 'data' => ''];
        }

        // Get stored state for this student
        $state = $DB->get_record('local_aicc_hacp_student_state', [
            'student_id' => $student_id,
            'scormid' => $session->scormid,
            'scoid' => $session->scoid
        ]);

        if ($state) {
            // Return stored state data
            $aicc_data = self::build_aicc_data_from_state($state);
        } else {
            // No stored state, return default values
            $aicc_data = self::get_default_aicc_data($student_id);
        }

        return ['code' => 0, 'text' => 'Successful', 'data' => $aicc_data];
    }

    protected static function put_param(string $session_id, array $parsed_data): array {
        global $DB;

        $session = $DB->get_record('local_aicc_export_sessions', ['session_id' => $session_id]);
        if (!$session) {
            return ['code' => 103, 'text' => 'Session not found', 'data' => ''];
        }

        $student_id = $parsed_data['Core']['Student_ID'] ?? $session->student_id ?? '';
        if (empty($student_id)) {
            return ['code' => 100, 'text' => 'Missing Student_ID', 'data' => ''];
        }

        // Store or update student state
        $state = $DB->get_record('local_aicc_hacp_student_state', [
            'student_id' => $student_id,
            'scormid' => $session->scormid,
            'scoid' => $session->scoid
        ]);

        if ($state) {
            // Update existing state
            $state->state_data = json_encode($parsed_data);
            $state->lesson_status = $parsed_data['Core']['Lesson_Status'] ?? $state->lesson_status;
            $state->lesson_location = $parsed_data['Core']['Lesson_Location'] ?? $state->lesson_location;
            $state->score = $parsed_data['Core']['Score'] ?? $state->score;
            $state->session_time = $parsed_data['Core']['Time'] ?? $state->session_time;
            $state->updated_at = time();
            $DB->update_record('local_aicc_hacp_student_state', $state);
        } else {
            // Create new state record
            $state = new \stdClass();
            $state->student_id = $student_id;
            $state->scormid = $session->scormid;
            $state->scoid = $session->scoid;
            $state->state_data = json_encode($parsed_data);
            $state->lesson_status = $parsed_data['Core']['Lesson_Status'] ?? '';
            $state->lesson_location = $parsed_data['Core']['Lesson_Location'] ?? '';
            $state->score = $parsed_data['Core']['Score'] ?? '';
            $state->session_time = $parsed_data['Core']['Time'] ?? '';
            $state->created_at = time();
            $state->updated_at = time();
            $DB->insert_record('local_aicc_hacp_student_state', $state);
        }

        return ['code' => 0, 'text' => 'Successful', 'data' => ''];
    }

    protected static function exit_au(string $session_id, array $parsed_data): array {
        global $DB;
        $session = $DB->get_record('local_aicc_export_sessions', ['session_id' => $session_id]);
        if (!$session) {
            return ['code' => 103, 'text' => 'Session not found', 'data' => ''];
        }

        $session->status = 'closed';
        $session->last_activity_at = time();
        $DB->update_record('local_aicc_export_sessions', $session);

        return ['code' => 0, 'text' => 'Successful', 'data' => ''];
    }

    private static function build_aicc_data_from_state($state): string {
        $aicc_sections = [];
        
        // Core section
        $core_data = [
            'Student_ID' => $state->student_id,
            'Student_Name' => $state->student_id, // Use student_id as name if no name available
            'Lesson_Location' => $state->lesson_location,
            'Lesson_Status' => $state->lesson_status,
            'Score' => $state->score,
            'Time' => $state->session_time,
            'Credit' => 'credit'
        ];
        
        $core_section = '';
        foreach ($core_data as $key => $value) {
            if (!empty($value)) {
                $core_section .= "{$key}={$value}\r\n";
            }
        }
        
        if (!empty($core_section)) {
            $aicc_sections['Core'] = $core_section;
        }
        
        // Core_Lesson section (suspend data)
        if (!empty($state->state_data)) {
            $parsed_data = json_decode($state->state_data, true);
            if (isset($parsed_data['Core_Lesson'])) {
                $aicc_sections['Core_Lesson'] = '';
                foreach ($parsed_data['Core_Lesson'] as $key => $value) {
                    $aicc_sections['Core_Lesson'] .= "{$key}={$value}\r\n";
                }
            }
        }
        
        // Build final AICC string
        $aicc_string = '';
        foreach ($aicc_sections as $section => $data) {
            $aicc_string .= "[{$section}]\r\n{$data}";
        }
        
        return $aicc_string;
    }

    private static function get_default_aicc_data(string $student_id): string {
        $core_section = "Student_ID={$student_id}\r\n";
        $core_section .= "Student_Name={$student_id}\r\n";
        $core_section .= "Lesson_Location=\r\n";
        $core_section .= "Lesson_Status=not attempted\r\n";
        $core_section .= "Score=\r\n";
        $core_section .= "Time=00:00:00\r\n";
        $core_section .= "Credit=credit\r\n";
        
        return "[Core]\r\n{$core_section}";
    }
}
