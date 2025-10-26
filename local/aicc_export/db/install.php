<?php
/**
 * Installation code for local_aicc_export plugin
 *
 * @package    local_aicc_export
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Post-installation code for local_aicc_export
 */
function xmldb_local_aicc_export_install() {
    global $CFG;
    
    // Ensure default settings are configured
    set_config('enabled', 1, 'local_aicc_export');
    set_config('default_aicc_version', '4.0', 'local_aicc_export');
    
    // Generate launch token secret if not already set
    if (empty(get_config('local_aicc_export', 'launch_token_secret'))) {
        $secret = \core\uuid::generate();
        set_config('launch_token_secret', $secret, 'local_aicc_export');
    }
    
    if (empty(get_config('local_aicc_export', 'launch_token_ttl'))) {
        set_config('launch_token_ttl', 3600, 'local_aicc_export'); // 1 hour default
    }
    
    return true;
}