<?php

defined('MOODLE_INTERNAL') || die();

class local_aicc_hacp_endpoint_testcase extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_process_request_integration() {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);
        $scorm_sco = $this->getDataGenerator()->create_scorm_sco($scorm->id);
        $user = $this->getDataGenerator()->create_user();
        $aicc_sid = 'test_sid_' . time();

        $session = new \stdClass();
        $session->aicc_sid = $aicc_sid;
        $session->userid = $user->id;
        $session->courseid = $course->id;
        $session->scormid = $scorm->id;
        $session->scoid = $scorm_sco->id;
        $session->timecreated = time();
        $session->timemodified = time();
        $DB->insert_record('local_aicc_hacp_sessions', $session);

        $handler = new \local_aicc_hacp\hacp_handler($aicc_sid);

        $aiccdata = "cmi.core.lesson_status=completed\ncmi.core.score.raw=100";
        $response = $handler->process_request('PUTPARAM', $aiccdata);
        $this->assertStringContainsString('error=0', $response);

        $tracking_record = $DB->get_record('local_aicc_hacp_tracking', [
            'userid' => $user->id,
            'scormid' => $scorm->id,
            'scoid' => $scorm_sco->id,
            'element' => 'cmi.core.lesson_status',
        ]);
        $this->assertNotEmpty($tracking_record);
        $this->assertEquals('completed', $tracking_record->value);

        $response = $handler->process_request('GETPARAM', '');
        $this->assertStringContainsString('error=0', $response);
        $this->assertStringContainsString('cmi.core.lesson_status=completed', $response);
        $this->assertStringContainsString('cmi.core.score.raw=100', $response);
    }
}
