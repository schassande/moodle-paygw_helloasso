<?php
// This file is part of Moodle - http://moodle.org/
//
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

/**
 * HelloAsso payment gateway external function for getting configuration.
 *
 * @package    paygw_helloasso
 * @copyright  2025 Sebastien Chassande-Barrioz <chassande@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_helloasso\external;

use core_external\external_api;
use core_external\external_function_parameters;
use core_external\external_value;
use core_external\external_single_structure;
use paygw_helloasso\gateway;
use paygw_helloasso\logger;

defined('MOODLE_INTERNAL') || die();

/**
 * External function to get HelloAsso configuration for JavaScript.
 *
 * @package    paygw_helloasso
 * @copyright  2025 Sebastien Chassande-Barrioz <chassande@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class get_config_for_js extends external_api {

    /**
     * Returns description of method parameters.
     *
     * @return external_function_parameters
     */
    public static function execute_parameters() {
        return new external_function_parameters([
            'component' => new external_value(PARAM_COMPONENT, 'Component'),
            'paymentarea' => new external_value(PARAM_AREA, 'Payment area'),
            'itemid' => new external_value(PARAM_INT, 'Item id'),
        ]);
    }

    /**
     * Get HelloAsso payment configuration and create checkout intent.
     *
     * @param string $component Component name
     * @param string $paymentarea Payment area
     * @param int $itemid Item ID
     * @return array Array with redirecturl
     */
    public static function execute(string $component, string $paymentarea, int $itemid) {
        global $USER, $DB, $CFG;

        $params = self::validate_parameters(self::execute_parameters(), [
            'component' => $component,
            'paymentarea' => $paymentarea,
            'itemid' => $itemid,
        ]);

        // Context validation - require user to be logged in
        $context = \context_system::instance();
        self::validate_context($context);
        require_login();

        $debug = get_config('paygw_helloasso', 'debugmode');
        
        if ($debug) {
            debugging('HelloAsso: Starting payment initialization', DEBUG_DEVELOPER);
            debugging("HelloAsso: Component=$component, PaymentArea=$paymentarea, ItemID=$itemid", DEBUG_DEVELOPER);
        }

        try {
            // Récupérer le compte de paiement associé et actif avec HelloAsso
            $sql = "SELECT pa.* 
                    FROM {payment_accounts} pa
                    JOIN {payment_gateways} pg ON pg.accountid = pa.id
                    WHERE pa.enabled = 1 
                    AND pg.gateway = 'helloasso'
                    AND pg.enabled = 1
                    LIMIT 1";
            
            $accountrecord = $DB->get_record_sql($sql);
            
            if (!$accountrecord) {
                if ($debug) {
                    debugging('HelloAsso: No active payment account found with HelloAsso gateway', DEBUG_DEVELOPER);
                }
                throw new \moodle_exception('accountnotfound', 'core_payment');
            }

            if ($debug) {
                debugging("HelloAsso: Found payment account ID={$accountrecord->id}", DEBUG_DEVELOPER);
            }

            // Créer l'objet account
            $account = new \core_payment\account($accountrecord->id);

            // Récupérer la configuration du compte (contient uniquement formid)
            $gateway = $DB->get_record('payment_gateways', [
                'accountid' => $account->get('id'),
                'gateway' => 'helloasso'
            ], '*', MUST_EXIST);
            
            $accountconfig = json_decode($gateway->config, true);
            
            // Récupérer les paramètres globaux du plugin
            $config = [
                'clientid' => get_config('paygw_helloasso', 'clientid'),
                'clientsecret' => get_config('paygw_helloasso', 'clientsecret'),
                'org_slug' => get_config('paygw_helloasso', 'org_slug'),
                'base_url' => get_config('paygw_helloasso', 'base_url'),
            ];

            if ($debug) {
                debugging("HelloAsso: Config - org_slug={$config['org_slug']}, base_url={$config['base_url']}", DEBUG_DEVELOPER);
            }

            // Récupérer le coût depuis la méthode d'inscription
            $amount = null;
            $currency = 'EUR';
            
            // Pour enrol_fee (inscription payante)
            if ($component === 'enrol_fee' && $paymentarea === 'fee') {
                $instance = $DB->get_record('enrol', ['id' => $itemid, 'enrol' => 'fee'], '*', MUST_EXIST);
                $amount = (float) $instance->cost;
                $currency = $instance->currency;
                
                if ($debug) {
                    debugging("HelloAsso: Found enrol_fee instance - cost={$instance->cost}, currency={$instance->currency}", DEBUG_DEVELOPER);
                }
            } else {
                // Essayer le callback générique
                $payable = component_callback(
                    $component,
                    'get_payable',
                    [$paymentarea, $itemid],
                    null
                );
                
                if (!empty($payable)) {
                    $amount = $payable['amount'];
                    $currency = $payable['currency'];
                    
                    if ($debug) {
                        debugging("HelloAsso: Got payable from callback - amount={$amount}, currency={$currency}", DEBUG_DEVELOPER);
                    }
                }
            }
            
            if ($amount === null || $amount <= 0) {
                if ($debug) {
                    debugging("HelloAsso ERROR: Could not determine payment amount", DEBUG_DEVELOPER);
                }
                throw new \moodle_exception('invalidamount', 'paygw_helloasso');
            }
            
            if ($currency !== 'EUR') {
                if ($debug) {
                    debugging("HelloAsso ERROR: Currency must be EUR, got {$currency}", DEBUG_DEVELOPER);
                }
                throw new \moodle_exception('Currency must be EUR for HelloAsso', 'paygw_helloasso');
            }

            if ($debug) {
                debugging("HelloAsso: Payment amount={$amount} {$currency}", DEBUG_DEVELOPER);
            }

            // Créer la transaction de paiement
            $paymentid = \core_payment\helper::save_payment(
                $account->get('id'),
                $component,
                $paymentarea,
                $itemid,
                $USER->id,
                $amount,
                'EUR',
                'helloasso'
            );

            if ($debug) {
                debugging("HelloAsso: Payment record created with ID={$paymentid}", DEBUG_DEVELOPER);
            }

            // Préparer les informations du payeur depuis le profil Moodle
            $payerinfo = null;
            if (!empty($USER->firstname) && !empty($USER->lastname)) {
                $payerinfo = [
                    'email' => $USER->email,
                    'firstName' => $USER->firstname,
                    'lastName' => $USER->lastname,
                ];
                
                // Ajouter l'adresse si disponible
                if (!empty($USER->city)) {
                    $payerinfo['city'] = $USER->city;
                }
                if (!empty($USER->country)) {
                    $payerinfo['country'] = strtoupper($USER->country);
                }
            }

            // Description de l'achat
            $itemname = "Paiement Moodle - {$component}";
            if ($component === 'enrol_fee' && isset($instance)) {
                $course = $DB->get_record('course', ['id' => $instance->courseid], 'fullname');
                if ($course) {
                    $itemname = "Inscription - {$course->fullname}";
                }
            }

            // UTILISER LA MÉTHODE CENTRALISÉE avec les nouveaux paramètres
            $payurl = gateway::generate_payment_url(
                $config,
                $paymentid,
                $amount,
                $USER->email,
                $itemname,
                $payerinfo
            );

            if ($debug) {
                debugging("HelloAsso: Payment URL generated: {$payurl->out(false)}", DEBUG_DEVELOPER);
            }

            // Logger l'initiation
            logger::log_action(
                $paymentid,
                $USER->id,
                'payment_initiation_js',
                'success',
                $amount,
                'Payment initiated from JavaScript',
                200,
                'PAY-' . $paymentid
            );

            return [
                'redirecturl' => $payurl->out(false),
            ];
            
        } catch (\Exception $e) {
            if ($debug) {
                debugging("HelloAsso ERROR: {$e->getMessage()}", DEBUG_DEVELOPER);
                debugging("HelloAsso ERROR Trace: {$e->getTraceAsString()}", DEBUG_DEVELOPER);
            }
            
            // Log l'erreur
            if (isset($paymentid)) {
                logger::log_action(
                    $paymentid,
                    $USER->id,
                    'payment_initiation_js',
                    'error',
                    $amount ?? 0,
                    $e->getMessage(),
                    500
                );
            }
            
            throw $e;
        }
    }

    /**
     * Returns description of method return value.
     *
     * @return external_single_structure
     */
    public static function execute_returns() {
        return new external_single_structure([
            'redirecturl' => new external_value(PARAM_URL, 'Redirect URL'),
        ]);
    }
}