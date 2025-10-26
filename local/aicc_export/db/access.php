<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/aicc_export:export' => [
        'captype' => 'write',
        'contextlevel' => CONTEXT_MODULE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
        ],
    ],
];
