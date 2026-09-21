<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/*
 * Cacti functions the plugin calls only when the running core provides them.
 *
 * The main bootstrap deliberately leaves these undefined so the fallback
 * branches are the ones exercised by default. Tests that need the other side
 * of a function_exists() check require this file in an isolated process.
 */

if (!function_exists('db_qstr_rlike')) {
	function db_qstr_rlike($s, $db_conn = false) {
		$s = (string) $s;

		if (strlen($s) > 255) {
			$s = substr($s, 0, 255);
		}

		$s = str_replace(["\0", '|', '{', '}'], '', $s);

		return 'RLIKE ' . db_qstr($s, $db_conn);
	}
}

if (!function_exists('get_total_row_data')) {
	function get_total_row_data($user_id, $sql, $sql_params = [], $class = '', $timeout = 86400) {
		CactiStubs::record('get_total_row_data', $sql, $sql_params);

		return CactiStubs::nextReturn('get_total_row_data', 0);
	}
}
