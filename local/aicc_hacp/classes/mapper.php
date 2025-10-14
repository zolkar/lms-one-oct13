<?php

namespace local_aicc_hacp;

defined('MOODLE_INTERNAL') || die();

class mapper {
    public static function find_user_by_studentid(string $studentid, string $origin = '') {
        global $DB;

        // Try manual mapping first.
        if ($map = $DB->get_record('local_aicc_hacp_usermap', ['external_id' => $studentid, 'trusted_origin' => $origin])) {
            if ($user = $DB->get_record('user', ['id' => $map->userid, 'deleted' => 0])) {
                return $user;
            }
        }

        // Then try idnumber.
        if ($user = $DB->get_record('user', ['idnumber' => $studentid, 'deleted' => 0])) {
            return $user;
        }

        // Then try username.
        if ($user = $DB->get_record('user', ['username' => $studentid, 'deleted' => 0])) {
            return $user;
        }

        // Finally, try email.
        if ($user = $DB->get_record('user', ['email' => $studentid, 'deleted' => 0])) {
            return $user;
        }

        return false;
    }

    public static function aicc_to_scorm(array $aicc_data): array {
        $scorm_data = [];
        foreach ($aicc_data as $section => $values) {
            foreach ($values as $key => $value) {
                $scorm_element = self::get_scorm_element($section, $key);
                if ($scorm_element) {
                    $scorm_data[$scorm_element] = $value;
                }
            }
        }
        return $scorm_data;
    }

    private static function get_scorm_element(string $section, string $key): ?string {
        $key = strtoupper($key);
        switch (strtoupper($section)) {
            case 'CORE':
                $map = [
                    'STUDENT_ID' => 'cmi.core.student_id',
                    'STUDENT_NAME' => 'cmi.core.student_name',
                    'LESSON_LOCATION' => 'cmi.core.lesson_location',
                    'LESSON_STATUS' => 'cmi.core.lesson_status',
                    'SCORE' => 'cmi.core.score.raw',
                    'TIME' => 'cmi.core.session_time',
                    'CREDIT' => 'cmi.core.credit',
                ];
                return $map[$key] ?? null;
            case 'CORE_LESSON':
                return 'cmi.suspend_data';
            case 'OBJECTIVES-STATUS':
                // Assuming the key is the objective ID
                return "cmi.objectives.{$key}.status";
        }
        return null;
    }

    public static function scorm_to_aicc(array $scorm_data): string {
        $aicc_sections = [];
        foreach ($scorm_data as $element => $track) {
            $aicc_element = self::get_aicc_element($element);
            if ($aicc_element) {
                if (!isset($aicc_sections[$aicc_element['section']])) {
                    $aicc_sections[$aicc_element['section']] = '';
                }
                $aicc_sections[$aicc_element['section']] .= "{$aicc_element['key']}={$track->value}\r\n";
            }
        }

        $aicc_string = '';
        foreach ($aicc_sections as $section => $data) {
            $aicc_string .= "[{$section}]\r\n{$data}";
        }

        return $aicc_string;
    }

    private static function get_aicc_element(string $scorm_element): ?array {
        $map = [
            'cmi.core.student_id' => ['section' => 'Core', 'key' => 'Student_ID'],
            'cmi.core.student_name' => ['section' => 'Core', 'key' => 'Student_Name'],
            'cmi.core.lesson_location' => ['section' => 'Core', 'key' => 'Lesson_Location'],
            'cmi.core.lesson_status' => ['section' => 'Core', 'key' => 'Lesson_Status'],
            'cmi.core.score.raw' => ['section' => 'Core', 'key' => 'Score'],
            'cmi.core.session_time' => ['section' => 'Core', 'key' => 'Time'],
            'cmi.core.credit' => ['section' => 'Core', 'key' => 'Credit'],
            'cmi.suspend_data' => ['section' => 'Core_Lesson', 'key' => ''],
        ];

        if (isset($map[$scorm_element])) {
            return $map[$scorm_element];
        }

        if (preg_match('/^cmi\.objectives\.(\d+)\.status$/', $scorm_element, $matches)) {
            return ['section' => 'Objectives-Status', 'key' => $matches[1]];
        }

        return null;
    }
}
