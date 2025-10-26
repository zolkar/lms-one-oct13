<?php
define('MOODLE_INTERNAL', true);
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/local/hacp_handler/lib.php');
global $DB;

class local_hacp_handler_handler_test extends \core\testing\testcase {
    public function test_get_param() {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create('course');
        $token = local_aicc_exporter_generate_token($course->id);
        $remote_user_id = 'testuser';

        $response = local_hacp_handler_get_param($remote_user_id, $course->id);
        $this->assertEquals("error=0\nerror_text=Success\naicc_data=\n", $response);
    }

    public function test_put_param() {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create('course');
        $token = local_aicc_exporter_generate_token($course->id);
        $remote_user_id = 'testuser';

        $aicc_data = "core_lesson=some_data";
        $response = local_hacp_handler_put_param($remote_user_id, $course->id, $aicc_data);
        $this->assertEquals("error=0\nerror_text=Success\n", $response);

        $response = local_hacp_handler_get_param($remote_user_id, $course->id);
        $this->assertEquals("error=0\nerror_text=Success\naicc_data=core_lesson=some_data", $response);
    }
}
