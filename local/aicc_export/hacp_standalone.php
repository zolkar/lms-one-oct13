<?php
// Standalone HACP endpoint that bypasses Moodle authentication
// This is a simplified version for testing progress tracking

// Database connection parameters
$dbhost = 'moodle_db';
$dbname = 'lms_one';
$dbuser = 'root';
$dbpass = 'example';

try {
    $db = new PDO("mysql:host=$dbhost;dbname=$dbname", $dbuser, $dbpass, [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo "Database connection error: " . $e->getMessage();
    exit;
}

// Set content type
header('Content-Type: text/plain');

// Get request parameters
$command = isset($_GET['command']) ? $_GET['command'] : '';
$session_id = isset($_GET['session_id']) ? $_GET['session_id'] : '';
$aicc_data = isset($_GET['aicc_data']) ? $_GET['aicc_data'] : '';
$student_id = isset($_GET['AICC_SID']) ? $_GET['AICC_SID'] : '';

if (empty($command) || empty($session_id)) {
    echo "ERROR\r\n";
    echo "error_code=100\r\n";
    echo "error_text=Missing required parameters\r\n";
    exit;
}

// Get session from database
$stmt = $db->prepare("SELECT * FROM mdl_local_aicc_export_sessions WHERE session_id = ?");
$stmt->execute([$session_id]);
$session = $stmt->fetch(PDO::FETCH_OBJ);

if (!$session) {
    echo "ERROR\r\n";
    echo "error_code=103\r\n";
    echo "error_text=Session not found\r\n";
    exit;
}

// Use student_id from parameter or session
if (empty($student_id)) {
    $student_id = $session->student_id;
}

if (empty($student_id)) {
    echo "ERROR\r\n";
    echo "error_code=100\r\n";
    echo "error_text=Missing Student_ID\r\n";
    exit;
}

// Process command
switch (strtoupper($command)) {
    case 'GETPARAM':
        // Get stored state for this student
        $stmt = $db->prepare("SELECT * FROM mdl_local_aicc_hacp_student_state WHERE student_id = ? AND scormid = ? AND scoid = ?");
        $stmt->execute([$student_id, $session->scormid, $session->scoid]);
        $state = $stmt->fetch(PDO::FETCH_OBJ);
        
        if ($state) {
            // Return stored state data
            echo "SUCCESS\r\n";
            echo "Student_ID=" . $state->student_id . "\r\n";
            echo "Student_Name=" . $state->student_id . "\r\n";
            echo "Lesson_Location=" . $state->lesson_location . "\r\n";
            echo "Lesson_Status=" . $state->lesson_status . "\r\n";
            echo "Score=" . $state->score . "\r\n";
            echo "Time=" . $state->session_time . "\r\n";
            echo "Credit=credit\r\n";
        } else {
            // No stored state, return default values
            echo "SUCCESS\r\n";
            echo "Student_ID=" . $student_id . "\r\n";
            echo "Student_Name=" . $student_id . "\r\n";
            echo "Lesson_Location=\r\n";
            echo "Lesson_Status=not attempted\r\n";
            echo "Score=\r\n";
            echo "Time=00:00:00\r\n";
            echo "Credit=credit\r\n";
        }
        break;
        
    case 'PUTPARAM':
        // Parse AICC data
        $parsed_data = [];
        if (!empty($aicc_data)) {
            $lines = explode("\r\n", $aicc_data);
            foreach ($lines as $line) {
                if (strpos($line, '=') !== false) {
                    list($key, $value) = explode('=', $line, 2);
                    $parsed_data[trim($key)] = trim($value);
                }
            }
        }
        
        // Store or update student state
        $stmt = $db->prepare("SELECT * FROM mdl_local_aicc_hacp_student_state WHERE student_id = ? AND scormid = ? AND scoid = ?");
        $stmt->execute([$student_id, $session->scormid, $session->scoid]);
        $state = $stmt->fetch(PDO::FETCH_OBJ);
        
        if ($state) {
            // Update existing state
            $stmt = $db->prepare("UPDATE mdl_local_aicc_hacp_student_state SET 
                state_data = ?, 
                lesson_status = ?, 
                lesson_location = ?, 
                score = ?, 
                session_time = ?, 
                updated_at = ? 
                WHERE id = ?");
            $stmt->execute([
                json_encode($parsed_data),
                $parsed_data['Lesson_Status'] ?? $state->lesson_status,
                $parsed_data['Lesson_Location'] ?? $state->lesson_location,
                $parsed_data['Score'] ?? $state->score,
                $parsed_data['Time'] ?? $state->session_time,
                time(),
                $state->id
            ]);
        } else {
            // Create new state record
            $stmt = $db->prepare("INSERT INTO mdl_local_aicc_hacp_student_state 
                (student_id, scormid, scoid, state_data, lesson_status, lesson_location, score, session_time, created_at, updated_at) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $stmt->execute([
                $student_id,
                $session->scormid,
                $session->scoid,
                json_encode($parsed_data),
                $parsed_data['Lesson_Status'] ?? '',
                $parsed_data['Lesson_Location'] ?? '',
                $parsed_data['Score'] ?? '',
                $parsed_data['Time'] ?? '',
                time(),
                time()
            ]);
        }
        
        echo "SUCCESS\r\n";
        break;
        
    case 'EXITAU':
        // Update session status
        $stmt = $db->prepare("UPDATE mdl_local_aicc_export_sessions SET status = 'closed', last_activity_at = ? WHERE id = ?");
        $stmt->execute([time(), $session->id]);
        
        echo "SUCCESS\r\n";
        break;
        
    default:
        echo "ERROR\r\n";
        echo "error_code=101\r\n";
        echo "error_text=Unknown command\r\n";
        break;
}
?>
