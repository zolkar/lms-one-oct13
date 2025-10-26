<?php

$string['pluginname'] = 'AICC HACP';
$string['external_progress_report'] = 'External Students Progress';
$string['student_details'] = 'Student Details';
$string['no_external_students'] = 'No external students found';
$string['course'] = 'Course';
$string['scorm_activity'] = 'SCORM Activity';
$string['external_students'] = 'External Students';
$string['active_sessions'] = 'Active Sessions';
$string['actions'] = 'Actions';
$string['view_details'] = 'View Details';
$string['student_id'] = 'Student ID';
$string['origin_lms'] = 'Origin LMS';
$string['lesson_status'] = 'Lesson Status';
$string['score'] = 'Score';
$string['session_time'] = 'Session Time';
$string['last_activity'] = 'Last Activity';
$string['session_status'] = 'Session Status';
$string['reset_progress'] = 'Reset Progress';
$string['confirm_reset_progress'] = 'Are you sure you want to reset this student\'s progress? This action cannot be undone.';
$string['summary_statistics'] = 'Summary Statistics';
$string['total_external_students'] = 'Total External Students';
$string['completed_students'] = 'Completed Students';
$string['passed_students'] = 'Passed Students';
$string['all_courses'] = 'All Courses';
$string['no_scoes'] = 'No SCOs found for this SCORM activity';

// Settings
$string['settings_title'] = 'AICC HACP Settings';
$string['setting_enabled'] = 'Enable AICC HACP';
$string['setting_enabled_desc'] = 'Enable the AICC HACP service for external LMS communication';
$string['setting_allowed_origins'] = 'Allowed Origins';
$string['setting_allowed_origins_desc'] = 'Comma-separated list of allowed origins (leave empty to allow all)';
$string['setting_shared_secret'] = 'Shared Secret';
$string['setting_shared_secret_desc'] = 'Secret key for HMAC signature validation';
$string['setting_require_https'] = 'Require HTTPS';
$string['setting_require_https_desc'] = 'Require HTTPS for all HACP communications';
$string['setting_log_level'] = 'Log Level';
$string['setting_log_level_desc'] = 'Level of logging for HACP requests';
$string['setting_max_requests_per_minute'] = 'Max Requests Per Minute';
$string['setting_max_requests_per_minute_desc'] = 'Maximum number of requests per minute per session (0 = no limit)';
$string['setting_launch_token_secret'] = 'Launch Token Secret';
$string['setting_launch_token_secret_desc'] = 'Secret key for generating launch tokens';
$string['setting_launch_token_ttl'] = 'Launch Token TTL';
$string['setting_launch_token_ttl_desc'] = 'Time-to-live for launch tokens in seconds';
$string['setting_session_timeout'] = 'Session Timeout';
$string['setting_session_timeout_desc'] = 'Session timeout in seconds';

// Log levels
$string['log_level_none'] = 'None';
$string['log_level_error'] = 'Errors Only';
$string['log_level_info'] = 'Info and Errors';
$string['log_level_debug'] = 'Debug (All)';

$string['progress_reset_success'] = 'Student progress has been reset successfully';
$string['progress_reset_error'] = 'Error resetting student progress';
$string['delete'] = 'Delete';
$string['confirm_delete_student'] = 'Are you sure you want to delete this student and all their data? This action cannot be undone.';
$string['student_deleted'] = 'Student has been deleted successfully';
$string['error_deleting_student'] = 'Error deleting student';
$string['view_logs'] = 'View Logs';
$string['view_logs_desc'] = 'View AICC HACP request logs';
$string['manual_mapping'] = 'Manual User Mapping';
$string['manual_mapping_desc'] = 'Manually map external users to internal users';