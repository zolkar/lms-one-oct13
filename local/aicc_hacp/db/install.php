<?php
/**
 * Installation code for local_aicc_hacp plugin
 *
 * @package    local_aicc_hacp
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Post-installation code for local_aicc_hacp
 */
function xmldb_local_aicc_hacp_install() {
    global $CFG;
    
    // Ensure default settings are configured
    set_config('enabled', 1, 'local_aicc_hacp');
    set_config('log_level', 'error', 'local_aicc_hacp');
    set_config('max_requests_per_minute', 60, 'local_aicc_hacp');
    set_config('session_timeout', 7200, 'local_aicc_hacp'); // 2 hours
    set_config('require_https', 0, 'local_aicc_hacp'); // Allow HTTP for testing
    
    // Generate shared secret if not already set
    if (empty(get_config('local_aicc_hacp', 'shared_secret'))) {
        $secret = \core\uuid::generate();
        set_config('shared_secret', $secret, 'local_aicc_hacp');
    }
    
    // Generate launch token secret if not already set
    if (empty(get_config('local_aicc_hacp', 'launch_token_secret'))) {
        $secret = \core\uuid::generate();
        set_config('launch_token_secret', $secret, 'local_aicc_hacp');
    }
    
    if (empty(get_config('local_aicc_hacp', 'launch_token_ttl'))) {
        set_config('launch_token_ttl', 3600, 'local_aicc_hacp'); // 1 hour default
    }
    
    // Set allowed origins for localhost testing
    set_config('allowed_origins', 'http://localhost:8300,http://localhost', 'local_aicc_hacp');
    
    return true;
}