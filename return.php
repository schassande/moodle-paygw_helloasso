<?php
require_once(__DIR__ . '/../../../../config.php');
require_login();

$paymentid = required_param('paymentid', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_RAW);
$transactionid = optional_param('transactionid', '', PARAM_RAW);

try {
    // Vérifier sesskey
    if (!confirm_sesskey($sesskey)) {
        \payment_gateway_helloasso\logger::log_action(
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
        \payment_gateway_helloasso\logger::log_action(
            $paymentid,
            $USER->id,
            'payment_return',
            'error',
            0,
            'Payment record not found'
        );
        throw new \moodle_exception('paymentnotfound', 'payment_gateway_helloasso');
    }

    // Vérifier l'état du paiement via HelloAsso API
    $verified = verify_helloasso_transaction($transactionid, $payment);
    
    if ($verified) {
        \payment_gateway_helloasso\logger::log_action(
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
        \payment_gateway_helloasso\logger::log_action(
            $paymentid,
            $USER->id,
            'payment_return',
            'error',
            $payment->amount,
            'Payment verification failed',
            400,
            $transactionid
        );
        throw new \moodle_exception('paymentverificationfailed', 'payment_gateway_helloasso');
    }

    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('payment_success', 'payment_gateway_helloasso'), 'notifysuccess');
    echo $OUTPUT->footer();

} catch (\Exception $e) {
    \payment_gateway_helloasso\logger::log_action(
        $paymentid,
        $USER->id,
        'payment_return',
        'error',
        $payment->amount ?? 0,
        $e->getMessage()
    );
    echo $OUTPUT->header();
    echo $OUTPUT->notification(get_string('payment_error', 'payment_gateway_helloasso'), 'notifyproblem');
    echo $OUTPUT->footer();
}

function verify_helloasso_transaction($transactionid, $payment) {
    // À implémenter avec l'API HelloAsso
    return true;
}
