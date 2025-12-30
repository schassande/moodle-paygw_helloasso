<?php
require_once(__DIR__ . '/../../../../config.php');
require_login();

global $USER, $DB;

// Récupérer l'ID du paiement si disponible
$paymentid = optional_param('paymentid', 0, PARAM_INT);

// Logger l'annulation
if ($paymentid > 0) {
    $payment = $DB->get_record('payments', ['id' => $paymentid]);
    \paygw_helloasso\logger::log_action(
        $paymentid,
        $USER->id,
        'payment_cancelled',
        'cancelled',
        $payment ? $payment->amount : 0,
        'User cancelled payment on HelloAsso page'
    );
    error_log("DEBUG: Payment cancelled by user - paymentid={$paymentid}, userid={$USER->id}\n", 3, __DIR__ . '/debug.log');
}

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('payment_cancelled', 'paygw_helloasso'), 'notifyproblem');
echo $OUTPUT->continue_button(new moodle_url('/'));
echo $OUTPUT->footer();
