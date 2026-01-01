<?php
// This file is part of Moodle - http://moodle.org/
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
// 
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
// 
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
// 
// @copyright 2025 Sebastien Chassande-Barrioz <chassande@gmail.com>

require_once(__DIR__ . '/../../../config.php');
require_login();
global $USER, $OUTPUT;

$paymentid = required_param('paymentid', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_RAW);

// Paramètres retournés par HelloAsso en cas d'erreur
$checkoutintentid = optional_param('checkoutIntentId', '', PARAM_INT);
$error = optional_param('error', 'unknown', PARAM_TEXT);

try {
    // Vérifier sesskey
    if (!confirm_sesskey($sesskey)) {
        \paygw_helloasso\logger::log_action(
            $paymentid,
            $USER->id,
            'payment_error_page',
            'fraud_detected',
            0,
            'Invalid session key'
        );
        throw new \moodle_exception('invalidsesskey');
    }

    // Récupérer le paiement
    $payment = $DB->get_record('payments', ['id' => $paymentid], '*', MUST_EXIST);
    if (!$payment) {
        \paygw_helloasso\logger::log_action(
            $paymentid,
            $USER->id,
            'payment_error_page',
            'error',
            0,
            'Payment record not found'
        );
        throw new \moodle_exception('paymentnotfound', 'paygw_helloasso');
    }

    // Logger l'erreur technique
    \paygw_helloasso\logger::log_action(
        $paymentid,
        $USER->id,
        'payment_technical_error',
        'error',
        $payment->amount,
        "Technical error during payment. Error code: {$error}, CheckoutIntent: {$checkoutintentid}",
        500,
        "CHECKOUT-{$checkoutintentid}"
    );

    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('payment_technical_error', 'paygw_helloasso'), 'notifyproblem');
    
    if (get_config('paygw_helloasso', 'debugmode')) {
        echo html_writer::tag('p', "Debug info: Error code = {$error}, CheckoutIntentId = {$checkoutintentid}");
    }
    
    echo $OUTPUT->continue_button(new moodle_url('/'));
    echo $OUTPUT->footer();

} catch (\Exception $e) {
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('payment_error', 'paygw_helloasso') . ': ' . $e->getMessage(), 'notifyproblem');
    echo $OUTPUT->continue_button(new moodle_url('/'));
    echo $OUTPUT->footer();
}
