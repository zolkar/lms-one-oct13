<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../config.php');

$cmid = 42;

$cm = get_coursemodule_from_id('', $cmid, 0, false);
$fs = get_file_storage();
$cmcontext = context_module::instance($cm->id);

$files = $fs->get_area_files($cmcontext->id, 'mod_scorm', 'content', 0, '/', null);

echo "Files in SCORM:\n";
foreach ($files as $f) {
    echo "- " . $f->get_filepath() . $f->get_filename() . "\n";
}

// Find main HTML file
foreach ($files as $f) {
    if (strpos(strtolower($f->get_filename()), 'index') !== false || 
        strpos(strtolower($f->get_filename()), '.html') !== false) {
        echo "\n=== Main HTML File ===\n";
        echo "Path: " . $f->get_filepath() . $f->get_filename() . "\n";
        $content = $f->get_content();
        
        // Extract src and href attributes
        preg_match_all('/(src|href)=["\']([^"\']+)["\']/i', $content, $matches);
        echo "\nAll URLs found:\n";
        foreach ($matches[0] as $url) {
            echo "- $url\n";
        }
        
        echo "\nFirst 1000 chars of HTML:\n";
        echo substr($content, 0, 1000);
        break;
    }
}
