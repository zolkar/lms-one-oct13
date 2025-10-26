<?php

namespace local_aicc_hacp;

defined('MOODLE_INTERNAL') || die();

class tracking_handler {

    public static function get_tracking_data(int $userid, int $scormid, int $scoid): string {
        global $DB;

        $records = $DB->get_records('local_aicc_hacp_tracking', [
            'userid' => $userid,
            'scormid' => $scormid,
            'scoid' => $scoid,
        ]);

        $aicc_data = "[Core]\n";
        foreach ($records as $record) {
            $aicc_data .= "{$record->element}={$record->value}\n";
        }
        $aicc_data .= "[Core_Lesson]\n";

        return $aicc_data;
    }

    public static function set_tracking_data(int $userid, int $scormid, int $scoid, string $aiccdata): void {
        global $DB;

        $lines = explode("\n", $aiccdata);
        foreach ($lines as $line) {
            if (strpos($line, '=') !== false) {
                list($element, $value) = explode('=', $line, 2);
                $element = trim($element);
                $value = trim($value);

                if ($existing = $DB->get_record('local_aicc_hacp_tracking', [
                    'userid' => $userid,
                    'scormid' => $scormid,
                    'scoid' => $scoid,
                    'element' => $element,
                ])) {
                    $existing->value = $value;
                    $existing->timemodified = time();
                    $DB->update_record('local_aicc_hacp_tracking', $existing);
                } else {
                    $record = new \stdClass();
                    $record->userid = $userid;
                    $record->scormid = $scormid;
                    $record->scoid = $scoid;
                    $record->element = $element;
                    $record->value = $value;
                    $record->timecreated = time();
                    $record->timemodified = time();
                    $DB->insert_record('local_aicc_hacp_tracking', $record);
                }
            }
        }
    }
}
