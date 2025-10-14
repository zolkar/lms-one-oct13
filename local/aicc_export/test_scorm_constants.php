<?php

require_once(__DIR__ . '/../../config.php');

// Test SCORM constants
echo "<h2>SCORM Constants Test</h2>";

// Include SCORM library
require_once($CFG->dirroot . '/mod/scorm/lib.php');

echo "<p><strong>SCORM Constants:</strong></p>";
echo "<ul>";
echo "<li>SCORM_TYPE_LOCAL: " . (defined('SCORM_TYPE_LOCAL') ? SCORM_TYPE_LOCAL : 'Not defined') . "</li>";
echo "<li>SCORM_TYPE_EXTERNAL: " . (defined('SCORM_TYPE_EXTERNAL') ? SCORM_TYPE_EXTERNAL : 'Not defined') . "</li>";
echo "<li>SCORM_TYPE_AICCURL: " . (defined('SCORM_TYPE_AICCURL') ? SCORM_TYPE_AICCURL : 'Not defined') . "</li>";
echo "</ul>";

echo "<p><a href='" . $CFG->wwwroot . "'>Return to site</a></p>";
