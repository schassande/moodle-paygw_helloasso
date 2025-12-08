<?php
namespace payment_gateway_helloasso;

use core_payment\gateway;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

class gateway extends \core_payment\gateway {

    public static function get_supported_currencies(): array {
        return ['EUR'];
    }

    public function get_name(): string {
        return get_string('pluginname', 'payment_gateway_helloasso');
    }

    public function initiate_payment(\core_payment\payment_transaction $payment, array $options = []): ?moodle_url {
        global $CFG, $USER;

        try {
            $clientid = get_config('payment_gateway_helloasso', 'clientid');
            $clientsecret = get_config('payment_gateway_helloasso', 'clientsecret');
            $orgslug = get_config('payment_gateway_helloasso', 'org_slug');
            $formid = get_config('payment_gateway_helloasso', 'formid');

            // Valider les configs
            if (empty($clientid) || empty($clientsecret) || empty($orgslug) || empty($formid)) {
                logger::log_action(
                    $payment->get_id(),
                    $USER->id,
                    'payment_initiation',
                    'error',
                    $payment->get_amount(),
                    'Missing configuration (clientid, clientsecret, orgslug or formid)'
                );
                throw new \moodle_exception('missingconfig', 'payment_gateway_helloasso');
            }

            $amount = $payment->get_amount();
            if ($amount <= 0) {
                logger::log_action(
                    $payment->get_id(),
                    $USER->id,
                    'payment_initiation',
                    'error',
                    $amount,
                    'Invalid amount: ' . $amount
                );
                throw new \moodle_exception('invalidamount', 'payment_gateway_helloasso');
            }

            // Log de l'initiation du paiement
            logger::log_action(
                $payment->get_id(),
                $USER->id,
                'payment_initiation',
                'success',
                $amount,
                'Payment initiated successfully'
            );

            // Commerce: Créer une session paiement HelloAsso
            // (documentation: https://api.helloasso.com/docs/)

            // 1. Obtenir un token OAuth2.0 pour HelloAsso
            $token = self::get_helloasso_token($clientid, $clientsecret);

            // 2. Créer ou utiliser un formulaire HelloAsso avec l'API
            // (Ici, on suppose que tu as un formulaire de type 'don' déjà créé).

            // 3. Générer un lien de paiement avec les bons paramètres.
            // Ici, par simplicité, on redirige vers le formulaire en transmettant le montant et la référence.
            // Dans l'idéal, il faudrait utiliser un endpoint serveur pour créer la ressource HelloAsso et l'associer.

            // Exemple de lien (remplacez par l'URL du formulaire HelloAsso réel) :
            $baseurl = "https://www.helloasso.com/associations/{$orgslug}/formulaires/{$formid}/paiement";
            
            $returnurl = rawurlencode($successUrl);
            $cancelurl = rawurlencode($cancelUrl);
            $amountcentimes = intval(round($amount * 100));

            // Tu devras ajuster avec les paramètres attendus par HelloAsso (à voir selon la doc ou le widget utilisé).
            $payurl = new moodle_url($baseurl, [
                'amount' => $amountcentimes,
                'reference' => $reference,
                'backUrl' => $returnurl,
                'cancelUrl' => $cancelurl,
                'email' => $USER->email
            ]);

            return $payurl;
        } catch (\Exception $e) {
            logger::log_action(
                $payment->get_id(),
                $USER->id,
                'payment_initiation',
                'error',
                $payment->get_amount(),
                $e->getMessage()
            );
            throw $e;
        }
    }

    // -- OBTENIR UN TOKEN OAUTH2 (exemple simplifié, utilise CURL ou Guzzle) --
    private static function get_helloasso_token($clientid, $clientsecret) {
        $url = 'https://api.helloasso.com/oauth2/token';
        $data = [
            'grant_type' => 'client_credentials',
            'client_id' => $clientid,
            'client_secret' => $clientsecret
        ];

        $curl = new \curl();
        $curl->setopt([
            'CURLOPT_SSL_VERIFYPEER' => true,
            'CURLOPT_SSL_VERIFYHOST' => 2,
            'CURLOPT_TIMEOUT' => 30
        ]);

        $result = $curl->post($url, http_build_query($data), [
            'Content-Type: application/x-www-form-urlencoded'
        ]);

        $httpcode = $curl->get_info()['http_code'] ?? 0;

        if (!$result) {
            debugging('HelloAsso token request failed', DEBUG_DEVELOPER);
            logger::log_action(
                0,
                0,
                'token_request',
                'error',
                0,
                'Token request failed',
                $httpcode
            );
            return null;
        }

        $json = json_decode($result, true);
        
        if (isset($json['access_token'])) {
            logger::log_action(
                0,
                0,
                'token_request',
                'success',
                0,
                'Token obtained successfully',
                $httpcode
            );
            return $json['access_token'];
        } else {
            logger::log_action(
                0,
                0,
                'token_request',
                'error',
                0,
                'No access token in response: ' . json_encode($json),
                $httpcode
            );
            return null;
        }
    }

    public function can_refund(): bool {
        return false; // A gérer si HelloAsso supporte les remboursements via API.
    }
}