<?php

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'payment_gateway_helloasso/clientid',
        get_string('clientid', 'payment_gateway_helloasso'),
        get_string('clientid_desc', 'payment_gateway_helloasso'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'payment_gateway_helloasso/clientsecret',
        get_string('clientsecret', 'payment_gateway_helloasso'),
        get_string('clientsecret_desc', 'payment_gateway_helloasso'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'payment_gateway_helloasso/org_slug',
        get_string('org_slug', 'payment_gateway_helloasso'),
        get_string('org_slug_desc', 'payment_gateway_helloasso'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'payment_gateway_helloasso/formid',
        get_string('formid', 'payment_gateway_helloasso'),
        get_string('formid_desc', 'payment_gateway_helloasso'),
        ''
    ));
}