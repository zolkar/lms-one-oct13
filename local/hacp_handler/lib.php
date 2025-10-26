<?php
defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/aicc_exporter/lib.php');
require_once($CFG->dirroot . '/enrol/locallib.php');

function local_hacp_handler_validate_token($token, $courseid) {
    return $token === local_aicc_exporter_generate_token($courseid);
}

function local_hacp_handler_get_proxy_user($remote_user_id, $courseid) {
    global $DB, $CFG;

    $user_mapping = $DB->get_record('local_hacp_handler_users', ['remote_user_id' => $remote_user_id, 'course_id' => $courseid]);
    if ($user_mapping) {
        return $DB->get_record('user', ['id' => $user_mapping->local_user_id]);
    }

    // Create a new proxy user
    $user = new stdClass();
    $user->username = 'proxy_' . $remote_user_id . '_' . $courseid;
    $user->password = auth_get_random_password();
    $user->firstname = 'Proxy';
    $user->lastname = $remote_user_id;
    $user->email = 'proxy_' . $remote_user_id . '_' . $courseid . '@' . $CFG->sitename;
    $user->auth = 'manual';
    $user->confirmed = 1;
    $user->lang = $CFG->lang;
    $user->id = user_create_user($user);

    if (!$user->id) {
        return false;
    }

    // Enrol the user in the course
    $enrol = enrol_get_plugin('manual');
    if ($enrol) {
        $enrol->enrol_user($enrol->get_course_enrolment_instance($courseid), $user->id, 5); // 5 = student
    }

    $mapping = new stdClass();
    $mapping->remote_user_id = $remote_user_id;
    $mapping->local_user_id = $user->id;
    $mapping->course_id = $courseid;
    $DB->insert_record('local_hacp_handler_users', $mapping);

    return $user;
}

function local_hacp_handler_get_param($remote_user_id, $courseid) {
    global $DB;
    $user = local_hacp_handler_get_proxy_user($remote_user_id, $courseid);
    if (!$user) {
        return "error=1\nerror_text=User not found\n";
    }

    $data = $DB->get_record('local_hacp_handler_data', ['user_id' => $user->id, 'course_id' => $courseid]);
    if ($data) {
        return "error=0\nerror_text=Success\naicc_data=" . $data->aicc_data;
    } else {
        return "error=0\nerror_text=Success\naicc_data=\n";
    }
}

function local_hacp_handler_put_param($remote_user_id, $courseid, $aicc_data) {
    global $DB;
    $user = local_hacp_handler_get_proxy_user($remote_user_id, $courseid);
    if (!$user) {
        return "error=1\nerror_text=User not found\n";
    }

    // Parse the AICC data and update Moodle's gradebook and activity completion
    // This is a complex task that requires a deep understanding of Moodle's APIs.
    // For this example, we will just store the raw data.
    $data = $DB->get_record('local_hacp_handler_data', ['user_id' => $user->id, 'course_id' => $courseid]);
    if ($data) {
        $data->aicc_data = $aicc_data;
        $DB->update_record('local_hacp_handler_data', $data);
    } else {
        $data = new stdClass();
        $data->user_id = $user->id;
        $data->course_id = $courseid;
        $data->aicc_data = $aicc_data;
        $DB->insert_record('local_hacp_handler_data', $data);
    }
    return "error=0\nerror_text=Success\n";
}

function local_hacp_handler_exit_au($remote_user_id, $courseid) {
    // This function is called when the user exits the course.
    // You can add any necessary cleanup or logging here.
    return "error=0\nerror_text=Success\n";
}
