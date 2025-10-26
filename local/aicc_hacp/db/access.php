<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/aicc_hacp:viewreport' => [
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
        ],
    ],
];
