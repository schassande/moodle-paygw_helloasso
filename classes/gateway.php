<?php
namespace paygw_helloasso;

use core_payment\gateway as payment_gateway;
use moodle_url;
require_once($CFG->libdir . '/filelib.php');

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/filelib.php');

class gateway extends payment_gateway {

    public static function get_supported_currencies(): array {
        return ['EUR'];
    }

    public function get_name(): string {
        return get_string('pluginname', 'paygw_helloasso');
    }

    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        // Aucune configuration spécifique au compte n'est nécessaire
        // L'API Checkout utilise uniquement la configuration globale du plugin
    }

    public static function validate_gateway_form(\core_payment\form\account_gateway $form, \stdClass $data, array $files, array &$errors): void {
        // Aucune validation nécessaire
    }

    /**
     * Crée un checkout intent HelloAsso et retourne l'URL de redirection
     * Utilise l'API v5 HelloAsso Checkout
     *
     * @param array $config Configuration (org_slug, clientid, clientsecret, base_url)
     * @param int $paymentid ID du paiement Moodle
     * @param float $amount Montant en euros
     * @param string $useremail Email de l'utilisateur
     * @param string $itemname Description de l'achat
     * @param array|null $payerinfo Informations du payeur (firstName, lastName, etc)
     * @return moodle_url URL de redirection HelloAsso
     */
    public static function generate_payment_url(array $config, int $paymentid, float $amount, string $useremail, string $itemname = 'Paiement Moodle', ?array $payerinfo = null): moodle_url {
        global $USER;
        
        $debug = get_config('paygw_helloasso', 'debugmode');
        
        $orgslug = $config['org_slug'] ?? '';
        $clientid = $config['clientid'] ?? '';
        $clientsecret = $config['clientsecret'] ?? '';
        $baseurl = $config['base_url'] ?? 'helloasso.com';
        
        // Construire les URLs à partir de base_url
        // base_url peut être "helloasso.com" ou "helloasso-sandbox.com"
        $apiurl = 'https://api.' . $baseurl;

        if ($debug) {
            debugging("HelloAsso: Config check - org_slug={$orgslug}, clientid=" . substr($clientid, 0, 10) . "..., base_url={$baseurl}, api_url={$apiurl}", DEBUG_DEVELOPER);
        }

        if (empty($orgslug) || empty($clientid) || empty($clientsecret)) {
            if ($debug) {
                debugging("HelloAsso ERROR: Missing config - org_slug=" . (empty($orgslug) ? 'EMPTY' : 'OK') . 
                         ", clientid=" . (empty($clientid) ? 'EMPTY' : 'OK') . 
                         ", clientsecret=" . (empty($clientsecret) ? 'EMPTY' : 'OK'), DEBUG_DEVELOPER);
            }
            throw new \moodle_exception('missingconfig', 'paygw_helloasso');
        }

        if ($amount <= 0) {
            throw new \moodle_exception('invalidamount', 'paygw_helloasso');
        }

        // Obtenir le token OAuth2
        $token = self::get_helloasso_token();
        if (!$token) {
            // Récupérer le dernier log d'erreur pour plus de détails
            global $DB;
            $lastlog = $DB->get_record_sql(
                "SELECT * FROM {payment_helloasso_logs} 
                 WHERE action = 'token_request' AND status = 'error' 
                 ORDER BY timecreated DESC LIMIT 1"
            );
            
            $errormsg = 'Failed to obtain authentication token';
            if ($lastlog && !empty($lastlog->message)) {
                $errormsg .= ': ' . $lastlog->message;
                if ($lastlog->response_code) {
                    $errormsg .= ' (HTTP ' . $lastlog->response_code . ')';
                }
            }
            
            if ($debug) {
                debugging("HelloAsso TOKEN ERROR: {$errormsg}", DEBUG_DEVELOPER);
            }
            
            throw new \moodle_exception('tokenfailed', 'paygw_helloasso', '', null, $errormsg);
        }

        // Préparer les URLs de retour
        $returnurl = new moodle_url('/payment/gateway/helloasso/return.php', [
            'paymentid' => $paymentid,
            'sesskey' => sesskey()
        ]);
        
        $backurl = new moodle_url('/payment/gateway/helloasso/cancel.php', [
            'paymentid' => $paymentid
        ]);
        error_log("DEBUG: backurl={$backurl->out(false)}\n", 3, __DIR__ . '/debug.log');
        
        $errorurl = new moodle_url('/payment/gateway/helloasso/error.php', [
            'paymentid' => $paymentid,
            'sesskey' => sesskey()
        ]);
        error_log("DEBUG: errorurl={$errorurl->out(false)}\n", 3, __DIR__ . '/debug.log');

        // Préparer les données du checkout intent
        $amountcentimes = intval(round($amount * 100));
        $checkoutdata = [
            'totalAmount' => $amountcentimes,
            'initialAmount' => $amountcentimes,
            'itemName' => $itemname,
            'backUrl' => $backurl->out(false),
            'errorUrl' => $errorurl->out(false),
            'returnUrl' => $returnurl->out(false),
            'containsDonation' => false,
            'metadata' => [
                'moodle_payment_id' => $paymentid,
                'moodle_user_id' => $USER->id,
            ]
        ];

        // Ajouter les informations du payeur si disponibles
        if ($payerinfo) {
            $checkoutdata['payer'] = $payerinfo;
            $country = $checkoutdata['payer']['country'] ?? '';
            if (!empty($country) && strlen($country) == 2) {
                // HelloAsso requiert un nom de pays sur 3 caractères, lorsqu'il est défini
                // Mais Moodle n'a pas de fonction native pour convertir ISO2 -> ISO3
                // On supprime simplement le champ si c'est ISO2 pour éviter l'erreur HelloAsso
                unset($checkoutdata['payer']['country']);
            }
        } else if (!empty($useremail)) {
            $checkoutdata['payer'] = ['email' => $useremail];
        }
        $body = json_encode($checkoutdata, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);

        // Appel POST à l'API checkout-intents
        $curl = new \curl();
        $curl->setHeader([
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json'
        ]);
        $checkouturl = "{$apiurl}/v5/organizations/{$orgslug}/checkout-intents";
        error_log("DEBUG: Checkout intents HTTP GET call :\n-url={$checkouturl}\n" 
            . "-token:" . $token . "\n"
            . "-body:" . $body . "\n"
            , 3, __DIR__ . '/debug.log');
        $result = $curl->post($checkouturl, $body);

        $httpcode = $curl->get_info()['http_code'] ?? 0;

        if (!$result) {
            error_log("Checkout intents, no HTTP result, httpcode={$httpcode}\n", 3, __DIR__ . '/debug.log');
            logger::log_action($paymentid, $USER->id, 'checkout_intent_creation', 'error', $amount, 'No response from API', $httpcode);
            throw new \moodle_exception('checkoutfailed', 'paygw_helloasso');
        }

        $response = json_decode($result, true);
        error_log("Checkout intents, HTTP result:\n-httpcode={$httpcode}\n-response=" . print_r($response, true) . "\n", 3, __DIR__ . '/debug.log');

        if ($httpcode != 200 || !isset($response['redirectUrl'])) {
            $errormsg = $response['message'] ?? 'Unknown error';
            logger::log_action($paymentid, $USER->id, 'checkout_intent_creation', 'error', $amount, "HTTP {$httpcode}: {$errormsg}", $httpcode);
            throw new \moodle_exception('checkoutfailed', 'paygw_helloasso', '', null, $errormsg);
        }

        // Enregistrer l'ID du checkout intent pour la réconciliation
        $checkoutintentid = $response['id'] ?? null;
        if ($checkoutintentid) {
            logger::log_action($paymentid, $USER->id, 'checkout_intent_creation', 'success', $amount, "Checkout intent created: {$checkoutintentid}", $httpcode, "CHECKOUT-{$checkoutintentid}");
        }

        return new moodle_url($response['redirectUrl']);
    }

    /**
     * Initie un paiement (méthode obligatoire de la classe parente)
     * Cette méthode est appelée par Moodle dans certains contextes
     * Elle délègue à generate_payment_url() pour éviter la duplication
     */
    public function initiate_payment(\core_payment\payment_transaction $payment, array $options = []): ?moodle_url {
        global $USER;

        try {
            $config = $this->get_configuration();
            
            logger::log_action(
                $payment->get_id(),
                $USER->id,
                'payment_initiation_server',
                'success',
                $payment->get_amount(),
                'Payment initiated from server (gateway.php)'
            );

            return self::generate_payment_url(
                $config,
                $payment->get_id(),
                $payment->get_amount(),
                $USER->email
            );

        } catch (\Exception $e) {
            logger::log_action(
                $payment->get_id(),
                $USER->id,
                'payment_initiation_server',
                'error',
                $payment->get_amount(),
                $e->getMessage()
            );
            throw $e;
        }
    }

    public static function get_API_url(): string {
        $baseurl = get_config('paygw_helloasso', 'base_url') ?? 'helloasso.com';
        return 'https://api.' . $baseurl;
    }

    /**
     * Obtient un token OAuth2 HelloAsso
     *
     * @param string $apiurl URL de l'API (production ou sandbox)
     * @return string|null Token d'accès ou null en cas d'erreur
     */
    public static function get_helloasso_token() {
        // l'URL API estconstruire depuis base_url
        $url = self::get_API_url() . "/oauth2/token";

        // Récupérer les credentials depuis la configuration
        $clientid = get_config('paygw_helloasso', 'clientid');
        $clientsecret = get_config('paygw_helloasso', 'clientsecret');
        
        // construction du body de la requête
        $data = "grant_type=client_credentials&client_id={$clientid}&client_secret={$clientsecret}";

        debugging("HelloAsso: Requesting token for client {$clientid} using URL {$url}", DEBUG_DEVELOPER);
        $curl = new \curl();
        $result = $curl->post($url, $data, [ 'CURLOPT_HTTPHEADER' => [ 'Content-Type: application/x-www-form-urlencoded'] ]);
        $httpcode = $curl->get_info()['http_code'] ?? 0;
        debugging("HelloAsso: Token response HTTP {$httpcode}. Response body: " . substr($result, 0, 200), DEBUG_DEVELOPER);

        if (!$result) {
            error_log("Token request failed - no response" . $httpcode . "\n", 3, __DIR__ . '/debug.log');
            logger::log_action(0, 0, 'token_request', 'error', 0, 'Token request failed - no response', $httpcode);
            return null;
        }

        $json = json_decode($result, true);        
        if (isset($json['access_token'])) {
            $token = $json['access_token'];
            debugging("HelloAsso: Token obtained: {$token}", DEBUG_DEVELOPER);
            error_log("HelloAsso: Token obtained: {$token}\n", 3, __DIR__ . '/debug.log');
            logger::log_action(0, 0, 'token_request', 'success', 0, 'Token obtained successfully', $httpcode);
            return $token;
        } else {
            $errormsg = isset($json['error']) ? $json['error'] : 'Unknown error';
            $errordesc = isset($json['error_description']) ? $json['error_description'] : '';
            error_log("HelloAsso: Token error - {$errormsg}: {$errordesc}\n", 3, __DIR__ . '/debug.log');
            logger::log_action(0, 0, 'token_request', 'error', 0, "Token error: {$errormsg} - {$errordesc}", $httpcode);
            return null;
        }
    }

    public function can_refund(): bool {
        return false;
    }

    /**
     * Configuration for the payment modal
     * This method tells Moodle which JavaScript module to load
     *
     * @param \stdClass $config Gateway configuration
     * @return array Configuration array for JS
     */
    public function get_payable(string $component, string $paymentarea, int $itemid): array {
        return [
            'javascript' => 'paygw_helloasso/gateways_modal',
            'submitbutton' => true,
        ];
    }
}
