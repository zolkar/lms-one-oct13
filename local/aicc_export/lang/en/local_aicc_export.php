$string['error_missing_token_secret'] = 'AICC launch token secret is not configured. Please contact your administrator.';
$string['export_failed'] = 'AICC export failed: {$a}';
<?php

defined('MOODLE_INTERNAL') || die();

$string['pluginname'] = 'AICC Export';
$string['aicc_export'] = 'AICC Export';
$string['aicc_export:export'] = 'Export AICC packages';
$string['settings_title'] = 'AICC Export Settings';
$string['setting_enabled'] = 'Enable AICC Export';
$string['setting_enabled_desc'] = 'Enable or disable the AICC export functionality.';
$string['setting_default_aicc_version'] = 'Default AICC Version';
$string['setting_default_aicc_version_desc'] = 'The default AICC version to be used for exported packages.';
$string['setting_launch_token_secret'] = 'Launch Token Secret';
$string['setting_launch_token_secret_desc'] = 'A secret key used to sign the launch tokens for the AICC packages.';
$string['setting_launch_token_ttl'] = 'Launch Token TTL';
$string['setting_launch_token_ttl_desc'] = 'The time-to-live (in seconds) for the launch tokens.';
$string['export_aicc_package'] = 'Export AICC Package';
$string['select_scorm'] = 'Select a SCORM activity to export';
$string['no_scorms_in_course'] = 'There are no SCORM activities in this course.';
$string['export'] = 'Export';
$string['error_course_not_found'] = 'Course not found.';
$string['error_scorm_not_found'] = 'SCORM activity not found.';
$string['error_not_entitled'] = 'You are not authorized to perform this action.';
$string['error_plugin_disabled'] = 'The AICC Export plugin is disabled.';
