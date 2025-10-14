<?php

require_once(__DIR__ . '/../../config.php');

// Debug script to see what's actually in the SCORM activity
require_login();
require_capability('moodle/site:config', \context_system::instance());

echo "<h2>SCORM Content Debug</h2>";

$courseid = optional_param('courseid', 0, PARAM_INT);
if (!$courseid) {
    echo "<p>Select a course:</p>";
    $courses = $DB->get_records('course', [], 'shortname', 'id,shortname,fullname', 0, 10);
    foreach ($courses as $course) {
        echo "<p><a href='?courseid={$course->id}'>{$course->shortname} - {$course->fullname}</a></p>";
    }
    exit;
}

$course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
echo "<h3>Course: {$course->fullname}</h3>";

// Get SCORM activities
$scorms = $DB->get_records_sql("
    SELECT cm.id, cm.instance, s.*
    FROM {course_modules} cm
    JOIN {scorm} s ON s.id = cm.instance
    WHERE cm.course = ? AND cm.module = (SELECT id FROM {modules} WHERE name = 'scorm')
    ORDER BY cm.section, cm.id
", [$courseid]);

if (empty($scorms)) {
    echo "<p style='color: red;'>No SCORM activities found!</p>";
    exit;
}

foreach ($scorms as $scorm) {
    echo "<h4>SCORM Activity: {$scorm->name}</h4>";
    echo "<p><strong>SCORM Type:</strong> " . ($scorm->scormtype == SCORM_TYPE_LOCAL ? 'Local Package' : 'External URL') . "</p>";
    
    if ($scorm->scormtype == SCORM_TYPE_LOCAL) {
        echo "<p><strong>Package Hash:</strong> {$scorm->sha1hash}</p>";
        
        // Get the files in this SCORM
        $context = \context_module::instance($scorm->id);
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'mod_scorm', 'content', 0, 'sortorder, itemid, filepath, filename', false);
        
        echo "<p><strong>Files in SCORM package:</strong></p>";
        echo "<ul>";
        foreach ($files as $file) {
            echo "<li>{$file->get_filepath()}{$file->get_filename()} (" . $file->get_filesize() . " bytes)</li>";
        }
        echo "</ul>";
        
        // Get SCOs
        $scoes = $DB->get_records('scorm_scoes', ['scorm' => $scorm->id], 'id', 'id,identifier,title,launch');
        echo "<p><strong>SCOs:</strong></p>";
        echo "<ul>";
        foreach ($scoes as $sco) {
            echo "<li>ID: {$sco->id}, Identifier: {$sco->identifier}, Title: {$sco->title}</li>";
            if (!empty($sco->launch)) {
                echo "<li style='margin-left: 20px;'>Launch: {$sco->launch}</li>";
            }
        }
        echo "</ul>";
        
    } else {
        echo "<p><strong>External URL:</strong> {$scorm->reference}</p>";
    }
}

echo "<p><a href='" . $CFG->wwwroot . "'>Return to site</a></p>";
