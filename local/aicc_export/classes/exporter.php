<?php
namespace local_aicc_export;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/launcher.php');

class exporter {
    /**
     * @var array<int,object> Map of SCO id to SCO object
     */
    protected $sco_id_map = [];
    /**
     * @var array<string,int> Map of SCO identifier to SCO id
     */
    protected $sco_identifier_map = [];

    protected $course;
    protected $scorm;
    protected $scos;

    public function __construct(\stdClass $course, \stdClass $scorm) {
        global $DB;
        $this->course = $course;
        $this->scorm = $scorm;
        $this->scos = $DB->get_records('scorm_scoes', ['scorm' => $this->scorm->id], 'id');
        // Build a map of id => sco and identifier => id for parent/child mapping.
        $this->sco_id_map = [];
        $this->sco_identifier_map = [];
        foreach ($this->scos as $sco) {
            $this->sco_id_map[$sco->id] = $sco;
            if (!empty($sco->identifier)) {
                $this->sco_identifier_map[$sco->identifier] = $sco->id;
            }
        }
    }

    public function generate_package(): string {
        $zip = new \ZipArchive();
        $zipfilename = tempnam(sys_get_temp_dir(), 'aicc_export_') . '.zip';

        if ($zip->open($zipfilename, \ZipArchive::CREATE) !== TRUE) {
            throw new \moodle_exception('error_zip_create', 'local_aicc_export');
        }

        $basefilename = clean_filename($this->course->shortname);

        // Always generate all 7 AICC files, even if empty.
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
        $content .= "Total_AUs = " . count($this->scos) . "\r\n";
        if (!empty($this->scorm->masteryscore)) {
            $content .= "Mastery_Score = " . $this->scorm->masteryscore . "\r\n";
        }
        if (!empty($this->course->summary)) {
            $content .= "Course_Description = " . $this->escape_aicc($this->course->summary) . "\r\n";
        }
        $content .= "\r\n[CORE_VENDOR]\r\n";
        $content .= "Moodle\r\n";
        return $content;
    }

    protected function get_cst_content(): string {
        $content = "Block,Title,Type,Parent,AU\r\n";
        foreach ($this->scos as $sco) {
            $parent = ($sco->parent === '/') ? '' : 'B' . $sco->parent;
            $title = $this->escape_aicc($this->get_sco_title($sco));
            $content .= "B{$sco->id},\"{$title}\",N,{$parent},AU{$sco->id}\r\n";
        }
        return $content;
    }

    protected function get_des_content(): string {
        $header = [
            'system_id', 'title', 'parent', 'type', 'command_line', 'Max_Time_Allowed', 'time_limit_action',
            'file_name', 'max_score', 'mastery_score', 'system_vendor', 'core_vendor', 'web_launch', 'AU_password'
        ];
        $rows = [];
        $rows[] = '"' . implode('","', $header) . '"';
        
        foreach ($this->scos as $sco) {
            $auid = 'AU' . $sco->id;
            $title = $this->escape_aicc($this->get_sco_title($sco));
            
            // Determine parent
            $parent = '/';
            if (!empty($sco->parent) && $sco->parent !== '/' && $sco->parent !== $sco->organization) {
                $parent = 'AU' . $sco->parent;
            }
            
            // Get launch file - use the actual SCORM content file
            $file_name = $this->get_launch_file($sco);
            
            $max_score = '';
            $mastery_score = '';
            if (!empty($this->scorm->maxgrade) && is_numeric($this->scorm->maxgrade)) {
                $max_score = $this->scorm->maxgrade;
                $mastery_score = $this->scorm->maxgrade;
            }

            $row = [
                $auid,
                $title,
                $parent,
                '', // type
                '', // command_line
                '', // Max_Time_Allowed
                '', // time_limit_action
                $file_name,
                $max_score,
                $mastery_score,
                'Moodle', // system_vendor
                '', // core_vendor
                $file_name, // web_launch
                '' // AU_password
            ];
            
            $row = array_map(function($v) {
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row);
            $rows[] = implode(',', $row);
        }
        return implode("\r\n", $rows);
    }

    protected function get_au_content(): string {
        $header = [
            'system_id', 'title', 'parent', 'type', 'command_line', 'Max_Time_Allowed', 'time_limit_action',
            'file_name', 'max_score', 'mastery_score', 'system_vendor', 'core_vendor', 'web_launch', 'AU_password'
        ];
        $rows = [];
        $rows[] = '"' . implode('","', $header) . '"';

        foreach ($this->scos as $sco) {
            $auid = 'AU' . $sco->id;
            $title = $this->escape_aicc($this->get_sco_title($sco));
            
            // Determine parent
            $parent = '/';
            if (!empty($sco->parent) && $sco->parent !== '/' && $sco->parent !== $sco->organization) {
                $parent = 'AU' . $sco->parent;
            }
            
            // Get launch file - use the actual SCORM content file
            $file_name = $this->get_launch_file($sco);
            
            $max_score = '';
            $mastery_score = '';
            if (!empty($this->scorm->maxgrade) && is_numeric($this->scorm->maxgrade)) {
                $max_score = $this->scorm->maxgrade;
                $mastery_score = $this->scorm->maxgrade;
            }

            $row = [
                $auid,
                $title,
                $parent,
                '', // type
                '', // command_line
                '', // Max_Time_Allowed
                '', // time_limit_action
                $file_name,
                $max_score,
                $mastery_score,
                'Moodle', // system_vendor
                '', // core_vendor
                $file_name, // web_launch
                '' // AU_password
            ];
            
            $row = array_map(function($v) {
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row);
            $rows[] = implode(',', $row);
        }
        return implode("\r\n", $rows);
    }

    protected function get_ort_content(): string {
        return "[Objectives]\r\n";
    }

    // Empty .pre file with correct section header.
    protected function get_pre_content(): string {
        return "[Prerequisites]\r\n";
    }

    // Empty .cmp file with correct section header.
    protected function get_cmp_content(): string {
        return "[Completion]\r\n";
    }
    protected function get_sco_title($sco): string {
        if (!empty(trim($sco->title ?? ''))) {
            return trim($sco->title);
        }
        if (!empty(trim($sco->name ?? ''))) {
            return trim($sco->name);
        }
        return 'AU' . $sco->id;
    }

    protected function get_launch_file($sco): string {
        // For AICC packages, we need to reference the actual content files
        // The launch file should be the main entry point of the SCORM content
        if (!empty($sco->launch)) {
            return $sco->launch;
        }
        
        // If no specific launch file, try to find the main content file
        // This could be index.html, start.html, or similar
        $common_files = ['index.html', 'start.html', 'main.html', 'course.html'];
        foreach ($common_files as $file) {
            if ($this->file_exists_in_scorm($file)) {
                return $file;
            }
        }
        
        // Fallback to the first HTML file found
        $html_files = $this->get_html_files_from_scorm();
        if (!empty($html_files)) {
            return $html_files[0];
        }
        
        return 'index.html'; // Default fallback
    }

    protected function file_exists_in_scorm($filename): bool {
        global $CFG;
        
        if (!isset($this->scorm->cmid)) {
            $cm = get_coursemodule_from_instance('scorm', $this->scorm->id);
            $this->scorm->cmid = $cm->id;
        }
        $context = \context_module::instance($this->scorm->cmid);
        $fs = get_file_storage();
        
        $file = $fs->get_file($context->id, 'mod_scorm', 'content', 0, '/', $filename);
        return $file !== false;
    }

    protected function get_html_files_from_scorm(): array {
        global $CFG;
        
        if (!isset($this->scorm->cmid)) {
            $cm = get_coursemodule_from_instance('scorm', $this->scorm->id);
            $this->scorm->cmid = $cm->id;
        }
        $context = \context_module::instance($this->scorm->cmid);
        $fs = get_file_storage();
        
        $files = $fs->get_area_files($context->id, 'mod_scorm', 'content', 0, 'sortorder, itemid, filepath, filename', false);
        $html_files = [];
        
        foreach ($files as $file) {
            if (!$file->is_directory() && preg_match('/\.html?$/i', $file->get_filename())) {
                $html_files[] = $file->get_filename();
            }
        }
        
        return $html_files;
    }

    protected function escape_aicc($value): string {
        if ($value === null) return '';
        $value = str_replace(["\r", "\n"], '', (string)$value);
        return trim($value);
    }

}

class course_exporter extends exporter {
    public function __construct(\stdClass $course, \stdClass $scorm) {
        parent::__construct($course, $scorm);
    }

    public function generate_package(): string {
        $zip = new \ZipArchive();
        $zipfilename = tempnam(sys_get_temp_dir(), 'aicc_export_') . '.zip';

        if ($zip->open($zipfilename, \ZipArchive::CREATE) !== TRUE) {
            throw new \moodle_exception('error_zip_create', 'local_aicc_export');
        }

        $basefilename = clean_filename($this->course->shortname);

        // Generate AICC descriptor files
        $zip->addFromString($basefilename . '.crs', $this->get_crs_content());
        $zip->addFromString($basefilename . '.cst', $this->get_cst_content());
        $zip->addFromString($basefilename . '.des', $this->get_des_content());
        $zip->addFromString($basefilename . '.au', $this->get_au_content());
        $zip->addFromString($basefilename . '.ort', $this->get_ort_content());
        $zip->addFromString($basefilename . '.pre', $this->get_pre_content());
        $zip->addFromString($basefilename . '.cmp', $this->get_cmp_content());

        // Add actual SCORM content files
        $this->add_scorm_content($zip);

        $zip->close();
        return $zipfilename;
    }

    protected function add_scorm_content(\ZipArchive $zip): void {
        global $CFG;
        
        if (!isset($this->scorm->cmid)) {
            $cm = get_coursemodule_from_instance('scorm', $this->scorm->id);
            $this->scorm->cmid = $cm->id;
        }
        $context = \context_module::instance($this->scorm->cmid);
        $fs = get_file_storage();
        
        // Get all files from the SCORM content area
        $files = $fs->get_area_files($context->id, 'mod_scorm', 'content', 0, 'sortorder, itemid, filepath, filename', false);
        
        foreach ($files as $file) {
            if (!$file->is_directory()) {
                $filepath = $file->get_filepath() . $file->get_filename();
                $zip->addFromString($filepath, $file->get_content());
            }
        }
    }
}