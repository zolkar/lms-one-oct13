<?php
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../config.php');

require_once($CFG->dirroot . '/local/aicc_hacp/classes/secure_auth.php');
require_once(__DIR__ . '/classes/exporter.php');

$course = $DB->get_record('course', ['id' => 9], '*', MUST_EXIST);

echo "=== Testing AICC Export Token Generation ===\n\n";

// Generate tokens multiple times
for ($i = 1; $i <= 3; $i++) {
    echo "Export #{$i}:\n";
    
    // Create exporter
    $exporter = new \local_aicc_export\exporter($course);
    
    // Get the URL
    $activities = $DB->get_records_sql("
        SELECT cm.id, cm.instance, m.name as modname
        FROM {course_modules} cm
        JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
        WHERE cm.course = ? AND cm.deletioninprogress = 0
        ORDER BY cm.section, cm.id
        LIMIT 1
    ", [$course->id]);
    
    if (!empty($activities)) {
        $activity = reset($activities);
        $url = $exporter->get_hacp_launch_url($activity);
        echo "  URL: " . substr($url, 0, 100) . "...\n";
        
        // Extract token from URL
        if (preg_match('/token=([^&]+)/', $url, $matches)) {
            $token = $matches[1];
            
            // Decode and show timestamp
            $payload = json_decode(base64_decode(strtr($token, '-_', '+/')), true);
            if ($payload) {
                echo "  Token issued_at: " . $payload['issued_at'] . " (" . date('Y-m-d H:i:s', $payload['issued_at']) . ")\n";
                echo "  Token expires_at: " . $payload['expires_at'] . " (" . date('Y-m-d H:i:s', $payload['expires_at']) . ")\n";
            }
        }
    }
    
    echo "\n";
    sleep(1); // Wait 1 second between exports
}

echo "Done!\n";
