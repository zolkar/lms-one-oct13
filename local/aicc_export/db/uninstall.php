<?php
/**
 * Uninstallation code for local_aicc_export plugin
 *
 * @package    local_aicc_export
 * @copyright  2024
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Uninstall plugin
 */
function xmldb_local_aicc_export_uninstall() {
    global $DB;
    
    // Purge caches
    purge_all_caches();
    
    return true;
}
