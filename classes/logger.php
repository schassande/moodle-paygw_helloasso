<?php
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