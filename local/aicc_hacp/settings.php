<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aicc_hacp_settings', get_string('settings_title', 'local_aicc_hacp'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_aicc_hacp/enabled',
        get_string('setting_enabled', 'local_aicc_hacp'),
        get_string('setting_enabled_desc', 'local_aicc_hacp'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_aicc_hacp/allowed_origins',
        get_string('setting_allowed_origins', 'local_aicc_hacp'),
        get_string('setting_allowed_origins_desc', 'local_aicc_hacp'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_aicc_hacp/shared_secret',
        get_string('setting_shared_secret', 'local_aicc_hacp'),
        get_string('setting_shared_secret_desc', 'local_aicc_hacp'),
        \core\uuid::generate()
    ));

    $settings->add(new admin_setting_configcheckbox(
        'local_aicc_hacp/require_https',
        get_string('setting_require_https', 'local_aicc_hacp'),
        get_string('setting_require_https_desc', 'local_aicc_hacp'),
        1
    ));

    $settings->add(new admin_setting_configselect(
        'local_aicc_hacp/log_level',
        get_string('setting_log_level', 'local_aicc_hacp'),
        get_string('setting_log_level_desc', 'local_aicc_hacp'),
        'error',
        [
            'none' => get_string('log_level_none', 'local_aicc_hacp'),
            'error' => get_string('log_level_error', 'local_aicc_hacp'),
            'info' => get_string('log_level_info', 'local_aicc_hacp'),
            'debug' => get_string('log_level_debug', 'local_aicc_hacp'),
        ]
    ));

    $settings->add(new admin_setting_configtext(
        'local_aicc_hacp/max_requests_per_minute',
        get_string('setting_max_requests_per_minute', 'local_aicc_hacp'),
        get_string('setting_max_requests_per_minute_desc', 'local_aicc_hacp'),
        60,
        PARAM_INT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_aicc_hacp/launch_token_secret',
        get_string('setting_launch_token_secret', 'local_aicc_hacp'),
        get_string('setting_launch_token_secret_desc', 'local_aicc_hacp'),
        \core\uuid::generate()
    ));

    $settings->add(new admin_setting_configtext(
        'local_aicc_hacp/launch_token_ttl',
        get_string('setting_launch_token_ttl', 'local_aicc_hacp'),
        get_string('setting_launch_token_ttl_desc', 'local_aicc_hacp'),
        86400,  // 24 hours
        PARAM_INT
    ));

    $settings->add(new admin_setting_configtext(
        'local_aicc_hacp/session_timeout',
        get_string('setting_session_timeout', 'local_aicc_hacp'),
        get_string('setting_session_timeout_desc', 'local_aicc_hacp'),
        7200,
        PARAM_INT
    ));

    $settings->add(new admin_setting_heading(
        'local_aicc_hacp_log_viewer',
        get_string('view_logs', 'local_aicc_hacp'),
        html_writer::link(new moodle_url('/local/aicc_hacp/admin/viewlog.php'), get_string('view_logs_desc', 'local_aicc_hacp'))
    ));

    $settings->add(new admin_setting_heading(
        'local_aicc_hacp_manual_mapping',
        get_string('manual_mapping', 'local_aicc_hacp'),
        html_writer::link(new moodle_url('/local/aicc_hacp/admin/manualmap.php'), get_string('manual_mapping_desc', 'local_aicc_hacp'))
    ));
}
