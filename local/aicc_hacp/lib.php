<?php

defined('MOODLE_INTERNAL') || die();

function local_aicc_hacp_log($code, $message, $session_id = '', $command = '', $request_body = '', $signature_valid = 0, $parsed_data = []) {
    global $DB;
    $loglevel = get_config('local_aicc_hacp', 'log_level');

    if ($loglevel == 'none') {
        return;
    }
    if ($loglevel == 'error' && $code == 0) {
        return;
    }
    if ($loglevel == 'info' && $code != 0) {
        return;
    }

    $log = new \stdClass();
    $log->session_id = $session_id;
    $log->command = $command;
    $log->request_body = $request_body;
    $log->signature_valid = $signature_valid;
    $log->remote_ip = getremoteaddr();
    $log->user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $log->parsed_data_json = json_encode($parsed_data);
    $log->result_code = $code;
    $log->result_message = $message;
    $log->created_at = time();
    $DB->insert_record('local_aicc_hacp_logs', $log);
}

function local_aicc_hacp_respond($code, $message, $data = '') {
    echo "error=$code\n";
    echo "error_text=$message\n";
    if (!empty($data)) {
        echo "aicc_data=$data\n";
    }
    exit;
}
