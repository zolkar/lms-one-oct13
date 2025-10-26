<?php
/**
 * Uninstallation code for local_aicc_hacp plugin
 *
 * @package    local_aicc_hacp
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Uninstall plugin
 * Note: Tables are automatically dropped by Moodle's uninstall process
 */
function xmldb_local_aicc_hacp_uninstall() {
    global $DB;
    
    // Log uninstallation
    error_log('local_aicc_hacp plugin uninstalled');
    
    // Purge caches
    purge_all_caches();
    
    return true;
}
