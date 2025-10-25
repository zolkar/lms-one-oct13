<?php
define('CLI_SCRIPT', true);
require_once('config.php');

echo "Checking SCORM content for course module 36...\n";

// Get the SCORM activity
$scorm = $DB->get_record('scorm', ['id' => 31]);
if ($scorm) {
    echo "SCORM Activity: " . $scorm->name . "\n";
    echo "SCORM ID: " . $scorm->id . "\n";
    
    // Get package files
    $fs = get_file_storage();
    $context = context_module::instance(36);
    $files = $fs->get_area_files($context->id, 'mod_scorm', 'package', 0, 'id', false);
    
    echo "Package files: " . count($files) . "\n";
    foreach ($files as $file) {
        echo "  File: " . $file->get_filename() . " (" . $file->get_filesize() . " bytes)\n";
    }
    
    // Get SCOs
    $scoes = $DB->get_records('scorm_scoes', ['scorm' => $scorm->id], 'id', 'id,identifier,title,launch');
    echo "SCOs: " . count($scoes) . "\n";
    foreach ($scoes as $sco) {
        echo "  SCO ID: " . $sco->id . " - " . $sco->identifier . " - " . $sco->title . "\n";
        echo "    Launch: " . $sco->launch . "\n";
    }
} else {
    echo "No SCORM activity found\n";
}
