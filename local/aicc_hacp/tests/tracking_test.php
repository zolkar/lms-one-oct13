<?php

defined('MOODLE_INTERNAL') || die();

class local_aicc_hacp_tracking_handler_testcase extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_set_and_get_tracking_data() {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);
        $scorm_sco = $this->getDataGenerator()->create_scorm_sco($scorm->id);
        $user = $this->getDataGenerator()->create_user();

        $aiccdata = "[Core]\nlesson_status=completed\nscore=100\n[Core_Lesson]\n";
        \local_aicc_hacp\tracking_handler::set_tracking_data($user->id, $scorm->id, $scorm_sco->id, $aiccdata);

        $retrieved_data = \local_aicc_hacp\tracking_handler::get_tracking_data($user->id, $scorm->id, $scorm_sco->id);

        $this->assertStringContainsString('lesson_status=completed', $retrieved_data);
        $this->assertStringContainsString('score=100', $retrieved_data);
    }
}
