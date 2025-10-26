<?php
defined('MOODLE_INTERNAL') || die();

if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_category('local_aicc_exporter', get_string('pluginname', 'local_aicc_exporter')));
    $settings = new admin_settingpage('local_aicc_exporter_settings', get_string('pluginname', 'local_aicc_exporter'));
    $settings->add(new admin_setting_heading('local_aicc_exporter_settings_heading', '', get_string('export_course_summary', 'local_aicc_exporter')));
    $settings->add(new admin_externalpage('local_aicc_exporter_export', get_string('export_course', 'local_aicc_exporter'), new moodle_url('/local/aicc_exporter/index.php')));

    $settings->add(new admin_setting_configtext('local_aicc_exporter/hacp_endpoint', get_string('hacp_endpoint_url', 'local_hacp_handler'), get_string('hacp_endpoint_url_desc', 'local_hacp_handler'), '', PARAM_URL));

    $ADMIN->add('local_aicc_exporter', $settings);
}
