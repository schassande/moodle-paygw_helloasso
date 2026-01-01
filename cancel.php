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

require_once(__DIR__ . '/../../../config.php');
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
    debugging("HelloAsso: Payment cancelled by user - paymentid={$paymentid}, userid={$USER->id}", DEBUG_DEVELOPER);
}

echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('payment_cancelled', 'paygw_helloasso'), 'notifyproblem');
echo $OUTPUT->continue_button(new moodle_url('/'));
echo $OUTPUT->footer();
