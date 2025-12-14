<?php

defined('MOODLE_INTERNAL') || die();

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'paygw_helloasso/clientid',
        get_string('clientid', 'paygw_helloasso'),
        get_string('clientid_desc', 'paygw_helloasso'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'paygw_helloasso/clientsecret',
        get_string('clientsecret', 'paygw_helloasso'),
        get_string('clientsecret_desc', 'paygw_helloasso'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'paygw_helloasso/org_slug',
        get_string('org_slug', 'paygw_helloasso'),
        get_string('org_slug_desc', 'paygw_helloasso'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'paygw_helloasso/formid',
        get_string('formid', 'paygw_helloasso'),
        get_string('formid_desc', 'paygw_helloasso'),
        ''
    ));

    \core_payment\helper::add_common_gateway_settings($settings, 'paygw_helloasso');
}