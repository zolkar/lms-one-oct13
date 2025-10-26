<?php

defined('MOODLE_INTERNAL') || die();

class local_aicc_export_exporter_testcase extends advanced_testcase {

    protected function setUp(): void {
        $this->resetAfterTest();
    }

    public function test_generate_package() {
        global $DB;

        $this->setAdminUser();
        $course = $this->getDataGenerator()->create_course();
        $scorm = $this->getDataGenerator()->create_module('scorm', ['course' => $course->id]);
        $scorm_sco = $this->getDataGenerator()->create_scorm_sco($scorm->id);

        $exporter = new \local_aicc_export\exporter($course, $scorm);
        $zipfilepath = $exporter->generate_package();

        $this->assertFileExists($zipfilepath);

        $zip = new \ZipArchive();
        $this->assertTrue($zip->open($zipfilepath));

        $this->assertNotFalse($zip->locateName($course->shortname . '.crs'));
        $this->assertNotFalse($zip->locateName($course->shortname . '.cst'));
        $this->assertNotFalse($zip->locateName($course->shortname . '.des'));
        $this->assertNotFalse($zip->locateName($course->shortname . '.au'));

        $au_content = $zip->getFromName($course->shortname . '.au');
        $this->assertStringContainsString('token=', $au_content);

        $crs_content = $zip->getFromName($course->shortname . '.crs');
        $this->assertStringContainsString('AICC_URL=', $crs_content);
        $this->assertStringContainsString('endpoint.php', $crs_content);

        $zip->close();
        unlink($zipfilepath);
    }
}
