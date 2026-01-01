<?php
// This file is part of Moodle - http://moodle.org/
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
// 
// @copyright 2025 Sebastien Chassande-Barrioz <chassande@gmail.com>

namespace paygw_helloasso;

defined('MOODLE_INTERNAL') || die();

class logger {

    /**
     * Log une action du plugin
     *
     * @param int $paymentid ID du paiement
     * @param int $userid ID de l'utilisateur
     * @param string $action Type d'action
     * @param string $status Statut (success, error, fraud_detected)
     * @param float $amount Montant du paiement
     * @param string $message Message détaillé
     * @param int $response_code Code HTTP de réponse
     * @param string $reference Référence HelloAsso
     */
    public static function log_action($paymentid, $userid, $action, $status, $amount, $message = '', $response_code = null, $reference = '') {
        global $DB;

        $log = new \stdClass();
        $log->paymentid = $paymentid;
        $log->userid = $userid;
        $log->action = $action;
        $log->status = $status;
        $log->amount = $amount;
        $log->reference = $reference;
        $log->message = $message;
        $log->response_code = $response_code;
        $log->ip_address = getremoteaddr();
        $log->timecreated = time();

        $DB->insert_record('payment_helloasso_logs', $log);
    }

    /**
     * Récupère les logs d'un paiement
     */
    public static function get_payment_logs($paymentid) {
        global $DB;
        return $DB->get_records('payment_helloasso_logs', ['paymentid' => $paymentid], 'timecreated DESC');
    }

    /**
     * Récupère les logs avec erreurs
     */
    public static function get_error_logs($limit = 100) {
        global $DB;
        return $DB->get_records('payment_helloasso_logs', 
            ['status' => 'error'], 
            'timecreated DESC', 
            '*', 
            0, 
            $limit
        );
    }

    /**
     * Récupère les paiements suspects (fraude)
     */
    public static function get_fraud_alerts($limit = 50) {
        global $DB;
        return $DB->get_records('payment_helloasso_logs', 
            ['status' => 'fraud_detected'], 
            'timecreated DESC', 
            '*', 
            0, 
            $limit
        );
    }
}