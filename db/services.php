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

defined('MOODLE_INTERNAL') || die();

$functions = [
    'paygw_helloasso_get_config_for_js' => [
        'classname'   => 'paygw_helloasso\external\get_config_for_js',
        'methodname'  => 'execute',
        'description' => 'Get HelloAsso configuration for JavaScript',
        'type'        => 'read',
        'ajax'        => true,
        'loginrequired' => true,
    ],
];
