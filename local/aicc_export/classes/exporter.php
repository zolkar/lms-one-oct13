<?php

namespace local_aicc_export;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/launcher.php');

class exporter {

    protected $course;
    protected $scorm;
    protected $scos;

    public function __construct(\stdClass $course, \stdClass $scorm) {
        global $DB;
        $this->course = $course;
        $this->scorm = $scorm;
        $this->scos = $DB->get_records('scorm_scoes', ['scorm' => $this->scorm->id], 'id');
    }

    public function generate_package(): string {
        $zip = new \ZipArchive();
        $zipfilename = tempnam(sys_get_temp_dir(), 'aicc_export_') . '.zip';

        if ($zip->open($zipfilename, \ZipArchive::CREATE) !== TRUE) {
            throw new \moodle_exception('error_zip_create', 'local_aicc_export');
        }

        $basefilename = clean_filename($this->course->shortname);

        $zip->addFromString($basefilename . '.crs', $this->get_crs_content());
        $zip->addFromString($basefilename . '.cst', $this->get_cst_content());
        $zip->addFromString($basefilename . '.des', $this->get_des_content());
        $zip->addFromString($basefilename . '.au', $this->get_au_content());

        $zip->close();

        return $zipfilename;
    }

    protected function get_crs_content(): string {
        $content = "[Course]\r\n";
        $content .= "Course_ID=COURSE-{$this->course->id}\r\n";
        $content .= "Course_Title={$this->course->fullname}\r\n";
        $content .= "Version=1.0\r\n";
        $content .= "Course_Date=" . date('Y-m-d') . "\r\n";
        return $content;
    }

    protected function get_cst_content(): string {
        $content = "[Course Structure]\r\n";
        $i = 1;
        foreach ($this->scos as $sco) {
            // reference the AU by identifier (we generate Identifier=AU<id> in the AU block)
            $content .= "Block{$i}=AU{$sco->id}\r\n";
            $i++;
        }
        return $content;
    }

    protected function get_des_content(): string {
        $content = "[Description]\r\n";
        $content .= "Title={$this->scorm->name}\r\n";
        $content .= "Author=Moodle A\r\n";
        $content .= "Abstract=Access SCORM hosted on Moodle A\r\n";
        return $content;
    }

    protected function get_au_content(): string {
        $content = "[Assignable Units]\r\n";
        // AU list: AUxxxx=Title
        foreach ($this->scos as $sco) {
            $auid = 'AU' . (isset($sco->id) ? $sco->id : uniqid());
            $title = '';
            if (isset($sco->title) && trim($sco->title) !== '') {
                $title = trim($sco->title);
            } elseif (isset($sco->name) && trim($sco->name) !== '') {
                $title = trim($sco->name);
            } else {
                $title = $auid;
            }
            $content .= $auid . '=' . $this->escape_aicc($title) . "\r\n";
        }
        $content .= "\r\n";

        // AU blocks
        foreach ($this->scos as $sco) {
            $auid = 'AU' . (isset($sco->id) ? $sco->id : uniqid());
            $identifier = $auid;
            $title = '';
            if (isset($sco->title) && trim($sco->title) !== '') {
                $title = trim($sco->title);
            } elseif (isset($sco->name) && trim($sco->name) !== '') {
                $title = trim($sco->name);
            } else {
                $title = $auid;
            }
            $file_name = $this->get_launch_url(isset($sco->id) ? $sco->id : 0);
            $system_id = 'MoodleA';
            $max_score = isset($this->scorm->maxgrade) && is_numeric($this->scorm->maxgrade) ? $this->scorm->maxgrade : 100;

            $content .= "[{$auid}]\r\n";
            $content .= "Identifier=" . $this->escape_aicc($identifier) . "\r\n";
            $content .= "Title=" . $this->escape_aicc($title) . "\r\n";
            $content .= "File_Name=" . $this->escape_aicc($file_name) . "\r\n";
            $content .= "System_ID=" . $this->escape_aicc($system_id) . "\r\n";
            $content .= "Max_Score=" . $this->escape_aicc($max_score) . "\r\n";
            $content .= "\r\n";
        }
        return $content;
    }

    /**
     * Escape AICC values for safe output (remove CR/LF, trim, fallback to empty string if null)
     */
    protected function escape_aicc($value): string {
        if ($value === null) return '';
        $value = str_replace(["\r", "\n"], '', (string)$value);
        return trim($value);
    }

    protected function get_launch_url(int $scoid): string {
        global $CFG;
        $ttl = get_config('local_aicc_export', 'launch_token_ttl');
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
        $url = new \moodle_url('/local/aicc_export/launch.php', ['token' => $token]);
        return $url->out(true);
    }
}
