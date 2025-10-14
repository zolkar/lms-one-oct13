<?php

require_once(__DIR__ . '/../../config.php');

// Test script to check SCO lookup
require_login();
require_capability('moodle/site:config', \context_system::instance());

echo "<h2>SCO Lookup Test</h2>";

// Find the first SCORM activity
$scorm = $DB->get_record('scorm', [], 'id,course', 'id,course');
if (!$scorm) {
    echo "<p style='color: red;'>No SCORM activities found!</p>";
    exit;
}

echo "<p>Testing SCORM activity: {$scorm->id}</p>";

// Test the old method (causes error)
echo "<h3>Old Method (get_record):</h3>";
try {
    $sco = $DB->get_record('scorm_scoes', ['scorm' => $scorm->id], 'id', 'id', 0, 1);
    if ($sco) {
        echo "<p style='color: green;'>✅ Found SCO: {$sco->id}</p>";
    } else {
        echo "<p style='color: red;'>❌ No SCO found</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Test the new method (should work)
echo "<h3>New Method (get_records):</h3>";
try {
    $scoes = $DB->get_records('scorm_scoes', ['scorm' => $scorm->id], 'id', 'id', 0, 1);
    if (!empty($scoes)) {
        $sco = reset($scoes);
        echo "<p style='color: green;'>✅ Found SCO: {$sco->id}</p>";
    } else {
        echo "<p style='color: red;'>❌ No SCO found</p>";
    }
} catch (Exception $e) {
    echo "<p style='color: red;'>❌ Error: " . $e->getMessage() . "</p>";
}

// Show all SCOs for this SCORM
echo "<h3>All SCOs for SCORM {$scorm->id}:</h3>";
$all_scoes = $DB->get_records('scorm_scoes', ['scorm' => $scorm->id], 'id', 'id,identifier,title');
if (!empty($all_scoes)) {
    echo "<ul>";
    foreach ($all_scoes as $sco) {
        echo "<li>ID: {$sco->id}, Identifier: {$sco->identifier}, Title: {$sco->title}</li>";
    }
    echo "</ul>";
} else {
    echo "<p>No SCOs found</p>";
}

echo "<p><a href='" . $CFG->wwwroot . "'>Return to site</a></p>";
