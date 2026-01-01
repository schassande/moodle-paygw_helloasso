<?php
/**
 * This file is part of Moodle - http://moodle.org/
 * Moodle is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * Moodle is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with Moodle.  If not, see <http://www.gnu.org/licenses/>.
 *
 * @copyright 2025 Sebastien Chassande-Barrioz <chassande@gmail.com>
 */
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

debugging("HelloAsso: return.php called - paymentid={$paymentid}, checkoutIntentId={$checkoutintentid}, code={$code}, orderId={$orderid}", DEBUG_DEVELOPER);

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
        debugging("HelloAsso: Invalid sesskey for paymentid {$paymentid} and user {$USER->id}", DEBUG_DEVELOPER);
        throw new \moodle_exception('invalidsesskey');
    }
    debugging("HelloAsso: Sesskey confirmed for paymentid {$paymentid} and user {$USER->id}", DEBUG_DEVELOPER);

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
        debugging("HelloAsso: Payment record not found for paymentid {$paymentid}", DEBUG_DEVELOPER);
        throw new \moodle_exception('paymentnotfound', 'paygw_helloasso');
    }
    debugging("HelloAsso: Payment record found for paymentid {$paymentid}", DEBUG_DEVELOPER);

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
        debugging("HelloAsso: Payment not succeeded for paymentid {$paymentid} with code {$code}", DEBUG_DEVELOPER);
        throw new \moodle_exception('paymentnotcompleted', 'paygw_helloasso');
    }
    debugging("HelloAsso: Payment succeeded for paymentid {$paymentid}", DEBUG_DEVELOPER);

    // IMPORTANT: Vérifier le paiement via l'API HelloAsso pour éviter la fraude
    // Ne JAMAIS faire confiance uniquement aux paramètres d'URL
    $verified = paygw_helloasso_verify_payment($checkoutintentid, $orderid, $payment);

    if ($verified) {
        debugging("HelloAsso: Payment verified successfully for paymentid {$paymentid}", DEBUG_DEVELOPER);
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
        debugging("HelloAsso: Payment verification failed for paymentid {$paymentid}", DEBUG_DEVELOPER);
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
    debugging("HelloAsso: Exception in return.php for paymentid {$paymentid}: " . $e->getMessage(), DEBUG_DEVELOPER);
}


/**
 * Vérifie le paiement via l'API HelloAsso
 * 
 * @param int $checkoutintentid ID du checkout intent
 * @param int $orderid ID de la commande HelloAsso
 * @param object $payment Enregistrement du paiement Moodle
 * @return bool True si le paiement est vérifié et validé
 */
function paygw_helloasso_verify_payment($checkoutintentid, $orderid, $payment) {
    global $DB;
    
    if (empty($checkoutintentid)) {
        return false;
    }
    
    $orgslug = get_config('paygw_helloasso', 'org_slug');
    if (empty($orgslug)) {
        debugging("HelloAsso ERROR: Organization slug is not configured", DEBUG_DEVELOPER);
        return false;
    }
    debugging("HelloAsso: Organization slug is {$orgslug}", DEBUG_DEVELOPER);

    // Obtenir un token OAuth2 via la méthode mutualisée (construit automatiquement l'URL API)
    $token = \paygw_helloasso\gateway::get_helloasso_token();
    debugging("HelloAsso: Obtained token for verification: " . ($token ? 'YES' : 'NO'), DEBUG_DEVELOPER);
    if (!$token) {
        return false;
    }
    debugging("HelloAsso: Token obtained successfully", DEBUG_DEVELOPER);

    // Récupérer le checkout intent depuis l'API
    $apiurl = \paygw_helloasso\gateway::get_API_url();
    $checkouturl = "{$apiurl}/v5/organizations/{$orgslug}/checkout-intents/{$checkoutintentid}";
    debugging("HelloAsso: Checkout URL is {$checkouturl}", DEBUG_DEVELOPER);

    $curl = new \curl();
    $curl->setHeader([ 'Authorization: Bearer ' . $token ]);
    $result = $curl->get($checkouturl);
    debugging("HelloAsso: Checkout data retrieved: " . $result, DEBUG_DEVELOPER);

    if (!$result) {
        return false;
    }
    debugging("HelloAsso: Checkout data retrieved successfully", DEBUG_DEVELOPER);

    $checkoutdata = json_decode($result, true);
    // log du resultat JSON complet pour debug
    debugging("HelloAsso: Checkout data structure: " . json_encode($checkoutdata), DEBUG_DEVELOPER);
    // Vérifier que le paiement correspond
    if (!isset($checkoutdata['order']) || !isset($checkoutdata['order']['id'])) {
        debugging("HelloAsso: Invalid checkout data structure", DEBUG_DEVELOPER);
        return false;
    }

    // Vérifier que l'orderId correspond
    if ($orderid && $checkoutdata['order']['id'] != $orderid) {
        debugging("HelloAsso: Order ID mismatch - expected {$orderid}, got {$checkoutdata['order']['id']}", DEBUG_DEVELOPER);
        return false;
    }
    // Vérifier le montant (en centimes)
    $expectedamount = intval(round($payment->amount * 100));
    $actualamount = $checkoutdata['order']['amount']['total'] ?? 0;
    if ($expectedamount != $actualamount) {
        debugging("HelloAsso: Amount mismatch - expected {$expectedamount}, got {$actualamount}", DEBUG_DEVELOPER);
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
                    debugging("HelloAsso: Payment amount mismatch in payments - expected {$expectedamount}, got {$paymentamount}", DEBUG_DEVELOPER);
                    return false;
                }
                $paymentstatus = true;
                break;
            }
        }
    }
    if (!$paymentstatus) {
        debugging("HelloAsso: No payment with authorized or processed status found", DEBUG_DEVELOPER);
        return false;
    }

    
    // Vérifier les métadonnées pour s'assurer que c'est bien notre paiement
    $metadata = $checkoutdata['metadata'] ?? [];
    if (isset($metadata['moodle_payment_id']) && $metadata['moodle_payment_id'] != $payment->id) {
        debugging("HelloAsso: Metadata payment ID mismatch - expected {$payment->id}, got {$metadata['moodle_payment_id']}", DEBUG_DEVELOPER);
        return false;
    }
    
    debugging("HelloAsso: Payment verification passed for paymentid {$payment->id}", DEBUG_DEVELOPER);
    return true;
}
