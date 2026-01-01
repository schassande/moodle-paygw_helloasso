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

defined('MOODLE_INTERNAL') || die();

/**
 * Callback to add payment gateways to the payment system.
 *
 * @return array
 */
function paygw_helloasso_payment_gateways(): array {
    return [
        'helloasso' => [
            'displayname' => get_string('pluginname', 'paygw_helloasso'),
            'component' => 'paygw_helloasso',
            'handlesrefrequest' => false,
        ]
    ];
}