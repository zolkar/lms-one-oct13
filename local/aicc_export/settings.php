<?php

defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aicc_export_settings', get_string('settings_title', 'local_aicc_export'));
    $ADMIN->add('localplugins', $settings);

    $settings->add(new admin_setting_configcheckbox(
        'local_aicc_export/enabled',
        get_string('setting_enabled', 'local_aicc_export'),
        get_string('setting_enabled_desc', 'local_aicc_export'),
        0
    ));

    $settings->add(new admin_setting_configtext(
        'local_aicc_export/default_aicc_version',
        get_string('setting_default_aicc_version', 'local_aicc_export'),
        get_string('setting_default_aicc_version_desc', 'local_aicc_export'),
        '4.0',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configpasswordunmask(
        'local_aicc_export/launch_token_secret',
        get_string('setting_launch_token_secret', 'local_aicc_export'),
        get_string('setting_launch_token_secret_desc', 'local_aicc_export'),
        \core\uuid::generate()
    ));

    $settings->add(new admin_setting_configtext(
        'local_aicc_export/launch_token_ttl',
        get_string('setting_launch_token_ttl', 'local_aicc_export'),
        get_string('setting_launch_token_ttl_desc', 'local_aicc_export'),
        3600,
        PARAM_INT
    ));
}
