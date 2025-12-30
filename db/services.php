<?php
defined('MOODLE_INTERNAL') || die();

$functions = [
    'paygw_helloasso_get_config_for_js' => [
        'classname'   => 'paygw_helloasso\external\get_config_for_js',
        'methodname'  => 'execute',
        'description' => 'Get HelloAsso configuration for JavaScript',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];