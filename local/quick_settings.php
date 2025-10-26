<?php
/**
 * Quick settings page for AICC plugins
 * Direct access to configure AICC export and HACP settings
 */

require_once(__DIR__ . '/../config.php');
require_login();
require_capability('moodle/site:config', context_system::instance());

$PAGE->set_context(context_system::instance());
$PAGE->set_url('/local/quick_settings.php');
$PAGE->set_title('AICC Plugin Settings');
$PAGE->set_heading('AICC Plugin Settings');

echo $OUTPUT->header();

// Get current settings
$launcher_url = get_config('local_aicc_export', 'lms2_launcher_url');
$token_ttl = get_config('local_aicc_export', 'launch_token_ttl');
$hacp_enabled = get_config('local_aicc_hacp', 'enabled');
$launch_token_secret = get_config('local_aicc_hacp', 'launch_token_secret');
$hacp_token_ttl = get_config('local_aicc_hacp', 'launch_token_ttl');

// Process form submission
if (optional_param('save', false, PARAM_BOOL)) {
    set_config('lms2_launcher_url', optional_param('lms2_launcher_url', '', PARAM_URL), 'local_aicc_export');
    set_config('launch_token_ttl', optional_param('launch_token_ttl', 86400, PARAM_INT), 'local_aicc_export');
    set_config('enabled', optional_param('hacp_enabled', 0, PARAM_INT), 'local_aicc_hacp');
    set_config('launch_token_secret', optional_param('launch_token_secret', '', PARAM_TEXT), 'local_aicc_hacp');
    set_config('launch_token_ttl', optional_param('hacp_token_ttl', 86400, PARAM_INT), 'local_aicc_hacp');
    
    redirect($PAGE->url, 'Settings saved successfully', null, \core\output\notification::NOTIFY_SUCCESS);
}

// Display form
echo html_writer::start_tag('form', ['method' => 'post', 'action' => $PAGE->url]);
echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => 'save', 'value' => '1']);

echo html_writer::tag('h2', 'AICC Export Settings');
echo html_writer::start_div();

echo html_writer::tag('label', 'LMS-2 Launcher URL:', ['for' => 'lms2_launcher_url', 'style' => 'display:block;margin-top:10px;']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'lms2_launcher_url',
    'id' => 'lms2_launcher_url',
    'value' => $launcher_url ?: 'http://localhost:8300/lms-two/local/aicc_use/launcher.php',
    'style' => 'width: 100%; max-width: 600px;',
    'class' => 'form-control'
]);
echo html_writer::tag('small', 'Full URL to the aicc_use launcher on LMS-2', ['class' => 'form-text text-muted']);

echo html_writer::tag('label', 'Launch Token TTL (seconds):', ['for' => 'launch_token_ttl', 'style' => 'display:block;margin-top:10px;']);
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'name' => 'launch_token_ttl',
    'id' => 'launch_token_ttl',
    'value' => $token_ttl ?: 86400,
    'style' => 'width: 200px;',
    'class' => 'form-control'
]);
echo html_writer::tag('small', 'How long (in seconds) the launch tokens are valid', ['class' => 'form-text text-muted']);

echo html_writer::end_div();

echo html_writer::tag('h2', 'AICC HACP Settings');
echo html_writer::start_div();

echo html_writer::start_div('form-check');
echo html_writer::empty_tag('input', [
    'type' => 'checkbox',
    'name' => 'hacp_enabled',
    'id' => 'hacp_enabled',
    'value' => '1',
    'class' => 'form-check-input',
    'checked' => $hacp_enabled ? 'checked' : ''
]);
echo html_writer::tag('label', 'Enable HACP communication', ['for' => 'hacp_enabled', 'class' => 'form-check-label']);
echo html_writer::end_div();

echo html_writer::tag('label', 'Launch Token Secret:', ['for' => 'launch_token_secret', 'style' => 'display:block;margin-top:10px;']);
echo html_writer::empty_tag('input', [
    'type' => 'text',
    'name' => 'launch_token_secret',
    'id' => 'launch_token_secret',
    'value' => $launch_token_secret ?: '',
    'style' => 'width: 100%; max-width: 600px;',
    'class' => 'form-control'
]);
echo html_writer::tag('small', 'Secret key for signing JWT launch tokens', ['class' => 'form-text text-muted']);

echo html_writer::tag('label', 'Launch Token TTL (seconds):', ['for' => 'hacp_token_ttl', 'style' => 'display:block;margin-top:10px;']);
echo html_writer::empty_tag('input', [
    'type' => 'number',
    'name' => 'hacp_token_ttl',
    'id' => 'hacp_token_ttl',
    'value' => $hacp_token_ttl ?: 86400,
    'style' => 'width: 200px;',
    'class' => 'form-control'
]);
echo html_writer::tag('small', 'How long (in seconds) the HACP launch tokens are valid', ['class' => 'form-text text-muted']);

echo html_writer::end_div();

// Show current exportable courses
echo html_writer::tag('h3', 'Quick Links');

echo html_writer::start_div();
$export_url = new moodle_url('/local/aicc_export/index.php');
echo html_writer::link($export_url, 'Export Courses as AICC Packages', ['class' => 'btn btn-primary']);
echo html_writer::end_div();

echo html_writer::start_div('mt-3');
$progress_url = new moodle_url('/local/aicc_hacp/admin/external_progress.php');
echo html_writer::link($progress_url, 'View External Student Progress', ['class' => 'btn btn-info']);
echo html_writer::end_div();

echo html_writer::tag('button', 'Save Settings', ['type' => 'submit', 'class' => 'btn btn-success', 'style' => 'margin-top: 20px;']);
echo html_writer::end_tag('form');

echo $OUTPUT->footer();

