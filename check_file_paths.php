<?php
define('CLI_SCRIPT', true);
require_once('config.php');

echo "Checking SCORM file paths...\n";

$fs = get_file_storage();
$context = context_module::instance(36);
$files = $fs->get_area_files($context->id, 'mod_scorm', 'package', 0, 'id', false);

foreach ($files as $file) {
    echo "File: " . $file->get_filename() . "\n";
    echo "Path: " . $file->get_filepath() . "\n";
    echo "Full path: " . $file->get_filepath() . $file->get_filename() . "\n";
    echo "Content hash: " . $file->get_contenthash() . "\n";
    echo "---\n";
}
