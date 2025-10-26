<?php

defined('MOODLE_INTERNAL') || die();

function local_aicc_hacp_extend_navigation_course(core_user\navigation\node $navnode, stdClass $course, stdClass $module, context_course $context) {
    if (has_capability('local/aicc_hacp:viewreport', $context)) {
        $url = new moodle_url('/local/aicc_hacp/report.php', ['courseid' => $course->id]);
        $navnode->add(
            get_string('remote_learner_report', 'local_aicc_hacp'),
            $url,
            core_user\navigation\node::TYPE_SETTING,
            null,
            'remote_learner_report',
            new pix_icon('i/report', '')
        );
    }
}
