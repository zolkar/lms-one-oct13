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

        $student_id = $parsed_data['Core']['Student_ID'] ?? '';
        if (empty($student_id)) {
            return ['code' => 100, 'text' => 'Missing Student_ID', 'data' => ''];
        }

        $user = mapper::find_user_by_studentid($student_id);
        if (!$user) {
            return ['code' => 104, 'text' => 'User mapping failed', 'data' => ''];
        }

        $scorm = $DB->get_record('scorm', ['id' => $session->scormid], '*', MUST_EXIST);
        $sco = $DB->get_record('scorm_scoes', ['id' => $session->scoid], '*', MUST_EXIST);
        $attempt = scorm_get_last_attempt($scorm->id, $user->id);
        $tracks = scorm_get_tracks($sco->id, $user->id, $attempt);

        $aicc_data = "[Core]\r\n";
        if (isset($tracks['cmi.core.lesson_status'])) {
            $aicc_data .= "Lesson_Status={$tracks['cmi.core.lesson_status']->value}\r\n";
        }
        if (isset($tracks['cmi.core.lesson_location'])) {
            $aicc_data .= "Lesson_Location={$tracks['cmi.core.lesson_location']->value}\r\n";
        }

        return ['code' => 0, 'text' => 'Successful', 'data' => $aicc_data];
    }

    protected static function put_param(string $session_id, array $parsed_data): array {
        global $DB;

        $session = $DB->get_record('local_aicc_export_sessions', ['session_id' => $session_id]);
        if (!$session) {
            return ['code' => 103, 'text' => 'Session not found', 'data' => ''];
        }

        $student_id = $parsed_data['Core']['Student_ID'] ?? '';
        if (empty($student_id)) {
            return ['code' => 100, 'text' => 'Missing Student_ID', 'data' => ''];
        }

        $user = mapper::find_user_by_studentid($student_id);
        if (!$user) {
            return ['code' => 104, 'text' => 'User mapping failed', 'data' => ''];
        }

        $scorm = $DB->get_record('scorm', ['id' => $session->scormid], '*', MUST_EXIST);
        $sco = $DB->get_record('scorm_scoes', ['id' => $session->scoid], '*', MUST_EXIST);

        $attempt = scorm_get_last_attempt($scorm->id, $user->id);

        $track_details = [
            'cmi.core.lesson_status' => $parsed_data['Core']['Lesson_Status'] ?? null,
            'cmi.core.score.raw' => $parsed_data['Core']['Score'] ?? null,
            'cmi.core.lesson_location' => $parsed_data['Core']['Lesson_Location'] ?? null,
            'cmi.core.session_time' => $parsed_data['Core']['Time'] ?? null,
        ];

        foreach ($track_details as $element => $value) {
            if ($value !== null) {
                scorm_insert_track($user->id, $scorm->id, $sco->id, $attempt, $element, $value);
            }
        }

        scorm_update_grades($scorm, $user->id);

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
}
