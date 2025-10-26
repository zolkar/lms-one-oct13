<?php

defined('MOODLE_INTERNAL') || die();

function local_aicc_export_extend_navigation_scorm(core_user\navigation\node $navnode, stdClass $course, stdClass $module, context_module $context) {
    if (has_capability('local/aicc_export:export', $context)) {
        $url = new moodle_url('/local/aicc_export/export.php', ['courseid' => $course->id, 'scormid' => $module->instance]);
        $navnode->add(
            get_string('export_aicc', 'local_aicc_export'),
            $url,
            core_user\navigation\node::TYPE_SETTING,
            null,
            'export_aicc',
            new pix_icon('i/export', '')
        );
    }
}
