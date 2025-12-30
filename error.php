<?php
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
