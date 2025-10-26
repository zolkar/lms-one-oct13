<?php

namespace local_aicc_hacp;

defined('MOODLE_INTERNAL') || die();

require_once(__DIR__ . '/tracking_handler.php');

class hacp_handler {

    protected $session;

    public function __construct(string $aicc_sid) {
        global $DB;

        $this->session = $DB->get_record('local_aicc_hacp_sessions', ['aicc_sid' => $aicc_sid], '*', MUST_EXIST);
    }

    public function process_request(string $command, string $aiccdata): string {
        switch (strtoupper($command)) {
            case 'GETPARAM':
                return $this->handle_getparam();
            case 'PUTPARAM':
                return $this->handle_putparam($aiccdata);
            case 'EXITAU':
                return $this->handle_exitau();
            default:
                return "error=1\nerror_text=Invalid command\n";
        }
    }

    protected function handle_getparam(): string {
        $aicc_data = tracking_handler::get_tracking_data($this->session->userid, $this->session->scormid, $this->session->scoid);
        return "error=0\naicc_data={$aicc_data}\n";
    }

    protected function handle_putparam(string $aiccdata): string {
        tracking_handler::set_tracking_data($this->session->userid, $this->session->scormid, $this->session->scoid, $aiccdata);
        return "error=0\n";
    }

    protected function handle_exitau(): string {
        global $DB;

        $this->session->timemodified = time();
        $DB->update_record('local_aicc_hacp_sessions', $this->session);

        return "error=0\n";
    }
}
