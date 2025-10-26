<?php
define('MOODLE_INTERNAL', true);
require_once(__DIR__ . '/../../../../config.php');
require_once($CFG->dirroot . '/local/aicc_exporter/lib.php');
global $DB;

class local_aicc_exporter_generator_test extends \core\testing\testcase {
    public function test_export_course() {
        $this->resetAfterTest();
        $course = $this->getDataGenerator()->create('course');

        $zip_path = local_aicc_exporter_export_course($course->id);
        $this->assertFileExists($zip_path);

        $zip = new zip_archive();
        $this->assertEquals(true, $zip->open($zip_path));

        $this->assertTrue($zip->locateName('course.des') !== false);
        $this->assertTrue($zip->locateName('course.au') !== false);
        $this->assertTrue($zip->locateName('course.crs') !== false);
        $this->assertTrue($zip->locateName('course.cst') !== false);

        $zip->close();
        unlink($zip_path);
    }
}
