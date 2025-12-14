<?php

defined('MOODLE_INTERNAL') || die();

$capabilities = array(
    'paygw/helloasso:manage' => array(
        'captype' => 'write',
        'contextlevel' => CONTEXT_SYSTEM,
        'archetypes' => array(
            'manager' => CAP_ALLOW
        ),
        'clonepermissionsfrom' => 'moodle/site:config'
    )
);