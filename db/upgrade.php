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
 * Upgrade script for paygw_helloasso.
 *
 * @package    paygw_helloasso
 * @copyright  2025 Sebastien Chassande-Barrioz <chassande@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

/**
 * Upgrade the plugin.
 *
 * @param int $oldversion The old version of the plugin
 * @return bool
 */
function xmldb_paygw_helloasso_upgrade($oldversion) {
    global $DB;

    $dbman = $DB->get_manager();

    if ($oldversion < 2026010102) {
        // Define table paygw_helloasso_logs to be created.
        $table = new xmldb_table('paygw_helloasso_logs');

        // Adding fields to table paygw_helloasso_logs.
        $table->add_field('id', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, XMLDB_SEQUENCE, null);
        $table->add_field('paymentid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('userid', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);
        $table->add_field('action', XMLDB_TYPE_CHAR, '50', null, XMLDB_NOTNULL, null, null);
        $table->add_field('status', XMLDB_TYPE_CHAR, '20', null, XMLDB_NOTNULL, null, null);
        $table->add_field('amount', XMLDB_TYPE_NUMBER, '10, 2', null, XMLDB_NOTNULL, null, null);
        $table->add_field('reference', XMLDB_TYPE_CHAR, '255', null, null, null, null);
        $table->add_field('message', XMLDB_TYPE_TEXT, null, null, null, null, null);
        $table->add_field('response_code', XMLDB_TYPE_INTEGER, '3', null, null, null, null);
        $table->add_field('ip_address', XMLDB_TYPE_CHAR, '45', null, null, null, null);
        $table->add_field('timecreated', XMLDB_TYPE_INTEGER, '10', null, XMLDB_NOTNULL, null, null);

        // Adding keys to table paygw_helloasso_logs.
        $table->add_key('primary', XMLDB_KEY_PRIMARY, ['id']);
        $table->add_key('paymentid', XMLDB_KEY_FOREIGN, ['paymentid'], 'payments', ['id']);
        $table->add_key('userid', XMLDB_KEY_FOREIGN, ['userid'], 'user', ['id']);

        // Adding indexes to table paygw_helloasso_logs.
        $table->add_index('timecreated', XMLDB_INDEX_NOTUNIQUE, ['timecreated']);
        $table->add_index('status', XMLDB_INDEX_NOTUNIQUE, ['status']);

        // Conditionally launch create table for paygw_helloasso_logs.
        if (!$dbman->table_exists($table)) {
            $dbman->create_table($table);
        }

        // Helloasso savepoint reached.
        upgrade_plugin_savepoint(true, 2026010102, 'paygw', 'helloasso');
    }

    return true;
}
