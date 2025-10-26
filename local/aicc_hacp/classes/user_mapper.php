<?php

namespace local_aicc_hacp;

defined('MOODLE_INTERNAL') || die();

class user_mapper {

    public static function get_or_create_user(string $externalsid, int $courseid): \stdClass {
        global $DB;

        if ($user_mapping = $DB->get_record('local_aicc_hacp_users', ['externalsid' => $externalsid])) {
            return $DB->get_record('user', ['id' => $user_mapping->internaluserid]);
        }

        $proxy_user = self::create_proxy_user($externalsid, $courseid);
        self::create_user_mapping($externalsid, $proxy_user->id, $courseid);

        return $proxy_user;
    }

    private static function create_proxy_user(string $externalsid, int $courseid): \stdClass {
        $user = new \stdClass();
        $user->auth = 'manual';
        $user->confirmed = 1;
        $user->policyagreed = 1;
        $user->mnethostid = 1;
        $user->username = 'aicc_user_' . $externalsid;
        $user->password = \core\lock\manager::get_random_lock_secret();
        $user->firstname = 'AICC';
        $user->lastname = 'User ' . $externalsid;
        $user->email = 'aicc_user_' . $externalsid . '@example.com';
        $user->id = user_create_user($user);

        return $user;
    }

    private static function create_user_mapping(string $externalsid, int $internaluserid, int $courseid): void {
        global $DB;

        $mapping = new \stdClass();
        $mapping->externalsid = $externalsid;
        $mapping->internaluserid = $internaluserid;
        $mapping->courseid = $courseid;
        $mapping->timecreated = time();
        $mapping->timemodified = time();

        $DB->insert_record('local_aicc_hacp_users', $mapping);
    }
}
