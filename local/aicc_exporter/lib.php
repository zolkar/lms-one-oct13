<?php
defined('MOODLE_INTERNAL') || die();

function local_aicc_exporter_generate_token($courseid) {
    return md5(get_site_identifier() . $courseid);
}

function local_aicc_exporter_export_course($courseid) {
    global $CFG, $DB;

    $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
    $course_url = new moodle_url('/course/view.php', ['id' => $courseid]);
    $token = local_aicc_exporter_generate_token($courseid);
    $aicc_files_path = $CFG->tempdir . '/aicc_export_' . $courseid;
    if (!file_exists($aicc_files_path)) {
        mkdir($aicc_files_path, 0777, true);
    }

    // Create DES file
    $des_content = "[Course]\n";
    $des_content .= "Course_ID={$course->id}\n";
    $des_content .= "Course_Title={$course->fullname}\n";
    $des_content .= "Course_Description={$course->summary}\n";
    file_put_contents($aicc_files_path . '/course.des', $des_content);

    // Create AU file
    $au_content = "[Course]\n";
    $au_content .= "System_ID=LMS-1\n";
    $au_content .= "System_Vendor=Moodle\n";
    $au_content .= "Version=1.0\n";
    $au_content .= "Max_Normal=100\n";
    $au_content .= "[Block]\n";
    $au_content .= "Block1={$course->fullname}\n";
    $hacp_endpoint = get_config('local_aicc_exporter', 'hacp_endpoint');
    $au_content .= "File_Name={$course_url->out(false)}\n";
    $au_content .= "AICC_HACP_URL={$hacp_endpoint}\n";
    $au_content .= "Token={$token}\n";
    $au_content .= "Description={$course->summary}\n";
    file_put_contents($aicc_files_path . '/course.au', $au_content);

    // Create CRS file
    $crs_content = "[Course]\n";
    $crs_content .= "Course_ID={$course->id}\n";
    $crs_content .= "Course_Title={$course->fullname}\n";
    $crs_content .= "[Block]\n";
    $crs_content .= "Block1={$course->fullname}\n";
    file_put_contents($aicc_files_path . '/course.crs', $crs_content);

    // Create CST file
    $cst_content = "[Core]\n";
    $cst_content .= "Student_ID=\n";
    $cst_content .= "Student_Name=\n";
    $cst_content .= "Lesson_Location=\n";
    $cst_content .= "Lesson_Status=Not attempted\n";
    $cst_content .= "Score=\n";
    $cst_content .= "Time=\n";
    $cst_content .= "[Core_Lesson]\n";
    $cst_content .= "\n";
    file_put_contents($aicc_files_path . '/course.cst', $cst_content);

    // Create a zip file
    $zip_path = $CFG->tempdir . '/aicc_export_' . $course->shortname . '.zip';
    $zip = new zip_archive();
    if ($zip->open($zip_path, zip_archive::CREATE) !== true) {
        return false;
    }
    $zip->add_file($aicc_files_path . '/course.des', 'course.des');
    $zip->add_file($aicc_files_path . '/course.au', 'course.au');
    $zip->add_file($aicc_files_path . '/course.crs', 'course.crs');
    $zip->add_file($aicc_files_path . '/course.cst', 'course.cst');
    $zip->close();

    // Clean up temporary files
    unlink($aicc_files_path . '/course.des');
    unlink($aicc_files_path . '/course.au');
    unlink($aicc_files_path . '/course.crs');
    unlink($aicc_files_path . '/course.cst');
    rmdir($aicc_files_path);

    return $zip_path;
}
