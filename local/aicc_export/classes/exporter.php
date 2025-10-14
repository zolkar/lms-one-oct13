<?php
namespace local_aicc_export;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/launcher.php');


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
}

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
    // Empty .ort file with correct section header.
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

    protected function get_crs_content(): string {
        $content  = "[Course]\r\n";
        $content .= "Course_ID = {$this->course->id}\r\n";
        $content .= "Course_Title = " . $this->escape_aicc($this->course->fullname) . "\r\n";
        $content .= "Course_Level = 1\r\n";
        $content .= "Max_Normal = 1\r\n";
        $content .= "Version = 1.0\r\n";
        $content .= "Total_AUs = " . count($this->scos) . "\r\n";
        $content .= "Mastery_Score = " . ($this->scorm->masteryscore ?? '') . "\r\n";
        $content .= "Course_Description = " . $this->escape_aicc($this->course->summary ?? '') . "\r\n";
        $content .= "\r\n[CORE_VENDOR]\r\n";
        $content .= "Moodle\r\n";
        return $content;
    }

    protected function get_cst_content(): string {
        $content = "Block,Title,Type,Parent,AU\r\n";
        foreach ($this->scos as $sco) {
            $parent = ($sco->parent === '/') ? '' : 'B' . $this->sco_identifier_map[$sco->parent];
            $content .= "B{$sco->id},\"{$sco->title}\",N,{$parent},AU{$sco->id}\r\n";
        }
        return $content;
    }

    protected function get_des_content(): string {
        // Add 'parent' column for Moodle import compatibility, using real parent/child structure.
        $header = [
            'system_id', 'title', 'parent', 'type', 'command_line', 'Max_Time_Allowed', 'time_limit_action',
            'file_name', 'max_score', 'mastery_score', 'system_vendor', 'core_vendor', 'web_launch', 'AU_password'
        ];
        $rows = [];
        $rows[] = '"' . implode('","', $header) . '"';
        foreach ($this->scos as $sco) {
            $id = $sco->id ?? '';
            if ($id === '' || $id === null) {
                continue;
            }
            $auid = 'AU' . $id;
            $system_id = $auid;
            $title = $this->get_sco_title($sco);
            if (empty($title)) {
                $title = $auid;
            }
            // Determine parent: if root, '/', else AU{parent_id}
            $parent = '/';
            if (!empty($sco->parent) && $sco->parent !== '/' && $sco->parent !== $sco->organization) {
                // Try to resolve parent as identifier or id
                if (isset($this->sco_identifier_map[$sco->parent])) {
                    $parentid = $this->sco_identifier_map[$sco->parent];
                    $parent = 'AU' . $parentid;
                } elseif (isset($this->sco_id_map[$sco->parent])) {
                    $parent = 'AU' . $sco->parent;
                } else {
                    $parent = $sco->parent; // fallback, but should not happen
                }
            }
            $type = '';
            $command_line = '';
            $max_time_allowed = '';
            $time_limit_action = '';
            $file_name = $this->get_launch_url($sco->id);
            $max_score = is_numeric($this->scorm->maxgrade ?? null) ? $this->scorm->maxgrade : '';
            $mastery_score = $max_score;
            $system_vendor = 'Moodle';
            $core_vendor = '';
            $web_launch = $file_name;
            $au_password = '';

            $row = [
                $system_id,
                $title,
                $parent,
                $type,
                $command_line,
                $max_time_allowed,
                $time_limit_action,
                $file_name,
                $max_score,
                $mastery_score,
                $system_vendor,
                $core_vendor,
                $web_launch,
                $au_password
            ];
            $row = array_map(function($v) {
                return '"' . str_replace('"', '""', $v) . '"';
            }, $row);
            $rows[] = implode(',', $row);
        }
        return implode("\r\n", $rows);
    }

    protected function get_au_content(): string {
        // Add 'parent' column for Moodle import compatibility, using real parent/child structure.
        $header = [
            'system_id', 'title', 'parent', 'type', 'command_line', 'Max_Time_Allowed', 'time_limit_action',
            'file_name', 'max_score', 'mastery_score', 'system_vendor', 'core_vendor', 'web_launch', 'AU_password'
        ];
        $rows = [];
        $rows[] = '"' . implode('","', $header) . '"';

        foreach ($this->scos as $sco) {
            $id = $sco->id ?? '';
            if ($id === '' || $id === null) {
                continue;
            }
            $auid = 'AU' . $id;
            $system_id = $auid;
            $title = $this->get_sco_title($sco);
            if (empty($title)) {
                $title = $auid;
            }
            // Determine parent: if root, '/', else AU{parent_id}
            $parent = '/';
            if (!empty($sco->parent) && $sco->parent !== '/' && $sco->parent !== $sco->organization) {
                if (isset($this->sco_identifier_map[$sco->parent])) {
                    $parentid = $this->sco_identifier_map[$sco->parent];
                    $parent = 'AU' . $parentid;
                } elseif (isset($this->sco_id_map[$sco->parent])) {
                    $parent = 'AU' . $sco->parent;
                } else {
                    $parent = $sco->parent;
                }
            }
            $type = '';
            $command_line = '';
            $max_time_allowed = '';
            $time_limit_action = '';
            $file_name = $this->get_launch_url($sco->id);
            $max_score = is_numeric($this->scorm->maxgrade ?? null) ? $this->scorm->maxgrade : '';
            $mastery_score = $max_score;
            $system_vendor = 'Moodle';
            $core_vendor = '';
            $web_launch = $file_name;
            $au_password = '';

            $row = [
                $system_id,
                $title,
                $parent,
                $type,
                $command_line,
                $max_time_allowed,
                $time_limit_action,
                $file_name,
                $max_score,
                $mastery_score,
                $system_vendor,
                $core_vendor,
                $web_launch,
                $au_password
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

    protected function escape_aicc($value): string {
        if ($value === null) return '';
        $value = str_replace(["\r", "\n"], '', (string)$value);
        return trim($value);
    }

    protected function get_launch_url(int $scoid): string {
        global $CFG;
        $ttl = get_config('local_aicc_export', 'launch_token_ttl') ?? 3600;
        $payload = [
            'au' => 'AU' . $scoid,
            'scormid' => $this->scorm->id,
            'scoid' => $scoid,
            'courseid' => $this->course->id,
            'issued_at' => time(),
            'expires_at' => time() + $ttl,
            'nonce' => \core\uuid::generate(),
        ];
        $token = launcher::sign_token($payload);
        $url = new \moodle_url('/local/aicc_export/launch.php', [
            'token' => $token,
            'scoid' => $scoid,
        ]);

        // Prevent &amp; encoding so AICC launcher gets proper parameters
        return html_entity_decode($url->out(true), ENT_QUOTES);
    }
}
