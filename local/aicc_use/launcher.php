<?php
/**
 * AICC Launch Wrapper for LMS-2
 * 
 * This file wraps AICC launch URLs with current student information
 * before redirecting to LMS-1's content launcher.
 * 
 * Usage:
 * - Install this plugin on LMS-2
 * - Modify the .au file in the AICC package to point to this launcher
 * - This launcher will add student info and redirect to LMS-1
 * 
 * Example .au entry:
 * Original: http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=...
 * Modified: http://localhost:8301/lms-two/local/aicc_use/launcher.php?id=42&token=...
 */

require_once(__DIR__ . '/../../config.php');

require_login();

// Get the parameters that were passed through from the AICC package
$course_module_id = required_param('id', PARAM_INT);
$token = required_param('token', PARAM_RAW);

// Get all other parameters (like aicc_sid, aicc_url, etc.)
$other_params = $_GET;
unset($other_params['id'], $other_params['token']); // Remove params we already have

// Get current logged-in user information from LMS-2
$username = $USER->username;
$email = $USER->email;
$firstname = $USER->firstname;
$lastname = $USER->lastname;

// Determine the target LMS-1 URL based on the course or configuration
// For now, we'll extract it from the aicc_url parameter if available
$target_url = optional_param('target_lms', '', PARAM_URL);
if (empty($target_url)) {
    // Try to get from aicc_url parameter
    $aicc_url = optional_param('aicc_url', '', PARAM_URL);
    if (!empty($aicc_url)) {
        // Extract base URL (everything before the path)
        $parts = parse_url($aicc_url);
        $target_url = $parts['scheme'] . '://' . $parts['host'];
        if (isset($parts['port'])) {
            $target_url .= ':' . $parts['port'];
        }
    }
}

// If still empty, default to LMS-1
if (empty($target_url)) {
    $target_url = 'http://localhost:8300';
}

// Build the target URL with student parameters
$target_path = '/local/aicc_export/content_launcher.php';
$params = [
    'id' => $course_module_id,
    'token' => $token,
    'username' => $username,
    'email' => $email,
    'firstname' => $firstname,
    'lastname' => $lastname
];

// Add any other parameters that were passed through
if (!empty($other_params)) {
    foreach ($other_params as $key => $value) {
        if (strpos($key, 'http://') === 0) {
            // Skip malformed parameters
            continue;
        }
        $params[$key] = $value;
    }
}

// Build the final URL
$final_url = $target_url . $target_path . '?' . http_build_query($params);

// Log the redirect for debugging (remove in production if not needed)
error_log("AICC USE: Redirecting user {$username} ({$email}) to {$final_url}");

// Redirect to LMS-1 with student parameters
redirect($final_url);

