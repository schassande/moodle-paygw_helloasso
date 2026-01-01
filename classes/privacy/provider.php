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
 * Privacy Subsystem implementation for paygw_helloasso.
 *
 * @package    paygw_helloasso
 * @copyright  2025 Sebastien Chassande-Barrioz <chassande@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace paygw_helloasso\privacy;

use core_privacy\local\metadata\collection;
use core_privacy\local\request\approved_contextlist;
use core_privacy\local\request\approved_userlist;
use core_privacy\local\request\contextlist;
use core_privacy\local\request\userlist;
use core_privacy\local\request\writer;

/**
 * Privacy provider for the HelloAsso payment gateway plugin.
 *
 * @package    paygw_helloasso
 * @copyright  2025 Sebastien Chassande-Barrioz <chassande@gmail.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class provider implements
    \core_privacy\local\metadata\provider,
    \core_privacy\local\request\plugin\provider,
    \core_privacy\local\request\core_userlist_provider {

    /**
     * Returns meta data about this system.
     *
     * @param collection $collection The initialised collection to add items to.
     * @return collection A listing of user data stored through this system.
     */
    public static function get_metadata(collection $collection): collection {
        $collection->add_database_table(
            'paygw_helloasso_logs',
            [
                'paymentid' => 'privacy:metadata:paygw_helloasso_logs:paymentid',
                'userid' => 'privacy:metadata:paygw_helloasso_logs:userid',
                'action' => 'privacy:metadata:paygw_helloasso_logs:action',
                'status' => 'privacy:metadata:paygw_helloasso_logs:status',
                'amount' => 'privacy:metadata:paygw_helloasso_logs:amount',
                'reference' => 'privacy:metadata:paygw_helloasso_logs:reference',
                'message' => 'privacy:metadata:paygw_helloasso_logs:message',
                'response_code' => 'privacy:metadata:paygw_helloasso_logs:response_code',
                'ip_address' => 'privacy:metadata:paygw_helloasso_logs:ip_address',
                'timecreated' => 'privacy:metadata:paygw_helloasso_logs:timecreated',
            ],
            'privacy:metadata:paygw_helloasso_logs'
        );

        $collection->add_external_location_link(
            'helloasso',
            [
                'email' => 'privacy:metadata:helloasso:email',
                'firstname' => 'privacy:metadata:helloasso:firstname',
                'lastname' => 'privacy:metadata:helloasso:lastname',
                'amount' => 'privacy:metadata:helloasso:amount',
            ],
            'privacy:metadata:helloasso'
        );

        return $collection;
    }

    /**
     * Get the list of contexts that contain user information for the specified user.
     *
     * @param int $userid The user to search.
     * @return contextlist The contextlist containing the list of contexts used in this plugin.
     */
    public static function get_contexts_for_userid(int $userid): contextlist {
        $contextlist = new contextlist();

        // The HelloAsso payment gateway stores data at the system context level.
        $sql = "SELECT DISTINCT ctx.id
                  FROM {context} ctx
                  JOIN {paygw_helloasso_logs} phl ON phl.userid = :userid
                 WHERE ctx.contextlevel = :contextlevel";

        $params = [
            'userid' => $userid,
            'contextlevel' => CONTEXT_SYSTEM,
        ];

        $contextlist->add_from_sql($sql, $params);

        return $contextlist;
    }

    /**
     * Get the list of users who have data within a context.
     *
     * @param userlist $userlist The userlist containing the list of users who have data in this context/plugin combination.
     */
    public static function get_users_in_context(userlist $userlist) {
        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $sql = "SELECT userid
                  FROM {paygw_helloasso_logs}";

        $userlist->add_from_sql('userid', $sql, []);
    }

    /**
     * Export all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts to export information for.
     */
    public static function export_user_data(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }

            $logs = $DB->get_records('paygw_helloasso_logs', ['userid' => $user->id], 'timecreated DESC');

            if (!empty($logs)) {
                $data = [];
                foreach ($logs as $log) {
                    $data[] = (object) [
                        'paymentid' => $log->paymentid,
                        'action' => $log->action,
                        'status' => $log->status,
                        'amount' => $log->amount,
                        'reference' => $log->reference,
                        'message' => $log->message,
                        'response_code' => $log->response_code,
                        'ip_address' => $log->ip_address,
                        'timecreated' => transform::datetime($log->timecreated),
                    ];
                }

                writer::with_context($context)->export_data(
                    [get_string('privacy:metadata:paygw_helloasso_logs', 'paygw_helloasso')],
                    (object) ['logs' => $data]
                );
            }
        }
    }

    /**
     * Delete all data for all users in the specified context.
     *
     * @param \context $context The specific context to delete data for.
     */
    public static function delete_data_for_all_users_in_context(\context $context) {
        global $DB;

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $DB->delete_records('paygw_helloasso_logs');
    }

    /**
     * Delete all user data for the specified user, in the specified contexts.
     *
     * @param approved_contextlist $contextlist The approved contexts and user information to delete information for.
     */
    public static function delete_data_for_user(approved_contextlist $contextlist) {
        global $DB;

        $user = $contextlist->get_user();

        foreach ($contextlist->get_contexts() as $context) {
            if ($context->contextlevel != CONTEXT_SYSTEM) {
                continue;
            }

            $DB->delete_records('paygw_helloasso_logs', ['userid' => $user->id]);
        }
    }

    /**
     * Delete multiple users within a single context.
     *
     * @param approved_userlist $userlist The approved context and user information to delete information for.
     */
    public static function delete_data_for_users(approved_userlist $userlist) {
        global $DB;

        $context = $userlist->get_context();

        if ($context->contextlevel != CONTEXT_SYSTEM) {
            return;
        }

        $userids = $userlist->get_userids();

        if (!empty($userids)) {
            list($insql, $inparams) = $DB->get_in_or_equal($userids, SQL_PARAMS_NAMED);
            $DB->delete_records_select('paygw_helloasso_logs', "userid $insql", $inparams);
        }
    }
}
