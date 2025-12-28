<?php
namespace paygw_helloasso;

use core_payment\gateway as payment_gateway;
use moodle_url;

defined('MOODLE_INTERNAL') || die();

class gateway extends payment_gateway {

    public static function get_supported_currencies(): array {
        return ['EUR'];
    }

    public function get_name(): string {
        return get_string('pluginname', 'paygw_helloasso');
    }

    /**
     * Configuration form for the gateway in the payment account
     */
    public static function add_configuration_to_gateway_form(\core_payment\form\account_gateway $form): void {
        $mform = $form->get_mform();

        $mform->addElement('text', 'clientid', get_string('clientid', 'paygw_helloasso'));
        $mform->setType('clientid', PARAM_TEXT);
        $mform->addHelpButton('clientid', 'clientid', 'paygw_helloasso');

        $mform->addElement('text', 'clientsecret', get_string('clientsecret', 'paygw_helloasso'));
        $mform->setType('clientsecret', PARAM_TEXT);
        $mform->addHelpButton('clientsecret', 'clientsecret', 'paygw_helloasso');

        $mform->addElement('text', 'org_slug', get_string('org_slug', 'paygw_helloasso'));
        $mform->setType('org_slug', PARAM_TEXT);
        $mform->addHelpButton('org_slug', 'org_slug', 'paygw_helloasso');

        $mform->addElement('text', 'formid', get_string('formid', 'paygw_helloasso'));
        $mform->setType('formid', PARAM_TEXT);
        $mform->addHelpButton('formid', 'formid', 'paygw_helloasso');
    }

    /**
     * Validates the gateway configuration form
     */
    public static function validate_gateway_form(\core_payment\form\account_gateway $form, \stdClass $data, array $files, array &$errors): void {
        if (empty($data->clientid)) {
            $errors['clientid'] = get_string('required');
        }
        if (empty($data->clientsecret)) {
            $errors['clientsecret'] = get_string('required');
        }
        if (empty($data->org_slug)) {
            $errors['org_slug'] = get_string('required');
        }
        if (empty($data->formid)) {
            $errors['formid'] = get_string('required');
        }
    }

    public function initiate_payment(\core_payment\payment_transaction $payment, array $options = []): ?moodle_url {
        global $CFG, $USER;

        try {
            $config = (object) $this->get_configuration();
            $clientid = $config->clientid ?? '';
            $clientsecret = $config->clientsecret ?? '';
            $orgslug = $config->org_slug ?? '';
            $formid = $config->formid ?? '';

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
                throw new \moodle_exception('missingconfig', 'paygw_helloasso');
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
                throw new \moodle_exception('invalidamount', 'paygw_helloasso');
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

            // 1. Obtenir un token OAuth2.0 pour HelloAsso
            $token = self::get_helloasso_token($clientid, $clientsecret);

            $baseurl = "https://www.helloasso.com/associations/{$orgslug}/formulaires/{$formid}/paiement";
            
            // Générer les URLs de retour
            $successUrl = new moodle_url('/payment/gateway/helloasso/return.php', [
                'paymentid' => $payment->get_id(),
                'sesskey' => sesskey()
            ]);
            $cancelUrl = new moodle_url('/payment/gateway/helloasso/cancel.php');
            $reference = 'PAY-' . $payment->get_id();
            $amountcentimes = intval(round($amount * 100));
            $returnurl = rawurlencode($successUrl->out(false));
            $cancelurl = rawurlencode($cancelUrl->out(false));

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
        return false;
    }
}