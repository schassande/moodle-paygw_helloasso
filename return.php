<?php
require_once(__DIR__ . '/../../../../config.php');
require_login();
global $USER, $OUTPUT;
$payment = null;

$paymentid = required_param('paymentid', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_RAW);
$transactionid = optional_param('transactionid', '', PARAM_RAW);

try {
    // Vérifier sesskey
    if (!confirm_sesskey($sesskey)) {
        \paygw_helloasso\logger::log_action(
            $paymentid,
            $USER->id,
            'payment_return',
            'fraud_detected',
            0,
            'Invalid session key'
        );
        throw new \moodle_exception('invalidsesskey');
    }

    // Récupérer et valider le paiement
    $payment = \core_payment\helper::get_payment_record($paymentid);
    if (!$payment) {
        \paygw_helloasso\logger::log_action(
            $paymentid,
            $USER->id,
            'payment_return',
            'error',
            0,
            'Payment record not found'
        );
        throw new \moodle_exception('paymentnotfound', 'paygw_helloasso');
    }

    // Vérifier l'état du paiement via HelloAsso API
    $verified = verify_helloasso_transaction($transactionid, $payment);
    
    if ($verified) {
        \paygw_helloasso\logger::log_action(
            $paymentid,
            $USER->id,
            'payment_return',
            'success',
            $payment->amount,
            'Payment verified and processed',
            200,
            $transactionid
        );
        \core_payment\helper::deliver_order($payment);
    } else {
        \paygw_helloasso\logger::log_action(
            $paymentid,
            $USER->id,
            'payment_return',
            'error',
            $payment->amount,
            'Payment verification failed',
            400,
            $transactionid
        );
        throw new \moodle_exception('paymentverificationfailed', 'paygw_helloasso');
    }

    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('payment_success', 'paygw_helloasso'), 'notifysuccess');
    echo $OUTPUT->footer();

} catch (\Exception $e) {
    \paygw_helloasso\logger::log_action(
        $paymentid,
        $USER->id,
        'payment_return',
        'error',
        $payment->amount ?? 0,
        $e->getMessage()
    );
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('payment_error', 'paygw_helloasso'), 'notifyproblem');
    echo $OUTPUT->footer();
}

function verify_helloasso_transaction($transactionid, $payment) {
    // À implémenter avec l'API HelloAsso
    return true;
}
