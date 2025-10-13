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
}
