<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Callback to add payment gateways to the payment system.
 *
 * @return array
 */
function paygw_helloasso_payment_gateways(): array {
    return [
        'helloasso' => [
            'displayname' => get_string('pluginname', 'paygw_helloasso'),
            'component' => 'paygw_helloasso',
            'handlesrefrequest' => false,
        ]
    ];
}