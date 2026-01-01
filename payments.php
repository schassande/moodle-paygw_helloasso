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
 * Payment list page for HelloAsso gateway.
 *
 * @package    paygw_helloasso
 * @copyright  2025 Sebastien Chassande-Barrioz <chassande@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/adminlib.php');
require_once($CFG->libdir . '/tablelib.php');

require_login();
require_capability('paygw/helloasso:manage', context_system::instance());

$page = optional_param('page', 0, PARAM_INT);
$perpage = optional_param('perpage', 50, PARAM_INT);
$status = optional_param('status', '', PARAM_ALPHA);

$context = context_system::instance();
require_capability('paygw/helloasso:manage', $context);

$PAGE->set_url('/payment/gateway/helloasso/payments.php', ['page' => $page, 'perpage' => $perpage, 'status' => $status]);
$PAGE->set_title(get_string('payments_title', 'paygw_helloasso'));
$PAGE->set_heading(get_string('payments_title', 'paygw_helloasso'));

echo $OUTPUT->header();

// Filtres.
echo html_writer::start_tag('form', ['method' => 'get', 'action' => $PAGE->url->out_omit_querystring()]);
echo html_writer::start_div('form-inline mb-3');

echo html_writer::label(get_string('filter_status', 'paygw_helloasso'), 'status', false, ['class' => 'mr-2']);
echo html_writer::select([
    '' => get_string('all'),
    'success' => get_string('status_success', 'paygw_helloasso'),
    'error' => get_string('status_error', 'paygw_helloasso'),
    'cancelled' => get_string('status_cancelled', 'paygw_helloasso'),
    'fraud_detected' => get_string('status_fraud', 'paygw_helloasso'),
], 'status', $status, false, ['id' => 'status', 'class' => 'custom-select mr-2']);

echo html_writer::empty_tag('input', [
    'type' => 'submit',
    'value' => get_string('filter', 'moodle'),
    'class' => 'btn btn-primary'
]);

echo html_writer::end_div();
echo html_writer::end_tag('form');

// Table des logs.
$table = new flexible_table('paygw_helloasso_payments');
$table->define_columns(['timecreated', 'paymentid', 'userid', 'action', 'status', 'amount', 'reference', 'message']);
$table->define_headers([
    get_string('date'),
    get_string('payment_id', 'paygw_helloasso'),
    get_string('user'),
    get_string('action', 'paygw_helloasso'),
    get_string('status'),
    get_string('amount', 'paygw_helloasso'),
    get_string('reference', 'paygw_helloasso'),
    get_string('message', 'paygw_helloasso'),
]);

$table->define_baseurl($PAGE->url);
$table->sortable(true, 'timecreated', SORT_DESC);
$table->pageable(true);
$table->pagesize($perpage, 0);

$table->setup();

// Requête SQL.
$sql = "SELECT * FROM {paygw_helloasso_logs}";
$params = [];

if (!empty($status)) {
    $sql .= " WHERE status = :status";
    $params['status'] = $status;
}

$sql .= " ORDER BY timecreated DESC";

$logs = $DB->get_records_sql($sql, $params, $page * $perpage, $perpage);
$totalcount = $DB->count_records_sql("SELECT COUNT(*) FROM {paygw_helloasso_logs}" .
    (!empty($status) ? " WHERE status = :status" : ""), $params);

foreach ($logs as $log) {
    $user = $DB->get_record('user', ['id' => $log->userid], 'id, firstname, lastname');
    $username = $user ? fullname($user) : get_string('unknown', 'paygw_helloasso');

    // Colorer le statut.
    $statusclass = 'badge badge-secondary';
    switch ($log->status) {
        case 'success':
            $statusclass = 'badge badge-success';
            break;
        case 'error':
            $statusclass = 'badge badge-danger';
            break;
        case 'cancelled':
            $statusclass = 'badge badge-warning';
            break;
        case 'fraud_detected':
            $statusclass = 'badge badge-dark';
            break;
    }

    $statushtml = html_writer::tag('span', $log->status, ['class' => $statusclass]);

    // Formater le montant.
    $amounthtml = $log->amount > 0 ? number_format($log->amount, 2) . ' €' : '-';

    $table->add_data([
        userdate($log->timecreated, get_string('strftimedatetime', 'langconfig')),
        $log->paymentid > 0 ? $log->paymentid : '-',
        $username,
        $log->action,
        $statushtml,
        $amounthtml,
        $log->reference ?: '-',
        s($log->message),
    ]);
}

$table->finish_output();

// Statistiques.
echo html_writer::start_div('mt-4');
echo html_writer::tag('h3', get_string('statistics', 'paygw_helloasso'));

$stats = $DB->get_records_sql("
    SELECT status, COUNT(*) as count, SUM(amount) as total
    FROM {paygw_helloasso_logs}
    WHERE amount > 0
    GROUP BY status
");

echo html_writer::start_tag('table', ['class' => 'table table-bordered']);
echo html_writer::start_tag('thead');
echo html_writer::start_tag('tr');
echo html_writer::tag('th', get_string('status'));
echo html_writer::tag('th', get_string('count', 'paygw_helloasso'));
echo html_writer::tag('th', get_string('total_amount', 'paygw_helloasso'));
echo html_writer::end_tag('tr');
echo html_writer::end_tag('thead');
echo html_writer::start_tag('tbody');

foreach ($stats as $stat) {
    echo html_writer::start_tag('tr');
    echo html_writer::tag('td', $stat->status);
    echo html_writer::tag('td', $stat->count);
    echo html_writer::tag('td', number_format($stat->total, 2) . ' €');
    echo html_writer::end_tag('tr');
}

echo html_writer::end_tag('tbody');
echo html_writer::end_tag('table');
echo html_writer::end_div();

echo $OUTPUT->footer();
