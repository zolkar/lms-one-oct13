<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $settings = new admin_settingpage('local_aicc_export', get_string('pluginname', 'local_aicc_export'));
    
    $ADMIN->add('localplugins', $settings);
    
    // LMS-2 Launcher URL setting
    $settings->add(new admin_setting_configtext(
        'local_aicc_export/lms2_launcher_url',
        get_string('lms2_launcher_url', 'local_aicc_export'),
        get_string('lms2_launcher_url_desc', 'local_aicc_export'),
        'http://localhost:8301/lms-two/local/aicc_use/launcher.php',
        PARAM_URL
    ));
    
    // Launch token TTL setting
    $settings->add(new admin_setting_configtext(
        'local_aicc_export/launch_token_ttl',
        get_string('launch_token_ttl', 'local_aicc_export'),
        get_string('launch_token_ttl_desc', 'local_aicc_export'),
        86400,  // 24 hours
        PARAM_INT
    ));
}
