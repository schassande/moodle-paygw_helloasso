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

/**
 * This module handles the payment modal for HelloAsso gateway.
 *
 * @module     paygw_helloasso/gateways_modal
 * @copyright  2024 Your Name
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['core/ajax'], function(Ajax) {
    'use strict';

    return {
        /**
         * Process the payment
         *
         * @param {String} component
         * @param {String} paymentArea
         * @param {Number} itemId
         * @param {String} description
         * @returns {Promise}
         */
        process: function(component, paymentArea, itemId, description) {
            return Ajax.call([{
                methodname: 'paygw_helloasso_get_config_for_js',
                args: {
                    component: component,
                    paymentarea: paymentArea,
                    itemid: itemId
                }
            }])[0].then(function(config) {
                window.location.href = config.redirecturl;
                return new Promise(function() {
                    // Keep promise pending as we're redirecting
                });
            });
        }
    };
});