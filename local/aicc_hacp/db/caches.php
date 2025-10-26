<?php
defined('MOODLE_INTERNAL') || die();

$definitions = [
    'ratelimit' => [
        'mode' => cache_store::MODE_REQUEST,
        'simplekeys' => true,
        'simpledata' => true,
        'staticacceleration' => true,
        'staticaccelerationsize' => 30,
    ],
];
