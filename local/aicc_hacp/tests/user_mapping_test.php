<?php

defined('MOODLE_INTERNAL') || die();

class local_aicc_hacp_user_mapper_testcase extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_get_or_create_user() {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $externalsid = 'test_sid_' . time();

        $user = \local_aicc_hacp\user_mapper::get_or_create_user($externalsid, $course->id);

        $this->assertNotEmpty($user);
        $this->assertNotEmpty($user->id);

        $user_mapping = $DB->get_record('local_aicc_hacp_users', ['externalsid' => $externalsid]);
        $this->assertNotEmpty($user_mapping);
        $this->assertEquals($user->id, $user_mapping->internaluserid);
    }
}
