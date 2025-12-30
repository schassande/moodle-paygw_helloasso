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