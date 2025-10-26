<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aicc_export_settings', get_string('pluginname', 'local_aicc_export'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configtext(
        'local_aicc_export/secret_key',
        get_string('secret_key', 'local_aicc_export'),
        get_string('secret_key_desc', 'local_aicc_export'),
        '',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configtext(
        'local_aicc_export/launch_token_ttl',
        get_string('launch_token_ttl', 'local_aicc_export'),
        get_string('launch_token_ttl_desc', 'local_aicc_export'),
        '3600',
        PARAM_INT
    ));
}
