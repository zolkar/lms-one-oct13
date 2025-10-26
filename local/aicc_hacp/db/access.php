<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = [
    'local/aicc_hacp:manage' => [
        'riskbitmask' => RISK_CONFIG,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/aicc_hacp:viewlog' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/aicc_hacp:manualmap' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => [
            'manager' => CAP_ALLOW,
        ],
    ],
    'local/aicc_hacp:viewreports' => [
        'riskbitmask' => RISK_PERSONAL,
        'captype' => 'read',
        'contextlevel' => CONTEXT_COURSE,
        'archetypes' => [
            'manager' => CAP_ALLOW,
            'editingteacher' => CAP_ALLOW,
            'teacher' => CAP_ALLOW,
        ],
    ],
];

$externalpages = [
    'local_aicc_hacp_viewlog' => [
        'pagetype' => 'admin',
        'requiredcapability' => 'local/aicc_hacp:viewlog',
    ],
    'local_aicc_hacp_manualmap' => [
        'pagetype' => 'admin',
        'requiredcapability' => 'local/aicc_hacp:manualmap',
    ],
];
