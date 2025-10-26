<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aicc_hacp_settings', get_string('pluginname', 'local_aicc_hacp'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtextarea(
        'local_aicc_hacp/allowed_origins',
        get_string('allowed_origins', 'local_aicc_hacp'),
        get_string('allowed_origins_desc', 'local_aicc_hacp'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_aicc_hacp/requests_per_minute',
        get_string('requests_per_minute', 'local_aicc_hacp'),
        get_string('requests_per_minute_desc', 'local_aicc_hacp'),
        '60',
        PARAM_INT
    ));

    $settings = new admin_externalpage(
        'local_aicc_hacp_manage',
        get_string('manage', 'local_aicc_hacp'),
        new moodle_url('/local/aicc_hacp/admin/index.php')
    );
    $ADMIN->add('localplugins', $settings);
}
