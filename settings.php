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

if ($ADMIN->fulltree) {
    $settings->add(new admin_setting_configtext(
        'paygw_helloasso/clientid',
        get_string('clientid', 'paygw_helloasso'),
        get_string('clientid_desc', 'paygw_helloasso'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'paygw_helloasso/clientsecret',
        get_string('clientsecret', 'paygw_helloasso'),
        get_string('clientsecret_desc', 'paygw_helloasso'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'paygw_helloasso/org_slug',
        get_string('org_slug', 'paygw_helloasso'),
        get_string('org_slug_desc', 'paygw_helloasso'),
        ''
    ));

    $settings->add(new admin_setting_configtext(
        'paygw_helloasso/base_url',
        get_string('base_url', 'paygw_helloasso'),
        get_string('base_url_desc', 'paygw_helloasso'),
        'helloasso.com',
        PARAM_TEXT
    ));

    $settings->add(new admin_setting_configcheckbox(
        'paygw_helloasso/debugmode',
        get_string('debugmode', 'paygw_helloasso'),
        get_string('debugmode_desc', 'paygw_helloasso'),
        0
    ));

    \core_payment\helper::add_common_gateway_settings($settings, 'paygw_helloasso');
}