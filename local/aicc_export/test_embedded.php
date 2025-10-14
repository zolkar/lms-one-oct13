<?php

require_once(__DIR__ . '/../../config.php');

// Test file for embedded content launcher
// This helps verify that the embedded content launcher works correctly

echo "<h1>AICC Export - Embedded Content Test</h1>";

// Test if we can access SCORM activities
$scorm_activities = $DB->get_records_sql("
    SELECT cm.id, cm.instance, s.name as scorm_name, c.shortname as course_shortname
    FROM {course_modules} cm
    JOIN {modules} m ON m.id = cm.module
    JOIN {scorm} s ON s.id = cm.instance
    JOIN {course} c ON c.id = cm.course
    WHERE m.name = 'scorm'
    ORDER BY c.shortname, s.name
    LIMIT 5
");

if (empty($scorm_activities)) {
    echo "<p><strong>No SCORM activities found.</strong> Please create a SCORM activity first.</p>";
} else {
    echo "<h2>Available SCORM Activities:</h2>";
    echo "<ul>";
    foreach ($scorm_activities as $activity) {
        $embedded_url = new \moodle_url('/local/aicc_export/embedded_content.php', [
            'id' => $activity->id
        ]);
        $file_server_url = new \moodle_url('/local/aicc_export/scorm_file_server.php', [
            'id' => $activity->id
        ]);
        $simple_embedded_url = new \moodle_url('/local/aicc_export/simple_embedded.php', [
            'id' => $activity->id
        ]);
        $direct_content_url = new \moodle_url('/local/aicc_export/direct_content.php', [
            'id' => $activity->id
        ]);
        echo "<li>";
        echo "<strong>{$activity->scorm_name}</strong> (Course: {$activity->course_shortname})<br>";
        echo "Direct Content URL: <a href='{$direct_content_url}' target='_blank'>{$direct_content_url}</a><br>";
        echo "Simple Embedded URL: <a href='{$simple_embedded_url}' target='_blank'>{$simple_embedded_url}</a><br>";
        echo "Embedded URL: <a href='{$embedded_url}' target='_blank'>{$embedded_url}</a><br>";
        echo "File Server URL: <a href='{$file_server_url}' target='_blank'>{$file_server_url}</a><br>";
        echo "Test in iframe: <a href='#' onclick='testIframe(\"{$direct_content_url}\")'>Test Direct</a> | ";
        echo "<a href='#' onclick='testIframe(\"{$simple_embedded_url}\")'>Test Simple</a> | ";
        echo "<a href='#' onclick='testIframe(\"{$embedded_url}\")'>Test Embedded</a> | ";
        echo "<a href='#' onclick='testIframe(\"{$file_server_url}\")'>Test File Server</a>";
        echo "</li><br>";
    }
    echo "</ul>";
    
    echo "<h2>Iframe Test:</h2>";
    echo "<div id='test-frame' style='width: 100%; height: 600px; border: 1px solid #ccc;'></div>";
    
    echo "<script>
    function testIframe(url) {
        document.getElementById('test-frame').innerHTML = '<iframe src=\"' + url + '\" width=\"100%\" height=\"100%\" frameborder=\"0\"></iframe>';
    }
    </script>";
}

echo "<h2>Instructions:</h2>";
echo "<ol>";
echo "<li>Click on any 'Test' link above to load the SCORM content in an iframe</li>";
echo "<li>Verify that only the SCORM content is displayed (no Moodle navigation)</li>";
echo "<li>This is how the content will appear in the receiving LMS</li>";
echo "</ol>";

echo "<p><a href='/local/aicc_export/'>Back to AICC Export</a></p>";
