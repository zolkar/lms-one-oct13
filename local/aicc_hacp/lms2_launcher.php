<?php
/**
 * AICC Launch Wrapper for LMS-2
 * 
 * This file should be placed on LMS-2 at:
 * /local/aicc_hacp/lms2_launcher.php
 * 
 * Usage: This URL replaces the original content launcher URL in the .au file
 * 
 * Original URL in .au file:
 * http://localhost:8300/lms-one/local/aicc_export/content_launcher.php?id=42&token=...
 * 
 * Modified URL:
 * http://localhost:8301/lms-two/local/aicc_hacp/lms2_launcher.php?id=42&token=...
 * 
 * This wrapper will:
 * 1. Get the current logged-in user from LMS-2
 * 2. Append student information to the launch URL
 * 3. Redirect to LMS-1's content launcher
 */

// This would be on LMS-2, but since we're on LMS-1, this is just a reference
// The user needs to create this file on LMS-2

require_once(__DIR__ . '/../../../config.php');

require_login();

$launch_url = required_param('url', PARAM_URL);

// Get current user from LMS-2
$username = $USER->username;
$email = $USER->email;
$firstname = $USER->firstname;
$lastname = $USER->lastname;

// Build new URL with student parameters
$parsed = parse_url($launch_url);
$query_params = [];
if (isset($parsed['query'])) {
    parse_str($parsed['query'], $query_params);
}

// Replace any placeholders with actual values
$query_params['username'] = $username;
$query_params['email'] = $email;
$query_params['firstname'] = $firstname;
$query_params['lastname'] = $lastname;

// Rebuild the URL
$new_query = http_build_query($query_params);
$new_url = $parsed['scheme'] . '://' . $parsed['host'];
if (isset($parsed['port'])) {
    $new_url .= ':' . $parsed['port'];
}
$new_url .= $parsed['path'] . '?' . $new_query;

// Redirect to LMS-1 with student parameters
redirect($new_url);

