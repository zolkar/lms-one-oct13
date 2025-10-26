<?php
/**
 * AICC Use plugin library file
 */

defined('MOODLE_INTERNAL') || die();

/**
 * Serve the launcher.php file
 */
function local_aicc_use_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    return false; // This plugin doesn't use the file API
}

