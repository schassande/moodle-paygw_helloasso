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
 * Language strings for HelloAsso payment gateway (English).
 *
 * @package    paygw_helloasso
 * @copyright  2025 Sebastien Chassande-Barrioz <chassande@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$string['pluginname'] = 'HelloAsso';
$string['gatewayname'] = 'HelloAsso';
$string['gatewaydescription'] = 'Secure payment via HelloAsso';
$string['clientid'] = 'Client ID';
$string['clientid_desc'] = 'Your Client ID of the HelloAsso API';
$string['clientid_help'] = 'Enter your HelloAsso API Client ID. You can find it in your HelloAsso account settings under API section.';
$string['clientsecret'] = 'Client Secret';
$string['clientsecret_desc'] = 'Your Client Secret of the HelloAsso API';
$string['clientsecret_help'] = 'Enter your HelloAsso API Client Secret. Keep this confidential and never share it publicly.';
$string['org_slug'] = 'Organization slug';
$string['org_slug_desc'] = 'Identifier of your HelloAsso organization';
$string['org_slug_help'] = 'Your organization slug is the unique identifier in your HelloAsso URLs (e.g., "my-organization" from https://www.helloasso.com/associations/my-organization)';
$string['paymentdesc'] = 'HelloAsso Payment';
$string['formid'] = 'Form ID (legacy)';
$string['formid_desc'] = '[DEPRECATED] This field is no longer used with the Checkout API integration';
$string['formid_help'] = '[DEPRECATED] The plugin now uses the HelloAsso Checkout API which does not require a form ID';
$string['base_url'] = 'HelloAsso Base URL';
$string['base_url_desc'] = 'The base URL of HelloAsso (production or sandbox). Use helloasso.com for production or helloasso-sandbox.com for testing.';
$string['base_url_help'] = 'Enter only the domain name without https:// (e.g., helloasso.com or helloasso-sandbox.com)';
$string['payment_success'] = 'Your payment was successfully processed via HelloAsso.';
$string['payment_cancelled'] = 'Your payment was cancelled.';
$string['payment_technical_error'] = 'A technical error occurred during payment processing. Please try again or contact support.';
$string['paymentverificationfailed'] = 'Payment verification failed';
$string['paymentnotcompleted'] = 'Payment was not completed successfully';
$string['payment_error'] = 'An error occurred during payment processing';
$string['missingconfig'] = 'Missing HelloAsso configuration';
$string['invalidamount'] = 'Invalid payment amount';
$string['paymentnotfound'] = 'Payment not found';
$string['tokenfailed'] = 'Failed to obtain HelloAsso authentication token';
$string['checkoutfailed'] = 'Failed to create checkout intent';
$string['debugmode'] = 'Debug mode';
$string['debugmode_desc'] = 'Enable debug logging for troubleshooting. Logs will appear in Moodle debug output when debugging is enabled.';
$string['manage'] = 'Manage HelloAsso payment gateway';
$string['payments_title'] = 'HelloAsso Payments';
$string['filter_status'] = 'Filter by status';
$string['status_success'] = 'Success';
$string['status_error'] = 'Error';
$string['status_cancelled'] = 'Cancelled';
$string['status_fraud'] = 'Fraud detected';
$string['payment_id'] = 'Payment ID';
$string['action'] = 'Action';
$string['amount'] = 'Amount';
$string['reference'] = 'Reference';
$string['message'] = 'Message';
$string['unknown'] = 'Unknown';
$string['statistics'] = 'Statistics';
$string['count'] = 'Count';
$string['total_amount'] = 'Total amount';

// Privacy API.
$string['privacy:metadata:paygw_helloasso_logs'] = 'Payment transaction logs for HelloAsso gateway';
$string['privacy:metadata:paygw_helloasso_logs:paymentid'] = 'The ID of the payment transaction';
$string['privacy:metadata:paygw_helloasso_logs:userid'] = 'The ID of the user who made the payment';
$string['privacy:metadata:paygw_helloasso_logs:action'] = 'The action performed (e.g., payment_initiation, payment_return)';
$string['privacy:metadata:paygw_helloasso_logs:status'] = 'The status of the action (success, error, cancelled, fraud_detected)';
$string['privacy:metadata:paygw_helloasso_logs:amount'] = 'The payment amount in euros';
$string['privacy:metadata:paygw_helloasso_logs:reference'] = 'The HelloAsso reference for the transaction';
$string['privacy:metadata:paygw_helloasso_logs:message'] = 'Additional message or error details';
$string['privacy:metadata:paygw_helloasso_logs:response_code'] = 'HTTP response code from HelloAsso API';
$string['privacy:metadata:paygw_helloasso_logs:ip_address'] = 'The IP address of the user at the time of action';
$string['privacy:metadata:paygw_helloasso_logs:timecreated'] = 'The time when the log entry was created';
$string['privacy:metadata:helloasso'] = 'Payment data sent to HelloAsso payment platform';
$string['privacy:metadata:helloasso:email'] = 'User email address sent to HelloAsso for payment processing';
$string['privacy:metadata:helloasso:firstname'] = 'User first name sent to HelloAsso';
$string['privacy:metadata:helloasso:lastname'] = 'User last name sent to HelloAsso';
$string['privacy:metadata:helloasso:amount'] = 'Payment amount sent to HelloAsso';
