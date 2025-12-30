<?php
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/filelib.php');
require_login();

global $USER, $OUTPUT, $DB;
$payment = null;

$paymentid = required_param('paymentid', PARAM_INT);
$sesskey = required_param('sesskey', PARAM_RAW);

// Paramètres retournés par HelloAsso
$checkoutintentid = optional_param('checkoutIntentId', '', PARAM_INT);
$code = optional_param('code', '', PARAM_ALPHA);
$orderid = optional_param('orderId', '', PARAM_INT);

error_log("DEBUG: return.php called with params :\n"
    . "-paymentid={$paymentid}\n"
    . "-sesskey={$sesskey}\n"
    . "-checkoutIntentId={$checkoutintentid}\n"
    . "-code={$code}\n"
    . "-orderId={$orderid}\n"
    , 3, __DIR__ . '/debug.log');


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
        error_log("DEBUG: Invalid sesskey for paymentid {$paymentid} and user {$USER->id}\n", 3, __DIR__ . '/debug.log');
        throw new \moodle_exception('invalidsesskey');
    }
    error_log("DEBUG: Sesskey confirmed for paymentid {$paymentid} and user {$USER->id}\n", 3, __DIR__ . '/debug.log');

    // Récupérer et valider le paiement
    $payment = $DB->get_record('payments', ['id' => $paymentid], '*', MUST_EXIST);
    if (!$payment) {
        \paygw_helloasso\logger::log_action(
            $paymentid,
            $USER->id,
            'payment_return',
            'error',
            0,
            'Payment record not found'
        );
        error_log("DEBUG: Payment record not found for paymentid {$paymentid}\n", 3, __DIR__ . '/debug.log');
        throw new \moodle_exception('paymentnotfound', 'paygw_helloasso');
    }
    error_log("DEBUG: Payment record found for paymentid {$paymentid}\n", 3, __DIR__ . '/debug.log');

    // Vérifier que le code retourné est "succeeded"
    if ($code !== 'succeeded') {
        \paygw_helloasso\logger::log_action(
            $paymentid,
            $USER->id,
            'payment_return',
            'error',
            $payment->amount,
            "Payment not succeeded. Code: {$code}, CheckoutIntent: {$checkoutintentid}",
            400,
            "CHECKOUT-{$checkoutintentid}"
        );
        error_log("DEBUG: Payment not succeeded for paymentid {$paymentid} with code {$code}", 3, __DIR__ . '/debug.log');
        throw new \moodle_exception('paymentnotcompleted', 'paygw_helloasso');
    }
    error_log("DEBUG: Payment succeeded for paymentid {$paymentid}\n", 3, __DIR__ . '/debug.log');

    // IMPORTANT: Vérifier le paiement via l'API HelloAsso pour éviter la fraude
    // Ne JAMAIS faire confiance uniquement aux paramètres d'URL
    $verified = verify_helloasso_payment($checkoutintentid, $orderid, $payment);

    if ($verified) {
        error_log("DEBUG: Payment verified successfully for paymentid {$paymentid}\n", 3, __DIR__ . '/debug.log');
        \paygw_helloasso\logger::log_action(
            $paymentid,
            $USER->id,
            'payment_return',
            'success',
            $payment->amount,
            "Payment verified and processed. OrderID: {$orderid}",
            200,
            "CHECKOUT-{$checkoutintentid}"
        );
        
        // Délivrer le service (inscription, etc.)
        \core_payment\helper::deliver_order(
            $payment->component,
            $payment->paymentarea,
            $payment->itemid,
            $paymentid,
            $USER->id
        );
        
        $successmsg = get_string('payment_success', 'paygw_helloasso');
    } else {
        \paygw_helloasso\logger::log_action(
            $paymentid,
            $USER->id,
            'payment_return',
            'error',
            $payment->amount,
            'Payment verification failed via API',
            400,
            "CHECKOUT-{$checkoutintentid}"
        );
        error_log("DEBUG: Payment verification failed for paymentid {$paymentid}\n", 3, __DIR__ . '/debug.log');
        throw new \moodle_exception('paymentverificationfailed', 'paygw_helloasso');
    }

    echo $OUTPUT->header();
    echo $OUTPUT->notification($successmsg, 'notifysuccess');
    echo $OUTPUT->continue_button(new moodle_url('/'));
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
    error_log("DEBUG: Exception caught in return.php for paymentid {$paymentid}: " . $e->getMessage() . "\n", 3, __DIR__ . '/debug.log');
}


/**
 * Vérifie le paiement via l'API HelloAsso
 * 
 * @param int $checkoutintentid ID du checkout intent
 * @param int $orderid ID de la commande HelloAsso
 * @param object $payment Enregistrement du paiement Moodle
 * @return bool True si le paiement est vérifié et validé
 */
function verify_helloasso_payment($checkoutintentid, $orderid, $payment) {
    global $DB;
    
    if (empty($checkoutintentid)) {
        return false;
    }
    
    $orgslug = get_config('paygw_helloasso', 'org_slug');
    if (empty($orgslug)) {
        error_log("DEBUG: Organization slug is not configured\n", 3, __DIR__ . '/debug.log');
        debugging("HelloAsso ERROR: Organization slug is not configured", DEBUG_DEVELOPER);
        return false;
    }
    error_log("DEBUG: Organization slug is {$orgslug}\n", 3, __DIR__ . '/debug.log');

    // Obtenir un token OAuth2 via la méthode mutualisée (construit automatiquement l'URL API)
    $token = \paygw_helloasso\gateway::get_helloasso_token();
    error_log("DEBUG: Obtained token for verification: " . ($token ? 'YES' : 'NO')."\n", 3, __DIR__ . '/debug.log');
    if (!$token) {
        return false;
    }
    error_log("DEBUG: Token obtained successfully\n", 3, __DIR__ . '/debug.log');

    // Récupérer le checkout intent depuis l'API
    $apiurl = \paygw_helloasso\gateway::get_API_url();
    $checkouturl = "{$apiurl}/v5/organizations/{$orgslug}/checkout-intents/{$checkoutintentid}";
    error_log("DEBUG: Checkout URL is {$checkouturl}\n", 3, __DIR__ . '/debug.log');

    $curl = new \curl();
    $curl->setHeader([ 'Authorization: Bearer ' . $token ]);
    $result = $curl->get($checkouturl);
    error_log("DEBUG: Checkout data retrieved: \n" . $result . "\n", 3, __DIR__ . '/debug.log');

    if (!$result) {
        return false;
    }
    error_log("DEBUG: Checkout data retrieved successfully\n", 3, __DIR__ . '/debug.log');

    $checkoutdata = json_decode($result, true);
    // log du resultat JSON complet pour debug avec indentation
    error_log("DEBUG: " . print_r($checkoutdata, true) . "\n", 3, __DIR__ . '/debug.log');
    // Vérifier que le paiement correspond
    if (!isset($checkoutdata['order']) || !isset($checkoutdata['order']['id'])) {
        error_log("DEBUG: Invalid checkout data structure\n", 3, __DIR__ . '/debug.log');
        return false;
    }

    // Vérifier que l'orderId correspond
    if ($orderid && $checkoutdata['order']['id'] != $orderid) {
        error_log("DEBUG: Order ID mismatch: expected {$orderid}, got {$checkoutdata['order']['id']}\n", 3, __DIR__ . '/debug.log');
        return false;
    }
    // Vérifier le montant (en centimes)
    $expectedamount = intval(round($payment->amount * 100));
    $actualamount = $checkoutdata['order']['amount']['total'] ?? 0;
    if ($expectedamount != $actualamount) {
        error_log("DEBUG: Amount mismatch: expected {$expectedamount}, got {$actualamount}\n", 3, __DIR__ . '/debug.log');
        return false;
    }
    // Vérifier le statut du paiement
    // Trouver dans $checkoutdata['order']['payments'] un paiement avec le statut correct
    $paymentstatus = false;
    if (isset($checkoutdata['order']['payments']) && is_array($checkoutdata['order']['payments'])) {
        foreach ($checkoutdata['order']['payments'] as $p) {
            if (isset($p['state']) && ($p['state'] === 'Authorized' || $p['state'] === 'Processed')) {
                // verification que le montant du paiement correspond aussi
                $paymentamount = $p['amount'] ?? 0;
                if ($paymentamount != $expectedamount) {
                    error_log("DEBUG: Payment amount mismatch in payments: expected {$expectedamount}, got {$paymentamount}\n", 3, __DIR__ . '/debug.log');
                    return false;
                }
                $paymentstatus = true;
                break;
            }
        }
    }
    if (!$paymentstatus) {
        error_log("DEBUG: No payment with authorized or processed status found\n", 3, __DIR__ . '/debug.log');
        return false;
    }

    
    // Vérifier les métadonnées pour s'assurer que c'est bien notre paiement
    $metadata = $checkoutdata['metadata'] ?? [];
    if (isset($metadata['moodle_payment_id']) && $metadata['moodle_payment_id'] != $payment->id) {
        error_log("DEBUG: Metadata payment ID mismatch: expected {$payment->id}, got {$metadata['moodle_payment_id']}\n", 3, __DIR__ . '/debug.log');
        return false;
    }
    
    error_log("DEBUG: Payment verification passed for paymentid {$payment->id}\n", 3, __DIR__ . '/debug.log');
    return true;
}

?>

