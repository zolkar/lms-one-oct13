<?php
namespace local_aicc_export;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/launcher.php');

class exporter {
    protected $course;
    protected $activities;

    public function __construct(\stdClass $course) {
        global $DB;
        $this->course = $course;
        
        // Get all SCORM activities in the course
        try {
            $this->activities = $DB->get_records_sql("
                SELECT cm.id, cm.instance, m.name as modname, m.id as moduleid
                FROM {course_modules} cm
                JOIN {modules} m ON m.id = cm.module AND m.name = 'scorm'
                WHERE cm.course = ? AND cm.deletioninprogress = 0
                ORDER BY cm.section, cm.id
            ", [$course->id]);
            
            // If no SCORM activities found, throw error
            if (empty($this->activities)) {
                throw new \moodle_exception('no_scorms_in_course', 'local_aicc_export');
            }
        } catch (\dml_exception $e) {
            error_log('AICC Export database error: ' . $e->getMessage());
            throw new \moodle_exception('error_reading_database', 'local_aicc_export', '', $e->getMessage());
        }
    }

    public function generate_package(): string {
        $zip = new \ZipArchive();
        $zipfilename = tempnam(sys_get_temp_dir(), 'aicc_export_') . '.zip';

        if ($zip->open($zipfilename, \ZipArchive::CREATE) !== TRUE) {
            throw new \moodle_exception('error_zip_create', 'local_aicc_export');
        }

        $basefilename = clean_filename($this->course->shortname);

        // Generate AICC descriptor files with URLs pointing back to this LMS
        $zip->addFromString($basefilename . '.crs', $this->get_crs_content());
        $zip->addFromString($basefilename . '.cst', $this->get_cst_content());
        $zip->addFromString($basefilename . '.des', $this->get_des_content());
        $zip->addFromString($basefilename . '.au', $this->get_au_content());
        $zip->addFromString($basefilename . '.ort', $this->get_ort_content());
        $zip->addFromString($basefilename . '.pre', $this->get_pre_content());
        $zip->addFromString($basefilename . '.cmp', $this->get_cmp_content());

        $zip->close();
        return $zipfilename;
    }

    protected function get_crs_content(): string {
        $content  = "[Course]\r\n";
        $content .= "Course_ID = " . $this->escape_aicc($this->course->shortname) . "\r\n";
        $content .= "Course_Title = " . $this->escape_aicc($this->course->fullname) . "\r\n";
        $content .= "Course_Level = 1\r\n";
        $content .= "Max_Normal = 1\r\n";
        $content .= "Version = " . (get_config('local_aicc_export', 'default_aicc_version') ?: '4.0') . "\r\n";
        $content .= "Total_AUs = 1\r\n";
        if (!empty($this->course->summary)) {
            $content .= "Course_Description = " . $this->escape_aicc($this->course->summary) . "\r\n";
        }
        $content .= "\r\n[CORE_VENDOR]\r\n";
        $content .= "Moodle\r\n";
        return $content;
    }

    protected function get_cst_content(): string {
        // Create an empty CST file with just the header
        // This avoids the parsing error where CST tries to set parent on null elements
        $content = "Block,Title,Type,Parent,AU\r\n";
        return $content;
    }

    protected function get_des_content(): string {
        $header = [
            'system_id', 'title', 'parent', 'type', 'command_line', 'Max_Time_Allowed', 'time_limit_action',
            'file_name', 'max_score', 'mastery_score', 'system_vendor', 'core_vendor', 'web_launch', 'AU_password'
        ];
        $rows = [];
        $rows[] = '"' . implode('","', $header) . '"';
        
        // Create a single AU1 element that represents the course
        $title = $this->escape_aicc($this->course->fullname);
        
        // For the launch URL, we'll create a simple course view URL
        $launch_url = $this->get_course_launch_url();

        $row = [
            'AU1',
            $title,
            '/', // parent
            '', // type
            '', // command_line
            '', // Max_Time_Allowed
            '', // time_limit_action
            $launch_url, // file_name - URL
            '', // max_score
            '', // mastery_score
            'Moodle', // system_vendor
            '', // core_vendor
            $launch_url, // web_launch - URL
            '' // AU_password
        ];
        
        $row = array_map(function($v) {
            return '"' . str_replace('"', '""', $v) . '"';
        }, $row);
        $rows[] = implode(',', $row);
        
        return implode("\r\n", $rows);
    }

    protected function get_au_content(): string {
        // AU file is typically identical to DES file for simple AICC packages
        return $this->get_des_content();
    }

    protected function get_ort_content(): string {
        return "[Objectives]\r\n";
    }

    protected function get_pre_content(): string {
        return "[Prerequisites]\r\n";
    }

    protected function get_cmp_content(): string {
        return "[Completion]\r\n";
    }

    protected function get_activity_title($activity): string {
        global $DB;
        
        // Get the actual activity name based on module type
        switch ($activity->modname) {
            case 'scorm':
                $scorm = $DB->get_record('scorm', ['id' => $activity->instance]);
                return $scorm ? $scorm->name : 'SCORM Activity';
            case 'resource':
                $resource = $DB->get_record('resource', ['id' => $activity->instance]);
                return $resource ? $resource->name : 'Resource';
            case 'page':
                $page = $DB->get_record('page', ['id' => $activity->instance]);
                return $page ? $page->name : 'Page';
            case 'lesson':
                $lesson = $DB->get_record('lesson', ['id' => $activity->instance]);
                return $lesson ? $lesson->name : 'Lesson';
            case 'quiz':
                $quiz = $DB->get_record('quiz', ['id' => $activity->instance]);
                return $quiz ? $quiz->name : 'Quiz';
            default:
                return 'Activity ' . $activity->id;
        }
    }

    protected function get_course_launch_url(): string {
        global $CFG;
        
        // Create AICC HACP URL that points to a SCORM activity in this course
        // This allows seamless communication without requiring student login
        $scorm_activities = array_filter($this->activities, function($activity) {
            return $activity->modname === 'scorm';
        });
        
        if (!empty($scorm_activities)) {
            // Use the first SCORM activity for HACP communication
            $scorm_activity = reset($scorm_activities);
            return $this->get_hacp_launch_url($scorm_activity);
        } else {
            // If no SCORM activities, create a simple course URL
            // But this won't work with HACP - need SCORM for proper AICC communication
            $courseurl = new \moodle_url('/course/view.php', ['id' => $this->course->id]);
            return $courseurl->out(false);
        }
    }
    
    protected function get_hacp_launch_url($activity): string {
        global $CFG, $DB; // $DB is already global in Moodle context
        
        // Generate a secure token for this SCORM activity
        $scorm = $DB->get_record('scorm', ['id' => $activity->instance], '*', MUST_EXIST);
        
        // Include the secure_auth class from the aicc_hacp plugin  
        $secure_auth_path = $CFG->dirroot . '/local/aicc_hacp/classes/secure_auth.php';
        if (!file_exists($secure_auth_path)) {
            throw new \moodle_exception('error_plugin_not_installed', 'local_aicc_export', '', 'local_aicc_hacp');
        }
        require_once($secure_auth_path);
        $token = \local_aicc_hacp\secure_auth::generate_launch_token(
            $this->course->id,
            $scorm->id,
            'external_lms'
        );
        
        // Debug: Log the token generation  
        error_log("AICC Export: Generating token at timestamp " . time() . " for course {$this->course->id}, scorm {$scorm->id}");
        error_log("AICC Export: Token payload starts with: " . substr($token, 0, 50) . "...");
        
        // Build the URL based on configuration
        // If LMS-2 launcher URL is configured, use it
        // Otherwise, use direct launch with placeholders
        
        $lms2_launcher_url = get_config('local_aicc_export', 'lms2_launcher_url');
        
        if (!empty($lms2_launcher_url)) {
            // Use LMS-2 launcher (recommended - sends real student data)
            $params = [
                'id' => $activity->id,
                'token' => $token,
                'target_lms' => $CFG->wwwroot // Tell launcher where LMS-1 is
            ];
            
            $content_url = $lms2_launcher_url . '?' . http_build_query($params);
            error_log("AICC Export: Using LMS-2 launcher: {$content_url}");
            return $content_url;
        } else {
            // Direct launch with placeholders (fallback)
            $content_url = new \moodle_url('/local/aicc_export/content_launcher.php', [
                'id' => $activity->id,
                'token' => $token,
                'username' => '{{student.username}}',
                'email' => '{{student.email}}',
                'firstname' => '{{student.firstname}}',
                'lastname' => '{{student.lastname}}'
            ]);
            
            error_log("AICC Export: Using direct launch with placeholders");
            return $content_url->out(true);
        }
    }
    
    protected function get_launch_url($activity): string {
        global $CFG;
        
        // For SCORM activities, use the content launcher
        if ($activity->modname === 'scorm') {
            return $this->get_hacp_launch_url($activity);
        } else {
            // For other activities, create a simple launch URL
            $modurl = new \moodle_url('/mod/' . $activity->modname . '/view.php', ['id' => $activity->id]);
            return $modurl->out(false);
        }
    }
    protected function escape_aicc($value): string {
        if ($value === null) return '';
        $value = str_replace(["\r", "\n"], '', (string)$value);
        return trim($value);
    }
}

class course_exporter extends exporter {
    public function __construct(\stdClass $course) {
        parent::__construct($course);
    }

    public function generate_package(): string {
        return parent::generate_package();
    }
}