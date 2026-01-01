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
