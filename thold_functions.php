<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2024 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

if (!defined('MESSAGE_LEVEL_NONE')) {
	define('MESSAGE_LEVEL_NONE',  0);
	define('MESSAGE_LEVEL_INFO',  1);
	define('MESSAGE_LEVEL_WARN',  2);
	define('MESSAGE_LEVEL_ERROR', 3);
	define('MESSAGE_LEVEL_CSRF',  4);
}

if (!defined('STAT_HI')) {
	define('STAT_HI',     2);
	define('STAT_LO',     1);
	define('STAT_NORMAL', 0);
}

if (!defined('THOLD_SEVERITY_NORMAL')) {
	define('THOLD_SEVERITY_NORMAL',   0);
	define('THOLD_SEVERITY_ALERT',    1);
	define('THOLD_SEVERITY_WARNING',  2);
	define('THOLD_SEVERITY_NOTICE',   3);
	define('THOLD_SEVERITY_ACKREQ',   4);
	define('THOLD_SEVERITY_DISABLED', 5);
	define('THOLD_SEVERITY_BASELINE', 6);
}

/**
 * sanitize_thold_sort_string - cleans up a search string submitted by the user to be passed
 * to the database. NOTE: some of the code for this function came from the phpBB project.
 *
 * @param $string - the original raw search string
 *
 * @return - the sanitized search string
 */
function sanitize_thold_sort_string($string) {
	static $drop_char_match = array('^', '$', '<', '>', '`', '\'', '"', '|', '?', '+', '[', ']', '{', '}', '#', ';', '!', '=', '*');
	static $drop_char_replace = array(' ', ' ', ' ', ' ', '', '', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ', ' ');

	/* Replace line endings by a space */
	$string = preg_replace('/[\n\r]/is', ' ', $string);

	/* HTML entities like &nbsp; */
	$string = preg_replace('/\b&[a-z]+;\b/', ' ', $string);

	/* Remove URL's */
	$string = preg_replace('/\b[a-z0-9]+:\/\/[a-z0-9\.\-]+(\/[a-z0-9\?\.%_\-\+=&\/]+)?/', ' ', $string);

	/* Filter out strange characters like ^, $, &, change "it's" to "its" */
	for($i = 0; $i < cacti_count($drop_char_match); $i++) {
		$string =  str_replace($drop_char_match[$i], $drop_char_replace[$i], $string);
	}

	return $string;
}

function get_time_since_last_event($thold) {
	$local_data_id = $thold['local_data_id'];

	if (empty($thold['instate']) || $thold['instate'] < 60) {
		return __esc('< 1 Minute', 'thold');
	} elseif (time() - $thold['instate'] < 1000) {
		return __esc('Since Created', 'thold');
	}

	switch($thold['thold_alert']) {
		case '0':
			return get_daysfromtime($thold['instate']);

			break;
		case '1':
		case '2':
			return get_daysfromtime($thold['instate']);

			break;
		default:
			return __('Never', 'thold');
	}
}

function thold_update_contacts() {
	$users = db_fetch_assoc("SELECT id, 'email' AS type, email_address
		FROM user_auth
		WHERE email_address != ''");

	if (cacti_sizeof($users)) {
		foreach ($users as $u) {
			$cid = db_fetch_cell_prepared('SELECT id
				FROM plugin_thold_contacts
				WHERE type="email"
				AND user_id = ?',
				array($u['id']));

			if ($cid) {
				db_execute_prepared('REPLACE INTO plugin_thold_contacts
					(id, user_id, type, data)
					VALUES (?, ?, "email", ?)',
					array($cid, $u['id'], $u['email_address']));
			} else {
				db_execute_prepared('REPLACE INTO plugin_thold_contacts
					(user_id, type, data)
					VALUES (?, "email", ?)',
					array($u['id'], $u['email_address']));
			}
		}
	}

	/* cleanup old accounts */
	db_execute('DELETE ptc
		FROM plugin_thold_contacts AS ptc
		LEFT JOIN user_auth AS ua
		ON ptc.user_id = ua.id
		WHERE ua.email_address = ""
		OR ua.id IS NULL');
}

function thold_tabs() {
	global $config;

	/* present a tabbed interface */
	$tabs = array(
		'thold'    => __('Thresholds', 'thold'),
		'log'      => __('Log', 'thold'),
		'hoststat' => __('Device Status', 'thold')
	);

	$tabs = api_plugin_hook_function('thold_graph_tabs', $tabs);

	get_filter_request_var('tab', FILTER_VALIDATE_REGEXP, array('options' => array('regexp' => '/^([a-zA-Z]+)$/')));

	load_current_session_value('tab', 'sess_thold_graph_tab', 'general');
	$current_tab = get_request_var('action');

	/* draw the tabs */
	print "<div class='tabs'><nav><ul>\n";

	if (cacti_sizeof($tabs)) {
		foreach (array_keys($tabs) as $tab_short_name) {
			print "<li><a class='tab" . (($tab_short_name == $current_tab) ? " selected'" : "'") .
				" href='" . html_escape($config['url_path'] .
				'plugins/thold/thold_graph.php?' .
				'action=' . $tab_short_name) .
				"'>" . $tabs[$tab_short_name] . "</a></li>\n";
		}
	}

	print "</ul></nav></div>\n";
}

function thold_debug($txt, $thread = '') {
	global $debug;

	if (read_config_option('thold_log_debug') == 'on' || $debug) {
		thold_cacti_log($txt, $thread, true);
	}
}

function thold_template_avail_devices($thold_template_id = 0) {
	/* display the host dropdown */
	if ($thold_template_id > 0) {
		$graph_templates = array_rekey(db_fetch_assoc_prepared('SELECT DISTINCT gt.id
			FROM graph_templates AS gt
			INNER JOIN graph_templates_item AS gti
			ON gt.id=gti.graph_template_id
			AND local_graph_id=0
			INNER JOIN data_template_rrd AS dtr
			ON dtr.id=task_item_id
			INNER JOIN thold_template AS tt
			ON tt.data_template_id=dtr.data_template_id
			INNER JOIN thold_data AS td
			ON td.data_template_id=dtr.data_template_id
			AND gt.id=td.graph_template_id
			AND tt.id = ?',
			array($thold_template_id)), 'id', 'id');
	} else {
		$graph_templates = array_rekey(db_fetch_assoc('SELECT DISTINCT gt.id
			FROM graph_templates AS gt
			INNER JOIN graph_templates_item AS gti
			ON gt.id=gti.graph_template_id
			AND local_graph_id=0
			INNER JOIN data_template_rrd AS dtr
			ON dtr.id=task_item_id
			INNER JOIN thold_template AS tt
			ON tt.data_template_id=dtr.data_template_id
			INNER JOIN thold_data AS td
			ON td.data_template_id=dtr.data_template_id
			AND gt.id=td.graph_template_id'), 'id', 'id');
	}

	// Limit the hosts to only hosts that either have a graph template
	// Listed as multiple, or do not have a threshold created
	// Using the Graph Template listed
	$device_ids = array();
	if (cacti_sizeof($graph_templates)) {
		$device_ids = array_rekey(db_fetch_assoc('SELECT DISTINCT rs.id
			FROM (
				SELECT h.id, gt.id AS gti, gt.multiple
				FROM host AS h,graph_templates AS gt
			) AS rs
			LEFT JOIN graph_local AS gl
			ON gl.graph_template_id=rs.gti
			AND gl.host_id=rs.id
			WHERE (gti IN(' . implode(', ', $graph_templates) . ')
			AND host_id IS NULL)
			OR rs.multiple = "on"'), 'id', 'id');
	}

	return (cacti_sizeof($device_ids) ? 'h.id IN (' . implode(', ', $device_ids) . ')':'');
}

function thold_initialize_rusage() {
	global $thold_start_rusage;

	if (function_exists('getrusage')) {
		$thold_start_rusage = getrusage();
	}

	$thold_start_rusage['microtime'] = microtime(true);
}

function thold_display_rusage() {
	global $thold_start_rusage;

	if (function_exists('getrusage')) {
		$dat = getrusage();

		html_start_box('', '100%', false, '3', 'left', '');
		print '<tr>';

		if (!isset($thold_start_rusage)) {
			print "<td colspan='10'>ERROR: Can not display RUSAGE please call thold_initialize_rusage first</td>";
		} else {
			$i_u_time = $thold_start_rusage['ru_utime.tv_sec'] + ($thold_start_rusage['ru_utime.tv_usec'] * 1E-6);
			$i_s_time = $thold_start_rusage['ru_stime.tv_sec'] + ($thold_start_rusage['ru_stime.tv_usec'] * 1E-6);
			$s_s      = $thold_start_rusage['ru_nswap'];
			$s_pf     = $thold_start_rusage['ru_majflt'];

			$start_time = $thold_start_rusage['microtime'];
			$end_time   = microtime(true);

			$utime    = ($dat['ru_utime.tv_sec'] + ($dat['ru_utime.tv_usec'] * 1E-6)) - $i_u_time;
			$stime    = ($dat['ru_stime.tv_sec'] + ($dat['ru_stime.tv_usec'] * 1E-6)) - $i_s_time;
			$swaps    = $dat['ru_nswap'] - $s_s;
			$pages    = $dat['ru_majflt'] - $s_pf;

			print "<td colspan='10' width='1%' style='text-align:left;'>";
			print '<b>' . __('Time:', 'thold') . '</b>&nbsp;'   . round($end_time - $start_time,2) . ' seconds, ';
			print '<b>' . __('User:', 'thold') . '</b>&nbsp;'   . round($utime,2) . ' seconds, ';
			print '<b>' . __('System:', 'thold') . '</b>&nbsp;' . round($stime,2) . ' seconds, ';
			print '<b>' . __('Swaps:', 'thold') . '</b>&nbsp;'  . ($swaps) . ' swaps, ';
			print '<b>' . __('Pages:', 'thold') . '</b>&nbsp;'  . ($pages) . ' pages';
			print '</td>';
		}

		print '</tr>';
		html_end_box(false);
	}

}

function thold_legend() {
	global $thold_states;

	html_start_box('', '100%', false, '3', 'center', '');

	print '<tr class="tableRow">';
	foreach ($thold_states as $index => $state) {
		print "<td class='" . $state['class'] . "'>" . $state['display'] . '</td>';
	}
	print '</tr>';

	html_end_box(false);
}

function host_legend() {
	global $thold_host_states;

	html_start_box('', '100%', false, '3', 'center', '');

	print '<tr class="tableRow">';
	foreach ($thold_host_states as $index => $state) {
		print "<td class='" . $state['class'] . "'>" . $state['display'] . '</td>';
	}
	print '</tr>';

	html_end_box(false);
}

function log_legend() {
	global $thold_log_states;

	html_start_box('', '100%', false, '3', 'center', '');

	print '<tr class="tableRow">';
	foreach ($thold_log_states as $index => $state) {
		print "<td class='" . $state['class'] . "'>" . $state['display_short'] . '</td>';
	}
	print '</tr>';

	html_end_box(false);
}

function thold_expression_rpn_pop(&$stack) {
	global $rpn_error;

	if (cacti_sizeof($stack)) {
		return array_pop($stack);
	} else {
		$rpn_error = true;
		return false;
	}
}

function thold_expression_math_rpn($operator, &$stack) {
	global $rpn_error;

	$orig_stack = $stack;

	switch($operator) {
	case '+':
	case '-':
	case '/':
	case '*':
	case '%':
	case '^':
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		$v3 = 'U';

		$rpn_evaled = false;

		if (!is_numeric($v1)) {
			cacti_log('ERROR: RPN value: v1 "' . $v1 . '" is Not valid for operator "' . $operator . '". Stack:"' . implode(',', $orig_stack) . '"', false, 'THOLD');
			$rpn_error = true;
		} elseif (!is_numeric($v2)) {
			cacti_log('ERROR: RPN value: v2 "' . $v2 . '" is Not valid for operator "' . $operator . '". Stack:"' . implode(',', $orig_stack) . '"', false, 'THOLD');
			$rpn_error = true;
		} elseif ($v1 == 0 && $v2 == 0 && $operator == '/') {
			$v3 = 0;
			$rpn_evaled = true;

			break;
		} elseif ($v1 == 0 &&  $operator == '/') {
			cacti_log('ERROR: RPN value: v1 can not be "0" when the operator is "/".  Stack:"' . implode(',', $orig_stack) . '"', false, 'THOLD');
			$rpn_error = true;
		}

		if ($rpn_evaled) {
			array_push($stack, $v3);
		} elseif (!$rpn_error) {
			eval("\$v3 = " . $v2 . ' ' . $operator . ' ' . $v1 . ';');

			if ($v3 == '') {
				$v3 = 0;
			}

			array_push($stack, $v3);
		}

		break;
	case 'SIN':
	case 'COS':
	case 'TAN':
	case 'ATAN':
	case 'SQRT':
	case 'FLOOR':
	case 'CEIL':
	case 'DEG2RAD':
	case 'RAD2DEG':
	case 'ABS':
	case 'EXP':
	case 'LOG':
		$v1 = thold_expression_rpn_pop($stack);

		if (!$rpn_error) {
			eval("\$v2 = " . $operator . '(' . $v1 . ');');
			array_push($stack, $v2);
		}
		break;
	case 'ATAN2':
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);

		if (!$rpn_error) {
			$v3 = atan2($v1, $v2);
			array_push($stack, $v3);
		}
		break;
	case 'ADDNAN':
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);

		if (!$rpn_error) {
			if ($v1 == 'NAN' || $v1 == 'U') $v1 = 0;
			if ($v2 == 'NAN' || $v2 == 'U') $v2 = 0;
			array_push($stack, $v1 + $v2);
		}
		break;
	}
}

function thold_expression_boolean_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'UN') {
		$v1 = thold_expression_rpn_pop($stack);
		if ($v1 == 'U' || $v1 == 'NAN') {
			array_push($stack, '1');
		} else {
			array_push($stack, '0');
		}
	} elseif ($operator == 'ISINF') {
		$v1 = thold_expression_rpn_pop($stack);
		if ($v1 == 'INF' || $v1 == 'NEGINF') {
			array_push($stack, '1');
		} else {
			array_push($stack, '0');
		}
	} elseif ($operator == 'AND') {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		if ($v1 > 0 && $v2 > 0) {
			array_push($stack, '1');
		} else {
			array_push($stack, '0');
		}
	} elseif ($operator == 'OR') {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		if ($v1 > 0 || $v2 > 0) {
			array_push($stack, '1');
		} else {
			array_push($stack, '0');
		}
	} elseif ($operator == 'IF') {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		$v3 = thold_expression_rpn_pop($stack);

		if ($v3 == 0) {
			array_push($stack, $v1);
		} else {
			array_push($stack, $v2);
		}
	} else {
		$v2 = thold_expression_rpn_pop($stack);
		$v1 = thold_expression_rpn_pop($stack);

		/* deal with unknown or infinite data */
		if (($v1 == 'INF' || $v2 == 'INF') ||
			($v1 == 'NAN' || $v2 == 'NAN') ||
			($v1 == 'U' || $v2 == 'U') ||
			($v1 == 'NEGINF' || $v2 == 'NEGINF')) {
			array_push($stack, '0');
		}

		switch($operator) {
		case 'LT':
			if ($v1 < $v2) {
				array_push($stack, '1');
			} else {
				array_push($stack, '0');
			}
			break;
		case 'GT':
			if ($v1 > $v2) {
				array_push($stack, '1');
			} else {
				array_push($stack, '0');
			}
			break;
		case 'LE':
			if ($v1 <= $v2) {
			array_push($stack, '1');
			} else {
				array_push($stack, '0');
			}
			break;
		case 'GE':
			if ($v1 >= $v2) {
				array_push($stack, '1');
			} else {
				array_push($stack, '0');
			}
			break;
		case 'EQ':
			if ($v1 == $v2) {
				array_push($stack, '1');
			} else {
				array_push($stack, '0');
			}
			break;
		case 'NE':
			if ($v1 != $v2) {
				array_push($stack, '1');
			} else {
				array_push($stack, '0');
			}
			break;
		}
	}
}

function thold_expression_compare_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'MAX' || $operator == 'MIN') {
		$v[0] = thold_expression_rpn_pop($stack);
		$v[1] = thold_expression_rpn_pop($stack);

		if (in_array('INF', $v)) {
			array_push($stack, 'INF');
		} elseif (in_array('NEGINF', $v)) {
			array_push($stack, 'NEGINF');
		} elseif (in_array('U', $v)) {
			array_push($stack, 'U');
		} elseif (in_array('NAN', $v)) {
			array_push($stack, 'NAN');
		} elseif ($operator == 'MAX') {
			array_push($stack, max($v));
		} else {
			array_push($stack, min($v));
		}
	} else {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		$v3 = thold_expression_rpn_pop($stack);

		if (($v1 == 'U' || $v1 == 'NAN') ||
			($v2 == 'U' || $v2 == 'NAN') ||
			($v3 == 'U' || $v3 == 'NAN')) {
			array_push($stack, 'U');
		} elseif (($v1 == 'INF' || $v1 == 'NEGINF') ||
			($v2 == 'INF' || $v2 == 'NEGINF') ||
			($v3 == 'INF' || $v3 == 'NEGINF')) {
			array_push($stack, 'U');
		} elseif ($v1 < $v2) {
			if ($v3 >= $v1 && $v3 <= $v2) {
				array_push($stack, $v3);
			} else {
				array_push($stack, 'U');
			}
		} else {
			if ($v3 >= $v2 && $v3 <= $v1) {
				array_push($stack, $v3);
			} else {
				array_push($stack, 'U');
			}
		}
	}
}

function thold_expression_specvals_rpn($operator, &$stack, $count) {
	global $rpn_error;

	if ($operator == 'UNKN') {
		array_push($stack, 'U');
	} elseif ($operator == 'INF') {
		array_push($stack, 'INF');
	} elseif ($operator == 'NEGINF') {
		array_push($stack, 'NEGINF');
	} elseif ($operator == 'COUNT') {
		array_push($stack, $count);
	} elseif ($operator == 'PREV') {
		/* still have to figure this out */
	}
}

function thold_expression_stackops_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'DUP') {
		$v1 = thold_expression_rpn_pop($stack);
		array_push($stack, $v1);
		array_push($stack, $v1);
	} elseif ($operator == 'POP') {
		thold_expression_rpn_pop($stack);
	} else {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		array_push($stack, $v2);
		array_push($stack, $v1);
	}
}

function thold_expression_time_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'NOW') {
		array_push($stack, time());
	} elseif ($operator == 'TIME') {
		/* still need to figure this one out */
	} elseif ($operator == 'LTIME') {
		/* still need to figure this one out */
	}
}

function thold_expression_setops_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'SORT') {
		$count = thold_expression_rpn_pop($stack);
		$v     = array();
		if ($count > 0) {
			for($i = 0; $i < $count; $i++) {
				$v[] = thold_expression_rpn_pop($stack);
			}

			sort($v, SORT_NUMERIC);

			foreach ($v as $val) {
				array_push($stack, $val);
			}
		}
	} elseif ($operator == 'REV') {
		$count = thold_expression_rpn_pop($stack);
		$v     = array();
		if ($count > 0) {
			for($i = 0; $i < $count; $i++) {
				$v[] = thold_expression_rpn_pop($stack);
			}

			$v = array_reverse($v);

			foreach ($v as $val) {
				array_push($stack, $val);
			}
		}
	} elseif ($operator == 'AVG') {
		$count = thold_expression_rpn_pop($stack);
		if ($count > 0) {
			$total  = 0;
			$inf    = false;
			$neginf = false;
			for($i = 0; $i < $count; $i++) {
				$v = thold_expression_rpn_pop($stack);
				if ($v == 'INF') {
					$inf = true;
				} elseif ($v == 'NEGINF') {
					$neginf = true;
				} else {
					$total += $v;
				}
			}

			if ($inf) {
				array_push($stack, 'INF');
			} elseif ($neginf) {
				array_push($stack, 'NEGINF');
			} else {
				array_push($stack, $total/$count);
			}
		}
	}
}

function thold_expression_ds_value($operator, &$stack, $data_sources) {
	global $rpn_error;

	if (cacti_sizeof($data_sources)) {
		foreach ($data_sources as $rrd_name => $value) {
			if (strtoupper($rrd_name) == $operator) {
				array_push($stack, $value);
				return;
			}
		}
	}

	array_push($stack, 0);
}

function thold_expression_specialtype_rpn($operator, &$stack, $local_data_id, $currentval) {
	switch ($operator) {
	case 'CURRENT_DATA_SOURCE':
		array_push($stack, $currentval);
		break;
	case 'CURRENT_GRAPH_MAXIMUM_VALUE':
		array_push(get_current_value($local_data_id, 'upper_limit'));
		break;
	case 'CURRENT_GRAPH_MINIMUM_VALUE':
		array_push(get_current_value($local_data_id, 'lower_limit'));
		break;
	case 'CURRENT_DS_MINIMUM_VALUE':
		array_push(get_current_value($local_data_id, 'rrd_minimum'));
		break;
	case 'CURRENT_DS_MAXIMUM_VALUE':
		array_push($stack, get_current_value($local_data_id, 'rrd_maximum'));
		break;
	case 'VALUE_OF_HDD_TOTAL':
		array_push($stack, get_current_value($local_data_id, 'hdd_total'));
		break;
	case 'ALL_DATA_SOURCES_NODUPS':
	case 'ALL_DATA_SOURCES_DUPS':
		$v1 = 0;

		$all_dsns = db_fetch_assoc_prepared('SELECT data_source_name
			FROM data_template_rrd
			WHERE local_data_id = ?',
			array($local_data_id));

		if (cacti_sizeof($all_dsns)) {
			foreach ($all_dsns as $dsn) {
				$v1 += get_current_value($local_data_id, $dsn['data_source_name']);
			}
		}

		array_push($stack, $v1);
		break;
	default:
		cacti_log('WARNING: CDEF property not implemented yet: ' . $operator, false, 'THOLD', POLLER_VERBOSITY_MEDIUM);
		array_push($stack, $currentval);
		break;
	}
}

function thold_get_currentval(&$thold_data, &$rrd_reindexed, &$rrd_time_reindexed, &$item, &$currenttime) {
	/* adjust the polling interval by the last read, if applicable */
	$currenttime = $rrd_time_reindexed[$thold_data['local_data_id']];
	if ($thold_data['lasttime'] > 0) {
		if (is_numeric($currenttime)) {
			$step = $currenttime - $thold_data['lasttime'];
		} else {
			$step = $thold_data['rrd_step'];
		}
	} else {
		$step = $thold_data['rrd_step'];
	}

	if (empty($step)) {
		$step = read_config_option('poller_interval');
	}

	$currentval = '';

	if (isset($rrd_reindexed[$thold_data['local_data_id']])) {
		$item = $rrd_reindexed[$thold_data['local_data_id']];
		if (isset($item[$thold_data['name']]) && is_numeric($item[$thold_data['name']])) {
			switch ($thold_data['data_source_type_id']) {
			case 2:	// COUNTER
				if ($thold_data['oldvalue'] != 0 && is_numeric($thold_data['oldvalue'])) {
					if ($item[$thold_data['name']] >= $thold_data['oldvalue']) {
						// Everything is Normal
						$currentval = $item[$thold_data['name']] - $thold_data['oldvalue'];
					} else {
						// Possible overflow, see if its 32bit or 64bit
						if ($thold_data['oldvalue'] > 4294967295) {
							$currentval = (18446744073709551615 - $thold_data['oldvalue']) + $item[$thold_data['name']];
						} else {
							$currentval = (4294967295 - $thold_data['oldvalue']) + $item[$thold_data['name']];
						}
					}

					if (strpos($thold_data['rrd_maximum'], '|query_') !== false) {
						$data_local = db_fetch_row_prepared('SELECT *
							FROM data_local
							WHERE id = ?',
							array($thold_data['local_data_id']));

						if ($thold_data['rrd_maximum'] == '|query_ifSpeed|' || $thold_data['rrd_maximum'] == '|query_ifHighSpeed|') {
							$highSpeed = db_fetch_cell_prepared("SELECT field_value
								FROM host_snmp_cache
								WHERE host_id = ?
								AND snmp_query_id = ?
								AND snmp_index = ?
								AND field_name = 'ifHighSpeed'",
								array($data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']));

							if (!empty($highSpeed)) {
								$thold_data['rrd_maximum'] = $highSpeed * 1000000;
							} else {
								$thold_data['rrd_maximum'] = substitute_snmp_query_data('|query_ifSpeed|', $data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']);
							}
						} else {
							$thold_data['rrd_maximum'] = substitute_snmp_query_data($thold_data['rrd_maximum'], $data_local['host_id'], $data_local['snmp_query_id'], $data_local['snmp_index']);
						}
					}

					/* assume counter reset if greater than max value */
					if ($thold_data['rrd_maximum'] > 0 && ($currentval / $step) > $thold_data['rrd_maximum']) {
						$currentval = $item[$thold_data['name']] / $step;
					} elseif ($thold_data['rrd_maximum'] == 0 && $currentval > 4.25E+9) {
						$currentval = $item[$thold_data['name']] / $step;
					} else {
						$currentval = $currentval / $step;
					}
				} else {
					$currentval = 0;
				}
				break;
			case 3:	// DERIVE
				$currentval = ($item[$thold_data['name']] - $thold_data['oldvalue']) / $step;
				break;
			case 4:	// ABSOLUTE
				$currentval = $item[$thold_data['name']] / $step;
				break;
			case 1:	// GAUGE
			default:
				$currentval = $item[$thold_data['name']];
				break;
			}
		}
	}

	return $currentval;
}

function thold_calculate_expression($thold, $currentval, &$rrd_reindexed, &$rrd_time_reindexed) {
	global $rpn_error;

	/* set an rpn error flag */
	$rpn_error = false;

	/* operators to support */
	$math       = array('+', '-', '*', '/', '%', '^', 'ADDNAN', 'SIN', 'COS', 'LOG', 'EXP',
		'SQRT', 'ATAN', 'ATAN2', 'FLOOR', 'CEIL', 'DEG2RAD', 'RAD2DEG', 'ABS');
	$boolean    = array('LT', 'LE', 'GT', 'GE', 'EQ', 'NE', 'UN', 'ISNF', 'IF', 'AND', 'OR');
	$comparison = array('MIN', 'MAX', 'LIMIT');
	$setops     = array('SORT', 'REV', 'AVG');
	$specvals   = array('UNKN', 'INF', 'NEGINF', 'PREV', 'COUNT');
	$stackops   = array('DUP', 'POP', 'EXC');
	$time       = array('NOW', 'TIME', 'LTIME');
	$spectypes  = array('CURRENT_DATA_SOURCE','CURRENT_GRAPH_MINIMUM_VALUE',
		'CURRENT_GRAPH_MINIMUM_VALUE','CURRENT_DS_MINIMUM_VALUE',
		'CURRENT_DS_MAXIMUM_VALUE','VALUE_OF_HDD_TOTAL',
		'ALL_DATA_SOURCES_NODUPS','ALL_DATA_SOURCES_DUPS');

	/* our expression array */
	$expression = explode(',', $thold['expression']);

	/* out current data sources */
	$data_sources = $rrd_reindexed[$thold['local_data_id']];

	if (cacti_sizeof($data_sources)) {
		foreach ($data_sources as $key => $value) {
			$nds[$key] = $value;
		}

		$data_sources = $nds;
	}

	/* replace all data tabs in the rpn with values */
	if (cacti_sizeof($expression)) {
		foreach ($expression as $key => $item) {
			if (strpos($item, '|ds:') !== false) {
				// Remove invalid characters
				$item = str_replace('\\', '', $item);

				$dsname = trim(str_replace('|ds:', '', $item), " |\n\r");

				$thold_item = db_fetch_row_prepared('SELECT td.id, td.local_graph_id,
					td.percent_ds, td.expression, td.upper_ds, td.data_type,
					td.host_id, td.cdef, td.local_data_id,
					td.data_template_rrd_id, td.lastread, UNIX_TIMESTAMP(td.lasttime) AS lasttime,
					td.oldvalue, dtr.data_source_name as name,
					dtr.data_source_type_id, dtd.rrd_step, dtr.rrd_maximum
					FROM thold_data AS td
					LEFT JOIN data_template_rrd AS dtr
					ON dtr.id = td.data_template_rrd_id
					LEFT JOIN data_template_data AS dtd
					ON dtd.local_data_id=td.local_data_id
					WHERE dtr.data_source_name = ?
					AND td.local_data_id = ?',
					array($dsname, $thold['local_data_id']));

				$value = '';
				if (cacti_sizeof($thold_item)) {
					$item = array();
					$currenttime = 0;
					$value = thold_get_currentval($thold_item, $rrd_reindexed, $rrd_time_reindexed, $item, $currenttime);
				}

				/*  Previous returns 'U' after device recovers.  Try alternate */
				if (empty($value) || $value == 'U') {
					if (read_config_option('dsstats_enable') == 'on') {
						$value = db_fetch_cell_prepared('SELECT calculated
							FROM data_source_stats_hourly_last
							WHERE local_data_id = ?
							AND rrd_name = ?',
							array($thold['local_data_id'], $dsname));
					}

					if (empty($value) || $value = 'U' || $value == '-90909090909') {
						$value = get_current_value($thold['local_data_id'], $dsname);
					}
				}

				$expression[$key] = $value;

				if ($expression[$key] == '') $expression[$key] = '0';
			} elseif (strpos($item, '|') !== false) {
				// Remove invalid characters
				$item = str_replace('\\', '', $item);

				$gl = db_fetch_row_prepared('SELECT *
					FROM graph_local
					WHERE id = ?',
					array($thold['local_graph_id']));

				if (cacti_sizeof($gl)) {
					$expression[$key] = thold_expand_string($thold, $item);
				} else {
					$expression[$key] = '0';
					cacti_log("WARNING: Query Replacement for '$item' Does Not Exist");
				}

				if ($expression[$key] == '') $expression[$key] = '0';
			} else {
				/* normal operator */
			}
		}
	}

	$processed_expression = $expression;

	//cacti_log(implode(',', array_keys($data_sources)));
	//cacti_log(implode(',', $data_sources));
	//cacti_log(implode(',', $expression));

	/* now let's process the RPN stack */
	$x = count($expression);

	if ($x == 0) {
		return $currentval;
	}

	/* operation stack for RPN */
	$stack = array();

	/* current pointer in the RPN operations list */
	$cursor = 0;

	while($cursor < $x) {
		$operator = strtoupper(trim($expression[$cursor]));

		if (stripos($operator, '|query_ifHighSpeed') !== false || stripos($operator, '|query_ifSpeed') !== false) {
			$data_local = db_fetch_row_prepared('SELECT *
				FROM data_local
				WHERE id = ?',
				array($thold['local_data_id']));

			$operator = rrdtool_function_interface_speed($data_local);
		}

		/* is the operator a data source */
		if (is_numeric($operator)) {
			//cacti_log("NOTE: Numeric '$operator'", false, "THOLD");
			array_push($stack, $operator);
		} elseif (array_key_exists($operator, $data_sources)) {
			//cacti_log("NOTE: DS Value '$operator'", false, "THOLD");
			thold_expression_ds_value($operator, $stack, $data_sources);
		} elseif (in_array($operator, $comparison)) {
			//cacti_log("NOTE: Compare '$operator'", false, "THOLD");
			thold_expression_compare_rpn($operator, $stack);
		} elseif (in_array($operator, $boolean)) {
			//cacti_log("NOTE: Boolean '$operator'", false, "THOLD");
			thold_expression_boolean_rpn($operator, $stack);
		} elseif (in_array($operator, $math)) {
			//cacti_log("NOTE: Math '$operator'", false, "THOLD");
			thold_expression_math_rpn($operator, $stack);
		} elseif (in_array($operator, $setops)) {
			//cacti_log("NOTE: SetOps '$operator'", false, "THOLD");
			thold_expression_setops_rpn($operator, $stack);
		} elseif (in_array($operator, $specvals)) {
			//cacti_log("NOTE: SpecVals '$operator'", false, "THOLD");
			thold_expression_specvals_rpn($operator, $stack, $cursor + 2);
		} elseif (in_array($operator, $stackops)) {
			//cacti_log("NOTE: StackOps '$operator'", false, "THOLD");
			thold_expression_stackops_rpn($operator, $stack);
		} elseif (in_array($operator, $time)) {
			//cacti_log("NOTE: Time '$operator'", false, "THOLD");
			thold_expression_time_rpn($operator, $stack);
		} elseif (in_array($operator, $spectypes)) {
			//cacti_log("NOTE: SpecialTypes '$operator'", false, "THOLD");
			thold_expression_specialtype_rpn($operator, $stack, $thold['local_data_id'], $currentval);
		} else {
			cacti_log("WARNING: Unsupported Field '$operator'", false, 'THOLD');
			$rpn_error = true;
		}

		$cursor++;

		if ($rpn_error) {
			cacti_log("ERROR: RPN Expression is invalid! THold:'" . $thold['name'] . "', Value:'" . $currentval . "', Expression:'" . $thold['expression'] . "', Processed:'" . implode(',', $processed_expression) . "'", false, 'THOLD');
			return 0;
		}
	}

	return $stack[0];
}

function thold_substitute_snmp_query_data($string, $device_id, $snmp_query_id, $snmp_index, $max_chars = 0) {
	$field_name = trim(str_replace('|query_', '', $string),"| \n\r");

	$snmp_cache_data = db_fetch_cell_prepared("SELECT field_value
		FROM host_snmp_cache
		WHERE host_id = ?
		AND snmp_query_id = ?
		AND snmp_index = ?
		AND field_name= ?",
		array($device_id, $snmp_query_id, $snmp_index, $field_name));

	if ($snmp_cache_data != '') {
		return $snmp_cache_data;
	} else {
		return $string;
	}
}

function thold_substitute_data_source_description($string, $local_data_id, $max_chars = 0) {
	$field_name = trim(str_replace('|data_source_description', '', $string),"| \n\r");

	$cache_data = db_fetch_cell_prepared('SELECT name_cache
		FROM data_template_data
		WHERE local_data_id = ?
		LIMIT 1',
		array($local_data_id));

	if ($cache_data != '') {
		return $cache_data;
	} else {
		return $string;
	}
}

function thold_substitute_host_data($string, $l_escape_string, $r_escape_string, $device_id) {
	$field_name = trim(str_replace('|host_', '', $string),"| \n\r");

	if (!isset($_SESSION['sess_host_cache_array'][$device_id])) {
		$host = db_fetch_row_prepared('SELECT *
			FROM host WHERE id = ?',
			array($device_id));

		$_SESSION['sess_host_cache_array'][$device_id] = $host;
	}

	if (isset($_SESSION['sess_host_cache_array'][$device_id][$field_name])) {
		return $_SESSION['sess_host_cache_array'][$device_id][$field_name];
	}

	$string = str_replace($l_escape_string . 'host_management_ip' . $r_escape_string, $_SESSION['sess_host_cache_array'][$device_id]['hostname'], $string);
	$temp = api_plugin_hook_function('substitute_host_data', array('string' => $string, 'l_escape_string' => $l_escape_string, 'r_escape_string' => $r_escape_string, 'host_id' => $device_id));
	$string = $temp['string'];

	return $string;
}

/**
 * thold_substitute_custom_data - takes a string and substitutes all custom data variables contained in it
 *
 * @param $string - the string to make custom data variable substitutions on
 * @param $l_escape_string - the character used to escape each variable on the left side
 * @param $r_escape_string - the character used to escape each variable on the right side
 * @param $local_data_id - (int) the local_data_id to match
 *
 * @return - the original string with all of the variable substitutions made
 */
function thold_substitute_custom_data($string, $l_escape, $r_escape, $local_data_id) {
	if (is_array($local_data_id)) {
		$local_data_ids = $local_data_id;
	} elseif ($local_data_id == '') {
		return;
	} else {
		$local_data_ids = array($local_data_id);
	}

	$match       = $l_escape . 'custom_';
	$matches     = array();
	$inclause    = array();
	$query_array = array();
	$cur_pos     = 0;

	while(true) {
		$start_pos = strpos($string, $match, $cur_pos);

		if ($start_pos !== false) {
			$cur_pos = strpos($string, $r_escape, $start_pos + 1);
			$cfield  = substr($string, $start_pos, $cur_pos - $start_pos + 1);
			$cfield  = trim(str_replace($match, '', $cfield), " \n\r\t$r_escape");
			$matches[]  = $cfield;
			$inclause[] = '?';
		} else {
			break;
		}
	}

	if (cacti_sizeof($matches)) {
	    $ids = array_rekey(
			db_fetch_assoc('SELECT id
				FROM data_template_data
				WHERE local_data_id IN (' . implode(', ', $local_data_ids) . ')'),
			'id', 'id'
		);

		if (cacti_sizeof($ids)) {
			$data_template_data_ids = implode(', ', $ids);

			foreach($matches as $match) {
				$query_array[] = $match;
			}

			$custom_data_array = db_fetch_assoc_prepared('SELECT
				dif.data_name AS name, did.value
				FROM data_input_fields AS dif
				INNER JOIN data_input_data AS did
				ON did.data_input_field_id = dif.id
				WHERE did.data_template_data_id IN (' . $data_template_data_ids . ')
				AND data_name IN (' . implode(', ', $inclause) . ')',
				$query_array);

			if (cacti_sizeof($custom_data_array)) {
				foreach($custom_data_array as $custom_data) {
					$custom_name  = $custom_data['name'];
					$custom_value = $custom_data['value'];
					$string = str_replace($l_escape . 'custom_' . $custom_name . $r_escape, $custom_value, $string);
				}
			}
		}
	}

	return $string;
}

function thold_calculate_percent($thold, $currentval, $rrd_reindexed) {
	$ds = $thold['percent_ds'];

	if (!is_numeric($currentval)) {
		return '';
	}

	if (isset($rrd_reindexed[$thold['local_data_id']][$ds])) {
		$t = (int) $rrd_reindexed[$thold['local_data_id']][$thold['percent_ds']];

		if ($t > 0) {
			$currentval = ($currentval / $t) * 100;
		} else {
			$currentval = 0;
		}
	} else {
		$currentval = '';
	}

	return $currentval;
}

function thold_calculate_lower_upper($thold, $currentval, $rrd_reindexed) {
	$ds = $thold['upper_ds'];

	if (isset($rrd_reindexed[$thold['local_data_id']][$ds])) {
		$t = $rrd_reindexed[$thold['local_data_id']][$thold['upper_ds']];
		$currentval = ($t << 32) + $currentval;
	}

	return $currentval;
}

function get_allowed_thresholds($sql_where = '', $order_by = 'td.name', $sql_limit = '', &$total_rows = 0, $user_id = 0, $graph_id = 0) {
	if ($sql_limit != '') {
		$sql_limit = "LIMIT $sql_limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if ($graph_id > 0) {
		$sql_where .= (strlen($sql_where) ? ' AND ':' ') . " gl.id=$graph_id";
	}

	if (strlen($sql_where)) {
		$sql_where = "WHERE $sql_where";
	}

	if ($user_id == -1) {
		$auth_method = 0;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method > 0 && $user_id == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$user_id = $_SESSION['sess_user_id'];
		} else {
			return array();
		}
	}

	/* see if permissions are simple */
	$simple_perms = get_simple_graph_perms($user_id);

	/* in case we need to review get the graph_auth_method */
	$graph_auth_method = read_config_option('graph_auth_method');

	/* get policies for all groups and user */
	$policies = get_policies($user_id);

	if (!$simple_perms && $auth_method != 0) {
		$sql_where = get_policy_where($graph_auth_method, $policies, $sql_where);
	}

	$tholds_sql = ("SELECT
		td.*, dtd.rrd_step, tt.name AS template_name, dtr.data_source_name AS data_source,
		IF(IFNULL(td.`lastread`,'') = '',NULL,(td.`lastread` + 0.0)) AS `flastread`,
		IF(IFNULL(td.`oldvalue`,'') = '',NULL,(td.`oldvalue` + 0.0)) AS `foldvalue`,
		UNIX_TIMESTAMP() - UNIX_TIMESTAMP(lastchanged) AS `instate`
		FROM thold_data AS td
		INNER JOIN graph_local AS gl
		ON gl.id = td.local_graph_id
		LEFT JOIN graph_templates AS gt
		ON gt.id = gl.graph_template_id
		LEFT JOIN host AS h
		ON h.id = gl.host_id
		LEFT JOIN thold_template AS tt
		ON tt.id = td.thold_template_id
		LEFT JOIN data_template_data AS dtd
		ON dtd.local_data_id = td.local_data_id
		LEFT JOIN data_template_rrd AS dtr
		ON dtr.id = td.data_template_rrd_id
		$sql_where
		$order_by
		$sql_limit");

	$tholds = db_fetch_assoc($tholds_sql);

	$sql = "SELECT COUNT(*)
		FROM (
			SELECT td.id
			FROM thold_data AS td
			INNER JOIN graph_local AS gl
			ON gl.id=td.local_graph_id
			LEFT JOIN graph_templates AS gt
			ON gt.id=gl.graph_template_id
			LEFT JOIN host AS h
			ON h.id=gl.host_id
			LEFT JOIN thold_template AS tt
			ON tt.id=td.thold_template_id
			$sql_where
		) AS rower";

	if (function_exists('get_total_row_data') && $graph_id == 0) {
		$total_rows = get_total_row_data($user_id, $sql, array(), 'thold');
	} else {
		$total_rows = db_fetch_cell($sql);
	}

	return $tholds;
}

function get_allowed_threshold_logs($sql_where = '', $order_by = 'td.name', $sql_limit = '', &$total_rows = 0, $user_id = 0, $graph_id = 0) {
	if ($sql_limit != '') {
		$sql_limit = "LIMIT $sql_limit";
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if ($graph_id > 0) {
		$sql_where .= (strlen($sql_where) ? ' AND ':' ') . " gl.id = $graph_id";
	}

	if (strlen($sql_where)) {
		$sql_where = "WHERE $sql_where";
	}

	if ($user_id == -1) {
		$auth_method = 0;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($auth_method > 0 && $user_id == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$user_id = $_SESSION['sess_user_id'];
		} else {
			return array();
		}
	}

	/* see if permissions are simple */
	$simple_perms = get_simple_graph_perms($user_id);

	/* in case we need to review get the graph_auth_method */
	$graph_auth_method = read_config_option('graph_auth_method');

	/* get policies for all groups and user */
	$policies = get_policies($user_id);

	if (!$simple_perms && $auth_method != 0) {
		$sql_where = get_policy_where($graph_auth_method, $policies, $sql_where);
	}

	$tholds = db_fetch_assoc("SELECT
		tl.`id`, tl.`time`, tl.`host_id`, tl.`local_graph_id`, tl.`threshold_id`,
		IF(IFNULL(tl.`threshold_value`,'')='',NULL,(tl.`threshold_value` + 0.0)) AS `threshold_value`,
		IF(IFNULL(tl.`current`,'')='',NULL,(tl.`current` + 0.0)) AS `current`, tl.`status`, tl.`type`,
		tl.`description`, h.description AS hdescription, td.name, gtg.title_cache
		FROM plugin_thold_log AS tl
		INNER JOIN thold_data AS td
		ON tl.threshold_id=td.id
		INNER JOIN graph_local AS gl
		ON gl.id=td.local_graph_id
		LEFT JOIN graph_templates AS gt
		ON gt.id=gl.graph_template_id
		LEFT JOIN graph_templates_graph AS gtg
		ON gtg.local_graph_id=gl.id
		LEFT JOIN host AS h
		ON h.id=gl.host_id
		$sql_where
		$order_by
		$sql_limit");

	$sql = "SELECT COUNT(*)
		FROM (
			SELECT tl.id
			FROM plugin_thold_log AS tl
			INNER JOIN thold_data AS td
			ON tl.threshold_id=td.id
			INNER JOIN graph_local AS gl
			ON gl.id=td.local_graph_id
			LEFT JOIN graph_templates AS gt
			ON gt.id=gl.graph_template_id
			LEFT JOIN graph_templates_graph AS gtg
			ON gtg.local_graph_id=gl.id
			LEFT JOIN host AS h
			ON h.id=gl.host_id
			$sql_where
		) AS rower";

	if (function_exists('get_total_row_data') && $graph_id == 0) {
		$total_rows = get_total_row_data($user_id, $sql, array(), 'thold_log');
	} else {
		$total_rows = db_fetch_cell($sql);
	}

	return $tholds;
}

function thold_get_thold_notification_format_file($thold_id, $notify_list) {
	$thold_format_file = db_fetch_cell_prepared('SELECT format_file
		FROM thold_data
		WHERE id = ?',
		array($thold_id));

	$notify_format_file = '';

	if ($notify_list > 0) {
		$notify_format_file = db_fetch_cell_prepared('SELECT format_file
			FROM plugin_notification_lists
			WHERE id = ?',
			array($notify_list));
	}

	if ($notify_format_file != '') {
		return $notify_format_file;
	} elseif ($thold_format_file != '') {
		return $thold_format_file;
	} else {
		return '';
	}
}

function thold_get_device_notification_format_file($host_id, $notify_list) {
	$device_format_file = '';

	if ($notify_list > 0) {
		$notify_format_file = db_fetch_cell_prepared('SELECT format_file
			FROM plugin_notify_list
			WHERE id = ?',
			array($notify_list));
	}

	if ($notify_format_file != '') {
		return $notify_format_file;
	} elseif ($device_format_file != '') {
		return $device_format_file;
	} else {
		return '';
	}
}

function thold_get_state_filter($state) {
	$statefilter = '';

	if ($state != '') {
		if (get_request_var('state') == '-1') { // All
			$statefilter = '';
		} elseif (get_request_var('state') == '0') { // Breached or Triggered
			$statefilter = '((td.thold_enabled = "on" AND td.thold_per_enabled = "on") AND
				(td.thold_alert != 0 OR td.bl_alert > 0 OR ((td.thold_alert != 0 AND td.thold_fail_count >= td.thold_fail_trigger) OR (td.bl_alert > 0 AND td.bl_fail_count >= td.bl_fail_trigger))))';
		} elseif (get_request_var('state') == '7') { // Disabled at Template
			$statefilter = '(td.thold_enabled = "off")';
		} elseif (get_request_var('state') == '2') { // Enabled
			$statefilter = '(td.thold_enabled = "on" AND td.thold_per_enabled = "on")';
		} elseif (get_request_var('state') == '1') { // Breached
			$statefilter = '((td.thold_enabled = "on" AND td.thold_per_enabled = "on") AND (td.thold_alert != 0 OR td.bl_alert > 0))';
		} elseif (get_request_var('state') == '3') { // Triggered
			$statefilter = '((td.thold_enabled = "on" AND td.thold_per_enabled = "on") AND ((td.thold_alert != 0 AND td.thold_fail_count >= td.thold_fail_trigger) OR (td.bl_alert > 0 AND td.bl_fail_count >= td.bl_fail_trigger)))';
		} elseif (get_request_var('state') == '4') { // Ack Required
			$statefilter = '(td.acknowledgment = "on")';
		} elseif (get_request_var('state') == '5') { // Disabled at Thold
			$statefilter = '(td.thold_per_enabled = "")';
		} elseif (get_request_var('state') == '6') { // Disabled all together
			$statefilter = '(td.thold_per_enabled = "" OR td.thold_enabled = "off")';
		}
	}

	return $statefilter;
}

function is_thold_allowed_graph($local_graph_id) {
	return is_graph_allowed($local_graph_id);
}

function is_thold_allowed($id) {
	$local_graph_id = db_fetch_cell_prepared('SELECT local_graph_id
		FROM thold_data
		WHERE id = ?',
		array($id));

	return is_thold_allowed_graph($local_graph_id);
}

function thold_log($save) {
	global $config;

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');

	if ($save['current'] == null) {
		$save['current'] = '';
	}

	$save['id'] = 0;
	if (read_config_option('thold_log_cacti') == 'on') {
		$thold = db_fetch_row_prepared('SELECT *
			FROM thold_data
			WHERE id = ?',
			array($save['threshold_id']));

		$dt = db_fetch_cell_prepared('SELECT data_template_id
			FROM data_template_data
			WHERE local_data_id = ?',
			array($thold['local_data_id']));

		$tname = db_fetch_cell_prepared('SELECT name
			FROM data_template
			WHERE id = ?',
			array($dt));

		if ($save['type'] != 99) {
			if ($save['status'] == 0) {
				$desc = 'Threshold Restored  ID: ' . $save['threshold_id'];
			} else {
				$desc = 'Threshold Breached  ID: ' . $save['threshold_id'];
			}

			$desc .= '  DataTemplate: ' . $tname;
			$desc .= '  DataSource: ' . $thold['data_source_name'];

			if (isset($thold_types[$thold['thold_type']])) {
				$desc .= '  Type: ' . $thold_types[$thold['thold_type']];
			}

			$desc .= '  Enabled: ' . ($thold['thold_enabled'] == 'on' && $thold['thold_per_enabled'] == 'on' ? 'on':'off');
			switch ($thold['thold_type']) {
			case 0:
				$desc .= '  Current: ' . $save['current'];
				$desc .= '  High: ' . $thold['thold_hi'];
				$desc .= '  Low: ' . $thold['thold_low'];
				$desc .= '  Trigger: ' . plugin_thold_duration_convert($thold['local_data_id'], $thold['thold_fail_trigger'], 'alert');
				$desc .= '  Warning High: ' . $thold['thold_warning_hi'];
				$desc .= '  Warning Low: ' . $thold['thold_warning_low'];
				$desc .= '  Warning Trigger: ' . plugin_thold_duration_convert($thold['local_data_id'], $thold['thold_warning_fail_trigger'], 'alert');
				break;
			case 1:
				$desc .= '  Current: ' . $save['current'];
				break;
			case 2:
				$desc .= '  Current: ' . $save['current'];
				$desc .= '  High: ' . $thold['time_hi'];
				$desc .= '  Low: ' . $thold['time_low'];
				$desc .= '  Trigger: ' . $thold['time_fail_trigger'];
				$desc .= '  Time: ' . plugin_thold_duration_convert($thold['local_data_id'], $thold['time_fail_length'], 'time');
				$desc .= '  Warning High: ' . $thold['time_warning_hi'];
				$desc .= '  Warning Low: ' . $thold['time_warning_low'];
				$desc .= '  Warning Trigger: ' . $thold['time_warning_fail_trigger'];
				$desc .= '  Warning Time: ' . plugin_thold_duration_convert($thold['local_data_id'], $thold['time_warning_fail_length'], 'time');
				break;
			}

			$desc .= ' SentTo: ' . (isset($save['emails']) && $save['emails'] != '' ? $save['emails']:'None');
			$desc .= ' SendBCC: ' . (isset($save['bcc_emails']) && $save['bcc_emails'] != '' ? $save['bcc_emails']:'None');
		} elseif (isset($save['description'])) {
			$desc = $save['description'];
		} else {
			$desc = 'Threshold Acknowledgment';
		}

		if ($save['status'] == ST_RESTORAL || $save['status'] == ST_NOTIFYRS) {
			thold_cacti_log($desc);
		}
	}

	unset($save['emails']);
	unset($save['bcc_emails']);

	$id = sql_save($save, 'plugin_thold_log');

	set_config_option('time_last_change_thold_log', time());
	set_config_option('time_last_change_thold', time());
}

function plugin_thold_duration_convert($rra, $data, $type, $field = 'local_data_id') {
	global $config, $repeatarray, $alertarray, $timearray;

	/* handle a null data value */
	if ($data == '') {
		return '';
	}

	$step = db_fetch_cell_prepared("SELECT rrd_step
		FROM data_template_data
		WHERE $field = ?",
		array($rra));

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');

	switch ($type) {
	case 'repeat':
		return (isset($repeatarray[$data]) ? $repeatarray[$data] : $data);

		break;
	case 'alert':
		return (isset($alertarray[$data]) ? $alertarray[$data] : $data);

		break;
	case 'time':
		return (isset($timearray[$data]) ? $timearray[$data] : $data);

		break;
	}

	return $data;
}

function plugin_thold_log_changes($id, $changed, $message = array()) {
	global $config;

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');

	$desc = '';

	if (read_config_option('thold_log_changes') != 'on') {
		return;
	}

	if (!$config['is_web']) {
		$user = 'poller';
	} elseif (isset($_SESSION['sess_user_id'])) {
		$user = $_SESSION['sess_user_id'];
	} else {
		$user = 'unknown';
	}

	switch ($changed) {
	case 'acknowledge_threshold':
		$thold = db_fetch_row_prepared('SELECT *
			FROM thold_data
			WHERE id = ?',
			array($id));

		$tname = db_fetch_cell_prepared('SELECT name
			FROM data_template
			WHERE id = ?',
			array($thold['data_template_id']));

		$desc  = "Threshold Acknowledged by User[$user] TH[$id]";

		$desc .= ' DataTemplate[' . $tname . ']';
		$desc .= ' DataSource[' . $thold['data_source_name'] . ']';

		break;
	case 'enabled_threshold':
		$thold = db_fetch_row_prepared('SELECT *
			FROM thold_data
			WHERE id = ?',
			array($id));

		$tname = db_fetch_cell_prepared('SELECT name
			FROM data_template
			WHERE id = ?',
			array($thold['data_template_id']));

		$desc  = "Threshold Enabled by User[$user] TH[$id]";

		$desc .= ' DataTemplate[' . $tname . ']';
		$desc .= ' DataSource[' . $thold['data_source_name'] . ']';

		break;
	case 'disabled_threshold':
		$thold = db_fetch_row_prepared('SELECT *
			FROM thold_data
			WHERE id = ?',
			array($id));

		$tname = db_fetch_cell_prepared('SELECT name
			FROM data_template
			WHERE id = ?',
			array($thold['data_template_id']));

		$desc  = "Threshold Disabled by User[$user] TH[$id]";

		$desc .= ' DataTemplate[' . $tname . ']';
		$desc .= ' DataSource[' . $thold['data_source_name'] . ']';

		break;
	case 'reapply_name':
		$thold = db_fetch_row_prepared('SELECT *
			FROM thold_data
			WHERE id = ?',
			array($id));

		$tname = db_fetch_cell_prepared('SELECT name
			FROM data_template
			WHERE id = ?',
			array($thold['data_template_id']));

		$desc  = "Threshold Reapply Suggested Name by User[$user] TH[$id]";

		$desc .= ' DataTemplate[' . $tname . ']';
		$desc .= ' DataSource[' . $thold['data_source_name'] . ']';

		break;
	case 'enabled_host':
		$host = db_fetch_row_prepared('SELECT *
			FROM host
			WHERE id = ?',
			array($id));

		$desc = "Device Enabled by User[$user] Device[$id]";

		break;
	case 'disabled_host':
		$host = db_fetch_row_prepared('SELECT *
			FROM host
			WHERE id = ?',
			array($id));

		$desc = "Device Disabled by User[$user] Device[$id]";

		break;
	case 'auto_created':
		$thold = db_fetch_row_prepared('SELECT *
			FROM thold_data
			WHERE id = ?',
			array($id));

		$tname = db_fetch_cell_prepared('SELECT name
			FROM data_template
			WHERE id = ?',
			array($thold['data_template_id']));

		$desc  = "Threshold Auto-created by User[$user] TH[$id]";

		$desc .= ' DataTemplate[' . $tname . ']';
		$desc .= ' DataSource[' . $thold['data_source_name'] . ']';

		break;
	case 'created':
		$thold = db_fetch_row_prepared('SELECT *
			FROM thold_data
			WHERE id = ?',
			array($id));

		$tname = db_fetch_cell_prepared('SELECT name
			FROM data_template
			WHERE id = ?',
			array($thold['data_template_id']));

		$desc  = "Threshold Created by User[$user] TH[$id]";

		$desc .= ' DataTemplate[' . $tname . ']';
		$desc .= ' DataSource[' . $thold['data_source_name'] . ']';

		break;
	case 'deleted':
		$thold = db_fetch_row_prepared('SELECT *
			FROM thold_data
			WHERE id = ?',
			array($id));

		$tname = db_fetch_cell_prepared('SELECT name
			FROM data_template
			WHERE id = ?',
			array($thold['data_template_id']));

		$desc  = "Threshold Deleted by User[$user] TH[$id]";

		$desc .= ' DataTemplate[' . $tname . ']';
		$desc .= ' DataSource[' . $thold['data_source_name'] . ']';

		if (cacti_sizeof($message)) {
			$desc .= ' Note[' . implode(', ', $message) . ']';
		}

		break;
	case 'deleted_template':
		$thold = db_fetch_row_prepared('SELECT *
			FROM thold_template
			WHERE id = ?',
			array($id));

		$desc  = "Template Deleted by User[$user] TH[$id]";
		$desc .= ' DataTemplate[' . $thold['data_template_name'] . ']';
		$desc .= ' DataSource[' . $thold['data_source_name'] . ']';

		break;
	case 'modified':
		$thold = db_fetch_row_prepared('SELECT *
			FROM thold_data
			WHERE id = ?',
			array($id));

		$rows  = db_fetch_assoc_prepared('SELECT ptc.data
			FROM plugin_thold_contacts AS ptc
			INNER JOIN plugin_thold_threshold_contact AS pttc
			WHERE ptc.id=pttc.contact_id
			AND pttc.thold_id = ?',
			array($id));

		$alert_emails = '';
		if (read_config_option('thold_disable_legacy') != 'on') {
			$alert_emails = array();

			if (count($rows)) {
				foreach ($rows as $row) {
				$alert_emails[] = $row['data'];
				}
			}

			$alert_emails = implode(',', $alert_emails);

			if ($alert_emails != '') {
				$alert_emails .= ',' . $thold['notify_extra'];
			} else {
				$alert_emails = $thold['notify_extra'];
			}
		}

		$alert_emails .= (strlen($alert_emails) ? ',':'') . get_thold_notification_emails($thold['notify_alert']);

		$warning_emails = '';
		if (read_config_option('thold_disable_legacy') != 'on') {
			$warning_emails = $thold['notify_warning_extra'];
		}

		if ($message['id'] > 0) {
			$desc = "Threshold Modified by User[$user] TH[$id]";
		} else {
			$desc = "Threshold Created by User[$user] TH[$id]";
		}

		$tname = db_fetch_cell_prepared('SELECT name
			FROM data_template
			WHERE id = ?',
			array($thold['data_template_id']));

		$desc .= ' DataTemplate[' . $tname . ']';
		$desc .= ' DataSource[' . $thold['data_source_name'] . ']';

		if ($message['template_enabled'] == 'on') {
			$desc .= ' Use Template[On]';
		} else {
			if (isset($thold_types[$thold['thold_type']])) {
				$desc .= ' Type Type[' . $thold_types[$thold['thold_type']] . ']';
			}

			$desc .= ' Enabled: ' . ($thold['thold_enabled'] == 'on' && $thold['thold_per_enabled'] == 'on' ? 'on':'off');

			switch ($message['thold_type']) {
			case 0:
				$desc .= ' High[' . $message['thold_hi'] . ']';
				$desc .= ' Low[' . $message['thold_low'] . ']';
				$desc .= ' Trigger[' . plugin_thold_duration_convert($thold['local_data_id'], $message['thold_fail_trigger'], 'alert') . ']';
				$desc .= ' WarnHigh[' . $message['thold_warning_hi'] . ']';
				$desc .= ' WarnLow[' . $message['thold_warning_low'] . ']';
				$desc .= ' WarnTrigger[' . plugin_thold_duration_convert($thold['local_data_id'], $message['thold_warning_fail_trigger'], 'alert') . ']';

				break;
			case 1:
				$desc .= ' BL Type[' . (!isset($message['bl_type']) ? 0:$message['bl_type']) . ']';

				if ($message['bl_type'] == 1) {
					$desc .= ' BL CF[' . (!isset($message['bl_cf']) ? 'AVG':$message['bl_cf']) . ']';
				}

				$desc .= ' Range[' . $message['bl_ref_time_range'] . ']';
				$desc .= ' DevUp[' . $message['bl_pct_up'] . ']';
				$desc .= ' DevDown[' . $message['bl_pct_down'] . ']';
				$desc .= ' Trigger[' . $message['bl_fail_trigger'] . ']';

				break;
			case 2:
				$desc .= ' High[' . $message['time_hi'] . ']';
				$desc .= ' Low[' . $message['time_low'] . ']';
				$desc .= ' Trigger[' . $message['time_fail_trigger'] . ']';
				$desc .= ' Time: ' . plugin_thold_duration_convert($thold['local_data_id'], $message['time_fail_length'], 'time') . ']';
				$desc .= ' WarnHigh[' . $message['time_warning_hi'] . ']';
				$desc .= ' WarnLow[' . $message['time_warning_low'] . ']';
				$desc .= ' WarnTrigger[' . $message['time_warning_fail_trigger'] . ']';
				$desc .= ' WarnTime[' . plugin_thold_duration_convert($thold['local_data_id'], $message['time_warning_fail_length'], 'time') . ']';

				break;
			}

			$desc .= ' CDEF[' . $message['cdef'] . ']';
			$desc .= ' ReAlert[' . plugin_thold_duration_convert($thold['local_data_id'], $message['repeat_alert'], 'alert') . ']';
			$desc .= ' AlertEmails[' . $alert_emails . ']';
			$desc .= ' WarnEmails[' . $warning_emails . ']';
		}

		break;
	case 'modified_template':
		$thold = db_fetch_row_prepared('SELECT *
			FROM thold_template
			WHERE id = ?',
			array($id));

		$rows = db_fetch_assoc_prepared('SELECT ptc.data
			FROM plugin_thold_contacts AS ptc
			INNER JOIN plugin_thold_template_contact AS pttc
			ON ptc.id=pttc.contact_id
			WHERE pttc.template_id = ?',
			array($id));

		$alert_emails = '';
		if (read_config_option('thold_disable_legacy') != 'on') {
			$alert_emails = array();

			if (count($rows)) {
				foreach ($rows as $row) {
					$alert_emails[] = $row['data'];
				}
			}

			$alert_emails = implode(',', $alert_emails);

			if ($alert_emails != '') {
				$alert_emails .= ',' . $thold['notify_extra'];
			} else {
				$alert_emails = $thold['notify_extra'];
			}
		}

		$alert_emails .= (strlen($alert_emails) ? ',':'') . get_thold_notification_emails($thold['notify_alert']);

		$warning_emails = '';
		if (read_config_option('thold_disable_legacy') != 'on') {
			$warning_emails = $thold['notify_warning_extra'];
		}

		if ($message['id'] > 0) {
			$desc = "Template Modified by User[$user] TT[$id]";
		} else {
			$desc = "Template Created by User[$user] TT[$id]";
		}

		$desc .= ' DataTemplate[' . $thold['data_template_name'] . ']';
		$desc .= ' DataSource[' . $thold['data_source_name'] . ']';

		if (isset($thold_types[$thold['thold_type']])) {
			$desc .= ' Thold Type[' . $thold_types[$message['thold_type']] . ']';
		}

		$desc .= ' Enabled: ' . ($thold['thold_enabled'] == 'on' ? 'on':'off');

		switch ($message['thold_type']) {
			case 0:
				$desc .= ' High[' . (isset($message['thold_hi']) ? $message['thold_hi'] : '') . ']';
				$desc .= ' Low[' . (isset($message['thold_low']) ? $message['thold_low'] : '') . ']';
				$desc .= ' Trigger[' . plugin_thold_duration_convert($thold['data_template_id'], (isset($message['thold_fail_trigger']) ? $message['thold_fail_trigger'] : ''), 'alert', 'data_template_id') . ']';
				$desc .= ' WarnHigh[' . (isset($message['thold_warning_hi']) ? $message['thold_warning_hi'] : '') . ']';
				$desc .= ' WarnLow[' . (isset($message['thold_warning_low']) ? $message['thold_warning_low'] : '') . ']';
				$desc .= ' WarnTrigger[' . plugin_thold_duration_convert($thold['data_template_id'], (isset($message['thold_warning_fail_trigger']) ? $message['thold_fail_trigger'] : ''), 'alert', 'data_template_id') . ']';

				break;
			case 1:
				$desc .= ' BL Type[' . (!isset($message['bl_type']) ? 0:$message['bl_type']) . ']';

				if ($message['bl_type'] == 1) {
					$desc .= ' BL CF[' . (!isset($message['bl_cf']) ? 'AVG':$message['bl_cf']) . ']';
				}

				$desc .= ' Range[' . $message['bl_ref_time_range'] . ']';
				$desc .= ' DevUp[' . (isset($message['bl_pct_up'])? $message['bl_pct_up'] : '' ) . ']';
				$desc .= ' DevDown[' . (isset($message['bl_pct_down'])? $message['bl_pct_down'] : '' ) . ']';
				$desc .= ' Trigger[' . $message['bl_fail_trigger'] . ']';

				break;
			case 2:
				$desc .= ' High[' . $message['time_hi'] . ']';
				$desc .= ' Low[' . $message['time_low'] . ']';
				$desc .= ' Trigger[' . $message['time_fail_trigger'] . ']';
				$desc .= ' Time[' . plugin_thold_duration_convert($thold['data_template_id'], $message['time_fail_length'], 'alert', 'data_template_id') . ']';
				$desc .= ' WarnHigh[' . $message['time_warning_hi'] . ']';
				$desc .= ' WarnLow[' . $message['time_warning_low'] . ']';
				$desc .= ' WarnTrigger[' . $message['time_warning_fail_trigger'] . ']';
				$desc .= ' WarnTime[' . plugin_thold_duration_convert($thold['data_template_id'], $message['time_warning_fail_length'], 'alert', 'data_template_id') . ']';

				break;
		}

		$desc .= ' CDEF[' . (isset($message['cdef']) ? $message['cdef']: '') . ']';
		$desc .= ' ReAlert[' . plugin_thold_duration_convert($thold['data_template_id'], $message['repeat_alert'], 'alert', 'data_template_id') . ']';
		$desc .= ' AlertEmails[' . $alert_emails . ']';
		$desc .= ' WarnEmails[' . $warning_emails . ']';

		break;
	}

	if ($desc != '') {
		thold_cacti_log($desc);
	}
}

function get_thold_severity(&$td) {
	$severity = THOLD_SEVERITY_NORMAL;

	if (($td['template_enabled'] == 'on' && $td['thold_enabled'] == 'off') || $td['thold_per_enabled'] == '') {
		return THOLD_SEVERITY_DISABLED;
	}

	if (!isset($td['thold_type'])) {
		return THOLD_SEVERITY_NORMAL;
	}

	switch($td['thold_type']) {
		case '0': // Hi/Low
			if ($td['thold_alert'] != 0) {
				if ($td['thold_hi'] != '' || $td['thold_low'] != '') {
					if ($td['thold_fail_count'] >= $td['thold_fail_trigger']) {
						$severity = THOLD_SEVERITY_ALERT;
					} elseif ($td['lastread'] > $td['thold_hi'] && $td['thold_fail_trigger'] == 0) {
						$severity = THOLD_SEVERITY_ALERT;
					} elseif ($td['lastread'] < $td['thold_low'] && $td['thold_fail_trigger'] == 0) {
						$severity = THOLD_SEVERITY_ALERT;
					} elseif ($td['thold_warning_hi'] != '' || $td['thold_warning_low'] != '') {
						if ($td['thold_warning_fail_count'] >= $td['thold_warning_fail_trigger']) {
							$severity = THOLD_SEVERITY_WARNING;
						} elseif ($td['lastread'] > $td['thold_warning_hi'] && $td['thold_warning_fail_trigger'] == 0) {
							$severity = THOLD_SEVERITY_WARNING;
						} elseif ($td['lastread'] < $td['thold_warning_low'] && $td['thold_warning_fail_trigger'] == 0) {
							$severity = THOLD_SEVERITY_WARNING;
						} else {
							$severity = THOLD_SEVERITY_NOTICE;
						}
					} else {
						$severity = THOLD_SEVERITY_NOTICE;
					}
				} elseif ($td['thold_warning_hi'] != '' || $td['thold_warning_low'] != '') {
					if ($td['thold_warning_fail_count'] >= $td['thold_warning_fail_trigger']) {
						$severity = THOLD_SEVERITY_WARNING;
					} elseif ($td['lastread'] > $td['thold_warning_hi'] && $td['thold_warning_fail_trigger'] == 0) {
						$severity = THOLD_SEVERITY_WARNING;
					} elseif ($td['lastread'] < $td['thold_warning_low'] && $td['thold_warning_fail_trigger'] == 0) {
						$severity = THOLD_SEVERITY_WARNING;
					} else {
						$severity = THOLD_SEVERITY_NOTICE;
					}
				}
			} elseif ($td['acknowledgment'] == 'on') {
				$severity = THOLD_SEVERITY_ACKREQ;
			}

			break;
		case '1': // Baseline
			if ($td['bl_alert'] == 1) {
				if ($td['bl_fail_count'] >= $td['bl_fail_trigger']) {
					$severity = THOLD_SEVERITY_BASELINE;
				} else {
					$severity = THOLD_SEVERITY_NOTICE;
				}
			} elseif ($td['bl_alert'] == 2)  {
				if ($td['bl_fail_count'] >= $td['bl_fail_trigger']) {
					$severity = THOLD_SEVERITY_BASELINE;
				} else {
					$severity = THOLD_SEVERITY_NOTICE;
				}
			} elseif ($td['acknowledgment'] == 'on') {
				$severity = THOLD_SEVERITY_ACKREQ;
			}

			break;
		case '2': // Time Based
			if ($td['thold_alert'] != 0) {
				if ($td['time_hi'] != '' || $td['time_low'] != '') {
					if ($td['thold_fail_count'] >= $td['time_fail_trigger']) {
						$severity = THOLD_SEVERITY_ALERT;
					} elseif ($td['lastread'] > $td['time_hi'] && $td['time_fail_trigger'] == 0) {
						$severity = THOLD_SEVERITY_ALERT;
					} elseif ($td['lastread'] < $td['time_low'] && $td['time_fail_trigger'] == 0) {
						$severity = THOLD_SEVERITY_ALERT;
					} elseif ($td['time_warning_hi'] != '' || $td['time_warning_low']) {
						if ($td['thold_warning_fail_count'] >= $td['time_warning_fail_trigger']) {
							$severity = THOLD_SEVERITY_WARNING;
						} elseif ($td['lastread'] > $td['time_warning_hi'] && $td['time_warning_fail_trigger'] == 0) {
							$severity = THOLD_SEVERITY_WARNING;
						} elseif ($td['lastread'] < $td['time_warning_low'] && $td['time_warning_fail_trigger'] == 0) {
							$severity = THOLD_SEVERITY_WARNING;
						} else {
							$severity = THOLD_SEVERITY_NOTICE;
						}
					} else {
						$severity = THOLD_SEVERITY_NOTICE;
					}
				} elseif ($td['time_warning_hi'] != '' || $td['time_warning_low']) {
					if ($td['thold_warning_fail_count'] >= $td['time_warning_fail_trigger']) {
						$severity = THOLD_SEVERITY_WARNING;
					} elseif ($td['lastread'] > $td['time_warning_hi'] && $td['time_warning_fail_trigger'] == 0) {
						$severity = THOLD_SEVERITY_WARNING;
					} elseif ($td['lastread'] < $td['time_warning_low'] && $td['time_warning_fail_trigger'] == 0) {
						$severity = THOLD_SEVERITY_WARNING;
					} else {
						$severity = THOLD_SEVERITY_NOTICE;
					}
				}
			} elseif ($td['acknowledgment'] == 'on') {
				$severity = THOLD_SEVERITY_ACKREQ;
			}

			break;
		default:
			break;
	}

	return $severity;
}

function thold_datasource_required($name, $data_source) {
	$show_datasource = read_config_option('show_datasource');

	if ($show_datasource == 'on') {
		if (strstr($name, "[$data_source]") !== false) {
			return false;
		}
	} else {
		return false;
	}

	return true;
}

function thold_check_threshold(&$thold_data) {
	global $config, $plugins, $debug;

	// Modify critical thold values based upon a cdef if present
	thold_modify_values_by_cdef($thold_data);

	thold_debug('Checking Threshold:' .
		' id: '                    . var_export($thold_data['id'], true) .
		', name: '                 . var_export($thold_data['name_cache'], true) .
		', data source: '          . var_export($thold_data['data_source_name'], true) .
		', local_data_id: '        . var_export($thold_data['local_data_id'], true) .
		', data_template_rrd_id: ' . var_export($thold_data['data_template_rrd_id'], true) .
		', value: '                . var_export($thold_data['lastread'], true));

	// Do not proceed if we have chosen to globally disable all alerts
	if (read_config_option('thold_disable_all') == 'on') {
		thold_debug('Threshold checking is disabled globally');
		return;
	}

	$alert_exempt = read_config_option('alert_exempt');
	/* check for exemptions */
	$weekday = date('l');
	if (($weekday == 'Saturday' || $weekday == 'Sunday') && $alert_exempt == 'on') {
		thold_debug('Threshold checking is disabled by global weekend exemption');
		return;
	}

	/* check for the weekend exemption on the threshold level */
	if (($weekday == 'Saturday' || $weekday == 'Sunday') && $thold_data['exempt'] == 'on') {
		thold_debug('Threshold checking is disabled by global weekend exemption');
		return;
	}

	/* don't alert for this host if it's selected for maintenance */
	if (api_plugin_is_enabled('maint') || in_array('maint', $plugins)) {
		include_once($config['base_path'] . '/plugins/maint/functions.php');
	}

	if (api_plugin_is_enabled('maint') && plugin_maint_check_cacti_host($thold_data['host_id'])) {
		$maint_dev = true;
	} else {
		$maint_dev = false;
	}

	$local_graph_id = $thold_data['local_graph_id'];

	$h = array();
	if (isset($thold_data['host_id']) && $thold_data['host_id'] > 0) {
		/* only alert if Device is in UP mode (not down, unknown, or recovering) */
		$h = db_fetch_row_prepared('SELECT *
			FROM host
			WHERE id = ?',
			array($thold_data['host_id']));

		if (cacti_sizeof($h) && $h['status'] != 3) {
			thold_debug('Threshold checking halted by Device Status (' . $h['status'] . ')' );
			return;
		}

		$h['thold_id'] = $thold_data['id'];
	} else {
		/* function called during polling */
		$h['id']             = $thold_data['host_id'];
		$h['snmp_engine_id'] = $thold_data['snmp_engine_id'];
		$h['description']    = $thold_data['description'];
		$h['hostname']       = $thold_data['hostname'];
		$h['thold_id']       = $thold_data['id'];
	}

	/* ensure that Cacti will make of individual defined SNMP Engine IDs */
	$overwrite['snmp_engine_id'] = $h['snmp_engine_id'];

	/* pull a few default settings */
	$global_alert_address  = read_config_option('alert_email');

	// Settings for syslogging
	$syslog                = $thold_data['syslog_enabled'] == 'on' ? true:false;
	$syslog_priority       = $thold_data['syslog_priority'];
	$syslog_facility       = $thold_data['syslog_facility'];

	$deadnotify            = (read_config_option('alert_deadnotify') == 'on');
	$realert               = read_config_option('alert_repeat');
	$alert_trigger         = read_config_option('alert_trigger');
	$alert_bl_trigger      = read_config_option('alert_bl_trigger');
	$httpurl               = read_config_option('base_url');
	$thold_send_text_only  = read_config_option('thold_send_text_only');

	$thold_snmp_traps         = (read_config_option('thold_alert_snmp') == 'on');
	$thold_snmp_warning_traps = (read_config_option('thold_alert_snmp_warning') != 'on');
	$thold_snmp_normal_traps  = (read_config_option('thold_alert_snmp_normal') != 'on');
	$cacti_polling_interval   = read_config_option('poller_interval');

	/* remove this after adding an option for it */
	$show_datasource = thold_datasource_required(thold_get_cached_name($thold_data), $thold_data['data_source_name']);

	$trigger         = ($thold_data['thold_fail_trigger'] == '' ? $alert_trigger : $thold_data['thold_fail_trigger']);
	$warning_trigger = ($thold_data['thold_warning_fail_trigger'] == '' ? $alert_trigger : $thold_data['thold_warning_fail_trigger']);
	$alertstat       = $thold_data['thold_alert'];

	/* see if we have two notification lists or one */
	$notify_different = false;

	if ($thold_data['notify_warning'] > 0) {
		if ($thold_data['notify_warning'] != $thold_data['notify_alert']) {
			if (read_config_option('thold_notify_alerts_to_warning_recipients') == 'on') {
				$notify_different = true;
			}
		}
	}

	/* setup base units */
	$baseu = db_fetch_cell_prepared('SELECT base_value
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		array($thold_data['local_graph_id']));

	if ($thold_data['data_type'] == 2) {
		$suffix = false;
	} else {
		$suffix = true;
	}

	$show_units   = ($thold_data['show_units'] ? true : false);
	$units_suffix = $thold_data['units_suffix'];
	$decimals     = $thold_data['decimals'] >= 0 ? $thold_data['decimals']:2;

	$file_array = array();
	if ($thold_send_text_only != 'on') {
		if (!empty($thold_data['local_graph_id'])) {
			$file_array = array(
				'local_graph_id' => $thold_data['local_graph_id'],
				'local_data_id'  => $thold_data['local_data_id'],
				'rra_id'         => 0,
				'file'           => "$httpurl/graph_image.php?local_graph_id=" . $thold_data['local_graph_id'] . '&rra_id=0&view_type=tree',
				'mimetype'       => 'image/png',
				'filename'       => clean_up_name(thold_get_cached_name($thold_data))
			);
		}
	}

	$url      = $httpurl . '/graph.php?local_graph_id=' . $thold_data['local_graph_id'] . '&rra_id=all';
	$lastread = $thold_data['lastread'];

	$alert_emails       = get_thold_emails($thold_data, 'alert', 'to');
	$alert_bcc_emails   = get_thold_emails($thold_data, 'alert', 'bcc');
	$warning_emails     = get_thold_emails($thold_data, 'warning', 'to');
	$warning_bcc_emails = get_thold_emails($thold_data, 'warning', 'bcc');

	switch ($thold_data['thold_type']) {
	case 0:	/* hi/low */
		if (is_numeric($thold_data['lastread'])) {
			$breach_up           = ($thold_data['thold_hi'] != '' && $thold_data['lastread'] > $thold_data['thold_hi']);
			$breach_down         = ($thold_data['thold_low'] != '' && $thold_data['lastread'] < $thold_data['thold_low']);
			$warning_breach_up   = ($thold_data['thold_warning_hi'] != '' && $thold_data['lastread'] > $thold_data['thold_warning_hi']);
			$warning_breach_down = ($thold_data['thold_warning_low'] != '' && $thold_data['lastread'] < $thold_data['thold_warning_low']);
		} else {
			$breach_up           = $breach_down = $warning_breach_up = $warning_breach_down = false;
		}

		/* is in alert status */
		if ($breach_up || $breach_down) {
			$notify = false;

			thold_debug('Threshold HI / Low check breached HI:' . $thold_data['thold_hi'] . '  LOW:' . $thold_data['thold_low'] . ' VALUE:' . $thold_data['lastread']);

			if (!$maint_dev) {
				$thold_data['thold_fail_count']++;
			}

			$thold_data['thold_alert'] = ($breach_up ? STAT_HI : STAT_LO);

			/* Re-Alert? */
			$ra = ($thold_data['thold_fail_count'] > $trigger && $thold_data['repeat_alert'] != 0 && $thold_data['thold_fail_count'] % $thold_data['repeat_alert'] == 0);

			if (($thold_data['thold_fail_count'] == $trigger || $ra) && !$maint_dev) {
				$notify = true;
			}

			if ($notify && !$ra) {
				db_execute_prepared('UPDATE thold_data
					SET lastchanged = NOW()
					WHERE id = ?',
					array($thold_data['id']));

				if ($thold_data['persist_ack'] == 'on' || $thold_data['reset_ack'] == 'on') {
					db_execute_prepared('UPDATE thold_data
						SET acknowledgment = "on"
						WHERE id = ?',
						array($thold_data['id']));
				}
			}

			// If this is a realert and the operator has reset the ack, don't notify
			if ($notify && $ra && $thold_data['reset_ack'] == 'on' && $thold_data['acknowledgment'] == '') {
				$suspend_notify = true;
			} else {
				$suspend_notify = false;
			}

			$subject = get_email_subject('ALERT', false, $lastread, $ra, $breach_up, $thold_data);

			if ($notify) {
				if (!$suspend_notify && !$maint_dev) {
					thold_debug('Alerting is necessary');

					$notify_list_id = $thold_data['notify_alert'];
					$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

					if ($syslog) {
						logger($subject, $url, $syslog_priority, $syslog_facility);
					}

					if (trim($alert_emails) != '' && $thold_data['acknowledgment'] == '') {
						$message = get_thold_alert_text($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id']);

						thold_mail($alert_emails, $alert_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file);
					}

					if ($notify_different) {
						$notify_list_id = $thold_data['notify_warning'];
						$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

						if (trim($warning_emails) != '' && $thold_data['acknowledgment'] == '') {
							thold_mail($warning_emails, $warning_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file);
						}
					}

					thold_command_execution($thold_data, $h, $breach_up, $breach_down);

					$save = array(
						'class'               => 'alert',
						'thold_data'          => $thold_data,
						'subject'             => $subject,
						'repeat_alert'        => $ra,
						'host_data'           => $h,
						'breach_up'           => $breach_up,
						'breach_down'         => $breach_down,
						'warning_breach_up'   => $warning_breach_up,
						'warning_breach_down' => $warning_breach_down
					);

					api_plugin_hook_function('thold_action', $save);

					if ($thold_snmp_traps) {
						$thold_snmp_data = get_thold_snmp_data($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread']);

						$thold_snmp_data['eventClass']               = 3;
						$thold_snmp_data['eventSeverity']            = $thold_data['snmp_event_severity'];
						$thold_snmp_data['eventStatus']              = $thold_data['thold_alert']+1;
						$thold_snmp_data['eventRealertStatus']       = ($ra ? ($breach_up ? 3:2) :1);
						$thold_snmp_data['eventNotificationType']    = ($ra ? ST_NOTIFYRA:ST_NOTIFYAL)+1;
						$thold_snmp_data['eventFailCount']           = $thold_data['thold_fail_count'];
						$thold_snmp_data['eventFailDuration']        = $thold_data['thold_fail_count'] * $cacti_polling_interval;
						$thold_snmp_data['eventFailDurationTrigger'] = $trigger * $cacti_polling_interval;
						$thold_snmp_data['eventDeviceIp']            = gethostbyname($h['hostname']);
						$thold_snmp_data['eventDescription']         = str_replace(
						    array('<FAIL_COUNT>', '<FAIL_DURATION>'),
						    array($thold_snmp_data['eventFailCount'], $thold_snmp_data['eventFailDuration']),
						    $thold_snmp_data['eventDescription']
						);

						thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
					}
				}

				thold_log(array(
					'type'            => 0,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($breach_up ? $thold_data['thold_hi'] : $thold_data['thold_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ($ra ? ST_NOTIFYRA:ST_NOTIFYAL),
					'description'     => ($maint_dev ? $subject . '. ' . __('Only logging, maint device', 'thold') : $subject),
					'emails'          => $alert_emails,
					'bcc_emails'      => $alert_bcc_emails)
				);
			}

			db_execute_prepared('UPDATE thold_data
				SET thold_alert = ?,
				thold_fail_count = ?,
				thold_warning_fail_count = 0
				WHERE id = ?',
				array($thold_data['thold_alert'], $thold_data['thold_fail_count'], $thold_data['id']));
		} elseif ($warning_breach_up || $warning_breach_down) {
			$notify = false;

			thold_debug('Threshold HI / Low Warning check breached HI:' . $thold_data['thold_warning_hi'] .
				' LOW:'     . $thold_data['thold_warning_low'] .
				' VALUE:'   . $thold_data['lastread'] .
				' TRIGGER:' . $trigger .
				' WRNCNT:'  . $thold_data['thold_warning_fail_count']);

			if (!$maint_dev) {
				$thold_data['thold_warning_fail_count']++;
			}

			$thold_data['thold_alert'] = ($warning_breach_up ? STAT_HI:STAT_LO);

			/* re-alert? */
			$ra = ($thold_data['thold_warning_fail_count'] > $warning_trigger && $thold_data['repeat_alert'] != 0 && $thold_data['thold_warning_fail_count'] % $thold_data['repeat_alert'] == 0);

			if (($thold_data['thold_warning_fail_count'] == $warning_trigger || $ra) && !$maint_dev) {
				$notify = true;
			}

			if ($notify && !$ra) {
				db_execute_prepared('UPDATE thold_data
					SET lastchanged = NOW()
					WHERE id = ?',
					array($thold_data['id']));

				if ($thold_data['persist_ack'] == 'on' || $thold_data['reset_ack'] == 'on') {
					db_execute_prepared('UPDATE thold_data
						SET acknowledgment = "on"
						WHERE id = ?',
						array($thold_data['id']));
				}
			}

			// If this is a realert and the operator has reset the ack, don't notify
			if ($notify && $ra && $thold_data['reset_ack'] == 'on' && $thold_data['acknowledgment'] == '') {
				$suspend_notify = true;
			} else {
				$suspend_notify = false;
			}

			if ($notify) {
				$etrigger = false;
			} else {
				$etrigger = true;
			}

			$subject = get_email_subject('WARNING', $etrigger, $lastread, $ra, $warning_breach_up, $thold_data);

			if ($notify) {
				if (!$suspend_notify && !$maint_dev) {
					thold_debug('Alerting is necessary');

					if ($syslog) {
						logger($subject, $url, $syslog_priority, $syslog_facility);
					}

					$notify_list_id = $thold_data['notify_warning'];
					$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

					if (trim($warning_emails) != '' && $thold_data['acknowledgment'] == '') {
						$message = get_thold_warning_text($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id']);

						thold_mail($warning_emails, $warning_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file, $thold_data['graph_timespan']);
					}

					$save = array(
						'class'               => 'warn',
						'thold_data'          => $thold_data,
						'subject'             => $subject,
						'repeat_alert'        => $ra,
						'host_data'           => $h,
						'breach_up'           => $breach_up,
						'breach_down'         => $breach_down,
						'warning_breach_up'   => $warning_breach_up,
						'warning_breach_down' => $warning_breach_down
					);

					api_plugin_hook_function('thold_action', $save);

					if ($thold_snmp_traps && $thold_snmp_warning_traps) {
						$thold_snmp_data = get_thold_snmp_data($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread']);

						$thold_snmp_data['eventClass']               = 2;
						$thold_snmp_data['eventSeverity']            = $thold_data['snmp_event_warning_severity'];
						$thold_snmp_data['eventStatus']              = $thold_data['thold_alert']+1;
						$thold_snmp_data['eventRealertStatus']       = ($ra ? ($warning_breach_up ? 3:2) :1);
						$thold_snmp_data['eventNotificationType']    = ($ra ? ST_NOTIFYRAW:ST_NOTIFYWA)+1;
						$thold_snmp_data['eventFailCount']           = $thold_data['thold_warning_fail_count'];
						$thold_snmp_data['eventFailDuration']        = $thold_data['thold_warning_fail_count'] * $cacti_polling_interval;
						$thold_snmp_data['eventFailDurationTrigger'] = $warning_trigger * $cacti_polling_interval;
						$thold_snmp_data['eventDeviceIp']            = gethostbyname($h['hostname']);
						$thold_snmp_data['eventDescription']         = str_replace(
						    array('<FAIL_COUNT>', '<FAIL_DURATION>'),
						    array($thold_snmp_data['eventFailCount'], $thold_snmp_data['eventFailDuration']),
						    $thold_snmp_data['eventDescription']
						);

						thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
					}
				}

				thold_log(array(
					'type'            => 0,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($warning_breach_up ? $thold_data['thold_warning_hi'] : $thold_data['thold_warning_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ($ra ? ST_NOTIFYRAW:ST_NOTIFYWA),
					'description'     => ($maint_dev ? $subject . '. ' . __('Only logging, maint device', 'thold') : $subject),
					'emails'          => $alert_emails,
					'bcc_emails'      => $alert_bcc_emails)
				);
			} elseif (($thold_data['thold_warning_fail_count'] >= $warning_trigger) && ($thold_data['thold_fail_count'] >= $trigger)) {
				$subject = get_email_subject('ALERT > WARNING', false, $lastread, $ra, $warning_breach_up, $thold_data);

				if (!$suspend_notify && !$maint_dev) {
					$notify_list_id = $thold_data['notify_alert'];
					$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

					if (trim($alert_emails) != '' && $thold_data['acknowledgment'] == '') {
						$message = get_thold_warning_text($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id']);

						thold_mail($alert_emails, $alert_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file, $thold_data['graph_timespan']);
					}

					if ($notify_different) {
						$notify_list_id = $thold_data['notify_warning'];
						$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

						if (trim($warning_emails) != '' && $thold_data['acknowledgment'] == '') {
							thold_mail($warning_emails, $warning_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file);
						}
					}

					$save = array(
						'class'               => 'alert2warn',
						'thold_data'          => $thold_data,
						'subject'             => $subject,
						'repeat_alert'        => $ra,
						'host_data'           => $h,
						'breach_up'           => $breach_up,
						'breach_down'         => $breach_down,
						'warning_breach_up'   => $warning_breach_up,
						'warning_breach_down' => $warning_breach_down
					);

					api_plugin_hook_function('thold_action', $save);

					if ($thold_snmp_traps && $thold_snmp_warning_traps) {
						$thold_snmp_data = get_thold_snmp_data($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread']);

						$thold_snmp_data['eventClass']               = 2;
						$thold_snmp_data['eventSeverity']            = $thold_data['snmp_event_warning_severity'];
						$thold_snmp_data['eventStatus']              = $thold_data['thold_alert']+1;
						$thold_snmp_data['eventNotificationType']    = ST_NOTIFYAW+1;
						$thold_snmp_data['eventFailCount']           = $thold_data['thold_warning_fail_count'];
						$thold_snmp_data['eventFailDuration']        = $thold_data['thold_warning_fail_count'] * $cacti_polling_interval;
						$thold_snmp_data['eventFailDurationTrigger'] = $trigger * $cacti_polling_interval;
						$thold_snmp_data['eventDeviceIp']            = gethostbyname($h['hostname']);
						$thold_snmp_data['eventDescription']         = str_replace(
						    array('<FAIL_COUNT>', '<FAIL_DURATION>'),
						    array($thold_snmp_data['eventFailCount'], $thold_snmp_data['eventFailDuration']),
						    $thold_snmp_data['eventDescription']
						);

						thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
					}
				}

				thold_log(array(
					'type'            => 0,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($warning_breach_up ? $thold_data['thold_warning_hi'] : $thold_data['thold_warning_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ST_NOTIFYAW,
					'description'     => ($maint_dev ? $subject . '. ' . __('Only logging, maint device', 'thold') : $subject),
					'emails'          => $alert_emails,
					'bcc_emails'      => $alert_bcc_emails)
				);
			}

			db_execute_prepared('UPDATE thold_data
				SET thold_alert = ?,
				thold_warning_fail_count = ?,
				thold_fail_count = 0
				WHERE id = ?',
				array($thold_data['thold_alert'], $thold_data['thold_warning_fail_count'], $thold_data['id']));
		} else {
			thold_debug('Threshold HI / Low check is Normal HI:' . $thold_data['thold_hi'] . '  LOW:' . $thold_data['thold_low'] . ' VALUE:' . $thold_data['lastread']);

			$subject = get_email_subject('NORMAL', false, $lastread, false, false, $thold_data);

			/* if we were at an alert status before */
			if ($alertstat != 0) {
				db_execute_prepared('UPDATE thold_data
					SET thold_alert = 0,
					thold_fail_count = 0,
					lastchanged = NOW(),
					thold_warning_fail_count=0
					WHERE id = ?',
					array($thold_data['id']));

				if ($thold_data['reset_ack'] == 'on') {
					db_execute_prepared('UPDATE thold_data
						SET acknowledgment=""
						WHERE id = ?',
						array($thold_data['id']));
				}

				if ($thold_data['thold_warning_fail_count'] >= $warning_trigger && $thold_data['restored_alert'] != 'on' && !$maint_dev) {
					if ($syslog) {
						logger($subject, $url, $syslog_priority, $syslog_facility);
					}

					$notify_list_id = $thold_data['notify_warning'];
					$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

					if (trim($warning_emails) != '' && $thold_data['restored_alert'] != 'on' && $thold_data['acknowledgment'] == '') {
						$message = get_thold_restoral_text($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id']);

						thold_mail($warning_emails, $warning_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file, $thold_data['graph_timespan']);
					}

					$save = array(
						'class'               => 'warn2normal',
						'thold_data'          => $thold_data,
						'subject'             => $subject,
						'host_data'           => $h,
						'breach_up'           => $breach_up,
						'breach_down'         => $breach_down,
						'warning_breach_up'   => $warning_breach_up,
						'warning_breach_down' => $warning_breach_down
					);

					api_plugin_hook_function('thold_action', $save);

					if ($thold_snmp_traps && $thold_snmp_normal_traps) {
						$thold_snmp_data = get_thold_snmp_data($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread']);

						$thold_snmp_data['eventClass']            = 1;
						$thold_snmp_data['eventSeverity']         = 1;
						$thold_snmp_data['eventStatus']           = 1;
						$thold_snmp_data['eventNotificationType'] = ST_NOTIFYRS+1;
						$thold_snmp_data['eventDeviceIp']         = gethostbyname($h['hostname']);

						thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
					}

					thold_log(array(
						'type'            => 0,
						'time'            => time(),
						'host_id'         => $thold_data['host_id'],
						'local_graph_id'  => $thold_data['local_graph_id'],
						'threshold_id'    => $thold_data['id'],
						'threshold_value' => '',
						'current'         => $thold_data['lastread'],
						'status'          => ST_NOTIFYRS,
						'description'     => $subject,
						'emails'          => $warning_emails,
						'bcc_emails'      => $warning_bcc_emails)
					);
				} elseif ($thold_data['thold_fail_count'] >= $trigger && $thold_data['restored_alert'] != 'on' && !$maint_dev) {
					if ($syslog) {
						logger($subject, $url, $syslog_priority, $syslog_facility);
					}

					$notify_list_id = $thold_data['notify_alert'];
					$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

					if (trim($alert_emails) != '' && $thold_data['restored_alert'] != 'on' && $thold_data['acknowledgment'] == '') {
						$message = get_thold_restoral_text($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id']);

						thold_mail($alert_emails, $alert_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file, $thold_data['graph_timespan']);
					}

					if ($notify_different) {
						$notify_list_id = $thold_data['notify_warning'];
						$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

						if (trim($warning_emails) != '' && $thold_data['acknowledgment'] == '') {
							thold_mail($warning_emails, $warning_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file);
						}
					}

					thold_command_execution($thold_data, $h, false, false, true);

					$save = array(
						'class'               => 'alert2normal',
						'thold_data'          => $thold_data,
						'subject'             => $subject,
						'host_data'           => $h,
						'breach_up'           => $breach_up,
						'breach_down'         => $breach_down,
						'warning_breach_up'   => $warning_breach_up,
						'warning_breach_down' => $warning_breach_down
					);

					api_plugin_hook_function('thold_action', $save);

					if ($thold_snmp_traps && $thold_snmp_normal_traps) {
						$thold_snmp_data = get_thold_snmp_data($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread']);

						$thold_snmp_data['eventClass']            = 1;
						$thold_snmp_data['eventSeverity']         = 1;
						$thold_snmp_data['eventStatus']           = 1;
						$thold_snmp_data['eventNotificationType'] = ST_NOTIFYRS+1;
						$thold_snmp_data['eventDeviceIp']         = gethostbyname($h['hostname']);

						thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
					}

					thold_log(array(
						'type'            => 0,
						'time'            => time(),
						'host_id'         => $thold_data['host_id'],
						'local_graph_id'  => $thold_data['local_graph_id'],
						'threshold_id'    => $thold_data['id'],
						'threshold_value' => '',
						'current'         => $thold_data['lastread'],
						'status'          => ST_NOTIFYRS,
						'description'     => $subject,
						'emails'          => $alert_emails,
						'bcc_emails'      => $alert_bcc_emails)
					);
				}

				if ($maint_dev) {
					thold_log(array(
						'type'            => 0,
						'time'            => time(),
						'host_id'         => $thold_data['host_id'],
						'local_graph_id'  => $thold_data['local_graph_id'],
						'threshold_id'    => $thold_data['id'],
						'threshold_value' => '',
						'current'         => $thold_data['lastread'],
						'status'          => ST_NOTIFYRS,
						'description'     => ($maint_dev ? $subject . '. ' . __('Only logging, maint device', 'thold') : $subject),
						'emails'          => $alert_emails,
						'bcc_emails'      => $alert_bcc_emails)
					);
				}
			}
		}

		break;
	case 1:	/* baseline */
		$bl_alert_prev   = $thold_data['bl_alert'];
		$bl_count_prev   = $thold_data['bl_fail_count'];
		$bl_fail_trigger = ($thold_data['bl_fail_trigger'] == '' ? $alert_bl_trigger : $thold_data['bl_fail_trigger']);

		$thold_data['bl_alert'] = thold_check_baseline($thold_data['local_data_id'], $thold_data['data_source_name'], $thold_data['lastread'], $thold_data);

		switch($thold_data['bl_alert']) {
			case -2:	/* exception is active, Future Release 'todo' */
				break;
			case -1:	/* reference value not available, Future Release 'todo' */
				break;
			case 0:		/* all clear */
				/* if we were at an alert status before */
				if ($bl_alert_prev != 0) {
					thold_debug('Threshold Baseline check is Normal');

					$subject = get_email_subject('NORMAL', false, $lastread, false, false, $thold_data);

					if ($thold_data['bl_fail_count'] >= $bl_fail_trigger && $thold_data['restored_alert'] != 'on' && !$maint_dev) {
						thold_debug('Threshold Baseline check returned to normal');

						if ($syslog) {
							logger($subject, $url, $syslog_priority, $syslog_facility);
						}

						$notify_list_id = $thold_data['notify_alert'];
						$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

						if (trim($alert_emails) != '' && $thold_data['acknowledgment'] == '') {
							$message = get_thold_restoral_text($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id']);

							thold_mail($alert_emails, $alert_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file, $thold_data['graph_timespan']);
						}

						if ($notify_different) {
							$notify_list_id = $thold_data['notify_warning'];
							$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

							if (trim($warning_emails) != '' && $thold_data['acknowledgment'] == '') {
								thold_mail($warning_emails, $warning_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file);
							}
						}

						thold_command_execution($thold_data, $h, false, false, true);

						$save = array(
							'class'      => 'blnormal',
							'thold_data' => $thold_data,
							'subject'    => $subject,
							'host_data'  => $h
						);

						api_plugin_hook_function('thold_action', $save);

						if ($thold_snmp_traps && $thold_snmp_normal_traps) {
							$thold_snmp_data = get_thold_snmp_data($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread']);

							$thold_snmp_data['eventClass']    = 1;
							$thold_snmp_data['eventSeverity'] = 1;
							$thold_snmp_data['eventStatus']   = 1;
							$thold_snmp_data['eventDeviceIp'] = gethostbyname($h['hostname']);

							$thold_snmp_data['eventNotificationType'] = ST_NOTIFYRS + 1;

							thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
						}

						if ($thold_data['reset_ack'] == 'on') {
							db_execute_prepared('UPDATE thold_data
								SET acknowledgment = ""
								WHERE id = ?',
								array($thold_data['id']));
						}

						// Set the return to normal time
						db_execute_prepared('UPDATE thold_data SET lastchanged = NOW() WHERE id = ?', array($thold_data['id']));

						thold_log(array(
							'type'            => 1,
							'time'            => time(),
							'host_id'         => $thold_data['host_id'],
							'local_graph_id'  => $thold_data['local_graph_id'],
							'threshold_id'    => $thold_data['id'],
							'threshold_value' => '',
							'current'         => $thold_data['lastread'],
							'status'          => ST_RESTORAL,
							'description'     => ($maint_dev ? $subject . '. ' .  __('Only logging, maint device', 'thold') : $subject),
							'emails'          => $alert_emails,
							'bcc_emails'      => $alert_bcc_emails)
						);
					}
				}

				$thold_data['bl_fail_count'] = 0;

				break;
			case 1: /* value is below calculated threshold */
			case 2: /* value is above calculated threshold */
				if (!$maint_dev) {
					$thold_data['bl_fail_count']++;
				}

				$breach_up   = ($thold_data['bl_alert'] == STAT_HI);
				$breach_down = ($thold_data['bl_alert'] == STAT_LO);

				thold_debug('Threshold Baseline check breached');

				/* re-alert? */
				$ra_modulo = ($thold_data['repeat_alert'] == '' ? $realert : $thold_data['repeat_alert']);

				$ra = ($thold_data['bl_fail_count'] > $bl_fail_trigger && !empty($ra_modulo) && ($thold_data['bl_fail_count'] % $ra_modulo) == 0);

				if ($thold_data['bl_fail_count'] == $bl_fail_trigger || $ra) {
					if (!$ra) {
						db_execute_prepared('UPDATE thold_data
							SET lastchanged = NOW()
							WHERE id = ?',
							array($thold_data['id']));

						if ($thold_data['persist_ack'] == 'on' || $thold_data['reset_ack'] == 'on') {
							db_execute_prepared('UPDATE thold_data
								SET acknowledgment = "on"
								WHERE id = ?',
								array($thold_data['id']));
						}
					}

					// If this is a realert and the operator has reset the ack, don't notify
					if ($ra && $thold_data['reset_ack'] == 'on' && $thold_data['acknowledgment'] == '') {
						$suspend_notify = true;
					} else {
						$suspend_notify = false;
					}

					$subject = get_email_subject('ALERT', false, $lastread, $ra, $breach_up, $thold_data);

					if (!$suspend_notify && !$maint_dev) {
						thold_debug('Alerting is necessary');

						if ($syslog) {
							logger($subject, $url, $syslog_priority, $syslog_facility);
						}

						$notify_list_id = $thold_data['notify_alert'];
						$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

						if (trim($alert_emails) != '' && $thold_data['acknowledgment'] == '') {
							$message = get_thold_alert_text($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id']);

							thold_mail($alert_emails, $alert_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file, $thold_data['graph_timespan']);
						}

						if ($notify_different) {
							$notify_list_id = $thold_data['notify_warning'];
							$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

							if (trim($warning_emails) != '' && $thold_data['acknowledgment'] == '') {
								thold_mail($warning_emails, $warning_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file);
							}
						}

						thold_command_execution($thold_data, $h, $breach_up, $breach_down, false);

						$save = array(
							'class'               => 'blalert',
							'thold_data'          => $thold_data,
							'subject'             => $subject,
							'repeat_alert'        => $ra,
							'host_data'           => $h,
							'breach_up'           => $breach_up,
							'breach_down'         => $breach_down
						);

						api_plugin_hook_function('thold_action', $save);

						if ($thold_snmp_traps) {
							$thold_snmp_data = get_thold_snmp_data($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread']);

							$thold_snmp_data['eventClass']            = 3;
							$thold_snmp_data['eventSeverity']         = $thold_data['snmp_event_severity'];
							$thold_snmp_data['eventStatus']           = $thold_data['bl_alert']+1;
							$thold_snmp_data['eventRealertStatus']    = ($ra ? ($breach_up ? 3:2) :1);
							$thold_snmp_data['eventNotificationType'] = ($ra ? ST_NOTIFYRA:ST_NOTIFYAL)+1;
							$thold_snmp_data['eventFailCount']        = $thold_data['bl_fail_count'];
							$thold_snmp_data['eventFailDuration']     = $thold_data['bl_fail_count'] * $cacti_polling_interval;
							$thold_snmp_data['eventFailCountTrigger'] = $bl_fail_trigger;
							$thold_snmp_data['eventDeviceIp']         = gethostbyname($h['hostname']);
							$thold_snmp_data['eventDescription']      = str_replace(
							    array('<FAIL_COUNT>', '<FAIL_DURATION>'),
							    array($thold_snmp_data['eventFailCount'], $thold_snmp_data['eventFailDuration']),
							    $thold_snmp_data['eventDescription']
							);

							thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
						}
					}

					thold_log(array(
						'type'            => 1,
						'time'            => time(),
						'host_id'         => $thold_data['host_id'],
						'local_graph_id'  => $thold_data['local_graph_id'],
						'threshold_id'    => $thold_data['id'],
						'threshold_value' => ($breach_up ? $thold_data['bl_pct_up'] : $thold_data['bl_pct_down']),
						'current'         => $thold_data['lastread'],
						'status'          => ($ra ? ST_NOTIFYRA:ST_NOTIFYAL),
						'description'     => ($maint_dev ? $subject . '. ' . __('Only logging, maint device', 'thold') : $subject),
						'emails'          => $alert_emails,
						'bcc_emails'      => $alert_bcc_emails)
					);
				} else {
					$subject = 'Thold Baseline Cache Log';

					thold_log(array(
						'type'            => 1,
						'time'            => time(),
						'host_id'         => $thold_data['host_id'],
						'local_graph_id'  => $thold_data['local_graph_id'],
						'threshold_id'    => $thold_data['id'],
						'threshold_value' => ($breach_up ? $thold_data['bl_pct_up'] : $thold_data['bl_pct_down']),
						'current'         => $thold_data['lastread'],
						'status'          => ST_TRIGGERA,
						'description'     => ($maint_dev ? $subject . '. ' . __('Only logging, maint device', 'thold') : $subject),
						'emails'          => $alert_emails,
						'bcc_emails'      => $alert_bcc_emails)
					);
				}

				break;
		}

		db_execute_prepared("UPDATE thold_data
			SET thold_alert = 0,
			thold_fail_count = 0,
			bl_alert = ?,
			bl_fail_count = ?,
			thold_low = ?,
			thold_hi = ?,
			bl_thold_valid = ?
			WHERE id = ?",
			array(
				$thold_data['bl_alert'],
				$thold_data['bl_fail_count'],
				$thold_data['thold_low'],
				$thold_data['thold_hi'],
				$thold_data['bl_thold_valid'],
				$thold_data['id']
			)
		);

		break;
	case 2:	/* time based */
		if (is_numeric($thold_data['lastread'])) {
			$breach_up           = ($thold_data['time_hi']          != '' && $thold_data['lastread'] > $thold_data['time_hi']);
			$breach_down         = ($thold_data['time_low']         != '' && $thold_data['lastread'] < $thold_data['time_low']);
			$warning_breach_up   = ($thold_data['time_warning_hi']  != '' && $thold_data['lastread'] > $thold_data['time_warning_hi']);
			$warning_breach_down = ($thold_data['time_warning_low'] != '' && $thold_data['lastread'] < $thold_data['time_warning_low']);
		} else {
			$breach_up = $breach_down = $warning_breach_up = $warning_breach_down = false;
		}

		$step = db_fetch_cell_prepared('SELECT rrd_step
			FROM data_template_data
			WHERE local_data_id = ?',
			array($thold_data['local_data_id']));

		/* alerts */
		$trigger  = $thold_data['time_fail_trigger'];
		$time     = time() - ($thold_data['time_fail_length'] * $step);

		$failures = db_fetch_cell_prepared('SELECT COUNT(id)
			FROM plugin_thold_log
			WHERE threshold_id = ?
			AND status IN (?, ?, ?)
			AND time > ?',
			array(
				$thold_data['id'],
				ST_TRIGGERA,
				ST_NOTIFYRA,
				ST_NOTIFYAL,
				$time
			)
		);

		/* warnings */
		$warning_trigger  = $thold_data['time_warning_fail_trigger'];
		$warning_time     = time() - ($thold_data['time_warning_fail_length'] * $step);

		$warning_failures = db_fetch_cell_prepared('SELECT COUNT(id)
			FROM plugin_thold_log
			WHERE threshold_id = ?
			AND status IN (?, ?, ?)
			AND time > ?',
			array(
				$thold_data['id'],
				ST_NOTIFYWA,
				ST_TRIGGERW,
				ST_NOTIFYRAW,
				$warning_time
			)
		) + $failures;

		if ($breach_up || $breach_down) {
			$notify = false;

			thold_debug('Threshold Time Based check breached HI:' . $thold_data['time_hi'] . ' LOW:' . $thold_data['time_low'] . ' VALUE:' . $thold_data['lastread']);

			$thold_data['thold_alert']      = ($breach_up ? STAT_HI:STAT_LO);
			$thold_data['thold_fail_count'] = $failures;

			/**
			 * we should only re-alert X minutes after last email, not every 5 pollings, etc...
			 * re-alert?
			 */
			$realerttime   = ($thold_data['repeat_alert']-1) * $step;
			$lastemailtime = db_fetch_cell_prepared('SELECT time
				FROM plugin_thold_log
				WHERE threshold_id = ?
				AND status IN (?, ?)
				ORDER BY time DESC
				LIMIT 1',
				array($thold_data['id'], ST_NOTIFYRA, ST_NOTIFYAL));

			$ra = ($failures > $trigger && $thold_data['repeat_alert'] && !empty($lastemailtime) && ($lastemailtime+$realerttime <= time()));

			if (!$maint_dev) {
				$failures++;
			}

			thold_debug("Alert Time:'$time', Alert Trigger:'$trigger', Alert Failures:'$failures', RealertTime:'$realerttime', LastTime:'$lastemailtime', RA:'$ra', Diff:'" . ($realerttime+$lastemailtime) . "'<'". time() . "'");

			if (($failures == $trigger || $ra) && !$maint_dev) {
				$notify = true;
			}

			if ($notify && !$ra) {
				db_execute_prepared('UPDATE thold_data
					SET lastchanged = NOW()
					WHERE id = ?',
					array($thold_data['id']));

				if ($thold_data['persist_ack'] == 'on' || $thold_data['reset_ack'] == 'on') {
					db_execute_prepared('UPDATE thold_data
						SET acknowledgment = "on"
						WHERE id = ?',
						array($thold_data['id']));
				}
			}

			// If this is a realert and the operator has reset the ack, don't notify
			if ($notify && $ra && $thold_data['reset_ack'] == 'on' && $thold_data['acknowledgment'] == '') {
				$suspend_notify = true;
			} else {
				$suspend_notify = false;
			}

			if ($notify) {
				$trigger = false;
			} else {
				$trigger = true;
			}

			$subject = get_email_subject('ALERT', $trigger, $lastread, $ra, $breach_up, $thold_data);

			if ($notify) {
				if (!$suspend_notify && !$maint_dev) {
					thold_debug('Alerting is necessary');

					if ($syslog) {
						logger($subject, $url, $syslog_priority, $syslog_facility);
					}

					$notify_list_id = $thold_data['notify_alert'];
					$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

					if (trim($alert_emails) != '' && $thold_data['acknowledgment'] == '') {
						$message = get_thold_alert_text($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id']);

						thold_mail($alert_emails, $alert_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file, $thold_data['graph_timespan']);
					}

					if ($notify_different) {
						$notify_list_id = $thold_data['notify_warning'];
						$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

						if (trim($warning_emails) != '' && $thold_data['acknowledgment'] == '') {
							thold_mail($warning_emails, $warning_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file);
						}
					}

					thold_command_execution($thold_data, $h, $breach_up, $breach_down, false);

					$save = array(
						'class'               => 'alert',
						'thold_data'          => $thold_data,
						'subject'             => $subject,
						'repeat_alert'        => $ra,
						'host_data'           => $h,
						'breach_up'           => $breach_up,
						'breach_down'         => $breach_down,
						'warning_breach_up'   => $warning_breach_up,
						'warning_breach_down' => $warning_breach_down
					);

					api_plugin_hook_function('thold_action', $save);

					if ($thold_snmp_traps) {
						$thold_snmp_data = get_thold_snmp_data($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread']);

						$thold_snmp_data['eventClass']            = 3;
						$thold_snmp_data['eventSeverity']         = $thold_data['snmp_event_severity'];
						$thold_snmp_data['eventStatus']           = $thold_data['thold_alert']+1;
						$thold_snmp_data['eventRealertStatus']    = ($ra ? ($breach_up ? 3:2) :1);
						$thold_snmp_data['eventNotificationType'] = ($ra ? ST_NOTIFYRA:ST_NOTIFYAL)+1;
						$thold_snmp_data['eventFailCount']        = $failures;
						$thold_snmp_data['eventFailCountTrigger'] = $trigger;
						$thold_snmp_data['eventDeviceIp']         = gethostbyname($h['hostname']);
						$thold_snmp_data['eventDescription']      = str_replace('<FAIL_COUNT>', $thold_snmp_data['eventFailCount'], $thold_snmp_data['eventDescription']);

						thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
					}
				}

				thold_log(array(
					'type'            => 2,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($breach_up ? $thold_data['time_hi'] : $thold_data['time_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ($ra ? ST_NOTIFYRA:ST_NOTIFYAL),
					'description'     => ($maint_dev ? $subject . '. ' . __('Only logging, maint device', 'thold') : $subject),
					'emails'          => $alert_emails,
					'bcc_emails'      => $alert_bcc_emails)
				);
			} else {
				thold_log(array(
					'type'            => 2,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($breach_up ? $thold_data['time_hi'] : $thold_data['time_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ST_TRIGGERA,
					'description'     => ($maint_dev ? $subject . '. ' . __('Only logging, maint device', 'thold') : $subject),
					'emails'          => $alert_emails,
					'bcc_emails'      => $alert_bcc_emails)
				);
			}

			db_execute_prepared('UPDATE thold_data
				SET thold_alert = ?,
				thold_fail_count = ?
				WHERE id = ?',
				array($thold_data['thold_alert'], $failures, $thold_data['id']));

		} elseif ($warning_breach_up || $warning_breach_down) {
			$notify = false;

			$thold_data['thold_alert']              = ($warning_breach_up ? STAT_HI:STAT_LO);
			$thold_data['thold_warning_fail_count'] = $warning_failures;

			/**
			 * we should only re-alert X minutes after last email, not every 5 pollings, etc...
			 * re-alert?
			 */
			$realerttime   = ($thold_data['time_warning_fail_length']-1) * $step;
			$lastemailtime = db_fetch_cell_prepared('SELECT time
				FROM plugin_thold_log
				WHERE threshold_id = ?
				AND status IN (?, ?)
				ORDER BY time DESC
				LIMIT 1',
				array($thold_data['id'], ST_NOTIFYRAW, ST_NOTIFYWA));

			$ra = ($warning_failures > $warning_trigger && $thold_data['time_warning_fail_length'] && !empty($lastemailtime) && ($lastemailtime+$realerttime <= time()));

			if (!$maint_dev) {
				$warning_failures++;
			}

			thold_debug("Warn Time:'$warning_time', Warn Trigger:'$warning_trigger', Warn Failures:'$warning_failures', RealertTime:'$realerttime', LastTime:'$lastemailtime', RA:'$ra', Diff:'" . ($realerttime+$lastemailtime) . "'<'". time() . "'");

			if (($warning_failures == $warning_trigger || $ra) && !$maint_dev) {
				$notify = true;
			}

			if ($notify && !$ra) {
				db_execute_prepared('UPDATE thold_data
					SET lastchanged = NOW()
					WHERE id = ?',
					array($thold_data['id']));

				if ($thold_data['persist_ack'] == 'on' || $thold_data['reset_ack'] == 'on') {
					db_execute_prepared('UPDATE thold_data
						SET acknowledgment = "on"
						WHERE id = ?',
						array($thold_data['id']));
				}
			}

			// If this is a realert and the operator has reset the ack, don't notify
			if ($notify && $ra && $thold_data['reset_ack'] == 'on' && $thold_data['acknowledgment'] == '') {
				$suspend_notify = true;
			} else {
				$suspend_notify = false;
			}

			if ($notify) {
				$trigger = false;
			} else {
				$trigger = true;
			}

			$ra = ($warning_failures > $warning_trigger) ? true:false;

			$subject = get_email_subject('WARNING', $trigger, $lastread, $ra, $warning_breach_up, $thold_data);

			if ($notify) {
				if (!$suspend_notify && !$maint_dev) {
					if ($syslog) {
						logger($subject, $url, $syslog_priority, $syslog_facility);
					}

					if (trim($warning_emails) != '' && $thold_data['acknowledgment'] == '') {
						$message = get_thold_warning_text($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id']);

						$notify_list_id = $thold_data['notify_warning'];
						$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

						thold_mail($warning_emails, $warning_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file, $thold_data['graph_timespan']);
					}

					$save = array(
						'class'               => 'warn',
						'thold_data'          => $thold_data,
						'subject'             => $subject,
						'repeat_alert'        => $ra,
						'host_data'           => $h,
						'breach_up'           => $breach_up,
						'breach_down'         => $breach_down,
						'warning_breach_up'   => $warning_breach_up,
						'warning_breach_down' => $warning_breach_down
					);

					api_plugin_hook_function('thold_action', $save);

					if ($thold_snmp_traps && $thold_snmp_warning_traps) {
						$thold_snmp_data = get_thold_snmp_data($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread']);

						$thold_snmp_data['eventClass']            = 2;
						$thold_snmp_data['eventSeverity']         = $thold_data['snmp_event_warning_severity'];
						$thold_snmp_data['eventStatus']           = $thold_data['thold_alert']+1;
						$thold_snmp_data['eventRealertStatus']    = ($ra ? ($warning_breach_up ? 3:2) :1);
						$thold_snmp_data['eventNotificationType'] = ($ra ? ST_NOTIFYRAW:ST_NOTIFYWA)+1;
						$thold_snmp_data['eventFailCount']        = $warning_failures;
						$thold_snmp_data['eventFailCountTrigger'] = $warning_trigger;
						$thold_snmp_data['eventDeviceIp']         = gethostbyname($h['hostname']);
						$thold_snmp_data['eventDescription']      = str_replace('<FAIL_COUNT>', $thold_snmp_data['eventFailCount'], $thold_snmp_data['eventDescription']);

						thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
					}
				}

				thold_log(array(
					'type'            => 2,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($breach_up ? $thold_data['time_hi'] : $thold_data['time_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ($ra ? ST_NOTIFYRAW:ST_NOTIFYWA),
					'description'     => ($maint_dev ? $subject . '. ' . __('Only logging, maint device', 'thold') : $subject),
					'emails'          => $alert_emails,
					'bcc_emails'      => $alert_bcc_emails)
				);

			} elseif ($alertstat != 0 && $warning_failures < $warning_trigger && $failures < $trigger) {
				$subject = get_email_subject('ALERT > WARNING', false, $lastread, false, $warning_breach_up, $thold_data);

				thold_log(array(
					'type'            => 2,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($warning_breach_up ? $thold_data['time_hi'] : $thold_data['time_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ST_NOTIFYRAW,
					'description'     => ($maint_dev ? $subject . '. ' . __('Only logging, maint device', 'thold') : $subject),
					'emails'          => $alert_emails,
					'bcc_emails'      => $alert_bcc_emails)
				);
			} else {
				thold_log(array(
					'type'            => 2,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => ($warning_breach_up ? $thold_data['time_hi'] : $thold_data['time_low']),
					'current'         => $thold_data['lastread'],
					'status'          => ST_TRIGGERW,
					'description'     => ($maint_dev ? $subject . '. ' . __('Only logging, maint device', 'thold') : $subject),
					'emails'          => $warning_emails,
					'bcc_emails'      => $warning_bcc_emails)
				);
			}

			db_execute_prepared('UPDATE thold_data
				SET thold_alert = ?,
				thold_warning_fail_count = ?,
				thold_fail_count = ?
				WHERE id = ?',
				array($thold_data['thold_alert'], $warning_failures, $failures, $thold_data['id']));
		} else {
			thold_debug('Threshold Time Based check is Normal HI:' . $thold_data['time_hi'] . ' LOW:' . $thold_data['time_low'] . ' VALUE:' . $thold_data['lastread']);

			$subject = get_email_subject('NORMAL', false, $lastread, false, false, $thold_data);

			if ($alertstat != 0 && $warning_failures < $warning_trigger && $thold_data['restored_alert'] != 'on') {
				if (!$maint_dev) {
					if ($syslog) {
						logger($subject, $url, $syslog_priority, $syslog_facility);
					}

					$notify_list_id = $thold_data['notify_warning'];
					$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

					if (trim($warning_emails) != '' && $thold_data['restored_alert'] != 'on') {
						$message = get_thold_restoral_text($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id']);

						thold_mail($warning_emails, $warning_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file, $thold_data['graph_timespan']);
					}

					$save = array(
						'class'               => 'warn2normal',
						'thold_data'          => $thold_data,
						'subject'             => $subject,
						'host_data'           => $h,
						'breach_up'           => $breach_up,
						'breach_down'         => $breach_down,
						'warning_breach_up'   => $warning_breach_up,
						'warning_breach_down' => $warning_breach_down
					);

					api_plugin_hook_function('thold_action', $save);

					if ($thold_snmp_traps && $thold_snmp_normal_traps) {
						$thold_snmp_data = get_thold_snmp_data($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread']);

						$thold_snmp_data['eventClass']            = 1;
						$thold_snmp_data['eventSeverity']         = 1;
						$thold_snmp_data['eventStatus']           = 1;
						$thold_snmp_data['eventNotificationType'] = ST_NOTIFYRS+1;
						$thold_snmp_data['eventDeviceIp']         = gethostbyname($h['hostname']);

						thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
					}
				}

				thold_log(array(
					'type'            => 2,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => '',
					'current'         => $thold_data['lastread'],
					'status'          => ST_NOTIFYRS,
					'description'     => ($maint_dev ? $subject . '. ' . __('Only logging, maint device', 'thold') : $subject),
					'emails'          => $warning_emails,
					'bcc_emails'      => $warning_bcc_emails)
				);

				db_execute_prepared('UPDATE thold_data
					SET thold_alert = 0,
					lastchanged = NOW(),
					thold_warning_fail_count = ?,
					thold_fail_count = ?
					WHERE id = ?',
					array($warning_failures, $failures, $thold_data['id']));

				if ($thold_data['reset_ack'] == 'on') {
					db_execute_prepared('UPDATE thold_data
						SET acknowledgment = ""
						WHERE id = ?',
						array($thold_data['id']));
				}
			} elseif ($alertstat != 0 && $failures < $trigger && $thold_data['restored_alert'] != 'on') {
				$subject = get_email_subject('NORMAL', false, $lastread, false, false, $thold_data);

				if (!$maint_dev) {
					if ($syslog) {
						logger($subject, $url, $syslog_priority, $syslog_facility);
					}

					$notify_list_id = $thold_data['notify_alert'];
					$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

					if (trim($alert_emails) != '' && $thold_data['restored_alert'] != 'on') {
						$message = get_thold_restoral_text($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id']);

						thold_mail($alert_emails, $alert_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file, $thold_data['graph_timespan']);
					}

					if ($notify_different) {
						$notify_list_id = $thold_data['notify_warning'];
						$format_file    = thold_get_thold_notification_format_file($thold_data['id'], $notify_list_id);

						if (trim($warning_emails) != '' && $thold_data['acknowledgment'] == '') {
							thold_mail($warning_emails, $warning_bcc_emails, '', $subject, $message, $file_array, '', $notify_list_id, $h, $format_file);
						}
					}

					thold_command_execution($thold_data, $h, false, false, true);

					$save = array(
						'class'               => 'alert2normal',
						'thold_data'          => $thold_data,
						'subject'             => $subject,
						'host_data'           => $h,
						'breach_up'           => $breach_up,
						'breach_down'         => $breach_down,
						'warning_breach_up'   => $warning_breach_up,
						'warning_breach_down' => $warning_breach_down
					);

					api_plugin_hook_function('thold_action', $save);

					if ($thold_snmp_traps && $thold_snmp_normal_traps) {
						$thold_snmp_data = get_thold_snmp_data($thold_data['data_source_name'], $thold_data, $h, $thold_data['lastread']);

						$thold_snmp_data['eventClass']            = 1;
						$thold_snmp_data['eventSeverity']         = 1;
						$thold_snmp_data['eventStatus']           = 1;
						$thold_snmp_data['eventNotificationType'] = ST_NOTIFYRS+1;
						$thold_snmp_data['eventDeviceIp']         = gethostbyname($h['hostname']);

						thold_snmptrap($thold_snmp_data, SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite);
					}
				}

				thold_log(array(
					'type'            => 2,
					'time'            => time(),
					'host_id'         => $thold_data['host_id'],
					'local_graph_id'  => $thold_data['local_graph_id'],
					'threshold_id'    => $thold_data['id'],
					'threshold_value' => '',
					'current'         => $thold_data['lastread'],
					'status'          => ST_NOTIFYRS,
					'description'     => ($maint_dev ? $subject . '. ' . __('Only logging, maint device', 'thold') : $subject),
					'emails'          => $alert_emails,
					'bcc_emails'      => $alert_bcc_emails)
				);

				db_execute_prepared('UPDATE thold_data
					SET thold_alert = 0,
					lastchanged = NOW(),
					thold_warning_fail_count = ?,
					thold_fail_count = ?
					WHERE id = ?',
					array($warning_failures, $failures, $thold_data['id']));

				if ($thold_data['reset_ack'] == 'on') {
					db_execute_prepared('UPDATE thold_data
						SET acknowledgment=""
						WHERE id = ?',
						array($thold_data['id']));
				}
			} else {
				db_execute_prepared('UPDATE thold_data
					SET thold_fail_count = ?,
					thold_warning_fail_count = ?
					WHERE id = ?',
					array($failures, $warning_failures, $thold_data['id']));
			}
		}

		break;
	}
}

function get_email_subject($phase, $trigger, $lastread, $ra, $breach_up, &$thold_data) {
	$peralert = read_config_option('thold_enable_per_thold_body');

	/* setup base units */
	$baseu = db_fetch_cell_prepared('SELECT base_value
		FROM graph_templates_graph
		WHERE local_graph_id = ?',
		array($thold_data['local_graph_id']));

	if ($thold_data['data_type'] == 2) {
		$suffix = false;
	} else {
		$suffix = true;
	}

	$show_datasource = thold_datasource_required(thold_get_cached_name($thold_data), $thold_data['data_source_name']);
	$show_units      = ($thold_data['show_units'] ? true : false);
	$units_suffix    = $thold_data['units_suffix'];
	$decimals        = $thold_data['decimals'] >= 0 ? $thold_data['decimals']:2;

	$hostname = db_fetch_cell_prepared('SELECT hostname
		FROM host
		WHERE id = ?',
		array($thold_data['host_id']));

	switch($thold_data['thold_type']) {
		case '0': // High Low
			if ($phase == 'ALERT') {
				if ($breach_up) {
					$value = $thold_data['thold_hi'];
				} else {
					$value = $thold_data['thold_low'];
				}
			} elseif ($phase == 'WARNING') {
				if ($breach_up) {
					$value = $thold_data['thold_warning_hi'];
				} else {
					$value = $thold_data['thold_warning_low'];
				}
			} elseif ($phase == 'ALERT > WARNING') {
				if ($breach_up) {
					$value = $thold_data['thold_warning_hi'];
				} else {
					$value = $thold_data['thold_warning_low'];
				}
			}

			break;
		case '1': // Baseline
			if ($breach_up) {
				$value = $thold_data['thold_hi'];
			} else {
				$value = $thold_data['thold_low'];
			}

			break;
		case '2': // Time Based
			if ($phase == 'ALERT') {
				if ($breach_up) {
					$value = $thold_data['time_hi'];
				} else {
					$value = $thold_data['time_low'];
				}
			} elseif ($phase == 'WARNING') {
				if ($breach_up) {
					$value = $thold_data['time_warning_hi'];
				} else {
					$value = $thold_data['time_warning_low'];
				}
			} elseif ($phase == 'ALERT > WARNING') {
				if ($breach_up) {
					$value = $thold_data['time_warning_hi'];
				} else {
					$value = $thold_data['time_warning_low'];
				}
			}

			break;
	}

	if ($phase == 'NORMAL') {
		if ($thold_data['email_subject_restoral'] == '' || $peralert == '') {
			return 'NORMAL: '. thold_get_cached_name($thold_data) .
				($show_datasource ? ' [' . $thold_data['data_source_name'] . ']' : '') .
				' restored to Normal Threshold with value ' .
				thold_format_number($thold_data['lastread'], $decimals, $baseu, $suffix, $show_units, $units_suffix);
		} else {
			$find = array(
				'<PHASE>',
				'<THOLDVALUE>',
				'<CURRENTVALUE>',
				'<THRESHOLDNAME>',
				'<DSNAME>',
				'<HOSTNAME>',
				'<BREACHUP>',
				'<REALERT>'
			);

			$replace = array(
				$phase,
				$value,
				$lastread,
				thold_get_cached_name($thold_data),
				$thold_data['data_source_name'],
				$hostname,
				($breach_up ? 'above':'below'),
				($ra ? 'is still':'went'),
			);
		}
	} elseif ($phase == 'WARNING' || $phase == 'ALERT > WARNING') {
		if ($trigger) {
			$phase = 'TRIGGER';
		}

		if ($thold_data['email_subject_warn'] == '' || $peralert == '') {
			if ($phase == 'ALERT > WARNING') {
				return $phase . ': ' . thold_get_cached_name($thold_data) .
					($show_datasource ? ' [' . $thold_data['data_source_name'] . ']' : '') .
					' changed to Warning Threshold with value ' .
					thold_format_number($thold_data['lastread'], $decimals, $baseu, $suffix, $show_units, $units_suffix);
			} else {
				return $phase . ': ' . thold_get_cached_name($thold_data) .
					($show_datasource ? ' [' . $thold_data['data_source_name'] . ']' : '') . ' ' .
					($ra ? 'is still' : 'went') . ' ' .
					($breach_up ? 'above' : 'below') .
					($thold_data['thold_type'] == 1 ? ' Baseline Threshold of ':' Threshold of ') .
					($breach_up ? thold_format_number($value, $decimals, $baseu, $suffix, $show_units, $units_suffix):
					thold_format_number($value, $decimals, $baseu, $suffix, $show_units, $units_suffix)) . ' with ' .
					thold_format_number($lastread, $decimals, $baseu, $suffix, $show_units, $units_suffix);
			}
		} else {
			$find = array(
				'<PHASE>',
				'<THRESHOLDVALUE>',
				'<CURRENTVALUE>',
				'<THRESHOLDNAME>',
				'<DSNAME>',
				'<HOSTNAME>',
				'<BREACHUP>',
				'<REALERT>'
			);

			$replace = array(
				$phase,
				$value,
				$lastread,
				thold_get_cached_name($thold_data),
				$thold_data['data_source_name'],
				$hostname,
				($breach_up ? 'above':'below'),
				($ra ? 'is still':'went'),
			);

			return str_replace($find, $replace, $thold_data['email_subject_warn']);
		}
	} else {
		if ($trigger) {
			$phase = 'TRIGGER';
		}

		if ($thold_data['email_subject'] == '' || $peralert == '') {
			return $phase . ': ' . thold_get_cached_name($thold_data) .
				($show_datasource ? ' [' . $thold_data['data_source_name'] . ']' : '') . ' ' .
				($ra ? 'is still' : 'went') . ' ' .
				($breach_up ? 'above' : 'below') .
				($thold_data['thold_type'] == 1 ? ' Baseline Threshold of ':' Threshold of ') .
				($breach_up ? thold_format_number($value, $decimals, $baseu, $suffix, $show_units, $units_suffix):
				thold_format_number($value, $decimals, $baseu, $suffix, $show_units, $units_suffix)) . ' with ' .
				thold_format_number($lastread, $decimals, $baseu, $suffix, $show_units, $units_suffix);
		} else {
			$find = array(
				'<PHASE>',
				'<THRESHOLDVALUE>',
				'<CURRENTVALUE>',
				'<THRESHOLDNAME>',
				'<DSNAME>',
				'<HOSTNAME>',
				'<BREACHUP>',
				'<REALERT>'
			);

			$replace = array(
				$phase,
				$value,
				$lastread,
				thold_get_cached_name($thold_data),
				$thold_data['data_source_name'],
				$hostname,
				($breach_up ? 'above':'below'),
				($ra ? 'is still':'went'),
			);

			return str_replace($find, $replace, $thold_data['email_subject']);
		}
	}
}

function get_thold_snmp_data($data_source_name, $thold, $h, $currentval) {
	global $thold_types;

	// Do some replacement of variables
	$thold_snmp_data = array(
		'eventDateRFC822'			=> date(DATE_RFC822),
		'eventClass'				=> 3,						// default - see CACTI-THOLD-MIB
		'eventSeverity'				=> 3,						// default - see CACTI-THOLD-MIB
		'eventCategory'				=> ($thold['snmp_event_category'] ? $thold['snmp_event_category'] : ''),
		'eventSource'				=> $thold['name_cache'],
		'eventDescription'			=> '',						// default - see CACTI-THOLD-MIB
		'eventDevice'				=> $h['hostname'],
		'eventDataSource'			=> $data_source_name,
		'eventCurrentValue'			=> $currentval,
		'eventHigh'					=> ($thold['thold_type'] == 0 ? $thold['thold_hi'] : ($thold['thold_type'] == 2 ? $thold['time_warning_hi'] : '')),
		'eventLow'					=> ($thold['thold_type'] == 0 ? $thold['thold_low'] : ($thold['thold_type'] == 2 ? $thold['time_warning_low'] : '')),
		'eventNotificationType'		=> 5,						// default - see CACTI-THOLD-MIB
		'eventStatus'				=> 3,						// default - see CACTI-THOLD-MIB
		'eventRealertStatus'		=> 1,						// default - see CACTI-THOLD-MIB
		'eventFailDuration'			=> 0,						// default - see CACTI-THOLD-MIB
		'eventFailCount'			=> 0,						// default - see CACTI-THOLD-MIB
		'eventFailDurationTrigger'	=> 0,						// default - see CACTI-THOLD-MIB
		'eventFailCountTrigger'		=> 0,						// default - see CACTI-THOLD-MIB
	);

	if (isset($thold_types[$thold['thold_type']])) {
		$thold_snmp_data['eventThresholdType'] = $thold['thold_type'];
	} else {
		$thold_snmp_data['eventThresholdType'] = 1;
	}

	// substitute snmp query data
	$snmp_event_description = thold_expand_string($thold, $thold['snmp_event_description']);

	$snmp_event_description = thold_str_replace('<THRESHOLDNAME>', $thold_snmp_data['eventSource'], $snmp_event_description);
	$snmp_event_description = thold_str_replace('<HOSTNAME>', $thold_snmp_data['eventDevice'], $snmp_event_description);
	$snmp_event_description = thold_str_replace('<TEMPLATE_ID>', ($thold['thold_template_id'] ? $thold['thold_template_id'] : 'none'), $snmp_event_description);
	$snmp_event_description = thold_str_replace('<TEMPLATE_NAME>', (isset($thold['name_cache']) ? $thold['name_cache'] : 'none'), $snmp_event_description);
	$snmp_event_description = thold_str_replace('<THR_TYPE>', $thold_snmp_data['eventThresholdType'], $snmp_event_description);
	$snmp_event_description = thold_str_replace('<DS_NAME>', $thold_snmp_data['eventDataSource'], $snmp_event_description);
	$snmp_event_description = thold_str_replace('<HI>', $thold_snmp_data['eventHigh'], $snmp_event_description);
	$snmp_event_description = thold_str_replace('<LOW>', $thold_snmp_data['eventLow'], $snmp_event_description);
	$snmp_event_description = thold_str_replace('<EVENT_CATEGORY>', $thold_snmp_data['eventCategory'], $snmp_event_description);
	$thold_snmp_data['eventDescription'] = $snmp_event_description;

	return $thold_snmp_data;
}

function thold_expand_string($thold_data, $string) {
	global $config;

	include_once($config['library_path'] . '/variables.php');

	$str = $string;

	// Handle the blank string case
	if ($str == '') {
		if (isset($thold_data['thold_template_id']) && $thold_data['thold_template_id'] > 0) {
			$str = db_fetch_cell_prepared('SELECT suggested_name
				FROM thold_template
				WHERE id = ?',
				array($thold_data['thold_template_id']));

			if ($str == '') {
				$str = '|data_source_description| [|data_source_name|]';
			}
		} elseif (isset($thold_data['data_source_name']) && $thold_data['data_source_name'] > 0) {
			$str = thold_get_default_suggested_name(array('data_source_name' => $data_source_name), 0);
		}
	}

	// Do core replacements
	if (cacti_sizeof($thold_data) && isset($thold_data['local_graph_id']) && isset($thold_data['local_data_id'])) {
		$lg = db_fetch_row_prepared('SELECT *
			FROM graph_local
			WHERE id = ?',
			array($thold_data['local_graph_id']));

		if (cacti_sizeof($lg)) {
			// Handle speed queries replacements first
			if (strpos($str, '|query_ifHighSpeed|') !== false) {
				$value = thold_substitute_snmp_query_data('|query_ifHighSpeed|', $lg['host_id'], $lg['snmp_query_id'], $lg['snmp_index'], read_config_option('max_data_query_field_length'));

				/* if we are trying to replace 10GE of some odd data */
				if (!is_numeric($value) || $value == 0) {
					$value = read_config_option('thold_empty_if_speed_default');
				}

				$str = str_replace('|query_ifHighSpeed|', $value, $str);
			} elseif (strpos($str, '|query_ifSpeed|') !== false) {
				$value = thold_substitute_snmp_query_data('|query_ifSpeed|', $lg['host_id'], $lg['snmp_query_id'], $lg['snmp_index'], read_config_option('max_data_query_field_length'));

				if (!is_numeric($value) || $value == 0) {
					$value = read_config_option('thold_empty_if_speed_default');
				}

				$str = str_replace('|query_ifSpeed|', $value, $str);
			}

			$str = expand_title($lg['host_id'], $lg['snmp_query_id'], $lg['snmp_index'], $str);
			$str = thold_substitute_custom_data($str, '|', '|', $thold_data['local_data_id']);

			$data = array(
				'str'         => $str,
				'thold_data'  => $thold_data,
				'local_graph' => $lg
			);

			$data = api_plugin_hook_function('thold_substitute_custom_data', $data);
			if (isset($data['str'])) {
				$str = $data['str'];
			}

			if (strpos($str, '|query_') !== false) {
				cacti_log("WARNING: Expression Replacement for '$str' in THold '" . $thold_data['name'] . "' Failed, A Reindex may be required!");
			}
		}

		if (strpos($str, '|host_') !== false && !empty($device_id)) {
			$str = thold_substitute_host_data($str, '|', '|', $device_id);
		}

		// Replace |graph_title|
		if (strpos($str, '|graph_title|') !== false) {
			$title = get_graph_title($thold_data['local_graph_id']);
			$str   = str_replace('|graph_title|', $title, $str);
		}

		// Replace |data_source_description|
		if (strpos($str, '|data_source_description|') !== false) {
			$data_source_desc = db_fetch_cell_prepared('SELECT name_cache
				FROM data_template_data
				WHERE local_data_id = ?',
				array($thold_data['local_data_id']));

			$str = str_replace('|data_source_description|', $data_source_desc, $str);
		}

		// Replace |data_source_name|
		if (strpos($str, '|data_source_name|') !== false) {
			$str = str_replace('|data_source_name|', $thold_data['data_source_name'], $str);
		}
	}

	return trim($str);
}

function thold_command_execution(&$thold_data, &$h, $breach_up, $breach_down, $breach_norm = false) {
	if (read_config_option('thold_enable_scripts') == 'on') {
		$output = array();
		$return = 0;
		$command_executed = false;
		$data_source_name = $thold_data['data_source_name'];
		$queue = read_config_option('thold_notification_queue');

		if ($breach_up && $thold_data['trigger_cmd_high'] != '') {
			$cmd = thold_replace_threshold_tags($thold_data['trigger_cmd_high'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id'], $data_source_name);

			$cmd = thold_expand_string($thold_data, $cmd);

			$environment = thold_set_environ($thold_data['trigger_cmd_high'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id'], $data_source_name);

			if ($queue == 'on') {
				$data = array(
					'command'     => $cmd,
					'data'        => $thold_data,
					'environment' => $environment
				);

				thold_notification_add('thold_cmd', $data, 'id', 0, $h);
			} else {
				exec($cmd, $output, $return);
			}

			$command_executed = true;
		} elseif ($breach_down && $thold_data['trigger_cmd_low'] != '') {
			$cmd = thold_replace_threshold_tags($thold_data['trigger_cmd_low'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id'], $data_source_name);
			$cmd = thold_expand_string($thold_data, $cmd);

			$environment = thold_set_environ($thold_data['trigger_cmd_high'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id'], $data_source_name);

			if ($queue == 'on') {
				$data = array(
					'command'     => $cmd,
					'data'        => $thold_data,
					'environment' => $environment
				);

				thold_notification_add('thold_cmd', $data, 'id', 0, $h);
			} else {
				exec($cmd, $output, $return);
			}

			$command_executed = true;
		} elseif ($breach_norm && $thold_data['trigger_cmd_norm'] != '') {
			$cmd = thold_replace_threshold_tags($thold_data['trigger_cmd_norm'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id'], $data_source_name);
			$cmd = thold_expand_string($thold_data, $cmd);

			$environment = thold_set_environ($thold_data['trigger_cmd_high'], $thold_data, $h, $thold_data['lastread'], $thold_data['local_graph_id'], $data_source_name);

			if ($queue == 'on') {
				$data = array(
					'command'     => $cmd,
					'data'        => $thold_data,
					'environment' => $environment
				);

				thold_notification_add('thold_cmd', $data, 'id', 0, $h);
			} else {
				exec($cmd, $output, $return);
			}

			$command_executed = true;
		}

		if ($queue == '' && $command_executed) {
			thold_process_command_output($output, $return, 'thold', $thold_data);
		}
	}
}

function thold_process_command_output(&$output, $return, $topic = 'thold_cmd', &$data = array()) {
	if ($topic == 'thold_cmd') {
		if (cacti_sizeof($output)) {
			if ($return > 0) {
				cacti_log('WARNING: Threshold command execution for TH[' . $data['id'] . '] returned ' . $return . ', with output ' . implode(', ', $output), false, 'THOLD');
			} else {
				cacti_log('NOTE: Threshold command execution for TH[' . $data['id'] . '] returned ' . $return . ', with output ' . implode(', ', $output), false, 'THOLD');
			}
		} else {
			if ($return > 0) {
				cacti_log('WARNING: Threshold command execution for TH[' . $data['id'] . '] returned ' . $return . ', with no output.', false, 'THOLD');
			} else {
				cacti_log('NOTE: Threshold command execution for TH[' . $data['id'] . '] returned ' . $return . ', with no output.', false, 'THOLD');
			}
		}
	} elseif ($topic == 'thold_dhost_cmd') {
		cacti_log("Device Down Command for Device[" . $data['id'] . '] Command[' . $command . '] ExitStatus[' . $return . '] Output[' . implode(' ', $output) . ']', false, 'THOLD');
	} elseif ($topic == 'thold_uhost_cmd') {
		cacti_log("Device Up Command for Device[" . $data['id'] . '] Command[' . $command . '] ExitStatus[' . $return . '] Output[' . implode(' ', $output) . ']', false, 'THOLD');
	}
}

function thold_putenv($name_value_pair, $reset = false) {
	static $env_queue = array();

	$queue = read_config_option('thold_notification_queue');

	if ($reset == true) {
		$env_queue = array();
	}

	if ($queue == 'on') {
		$env_queue[] = $name_value_pair;
	} else {
		putenv($name_value_pair);
	}

	return $env_queue;
}

function thold_set_environ($text, &$thold, &$h, $currentval, $local_graph_id, $data_source_name) {
	global $thold_types;

	$httpurl = read_config_option('base_url');

	$queue = read_config_option('thold_notification_queue');

	// Do some replacement of variables
	thold_putenv('THOLD_ID='          . $thold['id'], true);
	thold_putenv('THOLD_DATA_ID='     . $thold['local_data_id']);
	thold_putenv('THOLD_GRAPH_ID='    . $thold['local_graph_id']);
	thold_putenv('THOLD_DESCRIPTION=' . $h['description']);
	thold_putenv('THOLD_HOSTNAME='    . $h['hostname']);
	thold_putenv('THOLD_GRAPHID='     . $local_graph_id);

	thold_putenv('THOLD_CURRENTVALUE='    . $currentval);
	thold_putenv('THOLD_THRESHOLDNAME='   . $thold['name_cache']);
	thold_putenv('THOLD_DSNAME='          . $data_source_name);
	thold_putenv('THOLD_TIMEINSTATE='     . get_timeinstate($h));
	thold_putenv('THOLD_HOST_STATUS='     . $h['status']);
	thold_putenv('THOLD_HOST_FAIL_DATE='  . $h['status_fail_date']);
	thold_putenv('THOLD_HOST_REC_DATE='   . $h['status_rec_date']);
	thold_putenv('THOLD_HOST_LAST_ERROR=' . $h['status_last_error']);

	if (isset($thold_types[$thold['thold_type']])) {
		thold_putenv('THOLD_THOLDTYPE=' . $thold_types[$thold['thold_type']]);
	} else {
		thold_putenv('THOLD_THOLDTYPE=');
	}

	if ($thold['notes'] != '') {
		$notes = thold_replace_threshold_tags($thold['notes'], $thold, $h, $currentval, $local_graph_id, $data_source_name);
		thold_putenv('THOLD_NOTES='  . $notes);
	} else {
		thold_putenv('THOLD_NOTES='  . '');
	}

	if ($thold['external_id'] != '') {
		thold_putenv('THOLD_EXTERNAL_ID='  . $thold['external_id']);
	}

	thold_putenv('THOLD_DEVICENOTE=' . $thold['dnotes']);

	if ($thold['thold_type'] == 0) {
		thold_putenv('THOLD_HI='       . $thold['thold_hi']);
		thold_putenv('THOLD_LOW='      . $thold['thold_low']);
		thold_putenv('THOLD_TRIGGER='  . $thold['thold_fail_trigger']);
		thold_putenv('THOLD_DURATION=' . '');
	} elseif ($thold['thold_type'] == 2) {
		thold_putenv('THOLD_HI='       . $thold['time_hi']);
		thold_putenv('THOLD_LOW='      . $thold['time_low']);
		thold_putenv('THOLD_TRIGGER='  . $thold['time_fail_trigger']);
		thold_putenv('THOLD_DURATION=' . plugin_thold_duration_convert($thold['local_data_id'], $thold['time_fail_length'], 'time'));
	} else {
		thold_putenv('THOLD_HI='       . '');
		thold_putenv('THOLD_LOW='      . '');
		thold_putenv('THOLD_TRIGGER='  . '');
		thold_putenv('THOLD_DURATION=' . '');
	}

	thold_putenv('THOLD_TIME='         . time());
	thold_putenv('THOLD_DATE='         . date(CACTI_DATE_TIME_FORMAT));
	thold_putenv('THOLD_DATE_RFC822='  . date(DATE_RFC822));

	$environment = thold_putenv('THOLD_URL=' . html_escape("$httpurl/graph.php?local_graph_id=$local_graph_id"));

	return $environment;
}

function thold_replace_threshold_tags($text, &$thold, &$h, $currentval, $local_graph_id, $data_source_name) {
	global $thold_types;

	if (substr(read_config_option('base_url'), 0, 4) != 'http') {
		if (read_config_option('force_https') == 'on') {
			$prefix = 'https://';
		} else {
			$prefix = 'http://';
		}

		set_config_option('base_url', $prefix . read_config_option('base_url'));
	}

	$httpurl = read_config_option('base_url', true);

	// Do some replacement of variables
	$text = thold_str_replace('<DESCRIPTION>',   $h['description'], $text);
	$text = thold_str_replace('<HOSTNAME>',      $h['hostname'], $text);
	$text = thold_str_replace('<GRAPHID>',       $local_graph_id, $text);
	$text = thold_str_replace('<THOLD_ID>',      $thold['id'], $text);

	$text = thold_str_replace('<CURRENTVALUE>',  $currentval, $text);
	$text = thold_str_replace('<THRESHOLDNAME>', $thold['name_cache'], $text);
	$text = thold_str_replace('<DSNAME>',        $data_source_name, $text);

	if (isset($thold_types[$thold['thold_type']])) {
		$text = thold_str_replace('<THOLDTYPE>', $thold_types[$thold['thold_type']], $text);
	}

	$text = thold_str_replace('<NOTES>',         $thold['notes'], $text);
	$text = thold_str_replace('<DNOTES>',        $thold['dnotes'], $text);
	$text = thold_str_replace('<DEVICENOTE>',    $thold['dnotes'], $text);
	$text = thold_str_replace('<EXTERNALID>',    $thold['external_id'], $text);

	if ($thold['thold_type'] == 0) {
		$text = thold_str_replace('<HI>',        $thold['thold_hi'], $text);
		$text = thold_str_replace('<LOW>',       $thold['thold_low'], $text);
		$text = thold_str_replace('<TRIGGER>',   $thold['thold_fail_trigger'], $text);
		$text = thold_str_replace('<DURATION>',  '', $text);
	} elseif ($thold['thold_type'] == 2) {
		$text = thold_str_replace('<HI>',        $thold['time_hi'], $text);
		$text = thold_str_replace('<LOW>',       $thold['time_low'], $text);
		$text = thold_str_replace('<TRIGGER>',   $thold['time_fail_trigger'], $text);
		$text = thold_str_replace('<DURATION>',  plugin_thold_duration_convert($thold['local_data_id'], $thold['time_fail_length'], 'time'), $text);
	} else {
		$text = thold_str_replace('<HI>',        '', $text);
		$text = thold_str_replace('<LOW>',       '', $text);
		$text = thold_str_replace('<TRIGGER>',   '', $text);
		$text = thold_str_replace('<DURATION>',  '', $text);
	}

	$text = thold_str_replace('<TIME>',          time(), $text);
	$text = thold_str_replace('<DATE>',          date(CACTI_DATE_TIME_FORMAT), $text);
	$text = thold_str_replace('<DATE_RFC822>',   date(DATE_RFC822), $text);

	$text = thold_str_replace('<URL>', "<a href='" . html_escape("$httpurl/graph.php?local_graph_id=$local_graph_id") . "'>" . __('Link to Graph in Cacti', 'thold') . "</a>", $text);

	$data = array(
		'thold_data' => $thold,
		'text' => $text
	);

	$data = api_plugin_hook_function('thold_replacement_text', $data);
	if (isset($data['text'])) {
		$text = $data['text'];
	}

	return $text;
}

function get_thold_alert_text($data_source_name, $thold, $h, $currentval, $local_graph_id) {
	$alert_text = read_config_option('thold_alert_text');
	$httpurl    = read_config_option('base_url');
	$peralert   = read_config_option('thold_enable_per_thold_body');

	if ($peralert == 'on') {
		$alert_text = $thold['email_body'];
	}

	/* make sure the alert text has been set */
	if ($alert_text == '') {
		$alert_text = __('<html><body>An alert has been issued that requires your attention.<br><br><b>Device</b>: <DESCRIPTION> (<HOSTNAME>)<br><b>URL</b>: <URL><br><b>Message</b>: <SUBJECT><br><br><GRAPH></body></html>', 'thold');
	}

	if ($thold['notes'] != '') {
		$notes      = thold_replace_threshold_tags($thold['notes'], $thold, $h, $currentval, $local_graph_id, $data_source_name);
		$alert_text = thold_str_replace('<NOTES>', $notes, $alert_text);
	}

	$alert_text = thold_replace_threshold_tags($alert_text, $thold, $h, $currentval, $local_graph_id, $data_source_name);

	return $alert_text;
}

function get_thold_warning_text($data_source_name, $thold, $h, $currentval, $local_graph_id) {
	$warning_text = read_config_option('thold_warning_text');
	$httpurl      = read_config_option('base_url');
	$peralert     = read_config_option('thold_enable_per_thold_body');

	if ($peralert == 'on') {
		$warning_text = $thold['email_body_warn'];
	}

	/* make sure the warning text has been set */
	if ($warning_text == '') {
		$warning_text = __('<html><body>A warning has been issued that requires your attention.<br><br><b>Device</b>: <DESCRIPTION> (<HOSTNAME>)<br><b>URL</b>: <URL><br><b>Message</b>: <SUBJECT><br><br><GRAPH></body></html>', 'thold');
	}

	if ($thold['notes'] != '') {
		$notes        = thold_replace_threshold_tags($thold['notes'], $thold, $h, $currentval, $local_graph_id, $data_source_name);
		$warning_text = thold_str_replace('<NOTES>', $notes, $warning_text);
	}

	$warning_text = thold_replace_threshold_tags($warning_text, $thold, $h, $currentval, $local_graph_id, $data_source_name);

	return $warning_text;
}

function get_thold_restoral_text($data_source_name, $thold, $h, $currentval, $local_graph_id) {
	$restoral_text = read_config_option('thold_restoral_text');
	$httpurl    = read_config_option('base_url');
	$peralert   = read_config_option('thold_enable_per_thold_body');

	if ($peralert == 'on') {
		$restoral_text = $thold['email_body_restoral'];
	}

	/* make sure the alert text has been set */
	if ($restoral_text == '') {
		$restoral_text = __('<html><body>A Threshold has returned to normal status. <br><br><strong>Device</strong>: <DESCRIPTION> (<HOSTNAME>)<br><strong>URL</strong>: <URL><br><strong>Message</strong>: <SUBJECT><br><br><GRAPH></body></html>', 'thold');
	}

	if ($thold['notes'] != '') {
		$notes         = thold_replace_threshold_tags($thold['notes'], $thold, $h, $currentval, $local_graph_id, $data_source_name);
		$restoral_text = thold_str_replace('<NOTES>', $notes, $restoral_text);
	}

	$restoral_text = thold_replace_threshold_tags($restoral_text, $thold, $h, $currentval, $local_graph_id, $data_source_name);

	return $restoral_text;
}

function thold_modify_values_by_cdef(&$thold_data) {
	$cdef = false;
	if ($thold_data['data_type'] != 1 || empty($thold_data['cdef'])) {
		// Check is the graph item has a cdef
		$cdef = db_fetch_cell_prepared('SELECT MAX(cdef_id)
			FROM graph_templates_item AS gti
			INNER JOIN data_template_rrd AS dtr
			ON gti.task_item_id = dtr.id
			WHERE local_graph_id = ?
			AND dtr.id = ?
			AND gti.graph_type_id IN (4, 5, 6, 7, 8, 20)
			AND dtr.data_source_name = ?',
			array($thold_data['local_graph_id'], $thold_data['data_template_rrd_id'], $thold_data['data_source_name']));
	}

	if ($cdef !== false && $cdef > 0 && $thold_data['data_type'] == 1) {
		$thold_data['lastread']  = thold_build_cdef($cdef, $thold_data['lastread'], $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);

		if ($thold_data['thold_type'] == 0) {
			$thold_data['thold_hi']  = thold_build_cdef($cdef, $thold_data['thold_hi'], $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
			$thold_data['thold_low'] = thold_build_cdef($cdef, $thold_data['thold_low'], $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);

			$thold_data['thold_warning_hi']  = thold_build_cdef($cdef, $thold_data['thold_warning_hi'], $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
			$thold_data['thold_warning_low'] = thold_build_cdef($cdef, $thold_data['thold_warning_low'], $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
		} elseif ($thold_data['thold_type'] == 2) {
			$thold_data['time_hi']  = thold_build_cdef($cdef, $thold_data['time_hi'], $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
			$thold_data['time_low'] = thold_build_cdef($cdef, $thold_data['time_low'], $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);

			$thold_data['time_warning_hi']  = thold_build_cdef($cdef, $thold_data['time_warning_hi'], $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
			$thold_data['time_warning_low'] = thold_build_cdef($cdef, $thold_data['time_warning_low'], $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
		}
	}
}

function thold_get_column_by_cdef(&$thold_data, $column = 'lastread') {
	// Check is the graph item has a cdef
	if (isset($thold_data['local_data_id'])) {
		$cdef = db_fetch_cell_prepared('SELECT MAX(cdef_id)
			FROM graph_templates_item AS gti
			INNER JOIN data_template_rrd AS dtr
			ON gti.task_item_id = dtr.id
			WHERE local_graph_id = ?
			AND dtr.id = ?
			AND dtr.data_source_name = ?',
			array(
				$thold_data['local_graph_id'],
				$thold_data['data_template_rrd_id'],
				$thold_data['data_source_name']
			)
		);

		if ($cdef > 0) {
			return thold_build_cdef($cdef, $thold_data['lastread'], $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
		} elseif (isset($thold_data['lastread'])) {
			return $thold_data['lastread'];
		}
	}

	return '-';
}

function thold_format_number($value, $digits = 2, $baseu = 1024, $show_suffix = true, $show_units = false, $units_suffix = '') {
	$units  = '';
	$suffix = '';

	if ($baseu == 1024 && $show_suffix) {
		$suffix = 'i';
	}

	if (!is_numeric($value)) {
		return '-';
	}

	if (empty($baseu)) {
		$baseu = 1000;
	}

	if ($value == '0') {
		return '0';
	}

	if (!$show_units && $units_suffix == '') {
		return number_format_i18n($value, $digits, $baseu);
	}

	if ($units_suffix != '') {
		$suffix = " $units_suffix";
	}

	if (abs($value) < 1) {
		$units = ' m';
		$value *= $baseu;

		if (abs($value) < 1) {
			$units = ' &#181;';
			$value *= $baseu;
		} else {
			return number_format_i18n($value, $digits, $baseu) . $units . $suffix;
		}

		if (abs($value) < 1) {
			$units = ' n';
			$value *= $baseu;
		} else {
			return number_format_i18n($value, $digits, $baseu) . $units . $suffix;
		}

		if (abs($value) < 1) {
			$units = ' p';
			$value *= $baseu;
		} else {
			return number_format_i18n($value, $digits, $baseu) . $units . $suffix;
		}
	} else {
		if (abs($value) >= $baseu) {
			$units  = ' K';
			$value /= $baseu;
		} else {
			return number_format_i18n($value, $digits, $baseu) . $units . $suffix;
		}

		if (abs($value) >= $baseu) {
			$units  = ' M';
			$value /= $baseu;
		} else {
			return number_format_i18n($value, $digits, $baseu) . $units . $suffix;
		}

		if (abs($value) >= $baseu) {
			$units  = ' G';
			$value /= $baseu;
		} else {
			return number_format_i18n($value, $digits, $baseu) . $units . $suffix;
		}

		if (abs($value) >= $baseu) {
			$units  = ' T';
			$value /= $baseu;
		} else {
			return number_format_i18n($value, $digits, $baseu) . $units . $suffix;
		}

		if (abs($value) >= $baseu) {
			$units  = ' P';
			$value /= $baseu;
		} else {
			return number_format_i18n($value, $digits, $baseu) . $units . $suffix;
		}
	}

	return number_format_i18n($value, $digits, $baseu) . $units . $suffix;
}

function get_reference_types($local_data_id = 0) {
	global $config, $timearray;

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');

	$poller_interval = read_config_option('poller_interval');

	$rra_steps = db_fetch_assoc('SELECT DISTINCT dsp.step * dspr.steps AS frequency
		FROM data_source_profiles AS dsp
		INNER JOIN data_source_profiles_rra AS dspr
		ON dsp.id = dspr.data_source_profile_id
		LEFT JOIN data_template_data AS dtd
		ON dtd.data_source_profile_id=dspr.data_source_profile_id
		WHERE dspr.steps > 1 ' . ($local_data_id > 0 ? "AND dtd.local_data_id = $local_data_id":'') . '
		ORDER BY frequency');

	$reference_types = array();
	if (cacti_sizeof($rra_steps)) {
		foreach ($rra_steps as $rra_step) {
			$seconds = $rra_step['frequency'];
			$setting = round($rra_step['frequency'] / $poller_interval, 0);
			if (isset($timearray[$setting])) {
				$reference_types[$seconds] = $timearray[$setting];
			}
		}
	}

	return $reference_types;
}

function logger($subject, $urlbreach, $syslog_priority = '', $syslog_facility = '') {
	if ($syslog_priority == '') {
		$syslog_priority = read_config_option('thold_syslog_priority');
	}

	if ($syslog_facility == '') {
		$syslog_facility = read_config_option('thold_syslog_facility');
	}

	if ($syslog_priority > 7 || $syslog_priority < 0 || $syslog_priority == '') {
		$syslog_priority = LOG_WARNING;
		set_config_option('thold_syslog_priority', $syslog_priority);
	}

	if ($syslog_facility == '') {
		$syslog_facility = LOG_DAEMON;
		set_config_option('thold_syslog_facility', $syslog_facility);
	}

	openlog('CactiTholdLog', LOG_PID | LOG_PERROR, $syslog_facility);

	syslog($syslog_priority, $subject . ' - ' . $urlbreach);

	if (function_exists('closelog')) {
		closelog();
	}
}

function ack_logging($thold_id, $desc = '') {
	$thold_data = db_fetch_row_prepared('SELECT name_cache, host_id, thold_hi, thold_low,
		syslog_enabled, syslog_facility, syslog_priority, lastread, local_graph_id
		FROM thold_data
		WHERE id = ?',
		array($thold_id));

	if ($thold_data['syslog_enabled']) {
		openlog('CactiTholdLog', LOG_PID | LOG_PERROR, $thold_data['syslog_facility']);

		syslog($thold_data['syslog_priority'], 'Threshold ' . $thold_id . ' has been acknowledged. Additional Comments: ' . $desc);
	}

	$status = 99;

	thold_log(
		array(
			'type' => 99,
			'time' => time(),
			'host_id' => $thold_data['host_id'],
			'local_graph_id' => $thold_data['local_graph_id'],
			'threshold_id' => $thold_id,
			'threshold_value' => '',
			'current' => $thold_data['lastread'],
			'status' => $status,
			'description' => 'Threshold Name ' . $thold_data['name_cache'] . ' has been acknowledged. Additional Comments: "' . $desc . '"',
			'emails' => '',
			'bcc_emails' => ''
		)
	);

	cacti_log('Threshold TH[' . $thold_id . '] has been acknowledged. Additional Comments: ' . $desc);
}

function thold_cdef_get_usable() {
	$cdef_items = db_fetch_assoc('SELECT *
		FROM cdef_items
		WHERE value = "CURRENT_DATA_SOURCE"
		ORDER BY cdef_id');

	$cdef_usable = array();

	if (cacti_sizeof($cdef_items)) {
		foreach ($cdef_items as $cdef_item) {
			$cdef_usable[] =  $cdef_item['cdef_id'];
		}
	}

	return $cdef_usable;
}

function thold_cdef_select_usable_names() {
	$ids   = thold_cdef_get_usable();
	$cdefs = db_fetch_assoc('SELECT id, name FROM cdef');

	$cdef_names[0] = '';

	if (cacti_sizeof($cdefs)) {
		foreach ($cdefs as $cdef) {
			if (in_array($cdef['id'], $ids)) {
				$cdef_names[$cdef['id']] =  $cdef['name'];
			}
		}
	}

	return $cdef_names;
}

function thold_build_cdef($cdef, $value, $local_data_id, $data_template_rrd_id) {
	if ($value == '') {
		$value = 0;
	}

	$oldvalue = $value;

	$cdefs = db_fetch_assoc_prepared('SELECT *
		FROM cdef_items
		WHERE cdef_id = ?
		ORDER BY sequence',
		array($cdef));

	$cdef_array = array();

	if (cacti_sizeof($cdefs)) {
		foreach ($cdefs as $cdef) {
			$prev_value = $cdef['value'];

			if ($cdef['type'] == 4) {
				$found        = false;
				$cdef['type'] = 6;

				if ($cdef['value'] != '') {
					if (strpos($cdef['value'], 'CURRENT_DATA_SOURCE') !== false) {
						$cdef['value'] = str_replace('CURRENT_DATA_SOURCE', $oldvalue, $cdef['value']);
						$found = true;
					}

					if (strpos($cdef['value'], 'CURRENT_GRAPH_MAXIMUM_VALUE') !== false) {
						$cdef['value'] = str_replace('CURRENT_GRAPH_MAXIMUM_VALUE', get_current_value($local_data_id, 'upper_limit'), $cdef['value']);
						$found = true;
					}

					if (strpos($cdef['value'], 'CURRENT_GRAPH_MINIMUM_VALUE') !== false) {
						$cdef['value'] = str_replace('CURRENT_GRAPH_MINIMUM_VALUE', get_current_value($local_data_id, 'lower_limit'), $cdef['value']);
						$found = true;
					}

					if (strpos($cdef['value'], 'CURRENT_DS_MINIMUM_VALUE') !== false) {
						$cdef['value'] = str_replace('CURRENT_DS_MINIMUM_VALUE', get_current_value($local_data_id, 'rrd_minimum'), $cdef['value']);
						$found = true;
					}

					if (strpos($cdef['value'], 'CURRENT_DS_MAXIMUM_VALUE') !== false) {
						$cdef['value'] = str_replace('CURRENT_DS_MAXIMUM_VALUE', get_current_value($local_data_id, 'rrd_maximum'), $cdef['value']);
						$found = true;
					}

					if (strpos($cdef['value'], 'VALUE_OF_HDD_TOTAL') !== false) {
						$cdef['value'] = str_replace('VALUE_OF_HDD_TOTAL', get_current_value($local_data_id, 'hdd_total'), $cdef['value']);
						$found = true;
					}

					if (strpos($cdef['value'], 'ALL_DATA_SOURCES_NODUPS') !== false || strpos($cdef['value'], 'ALL_DATA_SOURCES_DUPS') !== false) {
						$found = true;
						$total = 0;

						$all_dsns = db_fetch_assoc_prepared('SELECT data_source_name
							FROM data_template_rrd
							WHERE local_data_id = ?',
							array($local_data_id));

						if (cacti_sizeof($all_dsns)) {
							foreach ($all_dsns as $dsn) {
								$total += get_current_value($local_data_id, $dsn['data_source_name']);
							}
						}

						$cdef['value'] = str_replace('ALL_DATA_SOURCES_NODUPS', $total, $cdef['value']);
						$cdef['value'] = str_replace('ALL_DATA_SOURCES_DUPS', $total, $cdef['value']);
					}
				}

				if (!$found) {
					cacti_log('WARNING: Issues with CDEF property: ' . $cdef['value'] . ', CDEF ID: ' . $cdef['id'] . ', Returning Original Data: ' . $oldvalue, false, 'THOLD', POLLER_VERBOSITY_MEDIUM);

					return $oldvalue;

					break;
				}
			} elseif ($cdef['type'] == 6) {
				if ($cdef['value'] != '') {
					$regresult = preg_match('/^\|query_([A-Za-z0-9_]+)\|$/', $cdef['value'], $matches);

					if ($regresult > 0) {
						$cdef['value'] = db_fetch_cell_prepared('SELECT hsc.field_value
							FROM data_local AS dl
							INNER JOIN host_snmp_cache AS hsc
							ON hsc.host_id = dl.host_id
							AND hsc.snmp_query_id = dl.snmp_query_id
							AND hsc.snmp_index = dl.snmp_index
							WHERE dl.id = ?
							AND hsc.field_name = ?',
							array($local_data_id, $matches[1]));

						if ($cdef['value'] == '' || !is_numeric($cdef['value'])) {
							$cdef['value'] = 0;
						}
					}
				}
			}

			$cdef_array[] = $cdef;
			if ($cdef['type'] == 6 && !is_numeric($cdef['value'])) {
				cacti_log("WARNING: Value for Data ID $local_data_id is not numeric:'" . $cdef['value'] . "', Previous Value was:'". $prev_value . "'", false, 'THOLD', POLLER_VERBOSITY_MEDIUM);
			}
		}
	}

	$x = cacti_count($cdef_array);

	if ($x == 0) {
		return $oldvalue;
	}

	$stack = array(); // operation stack for RPN
	array_push($stack, $cdef_array[0]); // first one always goes on
	$cursor = 1; // current pointer through RPN operations list

	while($cursor < $x) {
		$type = $cdef_array[$cursor]['type'];
		switch($type) {
		case 6:
			array_push($stack, $cdef_array[$cursor]);

			break;
		case 2:
			// this is a binary operation. pop two values, and then use them.
			$v1 = thold_expression_rpn_pop($stack);
			$v2 = thold_expression_rpn_pop($stack);

			$result = thold_rpn($v2['value'], $v1['value'], $cdef_array[$cursor]['value'], $local_data_id);

			// put the result back on the stack.
			array_push($stack, array('type' => 6, 'value' => $result));

			break;
		default:
			cacti_log('Unknown RPN type: ' . $cdef_array[$cursor]['type'], false, 'THOLD', POLLER_VERBOSITY_MEDIUM);

			return($oldvalue);

			break;
		}

		$cursor++;
	}

	return $stack[0]['value'];
}

function thold_rpn($x, $y, $z, $local_data_id = 0) {
	if (empty($x) || $x == 'U') {
		$x = 0;
	}

	if (empty($y) || $y == 'U') {
		$y = 0;
	}

	if (!is_numeric($x)) {
		cacti_log("WARNING: Erroneous CDEF logic, the first value should be numeric, but is '$x'. Data ID $local_data_id", false, 'THOLD');
		return '';
	}

	if (!is_numeric($y)) {
		cacti_log("WARNING: Erroneous CDEF logic, the second value should be numeric, but is '$y'. Data ID $local_data_id", false, 'THOLD');
		return '';
	}

	switch ($z) {
	case 1:
		return $x + $y;

		break;
	case 2:
		return $x - $y;

		break;
	case 3:
		return $x * $y;

		break;
	case 4:
		if ($y == 0) {
			return (-1);
		}
		return $x / $y;

		break;
	case 5:
		return $x % $y;

		break;
	}

	return '';
}

function delete_old_thresholds() {
	$tholds = db_fetch_assoc('SELECT td.id, td.data_template_rrd_id, td.local_data_id
		FROM thold_data AS td
		LEFT JOIN data_template_rrd AS dtr
		ON dtr.id=td.data_template_rrd_id
		WHERE dtr.local_data_id IS NULL');

	if (cacti_sizeof($tholds)) {
		foreach ($tholds as $thold_data) {
			plugin_thold_log_changes($thold_data['id'], 'deleted', array('message' => 'Auto-delete due to Data Source removal'));

			thold_api_thold_remove($thold_data['id']);
		}
	}
}

function thold_api_thold_remove($id) {
	db_execute_prepared('DELETE FROM thold_data
		WHERE id = ?',
		array($id));

	db_execute_prepared('DELETE FROM plugin_thold_threshold_contact
		WHERE thold_id = ?',
		array($id));

	db_execute_prepared('DELETE FROM plugin_thold_daemon_data
		WHERE id = ?',
		array($id));
}

function thold_api_thold_template_remove($id) {
	db_execute_prepared('DELETE FROM thold_template
		WHERE id = ?',
		array($id));

	db_execute_prepared('DELETE FROM plugin_thold_template_contact
		WHERE template_id = ?',
		array($id));

	db_execute_prepared('UPDATE thold_data
		SET thold_template_id = 0, template_enabled = "off"
		WHERE thold_template_id = ?',
		array($id));
}

function thold_rrd_last($local_data_id) {
	$last_time_entry = @rrdtool_execute('last ' . trim(get_data_source_path($local_data_id, true)), false, RRDTOOL_OUTPUT_STDOUT);

	return trim($last_time_entry);
}

function get_current_value($local_data_id, $data_template_rrd_id, $cdef = 0) {
	/* get the information to populate into the rrd files */
	if (function_exists('boost_check_correct_enabled') && boost_check_correct_enabled()) {
		boost_process_poller_output($local_data_id);
	}

	$last_time_entry = thold_rrd_last($local_data_id);

	// This should fix and 'did you really mean month 899 errors', this is because your RRD has not polled yet
	if ($last_time_entry == -1) {
		$last_time_entry = time();
	}

	$data_template_data = db_fetch_row_prepared('SELECT *
		FROM data_template_data
		WHERE local_data_id = ?',
		array($local_data_id));

	$step = $data_template_data['rrd_step'];

	// Round down to the nearest 100
	$last_time_entry = (intval($last_time_entry /100) * 100) - $step;
	$last_needed = $last_time_entry + $step;

	$result = rrdtool_function_fetch($local_data_id, trim($last_time_entry), trim($last_needed));

	// Return Blank if the data source is not found (Newly created?)
	if (!isset($result['data_source_names'])) {
		return '';
	}

	$idx = array_search($data_template_rrd_id, $result['data_source_names']);

	// Return Blank if the value was not found (Cache Cleared?)

	if (!isset($result['values']) || $idx === null || !cacti_sizeof($result['values'][$idx])) {
		return '';
	}

	$value = array_values($result['values'][$idx])[0];

	if ($cdef > 0) {
		$value = thold_build_cdef($cdef, $value, $local_data_id, $data_template_rrd_id);
	}

	return round($value, 4);
}

function thold_get_ref_values($local_data_id, $data_source_name, $current_time, $prev_time, $avg_of_cf) {
	$data = thold_get_rrd_statistics($local_data_id, $current_time, $prev_time, $avg_of_cf);

	if (!isset($data[$data_source_name])) {
		return false;
	}

	return $data[$data_source_name];
}

/**
 * thold_check_exception_periods
 *
 * @to-do: This function should check 'globally' declared exceptions, like
 * holidays etc., as well as exceptions bound to the specific $local_data_id. $local_data_id
 * should inherit exceptions that are assigned on the higher level (i.e. device).
 *
 */
function thold_check_exception_periods($local_data_id, $ref_time, $ref_range) {
	// TO-DO
	// Check if the reference time falls into global exceptions
	// Check if the current time falls into global exceptions
	// Check if $local_data_id + $data_template_rrd_id have an exception (again both reference time and current time)
	// Check if there are inheritances

	// More on the exception concept:
	// -Exceptions can be one time and recurring
	// -Exceptions can be global and assigned to:
	// 	-templates
	//	-devices
	//	-data sources
	//

	return false;
}

/**
 * thold_check_baseline -
 * Should be called after hard limits have been checked and only when they are OK
 *
 * The function "goes back in time" $ref_time seconds and retrieves the data
 * for $ref_range seconds. Then it finds minimum and maximum values and calculates
 * allowed deviations from those values.
 *
 * @param $local_data_id - the data source to check the data
 * @param $data_source_name - the data source name to gather data from
 * @param $current_value - the current data source value
 * @param $thold_data - The thold data structure
 *
 * @return integer - integer value that indicates status
 *   -2 if the exception is active
 *   -1 if the reference value is not available
 *   0 if the current value is within the boundaries
 *   1 if the current value is below the calculated threshold
 *   2 if the current value is above the calculated threshold
 */
function thold_check_baseline($local_data_id, $data_source_name, $current_value, &$thold_data) {
	global $debug;

	$now = time();

	$poller_interval = read_config_option('poller_interval');

	if (empty($poller_interval)) {
		$poller_interval = 300;
	}

	$current_time = time() - $poller_interval;
	$prev_time    = $current_time - $thold_data['bl_ref_time_range'];
	$avg_of_cf    = '';

	if ($thold_data['bl_type'] < 4) {
		if ($thold_data['bl_ref_time_range'] > 86400 * 2) {
			$current_time = $prev_time + 86400;
		} elseif ($thold_data['bl_ref_time_range'] > 3600 * 2) {
			$current_time = $prev_time + 3600;
		} else {
			$current_time = $prev_time;
		}
	} elseif ($thold_data['bl_type'] == 5 || $thold_data['bl_type'] == 7) {
		$avg_of_cf = $thold_data['bl_cf'];
	}

	$ref_values = thold_get_ref_values($thold_data['local_data_id'], $data_source_name, $current_time, $prev_time, $avg_of_cf);

	if ($ref_values === false || cacti_sizeof($ref_values) == 0) {
		cacti_log(sprintf('WARNING: RRDtool was unable to return any reference values to TH[%s]', $thold_data['id']), false, 'THOLD');
		return -1;
	}

	$ref_value_min  = 0;
	$ref_value_max  = 0;
	$ref_value_avg  = 0;
	$ref_value_last = 0;
	$ref_value_cfa  = 0;

	if (isset($ref_values['MIN'])) {
		$ref_value_min = $ref_values['MIN'];
	}

	if (isset($ref_values['MAX'])) {
		$ref_value_max = $ref_values['MAX'];
	}

	if (isset($ref_values['AVG'])) {
		$ref_value_avg = $ref_values['AVG'];
	}

	if (isset($ref_values['LAST'])) {
		$ref_value_last = $ref_values['LAST'];
	}

	if (isset($ref_values['CFA'])) {
		$ref_value_cfa = $ref_values['CFA'];
	}

	if ($thold_data['cdef'] > 0 && $thold_data['data_type'] == 1) {
		if ($ref_value_min == '-nan' || $ref_value_max == '-nan' || $ref_value_avg == '-nan' ) {
			cacti_log( "WARNING: CDEF values are -nan: MIN $ref_value_min MAX $ref_value_max AVG $ref_value_avg LAST $ref_value_last " .
				" Threshold {$thold_data['id']}:{$thold_data['name_cache']} :" . __FILE__ . " " . __FUNCTION__ . ":" . __LINE__, false, 'THOLD' );
		}

		$ref_value_min  = thold_build_cdef($thold_data['cdef'], $ref_value_min, $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
		$ref_value_max  = thold_build_cdef($thold_data['cdef'], $ref_value_max, $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
		$ref_value_avg  = thold_build_cdef($thold_data['cdef'], $ref_value_avg, $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
		$ref_value_last = thold_build_cdef($thold_data['cdef'], $ref_value_last, $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
		$ref_value_cfa  = thold_build_cdef($thold_data['cdef'], $ref_value_cfa, $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
	}

	db_execute_prepared('UPDATE thold_data
		SET bl_reference_min = ?, bl_reference_max = ?, bl_reference_avg = ?, bl_reference_last = ?
		WHERE id = ?',
		array($ref_value_min, $ref_value_max, $ref_value_avg, $ref_value_last, $thold_data['id']));

	$blt_low  = '';
	$blt_high = '';

	/* setup for CF based deviations */
	switch($thold_data['bl_cf']) {
		case 'AVG':
			$ref_value = $ref_value_avg;
			break;
		case 'MIN':
			$ref_value = $ref_value_min;
			break;
		case 'MAX':
			$ref_value = $ref_value_max;
			break;
		case 'LAST':
			$ref_value = $ref_value_last;
			break;
	}

	/**
	 * Baseline types
	 *
	 * 0 - % Deviation of Min/Max from Time in the past 'LAST'
	 * 1 - % Deviation of CF in the past
	 * 2 - Absolute Value Deviation of Min/Max from Time in the past
	 * 3 - Absolute Value Deviation of CF from Time in the past
	 * 4 - % Deviation from Floating Average of over the time period
	 * 5 - % Deviation from Floating Average of CF over time period
	 * 6 - Absolute Value Deviation of Floating Average over the time period
	 * 7 - Absolute Value Deviation of CF over the time period
	 */
	if ($thold_data['bl_type'] == 0) {
		if ($thold_data['bl_pct_down'] != '' && is_numeric($ref_value_min)) {
			$blt_low  = $ref_value_min - (abs($ref_value_min) * $thold_data['bl_pct_down'] / 100);
		}

		if ($thold_data['bl_pct_up'] != '' && is_numeric($ref_value_max)) {
			$blt_high = $ref_value_max + (abs($ref_value_max) * $thold_data['bl_pct_up'] / 100);
		}
	} elseif ($thold_data['bl_type'] == 1) {
		if ($thold_data['bl_pct_down'] != '' && is_numeric($ref_value)) {
			$blt_low  = $ref_value - (abs($ref_value) * $thold_data['bl_pct_down'] / 100);
		}

		if ($thold_data['bl_pct_up'] != '' && is_numeric($ref_value)) {
			$blt_high = $ref_value + (abs($ref_value) * $thold_data['bl_pct_up'] / 100);
		}
	} elseif ($thold_data['bl_type'] == 2) {
		if ($thold_data['bl_pct_down'] != '' && is_numeric($ref_value_min)) {
			$blt_low  = $ref_value_min - $thold_data['bl_pct_down'];
		}

		if ($thold_data['bl_pct_up'] != '' && is_numeric($ref_value_max)) {
			$blt_high = $ref_value_max + $thold_data['bl_pct_up'];
		}
	} elseif ($thold_data['bl_type'] == 3) {
		if ($thold_data['bl_pct_down'] != '' && is_numeric($ref_value)) {
			$blt_low  = $ref_value - $thold_data['bl_pct_down'];
		}

		if ($thold_data['bl_pct_up'] != '' && is_numeric($ref_value)) {
			$blt_high = $ref_value + $thold_data['bl_pct_up'];
		}
	} elseif ($thold_data['bl_type'] == 4) {
		if ($thold_data['bl_pct_down'] != '' && is_numeric($ref_value_avg)) {
			$blt_low  = $ref_value_avg - (abs($ref_value_avg) * $thold_data['bl_pct_down'] / 100);
		}

		if ($thold_data['bl_pct_up'] != '' && is_numeric($ref_value_avg)) {
			$blt_high = $ref_value_avg + (abs($ref_value_avg) * $thold_data['bl_pct_up'] / 100);
		}
	} elseif ($thold_data['bl_type'] == 5) {
		if ($thold_data['bl_pct_down'] != '' && is_numeric($ref_value_avg)) {
			$blt_low  = $ref_value_avg - (abs($ref_value_avg) * $thold_data['bl_pct_down'] / 100);
		}

		if ($thold_data['bl_pct_up'] != '' && is_numeric($ref_value_avg)) {
			$blt_high = $ref_value_avg + (abs($ref_value_avg) * $thold_data['bl_pct_up'] / 100);
		}
	} elseif ($thold_data['bl_type'] == 6) {
		if ($thold_data['bl_pct_down'] != '' && is_numeric($ref_value_avg)) {
			$blt_low  = $ref_value_avg - $thold_data['bl_pct_down'];
		}

		if ($thold_data['bl_pct_up'] != '' && is_numeric($ref_value_avg)) {
			$blt_high = $ref_value_avg + $thold_data['bl_pct_up'];
		}
	} else {
		if ($thold_data['bl_pct_down'] != '' && is_numeric($ref_value_cfa)) {
			$blt_low  = $ref_value_cfa - $thold_data['bl_pct_down'];
		}

		if ($thold_data['bl_pct_up'] != '' && is_numeric($ref_value_cfa)) {
			$blt_high = $ref_value_cfa + $thold_data['bl_pct_up'];
		}
	}

	// Cache the calculated or empty values
	$thold_data['thold_low']      = $blt_low;
	$thold_data['thold_hi']       = $blt_high;
	$thold_data['bl_thold_valid'] = 0;

	$failed = 0;

	// Check low boundary
	if ($blt_low != '' && $current_value < $blt_low) {
		$failed = 1;
	}

	// Check up boundary
	if ($failed == 0 && $blt_high != '' && $current_value > $blt_high) {
		$failed = 2;
	}

	if ($debug) {
		print 'Local Data Id: '     . $local_data_id . ':' . $thold_data['data_template_rrd_id'] . "\n";
		print 'Ref. values count: ' . (isset($ref_values) ? count($ref_values):"N/A") . "\n";
		print 'Ref. value (min): '  . (isset($ref_value_min) ? $ref_value_min:'N/A') . "\n";
		print 'Ref. value (max): '  . (isset($ref_value_max) ? $ref_value_max:'N/A') . "\n";
		print 'Cur. value: '        . $current_value . "\n";
		print 'Low bl thresh: '     . (isset($blt_low) ? $blt_low:'N/A') . "\n";
		print 'High bl thresh: '    . (isset($blt_high) ? $blt_high:'N/A') . "\n";
		print 'Check against baseline: ';
		switch($failed) {
			case 0:
			print 'OK';
			break;

			case 1:
			print 'FAIL: Below baseline threshold!';
			break;

			case 2:
			print 'FAIL: Above baseline threshold!';
			break;
		}
		print "\n";
		print "------------------\n";
	}

	return $failed;
}

function get_bl_type($type, $cf) {
	global $bl_types;

	switch($type) {
		case '0':
		case '2':
		case '4':
		case '6':
			return str_replace(array('Deviation', 'Absolute'), array('Dev', 'Abs'), $bl_types[$type]);

			break;
		case '1':
			return __('%% Dev [TIP:%s]', $cf, 'thold');

			break;
		case '3':
			return __('Abs Val [TIP:%s]', $cf, 'thold');

			break;
		case '5':
			return __('%%% Dev [AOT:%s]', $cf, 'thold');

			break;
		case '7':
			return __('Abs Val [AOT:%s]', $cf, 'thold');

			break;
	}
}

function thold_create_new_graph_from_template() {
	if (!isset_request_var('host_id')) {
		$device_id = 0;
	} else {
		$device_id = get_filter_request_var('host_id');
	}

	if (isset_request_var('save_component_graph')) {
		/* summarize the 'create graph from host template/snmp index' stuff into an array */
		foreach ($_POST as $var => $val) {
			if (preg_match('/^cg_(\d+)$/', $var, $matches)) {
				$selected_graphs['cg'][$matches[1]][$matches[1]] = true;
			} elseif (preg_match('/^cg_g$/', $var)) {
				if (get_request_var('cg_g') > 0) {
					$selected_graphs['cg'][get_request_var('cg_g')][get_request_var('cg_g')] = true;
				}
			} elseif (preg_match('/^sg_(\d+)_([a-f0-9]{32})$/', $var, $matches)) {
				$selected_graphs['sg'][$matches[1]][get_nfilter_request_var('sgg_' . $matches[1])][$matches[2]] = true;
			}
		}

		if (!isset_request_var('host_template_id')) {
			$host_template_id = 0;
		} else {
			$host_template_id = get_filter_request_var('host_template_id');
		}

		if (isset($selected_graphs)) {
			html_graph_new_graphs('thold.php', $device_id, $host_template_id, $selected_graphs);
			exit;
		}
	} elseif (isset_request_var('save_component_new_graphs')) {
		thold_new_graphs_save($device_id);
	}
}

/**
 * thold_display_to_raw - Converts a displayed number to a raw
 * numeric value.  This function converts number like '100M'
 * to the raw number 100,000,000, etc.
 *
 * Supported Units
 *
 * Unit  Expression
 * ----  -------------------------------------
 * f     Fermo (10e-12)
 * p     Pico  (10e-9)
 * u     Micro (10e-6)
 * m     Milli (10e-3)
 * K     Killo (10e3)
 * M     Mega  (10e6)
 * G     Giga  (10e9)
 * T     Terra (10e12)
 * P     Peta  (10e15)
 * E     Exa   (10e18)
 * Z     Zeta  (10e21)
 * Y     Yota  (10e24)
 *
 */
function thold_display_to_raw($number, $field_name) {
	$number = trim($number);

	$_SESSION['sess_field_values'][$field_name] = $number;

	$suffix = substr($number, -1);

	/* if the number is numeric just return */
	if (is_numeric($suffix)) {
		return $number;
	}

	$number = trim(substr($number, 0, -1));

	if (!is_numeric($number)) {
		$_SESSION['sess_error_fields'][$field_name] = $field_name;
		raise_message(3);
		return false;
	}

	switch($suffix) {
		case 'f':
			return $number * 1e-15;
			break;
		case 'p':
			return $number * 1e-9;
			break;
		case 'u':
			return $number * 1e-6;
			break;
		case 'm':
			return $number * 1e-3;
			break;
		case 'K':
			return $number * 1e3;
			break;
		case 'M':
			return $number * 1e6;
			break;
		case 'G':
			return $number * 1e9;
			break;
		case 'T':
			return $number * 1e12;
			break;
		case 'P':
			return $number * 1e15;
			break;
		case 'E':
			return $number * 1e18;
			break;
		case 'Z':
			return $number * 1e21;
			break;
		case 'Y':
			return $number * 1e24;
			break;
		default:
			$_SESSION['sess_error_fields'][$field_name] = $field_name;
			raise_message(3);
			return false;
	}
}

/**
 * thold_display_to_raw - Converts a displayed number to a raw
 * numeric value.  This function converts number like '100M'
 * to the raw number 100,000,000, etc.
 *
 * Supported Units
 *
 * Unit  Expression
 * ----  -------------------------------------
 * f     Fermo (10e-12)
 * p     Pico  (10e-9)
 * u     Micro (10e-6)
 * m     Milli (10e-3)
 * K     Killo (10e3)
 * M     Mega  (10e6)
 * G     Giga  (10e9)
 * T     Terra (10e12)
 * P     Peta  (10e15)
 * E     Exa   (10e18)
 * Z     Zeta  (10e21)
 * Y     Yota  (10e24)
 *
 */
function thold_raw_to_display($number) {
	if ($number != '') {
		$number = trim($number);
	}

	if (!is_numeric($number)) {
		return false;
	}

	if ($number == 0) {
		return trim($number);
	}

	if ($number > 0) {
		$multiplier = 1;
	} else {
		$multiplier = -1;
	}

	$number = abs($number);
	$suffix = '';

	if ($number > 1) {
		$pattern = 'KMGTPEZY';
		$count = 0;

		while ($number >= 1e3) {
			$count++;
			$number /= 1e3;
		}

		if ($count > 0) {
			$suffix = $pattern[$count-1];
		}
	} else {
		$pattern = 'mupf';
		$count = 0;

		while ($number < 1) {
			$count++;
			$number *= 1e3;
		}

		if ($count > 0) {
			$suffix = $pattern[$count-1];
		}
	}

	return trim(($number * $multiplier) . $suffix);
}

function save_thold() {
	global $banner;

	$data_query_id     = get_filter_request_var('data_query_id');
	$data_template_id  = get_filter_request_var('data_template_id');
	$graph_template_id = get_filter_request_var('graph_template_id');
	$host_template_id  = get_filter_request_var('host_template_id');
	$thold_template_id = get_filter_request_var('thold_template_id');

	if (isset_request_var('save_component_new_graphs')) {
		// Correct issue where host_id variables comes back as an array
		if (isset_request_var('host_id')) {
			if (is_array(get_nfilter_request_var('host_id'))) {
				$temp = get_nfilter_request_var('host_id');
				foreach ($temp as $t) {
					set_request_var('host_id', input_validate_input_number($t));
				}
			}
		} else {
			$banner = __('The Device ID was not set while trying to create Graph and Threshold', 'thold');
			thold_raise_message($banner, MESSAGE_LEVEL_ERROR);

			return false;
		}

		$device_id = get_filter_request_var('host_id');

		if (isset_request_var('thold_template_id')) {
			$template = db_fetch_row_prepared('SELECT *
				FROM thold_template
				WHERE id = ?',
				array(get_filter_request_var('thold_template_id')));

			if (!sizeof($template)) {
				$banner = __('The Threshold Template ID was not found while trying to create Graph and Threshold', 'thold');
				thold_raise_message($banner, MESSAGE_LEVEL_ERROR);
				cacti_log('ERROR: The Threshold Template ID was not found', false, 'THOLD');

				return false;
			}
		} else {
			$banner = __('The Threshold Template ID was not set while trying to create Graph and Threshold', 'thold');
			thold_raise_message($banner, MESSAGE_LEVEL_ERROR);
			cacti_log('ERROR: The Threshold Template ID not set for save', false, 'THOLD');

			return false;
		}

		$graph_array = thold_new_graphs_save($device_id);

		if ($graph_array !== false) {
			if (isset($graph_array['local_graph_id'])) {
				set_request_var('local_graph_id', $graph_array['local_graph_id']);
			}

			if (empty($data_template_id)) {
				$data_template_id = db_fetch_cell_prepared('SELECT data_template_id
					FROM thold_template
					WHERE id = ?',
					array($thold_template_id));
			}

			if (isset($graph_array['local_data_id'][$data_template_id])) {
				set_request_var('local_data_id', $graph_array['local_data_id'][$data_template_id]);
			}

			$temp = db_fetch_cell_prepared('SELECT dtr.id
				FROM data_template_rrd AS dtr
				WHERE local_data_id = ?
				AND data_source_name = ?',
				array(get_request_var('local_data_id'), $template['data_source_name']));

			set_request_var('data_template_rrd_id', $temp);
		} else {
			$banner = __('The Graph Creation failed for Threshold Template', 'thold');
			thold_raise_message($banner, MESSAGE_LEVEL_ERROR);
			cacti_log('ERROR: Graph Creation failed for Threshold Template', false, 'THOLD');

			return false;
		}

		if (!isset_request_var('save_autocreate') || get_filter_request_var('save_autocreate') == 1) {
			$autocreated = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM plugin_thold_host
				WHERE host_id = ?
				AND thold_template_id = ?',
				array($device_id, $thold_template_id));

			if ($autocreated) {
				$thold = db_fetch_cell_prepared('SELECT id
					FROM thold_data
					WHERE local_data_id = ?
					AND local_graph_id = ?
					AND data_template_rrd_id = ?
					AND data_source_name = ?',
					array(
						get_request_var('local_data_id'),
						get_request_var('local_graph_id'),
						get_request_var('data_template_rrd_id'),
						$template['data_source_name']
					)
				);

				if ($thold > 0) {
					$banner = __('Threshold was Autocreated due to Device Template mapping', 'thold');
					thold_raise_message($banner, MESSAGE_LEVEL_INFO);
					return $thold;
				}
			}

			set_request_var('thold_enabled', 'on');
		}
	}

	if (isset_request_var('my_host_id')) {
		$device_id = get_filter_request_var('my_host_id');
	} else {
		$device_id = get_filter_request_var('host_id');
	}

	$local_data_id        = get_filter_request_var('local_data_id');
	$local_graph_id       = get_filter_request_var('local_graph_id');
	$data_template_rrd_id = get_filter_request_var('data_template_rrd_id');

	// In cases where a Graph can have multiple RRDtool data sources
	// coming from different RRDfiles, we have to reset the local
	// local_data_id to match the data_template_rrd_id
	if (!empty($data_template_rrd_id)) {
		$local_data_id = db_fetch_cell_prepared('SELECT local_data_id
			FROM data_template_rrd
			WHERE id = ?',
			array($data_template_rrd_id));
	}

	$template_enabled  = isset_request_var('template_enabled') && get_nfilter_request_var('template_enabled') == 'on' ? 'on' : 'off';
	$thold_per_enabled = isset_request_var('thold_per_enabled'   ) && get_nfilter_request_var('thold_per_enabled'   ) == 'on' ? 'on' : '';

	if ($template_enabled == 'on') {
		if ($local_graph_id > 0 && !is_thold_allowed_graph($local_graph_id)) {
			$banner = __('Permission Denied', 'thold');
			thold_raise_message($banner, MESSAGE_LEVEL_ERROR);

			return false;
		}

		if (get_request_var('id') > 0) {
			db_execute_prepared('UPDATE thold_data
				SET template_enabled = "on", thold_per_enabled = ?
				WHERE id = ?',
				array($thold_per_enabled, get_request_var('id')));
		}

		$data = db_fetch_row_prepared('SELECT id, thold_template_id
			FROM thold_data
			WHERE local_data_id = ?
			AND data_template_rrd_id = ?',
			array($local_data_id, $data_template_rrd_id));

		thold_template_update_threshold($data['id'], $data['thold_template_id']);

		$banner = __('Record Updated', 'thold');

		plugin_thold_log_changes($data['id'], 'modified', array('id' => $data['id'], 'template_enabled' => 'on'));

		thold_raise_message($banner, MESSAGE_LEVEL_INFO);

		return get_filter_request_var('id');
	}

	get_filter_request_var('thold_fail_trigger');
	get_filter_request_var('thold_warning_fail_trigger');

	get_filter_request_var('repeat_alert');

	get_filter_request_var('thold_type');

	get_filter_request_var('time_fail_trigger');
	get_filter_request_var('time_fail_length');
	get_filter_request_var('time_warning_fail_trigger');
	get_filter_request_var('time_warning_fail_length');

	get_filter_request_var('data_type');
	get_filter_request_var('cdef');

	get_filter_request_var('notify_warning');
	get_filter_request_var('notify_alert');

	get_filter_request_var('bl_type');
	get_filter_request_var('bl_ref_time_range');
	get_filter_request_var('bl_fail_trigger');

	get_filter_request_var('syslog_facility');
	get_filter_request_var('syslog_priority');

	$save = array();

	if ($thold_template_id > 0 && !isset_request_var('id')) {
		$save = $template;

		unset($save['id']);
		unset($save['hash']);
		unset($save['suggested_name']);
		unset($save['data_source_id']);
		unset($save['data_template_name']);
		unset($save['data_source_friendly']);
		unset($save['notify_templated']);

		set_request_var('thold_enabled', 'on');
		set_request_var('template_enabled', 'on');
	}

	if (isset_request_var('id')) {
		$save['id'] = get_request_var('id');
	} else {
		$save['id'] = '0';
	}

	if (isset_request_var('snmp_event_category')) {
		set_request_var('snmp_event_category', trim(str_replace(array("\\", "'", '"'), '', get_nfilter_request_var('snmp_event_category'))));
	}

	if (isset_request_var('snmp_event_description')) {
		set_request_var('snmp_event_description', trim(str_replace(array("\\", "'", '"'), '', get_nfilter_request_var('snmp_event_description'))));
	}

	if (isset_request_var('snmp_event_severity')) {
		get_filter_request_var('snmp_event_severity');
	}

	if (isset_request_var('snmp_event_warning_severity')) {
		get_filter_request_var('snmp_event_warning_severity');
	}

	if (!empty($data_template_rrd_id)) {
		$data_source_name = db_fetch_cell_prepared('SELECT data_source_name
			FROM data_template_rrd
			WHERE id = ?',
			array($data_template_rrd_id));

		$data_source_info = db_fetch_row_prepared('SELECT data_template_id, data_source_name
			FROM data_template_rrd
			WHERE id = ?',
			array($data_template_rrd_id));
	} elseif (!empty($local_graph_id) && empty($graph_template_id)) {
		$graph_template_id = db_fetch_cell_prepared('SELECT graph_template_id
			FROM graph_local
			WHERE id = ?',
			array($local_graph_id));
	}

	$save['host_id']              = $device_id;
	$save['data_template_rrd_id'] = $data_template_rrd_id;
	$save['local_data_id']        = $local_data_id;

	if ($thold_template_id == 0) {
		$save['thold_enabled'] = 'on';
	} else {
		$save['thold_enabled'] = isset_request_var('thold_enabled') && get_request_var('thold_enabled') == 'on' ? 'on':'off';
	}

	$save['thold_per_enabled'] = isset_request_var('thold_per_enabled') && get_request_var('thold_per_enabled') == 'on' ? 'on':'';

	if ($thold_template_id > 0) {
		$save['thold_template_id'] = $thold_template_id;
	}

	$save['exempt']               = isset_request_var('exempt') ? 'on':'';
	$save['repeat_alert']         = get_filter_request_var('repeat_alert');
	$save['data_template_id']     = $data_source_info['data_template_id'];
	$save['data_source_name']     = $data_source_info['data_source_name'];

	// Acknowledgment
	if (isset_request_var('acknowledgment')) {
		switch(get_nfilter_request_var('acknowledgment')) {
			case 'none':
				$save['reset_ack']   = '';
				$save['persist_ack'] = '';

				break;
			case 'reset_ack':
				$save['reset_ack']   = 'on';
				$save['persist_ack'] = '';

				break;
			case 'persist_ack':
				$save['reset_ack']   = '';
				$save['persist_ack'] = 'on';

				break;
		}
	} else {
		$save['reset_ack']   = '';
		$save['persist_ack'] = '';
	}

	// Syslog Settings
	$save['syslog_enabled']       = isset_request_var('syslog_enabled') ? 'on':'';
	$save['syslog_priority']      = get_request_var('syslog_priority');
	$save['syslog_facility']      = get_request_var('syslog_facility');

	// HRULE Settings
	$save['thold_hrule_warning']  = get_nfilter_request_var('thold_hrule_warning');
	$save['thold_hrule_alert']    = get_nfilter_request_var('thold_hrule_alert');
	$save['skipscale']            = isset_request_var('skipscale') ? 'on':'';

	$save['restored_alert']       = isset_request_var('restored_alert') ? 'on':'';
	$save['thold_type']           = get_request_var('thold_type');
	$save['template_enabled']     = isset_request_var('template_enabled') ? 'on':'off';

	// High / Low
	$save['thold_hi']             = trim_round_request_var('thold_hi', 4, 'thold_hi');
	$save['thold_low']            = trim_round_request_var('thold_low', 4, 'thold_low');
	$save['thold_fail_trigger']   = isempty_request_var('thold_fail_trigger') ? read_config_option('alert_trigger'):get_nfilter_request_var('thold_fail_trigger');

	// Time Based
	$save['time_hi']              = trim_round_request_var('time_hi', 4, 'time_hi');
	$save['time_low']             = trim_round_request_var('time_low', 4, 'time_low');
	$save['time_fail_trigger']    = isempty_request_var('time_fail_trigger') ? read_config_option('thold_warning_time_fail_trigger'):get_nfilter_request_var('time_fail_trigger');

	$save['time_fail_length']     = isempty_request_var('time_fail_length') ? (read_config_option('thold_warning_time_fail_length') > 0 ?
		read_config_option('thold_warning_time_fail_length') : 1) : get_nfilter_request_var('time_fail_length');

	// Warning High / Low
	$save['thold_warning_hi']           = trim_round_request_var('thold_warning_hi', 4, 'thold_warning_hi');
	$save['thold_warning_low']          = trim_round_request_var('thold_warning_low', 4, 'thold_warning_low');
	$save['thold_warning_fail_trigger'] = isempty_request_var('thold_warning_fail_trigger') ? read_config_option('alert_trigger'):get_nfilter_request_var('thold_warning_fail_trigger');

	// Warning Time Based
	$save['time_warning_hi']             = trim_round_request_var('time_warning_hi', 4, 'time_warning_hi');
	$save['time_warning_low']            = trim_round_request_var('time_warning_low', 4, 'time_warning_low');
	$save['time_warning_fail_trigger']   = isempty_request_var('time_warning_fail_trigger') ?
		read_config_option('thold_warning_time_fail_trigger') : get_nfilter_request_var('time_warning_fail_trigger');

	$save['time_warning_fail_length']    = isempty_request_var('time_warning_fail_length') ?
		(read_config_option('thold_warning_time_fail_length') > 0 ?
		read_config_option('thold_warning_time_fail_length') : 1) : get_nfilter_request_var('time_warning_fail_length');

	// Baseline
	$save['bl_thold_valid']    = '0';
	$save['bl_type']           = get_filter_request_var('bl_type');
	$save['bl_cf']             = get_nfilter_request_var('bl_cf');
	$save['bl_ref_time_range'] = isempty_request_var('bl_ref_time_range') ? read_config_option('alert_bl_timerange_def'):get_nfilter_request_var('bl_ref_time_range');
	$save['bl_pct_down']       = trim_round_request_var('bl_pct_down', 4, 'bl_pct_down');
	$save['bl_pct_up']         = trim_round_request_var('bl_pct_up', 4, 'bl_pct_up');
	$save['bl_fail_trigger']   = isempty_request_var('bl_fail_trigger') ? read_config_option('alert_bl_trigger'):get_nfilter_request_var('bl_fail_trigger');

	// Notification
	$save['notify_extra']         = get_nfilter_request_var('notify_extra');
	$save['notify_warning_extra'] = get_nfilter_request_var('notify_warning_extra');
	$save['notify_warning']       = get_filter_request_var('notify_warning');
	$save['notify_alert']         = get_filter_request_var('notify_alert');

	// Notes
	$save['notes']          = get_nfilter_request_var('notes');
	$save['external_id']    = get_nfilter_request_var('external_id');
	$save['format_file']    = get_nfilter_request_var('format_file');
	$save['graph_timespan'] = get_nfilter_request_var('graph_timespan');

	// Data Manipulation
	$save['data_type']    = get_nfilter_request_var('data_type');
	$save['percent_ds']   = (isset_request_var('percent_ds')) ? get_nfilter_request_var('percent_ds') : '';
	$save['cdef']         = get_filter_request_var('cdef');
	$save['expression']   = (isset_request_var('expression')) ? get_nfilter_request_var('expression') : '';
	$save['upper_ds']     = (isset_request_var('upper_ds')) ? get_nfilter_request_var('upper_ds') : '';
	$save['show_units']   = isset_request_var('show_units') && get_request_var('show_units') == 'on' ? 'on':'off';
	$save['units_suffix'] = get_nfilter_request_var('units_suffix');
	$save['decimals']     = get_nfilter_request_var('decimals');

	// Email Subjects
	$save['email_subject']          = get_nfilter_request_var('email_subject');
	$save['email_subject_warn']     = get_nfilter_request_var('email_subject_warn');
	$save['email_subject_restoral'] = get_nfilter_request_var('email_subject_restoral');

	// Email Bodies
	$save['email_body']          = get_nfilter_request_var('email_body');
	$save['email_body_warn']     = get_nfilter_request_var('email_body_warn');
	$save['email_body_restoral'] = get_nfilter_request_var('email_body_restoral');

	// Command execution
	$save['trigger_cmd_high'] = get_nfilter_request_var('trigger_cmd_high');
	$save['trigger_cmd_low']  = get_nfilter_request_var('trigger_cmd_low');
	$save['trigger_cmd_norm'] = get_nfilter_request_var('trigger_cmd_norm');

	// SNMP Information
	$save['snmp_event_category']         = get_filter_request_var('snmp_event_category');
	$save['snmp_event_description']      = get_nfilter_request_var('snmp_event_description');
	$save['snmp_event_severity']         = isset_request_var('snmp_event_severity') ? get_nfilter_request_var('snmp_event_severity'):4;
	$save['snmp_event_warning_severity'] = isset_request_var('snmp_event_warning_severity') ? get_nfilter_request_var('snmp_event_warning_severity'):3;

	if ($local_graph_id > 0 && $graph_template_id > 0) {
		$save['local_graph_id']    = $local_graph_id;
		$save['graph_template_id'] = $graph_template_id;
	} elseif ($local_graph_id > 0) {
		$save['local_graph_id']    = $local_graph_id;
		$save['graph_template_id'] = $graph_template_id;
	} else {
		$grapharr = db_fetch_row_prepared('SELECT DISTINCT local_graph_id, graph_template_id
			FROM graph_templates_item
			WHERE task_item_id = ?',
			array($save['data_template_rrd_id']));

		if ($grapharr === false || count($grapharr) == 0) {
			thold_raise_message(__('Failed to find linked Graph Template Item \'%d\' on Threshold \'%d\'', $save['data_template_rrd_id'], $save['id'], 'thold'), MESSAGE_LEVEL_ERROR);

			return false;
		}

		$save['local_graph_id']    = $grapharr['local_graph_id'];
		$save['graph_template_id'] = $grapharr['graph_template_id'];
	}

	if ($save['id'] > 0 && $save['local_graph_id'] > 0 && !is_thold_allowed_graph($save['local_graph_id'])) {
		thold_raise_message(__('Permission Denied', 'thold'), MESSAGE_LEVEL_ERROR);

		return false;
	}

	if (isset_request_var('name') && get_nfilter_request_var('name') != '') {
		$name = get_nfilter_request_var('name');
	} else {
		$name = '|data_source_description| [|data_source_name|]';
	}

	$name_cache = thold_expand_string($save, $name);

	$save['name']       = $name;
	$save['name_cache'] = $name_cache;

	$save = api_plugin_hook_function('thold_edit_save_thold', $save);

	if (!thold_validate_save($save, 'thold')) {
		header("Location: thold.php?action=edit&header=false&id=" . ($save['id'] > 0 ? $save['id']:''));
		exit;
	}

	$id = sql_save($save , 'thold_data');

	if (isempty_request_var('id')) {
		set_config_option('time_last_change_thold', time());
	}

	if (isset_request_var('notify_accounts') && is_array(get_nfilter_request_var('notify_accounts'))) {
		thold_save_threshold_contacts($id, get_nfilter_request_var('notify_accounts'));
	} elseif (!isset_request_var('notify_accounts')) {
		thold_save_threshold_contacts($id, array());
	}

	if ($id) {
		if ($thold_template_id > 0 && $save['template_enabled'] == 'on') {
			thold_template_update_threshold($id, $thold_template_id);
		}

		plugin_thold_log_changes($id, 'modified', $save);

		$thold_sql = "SELECT
			td.*, dtd.rrd_step, tt.name AS template_name,
			dtr.data_source_name as data_source, h.hostname,
			h.description, h.notes AS dnotes, h.snmp_engine_id,
			IF(IFNULL(td.`lastread`,'')='',NULL,(td.`lastread` + 0.0)) as `flastread`, td.`lasttime`,
			IF(IFNULL(td.`oldvalue`,'')='',NULL,(td.`oldvalue` + 0.0)) as `foldvalue`, td.`repeat_alert`,
			UNIX_TIMESTAMP() - UNIX_TIMESTAMP(lastchanged) AS `instate`
			FROM thold_data AS td
			INNER JOIN graph_local AS gl
			ON gl.id = td.local_graph_id
			LEFT JOIN graph_templates AS gt
			ON gt.id = gl.graph_template_id
			LEFT JOIN host AS h
			ON h.id = gl.host_id
			LEFT JOIN thold_template AS tt
			ON tt.id = td.thold_template_id
			LEFT JOIN data_template_data AS dtd
			ON dtd.local_data_id = td.local_data_id
			LEFT JOIN data_template_rrd AS dtr
			ON dtr.id = td.data_template_rrd_id
			WHERE td.id = ?";

		$thold = db_fetch_row_prepared($thold_sql, array($id));

		if ($save['thold_type'] == 1) {
			thold_check_threshold($thold);
		}

		set_request_var('id', $id);
	} else {
		set_request_var('id', '0');
	}

	if ($save['id'] == 0) {
		$banner = __esc('Created Threshold: %s', $save['name_cache'], 'thold');
	} else {
		$banner = __('Record Updated', 'thold');
	}

	thold_raise_message($banner, MESSAGE_LEVEL_INFO);

	return $id;
}

function trim_round_request_var($variable, $digits = 0, $field_name = '') {
	$variable = trim(get_nfilter_request_var($variable));

	if (!empty($variable)) {
		$variable = thold_display_to_raw($variable, $field_name);
	}

	if ($variable == '0') {
		return '0';
	} elseif (empty($variable)) {
		return '';
	} elseif ($digits > 0) {
		return round($variable, $digits);
	} else {
		return $variable;
	}
}

function thold_save_template_contacts($id, $contacts) {
	db_execute_prepared('DELETE
		FROM plugin_thold_template_contact
		WHERE template_id = ?',
		array($id));

	if (!empty($contacts)) {
		foreach ($contacts as $contact) {
			db_execute_prepared('INSERT INTO plugin_thold_template_contact
				(template_id, contact_id)
				VALUES (?, ?)',
				array($id, $contact));
		}
	}
}

function thold_raise_message($message, $level = MESSAGE_LEVEL_NONE) {
	static $thold_message_count = 0;
	$message_id = 'thold_message_' . $thold_message_count;

	cacti_log("raise_message($message_id, $message, $level);" . cacti_debug_backtrace('', false, false), false, 'THOLD', POLLER_VERBOSITY_DEBUG);

	raise_message($message_id, $message, $level);
	$thold_message_count++;
}

function thold_save_threshold_contacts($id, $contacts) {
	db_execute_prepared('DELETE
		FROM plugin_thold_threshold_contact
		WHERE thold_id = ?',
		array($id));

	foreach ($contacts as $contact) {
		db_execute_prepared('INSERT INTO plugin_thold_threshold_contact
			(thold_id, contact_id)
			VALUES (?, ?)',
			array($id, $contact));
	}
}

function thold_validate_save($save, $type = 'thold_template') {
	/**
	 * Type Types:
	 * 0 - Hi / Low
	 * 1 - Baseline Deviation
	 * 2 - Time Based
	 */
	$banner = '';

	/**
	 * Only validate thresholds once they are created
	 * This is required due to the wizard control on the
	 * Thold page.
	 */
	if ($type == 'thold' && empty($save['id'])) {
		return true;
	}

	if ($save['thold_type'] == 0) {
		/* Hi / Low Checks */
		/* Alert must always be set! */
		if ($save['thold_hi'] == '' && $save['thold_low'] == '') {
			$banner .= ($banner != '' ? '<br>':'') . __('You must specify either \'High Alert Threshold\' or \'Low Alert Threshold\' or both!', 'thold');

			$_SESSION['sess_error_fields']['thold_hi']  = 'thold_hi';
			$_SESSION['sess_error_fields']['thold_low'] = 'thold_low';
		}

		if ($save['thold_hi'] != '' && $save['thold_low'] != '' && $save['thold_low'] >= $save['thold_hi']) {
			$banner .= ($banner != '' ? '<br>':'') . __('Impossible threshold: \'High Alert Threshold\' smaller than the \'Low Alert Threshold\'!', 'thold');

			$_SESSION['sess_error_fields']['thold_hi']  = 'thold_hi';
			$_SESSION['sess_error_fields']['thold_low'] = 'thold_low';
		}

		/* Warning is optional.  Check if set! */
		if ($save['thold_warning_hi'] != '' || $save['thold_warning_low'] != '') {
			if ($save['thold_warning_hi'] != '' && $save['thold_warning_low'] != '' && $save['thold_warning_low'] >= $save['thold_warning_hi']) {
				$banner .= ($banner != '' ? '<br>':'') . __('Impossible threshold: \'High Warning Threshold\' smaller than the \'Low Warning Threshold\'!', 'thold');

				$_SESSION['sess_error_fields']['thold_warning_hi']  = 'thold_warning_hi';
				$_SESSION['sess_error_fields']['thold_warning_low'] = 'thold_warning_low';
			}

			if (!empty($save['thold_warning_hi']) && $save['thold_hi'] <= $save['thold_warning_hi']) {
				$banner .= ($banner != '' ? '<br>':'') . __('Impossible threshold: \'High Warning Threshold\' larger than the \'High Alert Threshold\'!', 'thold');

				$_SESSION['sess_error_fields']['thold_hi']         = 'thold_hi';
				$_SESSION['sess_error_fields']['thold_warning_hi'] = 'thold_warning_hi';
			}

			if (!empty($save['thold_warning_low']) && $save['thold_low'] >= $save['thold_warning_low']) {
				$banner .= ($banner != '' ? '<br>':'') . __('Impossible threshold: \'Low Alert Threshold\' larger than the \'Low Warning Threshold\'!', 'thold');

				$_SESSION['sess_error_fields']['thold_low']         = 'thold_low';
				$_SESSION['sess_error_fields']['thold_warning_low'] = 'thold_warning_low';
			}
		}
	} elseif ($save['thold_type'] == 1) {
		/* Baseline Deviation Checks */
		$banner .= ($banner != '' ? '<br>':'') . __('With baseline thresholds enabled.', 'thold');

		if (empty($save['bl_ref_time_range']) || $save['bl_ref_time_range'] <= 0) {
			$banner .= ($banner != '' ? '<br>':'') . __('Time reference in the past must be set to positive integer value!', 'thold');

			$_SESSION['sess_error_fields']['bl_ref_time_range'] = 'bl_ref_time_range';
		}

		if (empty($save['bl_pct_down']) && empty($save['bl_pct_up'])) {
			$banner .= ($banner != '' ? '<br>':'') . __('You must specify either \'Baseline Deviation UP\' or \'Baseline Deviation DOWN\' or both!', 'thold');

			$_SESSION['sess_error_fields']['bl_pct_up']   = 'bl_pct_up';
			$_SESSION['sess_error_fields']['bl_pct_down'] = 'bl_pct_down';
		}
	} elseif ($save['thold_type'] == 2) {
		/* Time Based Checks */
		/* Alert must always be set! */
		if ($save['time_hi'] == '' && $save['time_low'] == '') {
			$banner .= ($banner != '' ? '<br>':'') . __('You must specify either \'High Alert Threshold\' or \'Low Alert Threshold\' or both!', 'thold');

			$_SESSION['sess_error_fields']['time_hi']  = 'time_hi';
			$_SESSION['sess_error_fields']['time_low'] = 'time_low';
		}

		if ($save['time_hi'] != '' && $save['time_low'] != '' && $save['time_low'] >= $save['time_hi']) {
			$banner .= ($banner != '' ? '<br>':'') . __('Impossible threshold: \'High Alert Threshold\' smaller than the \'Low Alert Threshold\'', 'thold');

			$_SESSION['sess_error_fields']['time_hi']  = 'time_hi';
			$_SESSION['sess_error_fields']['time_low'] = 'time_low';
		}

		if (empty($save['time_fail_trigger']) || $save['time_fail_trigger'] <= 0) {
			$banner .= ($banner != '' ? '<br>':'') . __('Impossible threshold: The Alert Trigger Count must be greater or eval to 1!', 'thold');

			$_SESSION['sess_error_fields']['time_fail_trigger'] = 'time_fail_trigger';
		}

		if ($save['time_fail_length'] < $save['time_fail_trigger']) {
			$banner .= ($banner != '' ? '<br>':'') . __('Impossible threshold: The Alert Trigger Count must be less than or equal to the Trigger Count or eval to 1!', 'thold');

			$_SESSION['sess_error_fields']['time_fail_length'] = 'time_fail_length';
			$_SESSION['sess_error_fields']['time_fail_trigger'] = 'time_fail_trigger';
		}

		/* Warning is optional.  Check if set! */
		if ($save['time_warning_hi'] != '' || $save['time_warning_low'] != '') {
			if ($save['time_warning_hi'] != '' && $save['time_warning_low'] != '' && $save['time_warning_low'] >= $save['time_warning_hi']) {
				$banner .= ($banner != '' ? '<br>':'') . __('Impossible threshold: \'High Warning Threshold\' smaller than the \'Low Warning Threshold\'!', 'thold');

				$_SESSION['sess_error_fields']['time_warning_hi']  = 'time_warning_hi';
				$_SESSION['sess_error_fields']['time_warning_low'] = 'time_warning_low';
			}

			if (empty($save['time_warning_fail_trigger']) || $save['time_warning_fail_trigger'] <= 0) {
				$banner .= ($banner != '' ? '<br>':'') . __('Impossible threshold: The Warning Trigger Count must be greater or eval to 1!', 'thold');

				$_SESSION['sess_error_fields']['time_warning_fail_trigger'] = 'time_warning_fail_trigger';
			}

			if ($save['time_warning_fail_length'] < $save['time_warning_fail_trigger']) {
				$banner .= ($banner != '' ? '<br>':'') . __('Impossible threshold: The Warning Trigger Count must be less than or equal to the Trigger Count or eval to 1!', 'thold');
				$_SESSION['sess_error_fields']['time_warning_fail_trigger'] = 'time_warning_fail_trigger';
				$_SESSION['sess_error_fields']['time_warning_fail_length']  = 'time_warning_fail_length';
			}

			if (!empty($save['time_warning_hi']) && $save['time_hi'] <= $save['time_warning_hi']) {
				$banner .= ($banner != '' ? '<br>':'') . __('Impossible threshold: \'High Warning Threshold\' larger than the \'High Alert Threshold\'!', 'thold');

				$_SESSION['sess_error_fields']['time_hi']         = 'time_hi';
				$_SESSION['sess_error_fields']['time_warning_hi'] = 'time_warning_hi';
			}

			if (!empty($save['time_warning_low']) && $save['time_low'] >= $save['time_warning_low']) {
				$banner .= ($banner != '' ? '<br>':'') . __('Impossible threshold: \'Low Warning Threshold\' smaller than the \'Low Alert Threshold\'!', 'thold');

				$_SESSION['sess_error_fields']['time_low']         = 'time_low';
				$_SESSION['sess_error_fields']['time_warning_low'] = 'time_warning_low';
			}
		}
	} else {
		$banner .= ($banner != '' ? '<br>':'') . __('Unknown Threshold Type!', 'thold');
	}

	if ($banner != '') {
		thold_raise_message($banner, MESSAGE_LEVEL_ERROR);

		return false;
	}

	return true;
}

// populate the save structure from a thold template
function thold_create_thold_save_from_template($save, $template) {
	// General Settings
	$save['name']              = $template['suggested_name'];
	$save['data_template_id']  = $template['data_template_id'];
	$save['data_source_name']  = $template['data_source_name'];
	$save['thold_template_id'] = $template['id'];
	$save['template_enabled']  = 'on';
	$save['thold_enabled']     = $template['thold_enabled'];
	$save['thold_type']        = $template['thold_type'];

	// Additional General
	$save['thold_alert']    = 0;
	$save['restored_alert'] = $template['restored_alert'];
	$save['repeat_alert']   = $template['repeat_alert'];
	$save['exempt']         = $template['exempt'];

	// Alert High/Low
	$save['thold_hi']           = $template['thold_hi'];
	$save['thold_low']          = $template['thold_low'];
	$save['thold_fail_trigger'] = $template['thold_fail_trigger'];

	// Warning High/Low
	$save['thold_warning_hi']           = $template['thold_warning_hi'];
	$save['thold_warning_low']          = $template['thold_warning_low'];
	$save['thold_warning_fail_trigger'] = $template['thold_warning_fail_trigger'];
	$save['thold_warning_fail_count']   = $template['thold_warning_fail_count'];

	// Alert Time Based
	$save['time_hi']                    = $template['time_hi'];
	$save['time_low']                   = $template['time_low'];
	$save['time_fail_trigger']          = $template['time_fail_trigger'];
	$save['time_fail_length']           = $template['time_fail_length'];

	// Warning Time Based
	$save['time_warning_hi']            = $template['time_warning_hi'];
	$save['time_warning_low']           = $template['time_warning_low'];
	$save['time_warning_fail_trigger']  = $template['time_warning_fail_trigger'];
	$save['time_warning_fail_length']   = $template['time_warning_fail_length'];

	// Baseline
	$save['bl_type']           = $template['bl_type'];
	$save['bl_cf']             = $template['bl_cf'];
	$save['bl_ref_time_range'] = $template['bl_ref_time_range'];
	$save['bl_pct_down']       = $template['bl_pct_down'];
	$save['bl_pct_up']         = $template['bl_pct_up'];
	$save['bl_fail_trigger']   = $template['bl_fail_trigger'];
	$save['bl_fail_count']     = $template['bl_fail_count'];
	$save['bl_alert']          = $template['bl_alert'];

	// Notification
	$save['notify_alert']         = $template['notify_alert'];
	$save['notify_warning']       = $template['notify_warning'];
	$save['notify_extra']         = $template['notify_extra'];
	$save['notify_warning_extra'] = $template['notify_warning_extra'];

	// Data Manipulation
	$save['data_type']    = $template['data_type'];
	$save['cdef']         = $template['cdef'];
	$save['percent_ds']   = $template['percent_ds'];
	$save['expression']   = $template['expression'];
	$save['upper_ds']     = $template['upper_ds'];
	$save['decimals']     = $template['decimals'];
	$save['units_suffix'] = $template['units_suffix'];
	$save['show_units']   = $template['show_units'];

	// Hrules
	$save['thold_hrule_alert']   = $template['thold_hrule_alert'];
	$save['thold_hrule_warning'] = $template['thold_hrule_warning'];
	$save['skipscale']           = $template['skipscale'];

	// Syslog
	$save['syslog_enabled']  = $template['syslog_enabled'];
	$save['syslog_priority'] = $template['syslog_priority'];
	$save['syslog_facility'] = $template['syslog_facility'];

	// Command execution
	$save['trigger_cmd_high'] = $template['trigger_cmd_high'];
	$save['trigger_cmd_low']  = $template['trigger_cmd_low'];
	$save['trigger_cmd_norm'] = $template['trigger_cmd_norm'];

	// Acknowledgment
	$save['reset_ack']      = $template['reset_ack'];
	$save['persist_ack']    = $template['persist_ack'];

	// Email Subjects
	$save['email_subject']          = $template['email_subject'];
	$save['email_subject_warn']     = $template['email_subject_warn'];
	$save['email_subject_restoral'] = $template['email_subject_restoral'];

	// Email Bodies
	$save['email_body']          = $template['email_body'];
	$save['email_body_warn']     = $template['email_body_warn'];
	$save['email_body_restoral'] = $template['email_body_restoral'];

	// SNMP
	$save['snmp_event_category']         = $template['snmp_event_category'];
	$save['snmp_event_description']      = $template['snmp_event_description'];
	$save['snmp_event_severity']         = $template['snmp_event_severity'];
	$save['snmp_event_warning_severity'] = $template['snmp_event_warning_severity'];

	// Other
	$save['notes']          = $template['notes'];
	$save['external_id']    = $template['external_id'];
	$save['format_file']    = $template['format_file'];
	$save['graph_timespan'] = $template['graph_timespan'];

	return $save;
}

// Create tholds for all possible data elements for a host
function autocreate($device_ids, $graph_ids = '', $graph_template_id = '', $thold_template_id = '', $log = false) {
	$created = 0;
	$message = '';
	$device_id = 0;

	// Don't autocreate if not asked to
	if (isset_request_var('save_autocreate') && get_filter_request_var('save_autocreate') == 0) {
		return;
	}

	$sql_where = '';

	if (!empty($thold_template_id) && is_numeric($thold_template_id)) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'tt.id = ' . $thold_template_id;

		if (empty($graph_template_id)) {
			$data_template_id = db_fetch_cell_prepared('SELECT data_template_id
				FROM thold_template
				WHERE id = ?',
				array($thold_template_id));

			if ($data_template_id > 0) {
				$graph_template_ids = array_rekey(
					db_fetch_assoc_prepared('SELECT DISTINCT gti.graph_template_id
						FROM graph_templates_item AS gti
						INNER JOIN data_template_rrd AS dtr
						ON gti.task_item_id = dtr.id
						AND gti.local_graph_id = 0
						AND dtr.data_template_id = ?',
						array($data_template_id)),
					'graph_template_id', 'graph_template_id'
				);

				if (cacti_sizeof($graph_template_ids)) {
					$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'gl.graph_template_id IN (' . implode(', ', $graph_template_ids) . ')';
				}
			}
		}
	}

	if (!empty($graph_template_id) && is_numeric($graph_template_id)) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'gl.graph_template_id = ' . $graph_template_id;
	}

	if (is_array($graph_ids) && cacti_sizeof($graph_ids)) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'gti.local_graph_id IN(' . implode(', ', $graph_ids) . ')';
	}

	if (is_array($device_ids)) {
		$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'gl.host_id IN(' . implode($device_ids) . ')';
	} elseif ($device_ids > 0) {
		$device_id = $device_ids;
	}

	if ($device_id > 0) {
		$host_template_id = db_fetch_cell_prepared('SELECT host_template_id
			FROM host
			WHERE id = ?',
			array($device_id));

		$templates = db_fetch_assoc_prepared('SELECT tt.*
			FROM thold_template AS tt
			INNER JOIN plugin_thold_host AS pth
			ON tt.id = pth.thold_template_id
			WHERE pth.host_id = ?',
			array($device_id));

		if (!cacti_sizeof($templates)) {
			if ($log) {
				thold_raise_message(__('No Thresholds Templates associated with the Device\'s Template.', 'thold'), MESSAGE_LEVEL_ERROR);
			}

			return 0;
		} else {
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'gl.host_id = ' . $device_id;
			$sql_where .= ($sql_where != '' ? ' AND ':'WHERE ') . 'dtr.data_source_name = ?';

			foreach($templates as $template) {
				$new_where = $sql_where . ' AND tt.id = ' . $template['id'];

				$data_sources = db_fetch_assoc_prepared("SELECT DISTINCT
					dtr.id, gti.local_graph_id, local_data_id, gl.snmp_query_id
					FROM data_template_rrd AS dtr
					INNER JOIN thold_template AS tt
					ON tt.data_template_id = dtr.data_template_id
					AND tt.data_source_name = dtr.data_source_name
					INNER JOIN graph_templates_item AS gti
					ON gti.task_item_id = dtr.id
					INNER JOIN graph_local AS gl
					ON gl.id = gti.local_graph_id
					$new_where",
					array($template['data_source_name']));

				if (cacti_sizeof($data_sources)) {
					foreach($data_sources as $data_source) {
						$local_data_id        = $data_source['local_data_id'];
						$local_graph_id       = $data_source['local_graph_id'];
						$data_template_rrd_id = $data_source['id'];

						// Don't create a second threshold for a data source that already has a threshold
						if ($data_source['snmp_query_id'] > 0) {
							$exists = db_fetch_cell_prepared('SELECT id
								FROM thold_data
								WHERE local_data_id = ?
								AND thold_template_id = ?
								AND data_template_rrd_id = ?',
								array($local_data_id, $template['id'], $data_template_rrd_id));
						} else {
							$exists = false;
						}

						if (!$exists && thold_create_from_template($local_data_id, $local_graph_id, $data_template_rrd_id, $template, $message)) {
							$created++;
						}
					}
				}
			}
		}
	} else {
		$data_sources = db_fetch_assoc("SELECT DISTINCT
			dtr.id, gl.id AS local_graph_id, local_data_id, tt.id AS thold_template_id, gl.snmp_query_id
			FROM data_template_rrd AS dtr
			INNER JOIN thold_template AS tt
			ON tt.data_template_id = dtr.data_template_id
			AND tt.data_source_name = dtr.data_source_name
			INNER JOIN graph_templates_item AS gti
			ON gti.task_item_id = dtr.id
			INNER JOIN graph_local AS gl
			ON gl.id = gti.local_graph_id
			$sql_where");

		if (cacti_sizeof($data_sources)) {
			foreach($data_sources as $data_source) {
				$local_data_id        = $data_source['local_data_id'];
				$local_graph_id       = $data_source['local_graph_id'];
				$data_template_rrd_id = $data_source['id'];

				$template  = db_fetch_row_prepared('SELECT *
					FROM thold_template
					WHERE id = ?',
					array($data_source['thold_template_id']));

				if (cacti_sizeof($template)) {
					foreach($data_sources as $data_source) {
						// Don't create a second threshold for a data source that already has a threshold
						if ($data_source['snmp_query_id'] > 0) {
							$exists = db_fetch_cell_prepared('SELECT id
								FROM thold_data
								WHERE local_data_id = ?
								AND thold_template_id = ?
								AND data_template_rrd_id = ?',
								array($local_data_id, $template['id'], $data_template_rrd_id));
						} else {
							$exists = false;
						}

						if (!$exists && thold_create_from_template($local_data_id, $local_graph_id, $data_template_rrd_id, $template, $message)) {
							$created++;
						}
					}
				}
			}
		}
	}

	if (strlen($message)) {
		thold_raise_message($message, MESSAGE_LEVEL_INFO);
	} else {
		thold_raise_message(__('No Threshold(s) Created.  Either they already exist, or no suitable matches found.', 'thold'), MESSAGE_LEVEL_INFO);
	}

	return $created;
}

function thold_create_from_template($local_data_id, $local_graph_id, $data_template_rrd_id, $template_or_id, &$message) {
	if (is_array($template_or_id)) {
		$template = $template_or_id;
	} else {
		$template = db_fetch_row_prepared('SELECT *
			FROM thold_template
			WHERE id = ?',
			array($template_or_id));
	}

	$data_source_name = db_fetch_cell_prepared('SELECT data_source_name
		FROM data_template_rrd
		WHERE id = ?',
		array($data_template_rrd_id));

	// Don't create the threshold if the dtr data source
	// does not match the templates
	if ($data_source_name != $template['data_source_name']) {
		return false;
	}

	$exists = db_fetch_cell_prepared('SELECT id
		FROM thold_data
		WHERE local_graph_id = ?
		AND local_data_id = ?
		AND data_template_rrd_id = ?
		AND data_source_name = ?
		AND thold_template_id = ?',
		array(
			$local_graph_id,
			$local_data_id,
			$data_template_rrd_id,
			$template['data_source_name'],
			$template['id']
		)
	);

	if (!$exists) {
		$graph = db_fetch_row_prepared('SELECT gtg.local_graph_id, gtg.title_cache,
			gtg.graph_template_id, gl.host_id
			FROM graph_templates_graph AS gtg
			INNER JOIN graph_local AS gl
			ON gl.id = gtg.local_graph_id
			WHERE gtg.local_graph_id = ?',
			array($local_graph_id));

		if (cacti_sizeof($template) && cacti_sizeof($graph)) {
			$save                         = array();
			$save['id']                   = 0;
			$save['local_data_id']        = $local_data_id;
			$save['data_template_rrd_id'] = $data_template_rrd_id;
			$save['local_graph_id']       = $graph['local_graph_id'];
			$save['graph_template_id']    = $graph['graph_template_id'];
			$save['host_id']              = $graph['host_id'];

			$save = thold_create_thold_save_from_template($save, $template);

			$save['name_cache'] = thold_expand_string($save, $save['name']);

			$save = api_plugin_hook_function('thold_edit_save_thold', $save);

			if (!thold_validate_save($save, 'thold')) {
				header("Location: thold.php?action=edit&header=false&id=" . ($save['id'] > 0 ? $save['id']:''));
				exit;
			}

			$id = sql_save($save, 'thold_data');

			set_config_option('time_last_change_thold', time());

			if ($id) {
				thold_template_update_threshold($id, $save['thold_template_id']);
				plugin_thold_log_changes($id, 'auto_created', $save['name_cache']);

				$message .= __esc('Created Threshold: %s', $save['name_cache'], 'thold') . '<br>';

				return true;
			}
		}
	}

	return false;
}

/* Sends a group of graphs to a user */
function thold_mail($to_email, $bcc_email, $from_email, $subject, $message, $filename, $headers = array(), $notify_list_id = 0, &$host = array(), $format_file = '', $graph_timespan = 7, $topic = 'thold_mail') {
	thold_debug('Preparing to send email');
	global $config;

	$subject = trim($subject);
	$message = thold_str_replace('<SUBJECT>', $subject, $message);

	if ($from_email == '') {
		$from_email = read_config_option('thold_from_email');
		$from_name  = read_config_option('thold_from_name');

		if ($from_email == '') {
			if (isset($_SERVER['HOSTNAME'])) {
				$from_email = 'cacti@' . $_SERVER['HOSTNAME'];
			} else {
				$from_email = 'cacti@' . gethostname();
			}
		}

		if ($from_name == '') {
			$from_name = 'Cacti';
		}
	}

	if ($to_email == '') {
		return __('Mailer Error: No <b>TO</b> address set!!<br>If using the <i>Test Mail</i> link, please set the <b>Alert Email</b> setting.', 'thold');
	}

	$attachments = array();

	$notification_queue = read_config_option('thold_notification_queue');

	if (is_array($filename) && sizeof($filename) && strstr($message, '<GRAPH>') !== 0) {
		if (isset($filename['local_data_id'])) {
			$tmp      = array();
			$tmp[]    = $filename;
			$filename = $tmp;
		}

		$time = time();

		/* get config option for first-day-of-the-week */
		$first_weekdayid = read_user_setting('first_weekdayid');

		$timespan = array();

		/* get start/end time-since-epoch for actual time (now()) and given current-session-timespan */
		get_timespan($timespan, $time, $graph_timespan, $first_weekdayid);

		foreach ($filename as $val) {
			$graph_data_array = array(
				'graph_start'   => $timespan['begin_now'],
				'graph_end'     => $timespan['end_now'],
				'image_format'  => 'png',
				'graph_theme'   => 'modern',
				'output_flag'   => RRDTOOL_OUTPUT_STDOUT,
				'disable_cache' => true
			);

			if ($notification_queue == 'on') {
				$attach = base64_encode(rrdtool_function_graph($val['local_graph_id'], '', $graph_data_array, ''));
			} else {
				$attach = rrdtool_function_graph($val['local_graph_id'], '', $graph_data_array, '');
			}

			$attachments[] = array(
				'attachment'     => $attach,
				'filename'       => 'graph_' . $val['local_graph_id'] . '.png',
				'mime_type'      => 'image/png',
				'local_graph_id' => $val['local_graph_id'],
				'local_data_id'  => $val['local_data_id'],
				'inline'         => 'inline'
			);
		}
	}

	$text = array('text' => '', 'html' => '');
	if (empty($filename)) {
		$text['html'] = $message . '<br>';

		$message = str_replace('<br>',  "\n", $message);
		$message = str_replace('<BR>',  "\n", $message);
		$message = str_replace('</BR>', "\n", $message);
		$text['text'] = strip_tags(str_replace('<br>', "\n", $message));
	} else {
		$text['html'] = $message . '<br>';
		$text['text'] = strip_tags(str_replace('<br>', "\n", $message));
	}

	$format_data = '';
	$report_tag  = false;
	$format_ok   = false;
	$theme       = read_config_option('selected_theme');

	if ($theme == '') {
		$theme = 'classic';
	}

    /* process the format file as applicable */
    if ($format_file != '') {
		include_once($config['library_path'] . '/reports.php');
        $format_ok = reports_load_format_file($format_file, $format_data, $report_tag, $theme);
    }

	if (($format_ok && $report_tag) || strpos($text['html'], '<body>') !== false) {
		$include_body = false;
	} else {
		$include_body = true;
	}

	if ($include_body) {
		$text['html'] = '<body>' . $text['html'] . '</body>';
	}

	if ($format_ok) {
		if ($report_tag) {
			$text['html'] = str_replace('<REPORT>', $text['html'], $format_data);
		} else {
			$text['html'] = $format_data . PHP_EOL . $text['html'];
		}
	}

	$version = db_fetch_cell("SELECT version
		FROM plugin_config
		WHERE directory='thold'");

	if (!is_array($headers)) {
		$headers = array();
	}

	$headers['X-Mailer']   = 'Cacti-Thold-v' . $version;
	$headers['User-Agent'] = 'Cacti-Thold-v' . $version;

	if (read_config_option('thold_email_prio') == 'on') {
		$headers['X-Priority'] = '1';
	}

	thold_debug("Queuing email to '" . trim($to_email,', ') . "'");

	$thold_send_text_only         = read_config_option('thold_send_text_only');
	$thold_send_email_separately  = read_config_option('thold_send_email_separately');

	$any_error = '';

	if ($thold_send_email_separately != 'on') {
		if ($notification_queue == 'on') {
			$data = array(
				'from'        => array($from_email, $from_name),
				'to'          => $to_email,
				'bcc'         => $bcc_email,
				'subject'     => $subject,
				'body'        => $text['html'],
				'body_text'   => $text['text'],
				'attachments' => empty($attachments) ? null : $attachments,
				'headers'     => $headers,
				'html'        => $thold_send_text_only != 'on',
				'id'          => (isset($host['thold_id']) ? $host['thold_id']:$host['id'])
			);

			thold_notification_add($topic, $data, 'id', $notify_list_id, $host);
		} else {
			$any_error = mailer(
				array($from_email, $from_name),
				$to_email,
				'',
				$bcc_email,
				'',
				$subject,
				$text['html'],
				$text['text'],
				empty($attachments) ? null : $attachments,
				$headers,
				$thold_send_text_only != 'on'
			);
		}
	} else {
		$ar_to_email = explode(',', $to_email);

		foreach ($ar_to_email as $to) {
			if (filter_var($to, FILTER_VALIDATE_EMAIL) == $to) {
				if ($notification_queue == 'on') {
					$data = array(
						'from'        => array($from_email, $from_name),
						'to'          => $to,
						'bcc'         => $bcc_email,
						'subject'     => $subject,
						'body'        => $text['html'],
						'body_text'   => $text['text'],
						'attachments' => empty($attachments) ? '' : $attachments,
						'headers'     => $headers,
						'html'        => $thold_send_text_only != 'on',
						'id'          => (isset($host['thold_id']) ? $host['thold_id']:$host['id'])
					);

					thold_notification_add($topic, $data, 'id', $notify_list_id, $host);
				} else {
					$error = mailer(
						array($from_email, $from_name),
						$to,
						'',
						'',
						'',
						$subject,
						$text['html'],
						$text['text'],
						empty($attachments) ? '' : $attachments,
						$headers,
						$thold_send_text_only != 'on'
					);

					if (strlen($error)) {
						cacti_log('ERROR: Sending Email To ' . $to . ' Failed.  Error was ' . $error, true, 'THOLD');
						$any_error = $error;
					}
				}
			}
		}
	}

	if (strlen($any_error)) {
		return $any_error;
	}

	return '';
}

function thold_notification_add($topic, &$data, $id = 'id', $list_id = 0, &$host = array()) {
	$now = date('Y-m-d H:i:s');

	if (isset($data['data'][$id])) {
		$id = $data['data'][$id];
	} else {
		$id = 0;
	}

	if (isset($data['data']['name'])) {
		$name = $data['data']['name'];
	} elseif (isset($data['data']['description'])) {
		$name = $data['data']['description'];
	} elseif (isset($data['subject'])) {
		$name = $data['subject'];
	} else {
		$name = 'Not Specfified';
	}

	if (isset($host['id'])) {
		$host_id = $host['id'];
	} else {
		$host_id = 0;
	}

	if (isset($host['hostname'])) {
		if ($host['hostname'] == 'localhost') {
			$hostname = $host['description'];
		} else {
			$hostname = $host['hostname'];
		}
	} else {
		$hostname = 'unknown';
	}

	db_execute_prepared('INSERT INTO notification_queue
		(topic, notification_list_id, object_id, object_name, host_id, hostname, event_time, event_data) VALUES
		(?, ?, ?, ?, ?, ?, ?, ?)',
		array($topic, $list_id, $id, $name, $host_id, $hostname, $now, json_encode($data, JSON_THROW_ON_ERROR)));
}

function pre_process_device_notifications() {
	$delay_criteria = read_config_option('alert_notification_pause');
	$now            = time();
	$triggers       = array();
	$last_check     = read_config_option('alert_deadnotify_last_check');
	$last_trigger   = read_config_option('alert_deadnotify_state');

	if ($last_trigger != '') {
		$last_trigger = json_decode($last_trigger, true);
	}

	if ($delay_criteria != '') {
		$options = explode(',', $delay_criteria);

		foreach($options as $option) {
			$value_array = explode('|', $option);
			if (isset($value_array[0])) {
				$value = $value_array[0];
			} else {
				$value = '';
			}

			if (isset($value_array[1])) {
				$type = $value_array[1];
			} else {
				$type = '';
			}

			switch($type) {
				case 'eg':  // Global events
					$events = db_fetch_cell_prepared('SELECT COUNT(*) AS events
						FROM notification_queue
						WHERE event_processed = 0
						AND topic IN ("thold_dhost_mail", "thold_dhost_cmd")
						AND event_time BETWEEN ? AND ?',
						array(
							date('Y-m-d H:i:s', $last_check),
							date('Y-m-d H:i:s', $now)
						)
					);

					if ($events > $value) {
						$triggers[$option] = array(
							'events' => $events,
							'time' => $now
						);
					}

					break;
				case 'es': // Events per Site
					$events = db_fetch_assoc_prepared('SELECT site_id, COUNT(*) AS events
						FROM notification_queue AS nq
						INNER JOIN host AS h
						ON nq.host_id = h.id
						WHERE event_processed = 0
						AND topic IN ("thold_dhost_mail", "thold_dhost_cmd")
						AND event_time BETWEEN ? AND ?
						GROUP BY site_id
						HAVING events > ?',
						array(
							date('Y-m-d H:i:s', $last_check),
							date('Y-m-d H:i:s', $now),
							$value
						)
					);

					if (cacti_sizeof($events)) {
						foreach($events as $e) {
							$triggers[$option . '|' . $e['site_id']] = array(
								'events' => $e['events'],
								'time' => $now
							);
						}
					}

					break;
				case 'es': // Percent of events per Site
					$events = db_fetch_assoc_prepared('SELECT h.site_id, COUNT(*) AS events, th.total_hosts
						FROM notification_queue AS nq
						INNER JOIN host AS h
						ON nq.host_id = h.id
						INNER JOIN (
							SELECT site_id, COUNT(*) AS total_hosts
							FROM host
							WHERE disabled = ""
							AND deleted = ""
							GROUP BY site_id
						) AS th
						ON th.site_id = h.site_id
						WHERE nq.event_processed = 0
						AND nq.topic IN ("thold_dhost_mail", "thold_dhost_cmd")
						AND nq.event_time BETWEEN ? AND ?
						GROUP BY h.site_id
						HAVING (events / total_hosts) * 100 > ?',
						array(
							date('Y-m-d H:i:s', $last_check),
							date('Y-m-d H:i:s', $now),
							$value
						)
					);

					if (cacti_sizeof($events)) {
						foreach($events as $e) {
							$triggers[$option . '|' . $e['site_id']] = array(
								'events' => $e['hosts'],
								'time'   => $now
							);
						}
					}

					break;
				case 'peg': // Percent of events Globally
					$events = db_fetch_row_prepared('SELECT
						(SELECT COUNT(*) FROM host WHERE disabled = "" AND deleted = "") AS total_hosts,
						COUNT(nq.host_id) AS events
						FROM notification_queue AS nq
						INNER JOIN host AS h
						ON h.id = nq.host_id
						WHERE h.disabled = ""
						AND h.deleted = ""
						AND nq.event_processed = 0
						AND nq.topic IN ("thold_dhost_mail", "thold_dhost_cmd")
						AND nq.event_time BETWEEN ? AND ?
						HAVING (events / total_hosts) * 100 > ?',
						array(
							date('Y-m-d H:i:s', $last_check),
							date('Y-m-d H:i:s', $now),
							$value
						)
					);

					if (cacti_sizeof($events)) {
						$triggers[$option] = array(
							'events' => $events['events'],
							'time'   => $now
						);
					}

					break;
				default:
					$events   = array();
					$triggers = array();

					break;
			}
		}
	}

	/**
	 * Review the old triggers first to see if the trigger period is over
	 * and it's time to provide final notification and communication
	 *
	 * This function will provide initial notification to the notification
	 * list associated with the devices of the initial event.
	 */
	check_for_expired_delays($last_trigger, $triggers, $now, $last_check);

	/**
	 * Review the new triggers now to see if there are any new triggers
	 * and to setup notification delay for them.  The prior step will
	 * unset any active triggers leaving only new triggers to setup
	 * and deal with.
	 *
	 * This function will also provide any final notification and set the
	 * JSON config option for future notification delay actions
	 */
	check_for_new_delays($last_trigger, $triggers, $now, $last_check);
}

/**
 * check_for_expired_delays - This function will look at the start time of
 *   any trigger delays, and if the delay time has expired, it will check
 *   the status of the hosts in question, and if they are still in a down
 *   state, a final notification will be sent via Email and or Command with
 *
 * @param array - Any active triggers that may or may not remain active
 * @param array - Any new triggers that may be part of an existing delay
 *                or a new delay based upon the ruleset.
 * @param int   - The Unix Timestamp of the start time for the sample period
 * @param int   - The Unix Timestamp of the last time that a check was performed.
 *
 * @return null - Though the two trigger variables that were passed by reference
 *                will be modified based upon the findings.  For example, if a
 *                delay has expired and we provided notification the delay will
 *                be unset, also if we find additional events for and existing
 *                delay, it will be removed from the new events.
 */
function check_for_expired_delays(&$last_trigger, $triggers, $now, $last_check) {
	$delay_period   = read_config_option('alert_notification_delay');

	if (cacti_sizeof($last_trigger)) {
		foreach($last_trigger as $type => $details) {
			$parts = explode('|', $type);

			/**
			 * Its time to provide the final notification for this notification
			 * delay.  Check the type, the variout notification lists and the
			 * status of the hosts.  If they are no longer down, you can cancel
			 * any notifications if not and send out an all clear event.
			 * Otherwise, trigger the notifications as directed.
			 */
			if ($now - $details['start_time'] >= $delay_period) {
				switch($parts[0]) {
					case 'eg':
					case 'peg':
						$hosts_down = db_fetch_assoc_prepared('SELECT h.*,
							nq.id AS notification_id, nq.notification_list_id, nq.topic,
							nq.event_data, nq.event_time
							FROM host AS h
							INNER JOIN notification_queue AS nq
							ON h.id = nq.host_id
							WHERE nq.event_processed = 0
							AND nq.topic IN ("thold_dhost_mail", "thold_dhost_cmd")
							AND nq.event_time BETWEEN ? AND ?
							AND h.status NOT IN (?, ?)',
							array(
								date('Y-m-d H:i:s', $details['start_time']),
								date('Y-m-d H:i:s', $now),
								HOST_RECOVERING,
								HOST_UP
							)
						);

						if (cacti_sizeof($host_down)) {
							/* perform notification of errors */
							$hosts = array_rekey($hosts_down, 'id', 'id');
						} else {
							/* perform notification of clearing of errors */
						}

						if (isset($triggers[$type])) {
							unset($triggers[$type]);
						}

						break;
					case 'es':
					case 'pes':
						$hosts_down = db_fetch_assoc_prepared('SELECT h.*,
							nq.id AS notification_id, nq.notification_list_id, nq.topic,
							nq.event_data, nq.event_time
							FROM host AS h
							INNER JOIN notification_queue AS nq
							ON h.id = nq.host_id
							WHERE nq.event_processed = 0
							AND h.site_id = ?
							AND nq.topic IN ("thold_dhost_mail", "thold_dhost_cmd")
							AND nq.event_time BETWEEN ? AND ?
							AND h.status NOT IN (?, ?)',
							array(
								$parts[2],
								date('Y-m-d H:i:s', $details['start_time']),
								date('Y-m-d H:i:s', $now),
								HOST_RECOVERING,
								HOST_UP
							)
						);

						if (cacti_sizeof($host_down)) {
							/* perform notification of errors */
							$hosts = array_rekey($hosts_down, 'id', 'id');
						} else {
							/* perform notification of clearing of errors */
						}

						if (isset($triggers[$type])) {
							unset($triggers[$type]);
						}
				}
			}
		}
	}
}

/**
 * check_for_new_delays - Given the list of existing triggers and any
 *   new triggers, we will re-establish the triggers and provide the
 *   appropriate initial notification as required.  The function in the
 *   end will set the config option for the next sampling interval to
 *   evaluate.
 *
 * @param array - Any active triggers that may or may not remain active
 * @param array - Any new triggers that may be part of an existing delay
 *                or a new delay based upon the ruleset.
 * @param int   - The Unix Timestamp of the start time for the sample period
 * @param int   - The Unix Timestamp of the last time that a check was performed.
 *
 * @return null
 */
function check_for_new_delays($last_trigger, $triggers, $now, $last_check) {
	$delay_period   = read_config_option('alert_notification_delay');
}

function thold_notification_execute($pid = 0, $max_records = 'all') {
	if ($max_records == 'all') {
		$sql_limit = '';
	} else {
		$sql_limit = 'LIMIT ' . $max_records;
	}

	if ($pid > 0) {
		$sql_where = ' AND process_id = ' . $pid;
	} else {
		$sql_where = '';
	}

	/* See if and administrator has suspended notifications */
	$prev_suspended = read_config_option('thold_notification_suspended', true);

	/**
	 * See if notification delay is active and mark the events as such,
	 * which will potentially leave less events to process.
	 * This process will also enable notification once the delay is over
	 * for the devices or allow them to be sent.
	 */
	pre_process_device_notifications($pid, $max_records);

	/**
	 * Process any non-device up/down notifications first.  These
	 * notifications are not subject to notification delay
	 */
	process_non_device_notifications($pid, $max_records, $prev_suspended);

	/**
	 * Last process expired notification delays or device
	 * notifications that are not subject to notification delay
	 * not matching any rule type.
	 */
	process_device_notifications($pid, $max_records, $prev_suspended);
}

function process_device_notifications($pid, $max_records, $prev_suspended) {
	if ($max_records == 'all') {
		$sql_limit = '';
	} else {
		$sql_limit = 'LIMIT ' . $max_records;
	}

	if ($pid > 0) {
		$sql_where = ' AND process_id = ' . $pid;
	} else {
		$sql_where = '';
	}

	$records = db_fetch_assoc("SELECT *
		FROM notification_queue
		WHERE event_processed = 0
		AND topic NOT IN ('thold_dhost_mail', 'thold_uhost_mail', 'thold_dhost_cmd', 'thold_uhost_cmd')
		$sql_where
		ORDER BY event_time ASC
		$sql_limit");

	if ($prev_suspended == 0) {
		foreach($records as $r) {
			$nstart = microtime(true);

			/* if notification is suspended, break from this loop */
			$suspended = read_config_option('thold_notification_suspended', true);
			if ($suspended == 1) {
				if ($prev_suspended == 0) {
					debounce_run_notification('notify_suspend', 'WARNING: Notifications have been suspended by an operator.  Returning from processing loop');
				}

				break;
			}

			$topic     = $r['topic'];
			$processed = false;

			switch($topic) {
				case 'thold_dhost_mail':
				case 'thold_uhost_mail':
					$data = json_decode($r['event_data'], true);

					$attributes = array(
						'from', 'to', 'cc', 'bcc', 'replyto', 'subject', 'body', 'body_text', 'attachments', 'headers', 'html'
					);

					foreach($attributes as $a) {
						if (isset($data[$a])) {
							$$a = $data[$a];
						} else {
							$$a = '';
						}
					}

					if (cacti_sizeof($attachments)) {
						foreach($attachments as $index => $attach) {
							$attachments[$index]['attachment'] = base64_decode($attach['attachment']);
						}
					}

					$error = mailer($from, $to, $cc, $bcc, $replyto, $subject, $body, $body_text, $attachments, $headers, $html);

					if (strlen($error)) {
						cacti_log('ERROR: Sending Email To ' . $to . ' Failed.  Error was ' . $error, true, 'THOLD');
						$any_error = $error;
						$error_code = 1;
					} else {
						$error = '';
						$error_code = 0;
					}

					$nend = microtime(true);

					db_execute_prepared('UPDATE notification_queue
						SET error_code = ?, error_message = ?, event_processed = 1, event_processed_time=NOW(), event_processed_runtime = ?
						WHERE id = ?',
						array($error_code, str_replace("\n", ' ', $error), $nend - $nstart, $r['id']));

					break;
				case 'thold_dhost_cmd':
				case 'thold_uhost_cmd':
					$data = json_decode($r['event_data'], true);

					$attributes = array(
						'environment', 'command', 'data'
					);

					foreach($attributes as $a) {
						if (isset($data[$a])) {
							$$a = $data[$a];
						} else {
							$$a = '';
						}
					}

					$output = array();
					$return = 0;

					if (cacti_sizeof($data['environment'])) {
						foreach($data['environment'] as $e) {
							putenv($e);
						}
					}

					exec($command, $output, $return);

					thold_process_command_output($output, $return, $topic, $data);

					$nend = microtime(true);

					db_execute_prepared('UPDATE notification_queue
						SET error_code = ?, error_message = ?, event_processed = 1, event_processed_time=NOW(), event_processed_runtime = ?
						WHERE id = ?',
						array($return, implode("\n", $output), $nend - $nstart, $r['id']));

					break;
				default:
					cacti_log(sprintf('ERROR: Unable to process Thold Notification of topic %s', $topic), false, 'THOLD');
			}
		}
	} else {
		debounce_run_notification('notify_suspend', 'WARNING: Notifications have been suspended by an operator.  Returning from processing loop');
	}
}

function process_non_device_notifications($pid, $max_records, $prev_suspended) {
	if ($max_records == 'all') {
		$sql_limit = '';
	} else {
		$sql_limit = 'LIMIT ' . $max_records;
	}

	if ($pid > 0) {
		$sql_where = ' AND process_id = ' . $pid;
	} else {
		$sql_where = '';
	}

	$records = db_fetch_assoc("SELECT *
		FROM notification_queue
		WHERE event_processed = 0
		AND topic NOT IN ('thold_dhost_mail', 'thold_uhost_mail', 'thold_dhost_cmd', 'thold_uhost_cmd')
		$sql_where
		ORDER BY event_time ASC
		$sql_limit");

	if ($prev_suspended == 0) {
		foreach($records as $r) {
			$nstart = microtime(true);

			/* if notification is suspended, break from this loop */
			$suspended = read_config_option('thold_notification_suspended', true);
			if ($suspended == 1) {
				if ($prev_suspended == 0) {
					debounce_run_notification('notify_suspend', 'WARNING: Notifications have been suspended by an operator.  Returning from processing loop');
				}

				break;
			}

			$topic     = $r['topic'];
			$processed = false;

			switch($topic) {
				case 'thold_mail':
					$data = json_decode($r['event_data'], true);

					$attributes = array(
						'from', 'to', 'cc', 'bcc', 'replyto', 'subject', 'body', 'body_text', 'attachments', 'headers', 'html'
					);

					foreach($attributes as $a) {
						if (isset($data[$a])) {
							$$a = $data[$a];
						} else {
							$$a = '';
						}
					}

					if (cacti_sizeof($attachments)) {
						foreach($attachments as $index => $attach) {
							$attachments[$index]['attachment'] = base64_decode($attach['attachment']);
						}
					}

					$error = mailer($from, $to, $cc, $bcc, $replyto, $subject, $body, $body_text, $attachments, $headers, $html);

					if (strlen($error)) {
						cacti_log('ERROR: Sending Email To ' . $to . ' Failed.  Error was ' . $error, true, 'THOLD');
						$any_error = $error;
						$error_code = 1;
					} else {
						$error = '';
						$error_code = 0;
					}

					$nend = microtime(true);

					db_execute_prepared('UPDATE notification_queue
						SET error_code = ?, error_message = ?, event_processed = 1, event_processed_time=NOW(), event_processed_runtime = ?
						WHERE id = ?',
						array($error_code, str_replace("\n", ' ', $error), $nend - $nstart, $r['id']));

					break;
				case 'thold_cmd':
					$data = json_decode($r['event_data'], true);

					$attributes = array(
						'environment', 'command', 'data'
					);

					foreach($attributes as $a) {
						if (isset($data[$a])) {
							$$a = $data[$a];
						} else {
							$$a = '';
						}
					}

					$output = array();
					$return = 0;

					if (cacti_sizeof($data['environment'])) {
						foreach($data['environment'] as $e) {
							putenv($e);
						}
					}

					exec($command, $output, $return);

					thold_process_command_output($output, $return, $topic, $data);

					$nend = microtime(true);

					db_execute_prepared('UPDATE notification_queue
						SET error_code = ?, error_message = ?, event_processed = 1, event_processed_time=NOW(), event_processed_runtime = ?
						WHERE id = ?',
						array($return, implode("\n", $output), $nend - $nstart, $r['id']));

					break;
				default:
					cacti_log(sprintf('ERROR: Unable to process Thold Notification of topic %s', $topic), false, 'THOLD');
			}
		}
	} else {
		debounce_run_notification('notify_suspend', 'WARNING: Notifications have been suspended by an operator.  Returning from processing loop');
	}
}

function thold_template_update_threshold($id, $template) {
	db_execute_prepared("UPDATE thold_data AS td, thold_template AS tt
		SET
		td.name = tt.suggested_name, td.thold_hi = tt.thold_hi,
		td.data_source_name = tt.data_source_name, td.data_template_hash = tt.data_template_hash,
		td.data_template_id = tt.data_template_id, td.thold_low = tt.thold_low,
		td.thold_fail_trigger = tt.thold_fail_trigger, td.time_hi = tt.time_hi, td.time_low = tt.time_low,
		td.time_fail_trigger = tt.time_fail_trigger, td.time_fail_length = tt.time_fail_length,
		td.thold_warning_hi = tt.thold_warning_hi, td.thold_warning_low = tt.thold_warning_low,
		td.thold_warning_fail_trigger = tt.thold_warning_fail_trigger, td.time_warning_hi = tt.time_warning_hi,
		td.time_warning_low = tt.time_warning_low, td.time_warning_fail_trigger = tt.time_warning_fail_trigger,
		td.time_warning_fail_length = tt.time_warning_fail_length, td.thold_enabled = tt.thold_enabled,
		td.thold_type = tt.thold_type, td.bl_type = tt.bl_type, td.bl_cf = tt.bl_cf, td.bl_ref_time_range = tt.bl_ref_time_range,
		td.bl_pct_up = tt.bl_pct_up, td.bl_pct_down = tt.bl_pct_down, td.bl_pct_up = tt.bl_pct_up,
		td.bl_fail_trigger = tt.bl_fail_trigger, td.bl_alert = tt.bl_alert, td.bl_thold_valid = 0,
		td.repeat_alert = tt.repeat_alert, td.data_type = tt.data_type, td.cdef = tt.cdef,
		td.percent_ds = tt.percent_ds, td.expression = tt.expression, td.upper_ds = tt.upper_ds,
		td.exempt = tt.exempt, td.reset_ack = tt.reset_ack, td.persist_ack = tt.persist_ack,
		td.thold_hrule_alert = tt.thold_hrule_alert, td.thold_hrule_warning = tt.thold_hrule_warning,
		td.skipscale = tt.skipscale, td.restored_alert = tt.restored_alert,
		td.email_subject = tt.email_subject, td.email_subject_warn = tt.email_subject_warn,
		td.email_subject_restoral = tt.email_subject_restoral, td.email_body = tt.email_body,
		td.email_body_warn = tt.email_body_warn, td.email_body_restoral = tt.email_body_restoral,
		td.trigger_cmd_high = tt.trigger_cmd_high, td.trigger_cmd_low = tt.trigger_cmd_low,
		td.trigger_cmd_norm = tt.trigger_cmd_norm, td.syslog_enabled = tt.syslog_enabled,
		td.syslog_priority = tt.syslog_priority, td.syslog_facility = tt.syslog_facility,
		td.snmp_event_category = tt.snmp_event_category, td.snmp_event_description = tt.snmp_event_description,
		td.snmp_event_severity = tt.snmp_event_severity, td.snmp_event_warning_severity = tt.snmp_event_warning_severity,
		td.notes = tt.notes, td.external_id = tt.external_id, td.format_file = tt.format_file,
		td.graph_timespan = tt.graph_timespan
		WHERE td.id = ?
		AND td.template_enabled = 'on'
		AND tt.id = ?",
		array($id, $template));

	db_execute_prepared('DELETE FROM plugin_thold_threshold_contact
		WHERE thold_id = ?',
		array($id));

	db_execute_prepared('INSERT INTO plugin_thold_threshold_contact
		(thold_id, contact_id)
		SELECT ?, contact_id
		FROM plugin_thold_template_contact
		WHERE template_id = ?',
		array($id, $template));

	update_notification_list_from_template($template, $id);

	update_suggested_names_from_template($template, $id);
}

function thold_template_update_thresholds($id) {
	db_execute_prepared("UPDATE thold_data AS td, thold_template AS tt
		SET
		td.name = tt.suggested_name, td.thold_hi = tt.thold_hi, td.data_source_name = tt.data_source_name,
		td.data_template_hash = tt.data_template_hash, td.data_template_id = tt.data_template_id,
		td.thold_low = tt.thold_low, td.thold_fail_trigger = tt.thold_fail_trigger, td.time_hi = tt.time_hi,
		td.time_low = tt.time_low, td.time_fail_trigger = tt.time_fail_trigger,
		td.time_fail_length = tt.time_fail_length, td.thold_warning_hi = tt.thold_warning_hi,
		td.thold_warning_low = tt.thold_warning_low, td.thold_warning_fail_trigger = tt.thold_warning_fail_trigger,
		td.time_warning_hi = tt.time_warning_hi, td.time_warning_low = tt.time_warning_low,
		td.time_warning_fail_trigger = tt.time_warning_fail_trigger, td.time_warning_fail_length = tt.time_warning_fail_length,
		td.thold_enabled = tt.thold_enabled, td.thold_type = tt.thold_type,
		td.bl_type = tt.bl_type, td.bl_cf = tt.bl_cf, td.bl_ref_time_range = tt.bl_ref_time_range,
		td.bl_pct_up = tt.bl_pct_up, td.bl_pct_down = tt.bl_pct_down, td.bl_pct_up = tt.bl_pct_up,
		td.bl_fail_trigger = tt.bl_fail_trigger, td.bl_alert = tt.bl_alert, td.bl_thold_valid = 0,
		td.repeat_alert = tt.repeat_alert, td.data_type = tt.data_type, td.cdef = tt.cdef,
		td.percent_ds = tt.percent_ds, td.expression = tt.expression, td.upper_ds = tt.upper_ds, td.show_units = tt.show_units,
		td.units_suffix = tt.units_suffix, td.decimals = tt.decimals, td.exempt = tt.exempt,
		td.reset_ack = tt.reset_ack, td.persist_ack = tt.persist_ack,
		td.thold_hrule_alert = tt.thold_hrule_alert, td.thold_hrule_warning = tt.thold_hrule_warning,
		td.skipscale = tt.skipscale, td.restored_alert = tt.restored_alert,
		td.email_subject = tt.email_subject, td.email_subject_warn = tt.email_subject_warn,
		td.email_subject_restoral = tt.email_subject_restoral, td.email_body = tt.email_body,
		td.email_body_warn = tt.email_body_warn, td.email_body_restoral = tt.email_body_restoral,
		td.trigger_cmd_high = tt.trigger_cmd_high, td.trigger_cmd_low = tt.trigger_cmd_low, td.trigger_cmd_norm = tt.trigger_cmd_norm,
		td.syslog_enabled = tt.syslog_enabled, td.syslog_priority = tt.syslog_priority,
		td.syslog_facility = tt.syslog_facility,
		td.snmp_event_category = tt.snmp_event_category, td.snmp_event_description = tt.snmp_event_description,
		td.snmp_event_severity = tt.snmp_event_severity, td.snmp_event_warning_severity = tt.snmp_event_warning_severity,
		td.notes = tt.notes, td.external_id = tt.external_id, td.format_file = tt.format_file,
		td.graph_timespan = tt.graph_timespan
		WHERE td.thold_template_id = ?
		AND td.template_enabled = 'on'
		AND tt.id = ?",
		array($id, $id));

	$rows = db_fetch_assoc_prepared("SELECT id, thold_template_id
		FROM thold_data
		WHERE thold_data.thold_template_id = ?
		AND thold_data.template_enabled = 'on'",
		array($id));

	if (cacti_sizeof($rows)) {
		foreach ($rows as $row) {
			db_execute_prepared('DELETE FROM plugin_thold_threshold_contact
				WHERE thold_id = ?',
				array($row['id']));

			db_execute_prepared('INSERT INTO plugin_thold_threshold_contact
				(thold_id, contact_id)
				SELECT ?, contact_id
				FROM plugin_thold_template_contact
				WHERE template_id = ?',
				array($row['id'], $row['thold_template_id']));
		}
	}

	update_notification_list_from_template($id);

	update_suggested_names_from_template($id);
}

function update_notification_list_from_template($id, $thold_id = -1) {
	$templated = db_fetch_cell_prepared('SELECT notify_templated
		FROM thold_template
		WHERE id = ?',
		array($id));

	if ($thold_id > 0) {
		$sql_where = ' AND td.id = ' . $thold_id;
	} else {
		$sql_where = '';
	}

	if ($templated == 'on') {
		db_execute_prepared("UPDATE thold_data AS td, thold_template AS tt
			SET
			td.notify_warning = tt.notify_warning, td.notify_alert = tt.notify_alert,
			td.notify_extra = tt.notify_extra, td.notify_warning_extra = tt.notify_warning_extra
			WHERE td.thold_template_id = ?
			AND td.template_enabled = 'on'
			AND tt.id = ?" . $sql_where,
			array($id,$id));
	}
}

function update_suggested_names_from_template($id, $thold_id = -1) {
	$suggested_name = db_fetch_cell_prepared('SELECT suggested_name
		FROM thold_template
		WHERE id = ?',
		array($id));

	if ($thold_id > 0) {
		$sql_where = ' AND id = ' . $thold_id;
	} else {
		$sql_where = '';
	}

	$tholds = db_fetch_assoc_prepared('SELECT *
		FROM thold_data
		WHERE template_enabled = "on"
		AND thold_template_id = ?' . $sql_where,
		array($id));

	if (cacti_sizeof($tholds)) {
		foreach($tholds as $thold_data) {
			$name_cache = thold_expand_string($thold_data, $suggested_name);

			db_execute_prepared('UPDATE thold_data
				SET name_cache = ?
				WHERE id = ?',
				array($name_cache, $thold_data['id']));
		}
	}
}

function thold_cacti_log($string, $thread = '', $stdout = false) {
	global $config, $debug;
	static $start = null;

	$environ = 'THOLD' . ($thread != '' ? ' THREAD[' . $thread . ']':'');

	/* fill in the current date for printing in the log */
	if (defined('CACTI_DATE_TIME_FORMAT')) {
		$date = date(CACTI_DATE_TIME_FORMAT);
	} else {
		$date = date('Y-m-d H:i:s');
	}

	/* determine how to log data */
	$logdestination = read_config_option('log_destination');
	$logfile        = read_config_option('path_cactilog');

	/* format the message */
	if ($stdout) {
		if ($start == null) {
			$start = microtime(true);
			$total = 0.00;
		} else {
			$total = microtime(true) - $start;
		}

		if (!$debug) {
			printf("Total[%0.4f] - %s: %s" . PHP_EOL, $total, $environ, trim($string));
		} else {
			printf("Total[%0.4f] DEBUG: %s" . PHP_EOL, $total, trim($string));
		}
	}

	$message = "$date - " . $environ . ': ' . trim($string) . "\n";

	/* Log to Logfile */
	if ((($logdestination == 1) || ($logdestination == 2)) && (read_config_option('log_verbosity') != POLLER_VERBOSITY_NONE)) {
		if ($logfile == '') {
			$logfile = $config['base_path'] . '/log/cacti.log';
		}

		/* print the data to the log (append) */
		$fp = @fopen($logfile, 'a');

		if ($fp) {
			@fwrite($fp, $message);
			fclose($fp);
		}
	}

	/* Log to Syslog/Eventlog */
	/* Syslog is currently Unstable in Win32 */
	if (($logdestination == 2) || ($logdestination == 3)) {
		$string   = strip_tags($string);
		$log_type = '';

		if (substr_count($string,'ERROR:')) {
			$log_type = 'err';
		} elseif (substr_count($string,'WARNING:')) {
			$log_type = 'warn';
		} elseif (substr_count($string,'STATS:')) {
			$log_type = 'stat';
		} elseif (substr_count($string,'NOTICE:')) {
			$log_type = 'note';
		}

		if (strlen($log_type)) {
			if ($config['cacti_server_os'] == 'win32') {
				openlog('Cacti', LOG_NDELAY | LOG_PID, LOG_USER);
			} else {
				openlog('Cacti', LOG_NDELAY | LOG_PID, LOG_SYSLOG);
			}

			if (($log_type == 'err') && (read_config_option('log_perror'))) {
				syslog(LOG_CRIT, $environ . ': ' . $string);
			}

			if (($log_type == 'warn') && (read_config_option('log_pwarn'))) {
				syslog(LOG_WARNING, $environ . ': ' . $string);
			}

			if ((($log_type == 'stat') || ($log_type == 'note')) && (read_config_option('log_pstats'))) {
				syslog(LOG_INFO, $environ . ': ' . $string);
			}

			closelog();
		}
	}
}

function thold_threshold_enable($id) {
	if (api_user_realm_auth('thold.php')) {
		db_execute_prepared("UPDATE thold_data
			SET thold_per_enabled = 'on',
			thold_fail_count = 0,
			thold_warning_fail_count = 0,
			bl_fail_count = 0,
			thold_alert = 0,
			bl_alert = 0
			WHERE id = ?",
			array($id));
	}
}

function thold_threshold_disable($id) {
	if (api_user_realm_auth('thold.php')) {
		db_execute_prepared("UPDATE thold_data
			SET thold_per_enabled = '',
			thold_fail_count = 0,
			thold_warning_fail_count = 0,
			bl_fail_count = 0,
			thold_alert = 0,
			bl_alert = 0
			WHERE id = ?",
			array($id));
	}
}

function thold_threshold_ack_prompt($id) {
	global $config;

	top_header();

	form_start($config['url_path'] . 'plugins/thold/thold_graph.php');

	html_start_box(__('Acknowledge Threshold', 'thold'), '60%', '', '3', 'center', '');

	$message = __('Click \'Continue\' to Acknowledge the following Threshold(s).', 'thold');
	$button = __esc('Acknowledge Threshold(s)', 'thold');

	print "<tr>
		<td colspan='2'>
			<p>$message</p>
		</td>
	</tr>";

	print "<tr><td colspan='2'><p><i>Operator Message:</i><br><textarea class='ui-state-default ui-corner-all' style='width:70%;height:50px;' area-multiline='true' rows='2' id='message' name='message'></textarea></p></td></tr>";

	$save_html = "<input type='button' class='ui-button ui-corner-all ui-widget' value='" . __esc('Cancel', 'thold') . "' onClick='cactiReturnTo()'>";

	if (!empty($button)) {
		$save_html .= "&nbsp;<input type='submit' class='ui-button ui-corner-all ui-widget' value='" . __esc('Continue', 'thold') . "' title='$button'>";
	}

	print "<tr>
		<td colspan='2' class='saveRow'>
			<input type='hidden' name='action' value='ack_confirm'>
			<input type='hidden' name='threshold_id' value='$id'>
			$save_html
		</td>
	</tr>";

	html_end_box();

	form_end();

	bottom_footer();
}

function thold_threshold_ack($id) {
	if (api_user_realm_auth('thold.php')) {
		if (is_thold_allowed($id)) {
			plugin_thold_log_changes($id, 'acknowledge_threshold', array('id' => $id));

			db_execute_prepared('UPDATE thold_data
				SET acknowledgment = ""
				WHERE id = ?',
				array($id));

			ack_logging($id, get_request_var('message'));
		}
	}
}

function thold_threshold_suspend_ack($id) {
	if (api_user_realm_auth('thold.php')) {
		if (is_thold_allowed($id)) {
			plugin_thold_log_changes($id, 'suspend_acknowledge_threshold', array('id' => $id));

			db_execute_prepared('UPDATE thold_data
				SET acknowledgment=""
				WHERE id = ?
				AND reset_ack = "on"',
				array($id));
		}
	}
}

function thold_threshold_resume_ack($id) {
	if (api_user_realm_auth('thold.php')) {
		if (is_thold_allowed($id)) {
			plugin_thold_log_changes($id, 'resume_acknowledge_threshold', array('id' => $id));

			db_execute_prepared('UPDATE thold_data
				SET acknowledgment="on"
				WHERE id = ?
				AND thold_alert > 0
				AND reset_ack = "on"',
				array($id));
		}
	}
}

function get_thold_emails($thold, $class = 'alert', $recipient = 'to') {
	$emails = '';

	if ($class == 'alert') {
		if (read_config_option('thold_disable_legacy') != 'on' && $recipient == 'to') {
			$emails = array();

			$rows = db_fetch_assoc_prepared('SELECT ptc.data
				FROM plugin_thold_contacts AS ptc
				INNER JOIN plugin_thold_threshold_contact AS pttc
				ON ptc.id = pttc.contact_id
				WHERE pttc.thold_id = ?',
				array($thold['id']));

			if (count($rows)) {
				foreach ($rows as $row) {
					$emails[] = $row['data'];
				}
			}

			$emails = implode(',', $emails);

			if ($emails != '') {
				$emails .= ',' . $thold['notify_extra'];
			} else {
				$emails = $thold['notify_extra'];
			}
		}

		$emails .= (strlen($emails) ? ',':'') . get_thold_notification_emails($thold['notify_alert'], $recipient);
	} else {
		if (read_config_option('thold_disable_legacy') != 'on' && $recipient == 'to') {
			$emails = $thold['notify_warning_extra'];
		}

		$emails .= (strlen($emails) ? ',':'') . get_thold_notification_emails($thold['notify_warning'], $recipient);
	}

	return $emails;
}

function get_thold_notification_emails($id, $recipient = 'to') {
	if (!thold_notification_list_enabled($id)) {
		return '';
	}

	if (!empty($id)) {
		if ($recipient == 'to') {
			return trim(db_fetch_cell_prepared('SELECT emails
				FROM plugin_notification_lists
				WHERE id = ?',
				array($id)));
		} else {
			return trim(db_fetch_cell_prepared('SELECT bcc_emails
				FROM plugin_notification_lists
				WHERE id = ?',
				array($id)));
		}
	} else {
		return '';
	}
}

function thold_notification_list_enabled($list_id) {
	static $lists = null;

	if ($lists == null) {
		$lists = array_rekey(
			db_fetch_assoc('SELECT id, enabled
				FROM plugin_notification_lists'),
			'id', 'enabled'
		);
	}

	if (isset($lists[$list_id])) {
		return $lists[$list_id] == 'on';
	} else {
		return true;
	}
}

/* get_hash_thold_template - returns the current unique hash for a thold_template
   @param $id - (int) the ID of the thold template to return a hash for
   @returns - a 128-bit, hexadecimal hash */
function get_hash_thold_template($id) {
	$hash = db_fetch_cell_prepared('SELECT hash
		FROM thold_template
		WHERE id = ?',
		array($id));

	if (preg_match('/[a-fA-F0-9]{32}/', $hash)) {
		return $hash;
	} else {
		return generate_hash();
	}
}

function ia2xml($array) {
	$xml = '';

	if (cacti_sizeof($array)) {
		foreach ($array as $key=>$value) {
			if (is_array($value)) {
				$xml .= "\t<$key>" . ia2xml($value) . "</$key>\n";
			} else {
				$xml .= "\t<$key>" . html_escape($value) . "</$key>\n";
			}
		}
	}

	return $xml;
}

function array2xml($array, $tag = 'template') {
	static $index = 1;

	$xml = "<$tag$index>\n" . ia2xml($array) . "</$tag$index>\n";

	$index++;

	return $xml;
}

function thold_snmptrap($varbinds, $severity = SNMPAGENT_EVENT_SEVERITY_MEDIUM, $overwrite = false) {
	if (function_exists('snmpagent_notification')) {
		if (isset($varbinds['eventDescription']) && isset($varbinds['eventDeviceIp'])) {
			$varbinds['eventDescription'] = thold_str_replace('<HOSTIP>', $varbinds['eventDeviceIp'], $varbinds['eventDescription']);
		}

		snmpagent_notification('tholdNotify', 'CACTI-THOLD-MIB', $varbinds, $severity, $overwrite);
	} else {
		cacti_log("ERROR: THOLD was unable to generate SNMP notifications. Cacti SNMPAgent plugin is current missing or inactive.");
	}
}

function thold_prune_old_data() {
	// Remove failed entries from removed devices
	db_execute('DELETE pthf
		FROM plugin_thold_host_failed AS pthf
		LEFT JOIN host AS h
		ON pthf.host_id = h.id
		WHERE h.id IS NULL');

	if (db_affected_rows() > 0) {
		set_config_option('time_last_change_thold_device', time());
	}

	$queue = read_config_option('thold_notification_queue');

	// Remove log entries from removed devices
	db_execute('DELETE ptl
		FROM plugin_thold_log AS ptl
		LEFT JOIN host AS h
		ON ptl.host_id = h.id
		WHERE h.id IS NULL');

	if (db_affected_rows() > 0) {
		set_config_option('time_last_change_thold_log', time());
		set_config_option('time_last_change_thold', time());
	}

	// Remove thresholds from removed devices
	db_execute('DELETE td
		FROM thold_data AS td
		LEFT JOIN host AS h
		ON td.host_id = h.id
		WHERE h.id IS NULL');

	if (db_affected_rows() > 0) {
		set_config_option('time_last_change_thold', time());
	}

	// Remove thresholds from removed graphs
	db_execute('DELETE td
		FROM thold_data AS td
		LEFT JOIN graph_local AS gl
		ON td.local_graph_id = gl.id
		WHERE gl.id IS NULL');

	if (db_affected_rows() > 0) {
		set_config_option('time_last_change_thold', time());
	}

	if ($queue == 'on') {
		db_execute('DELETE FROM notification_queue
			WHERE event_processed = 1
			AND event_processed_time < FROM_UNIXTIME(UNIX_TIMESTAMP()-3600)');

		if (db_affected_rows() > 0) {
			db_execute('OPTIMIZE TABLE notification_queue');
		}
	}
}

function thold_get_allowed_devices($sql_where = '', $order_by = 'description', $sql_limit = '', &$total_rows = 0, $user_id = 0, $device_id = 0) {
	if ($user_id == -1) {
		$auth_method = 0;
	} else {
		$auth_method = read_config_option('auth_method');
	}

	if ($user_id == 0) {
		if (isset($_SESSION['sess_user_id'])) {
			$user_id = $_SESSION['sess_user_id'];
		} else {
			return array();
		}
	}

	$simple_perms = get_simple_device_perms($user_id);

	$init_rows = $total_rows;

	$host_list = array();

	if ($sql_limit != '' && $sql_limit != -1) {
		$sql_limit = "LIMIT $sql_limit";
	} else {
		$sql_limit = '';
	}

	if ($order_by != '') {
		$order_by = "ORDER BY $order_by";
	}

	if (read_user_setting('hide_disabled') == 'on') {
		$sql_where .= ($sql_where != '' ? ' AND':'') . ' (h.disabled = "" OR h.disabled IS NULL)';
	}

	if ($sql_where != '') {
		$sql_where = "WHERE $sql_where";
	}

	if ($device_id > 0) {
		$sql_where .= ($sql_where != '' ? ' AND ' : 'WHERE ') . " h.id = $device_id";
	}

	$graph_auth_method = read_config_option('graph_auth_method');

	$poller_interval = read_config_option('poller_interval');

	/* get policies for all groups and user */
	$policies = get_policies($user_id);

	if (!$simple_perms && $auth_method != 0) {
		$sql_where = get_policy_where($graph_auth_method, $policies, $sql_where);
	}

	$host_list = db_fetch_assoc("SELECT h1.*, graphs, data_sources,
		CAST(IF(availability_method = 0, '0',
			IF(status_event_count > 0 AND status IN (1, 2), status_event_count*$poller_interval,
			IF(UNIX_TIMESTAMP(status_rec_date) < 943916400 AND status IN (0, 3), total_polls*$poller_interval,
			IF(UNIX_TIMESTAMP(status_rec_date) > 943916400, UNIX_TIMESTAMP() - UNIX_TIMESTAMP(status_rec_date),
			IF(snmp_sysUptimeInstance>0 AND snmp_version > 0, snmp_sysUptimeInstance/100, UNIX_TIMESTAMP()
		))))) AS unsigned) AS instate
		FROM host AS h1
		INNER JOIN (
			SELECT DISTINCT id
			FROM (
				SELECT h.id
				FROM host AS h
				LEFT JOIN graph_local AS gl
				ON h.id = gl.host_id
				LEFT JOIN graph_templates AS gt
				ON gt.id=gl.graph_template_id
				LEFT JOIN host_template AS ht
				ON h.host_template_id = ht.id
				$sql_where
			) AS rs1
		) AS rs2
		ON rs2.id = h1.id
		LEFT JOIN (SELECT host_id, COUNT(*) AS graphs FROM graph_local GROUP BY host_id) AS gl
		ON h1.id = gl.host_id
		LEFT JOIN (SELECT host_id, COUNT(*) AS data_sources FROM data_local GROUP BY host_id) AS dl
		ON h1.id = dl.host_id
		$order_by
		$sql_limit"
	);

	$sql = "SELECT COUNT(DISTINCT id)
		FROM (
			SELECT h.id
			FROM host AS h
			LEFT JOIN graph_local AS gl
			ON h.id=gl.host_id
			LEFT JOIN graph_templates AS gt
			ON gt.id=gl.graph_template_id
			LEFT JOIN host_template AS ht
			ON h.host_template_id=ht.id
			$sql_where
		) AS rower";

	if (function_exists('get_total_row_data') && $device_id == 0) {
		$total_rows = get_total_row_data($user_id, $sql, array(), 'thold_device');
	} else {
		$total_rows = db_fetch_cell($sql);
	}

	return $host_list;
}

function thold_get_default_template_name($thold_data) {
	return $thold_data['data_template_name'] . ' [' . $thold_data['data_source_name'] . ']';
}

function thold_get_default_suggested_name($thold_data, $id = 0) {
	if (empty($thold_data) && $id) {
		$thold_data = db_fetch_row_prepared('SELECT suggested_name
			FROM thold_template
			WHERE id = ?',
			array($id));
	}

	$desc = '|data_source_description| [|data_source_name|]';

	if (isset($thold_data['suggested_name']) && !empty($thold_data['suggested_name'])) {
		$desc = $thold_data['suggested_name'];
	}

	return $desc;
}

function thold_get_cached_name(&$thold_data) {
	if (empty($thold_data['name_cache'])) {
		$thold_data['name_cache'] = thold_substitute_data_source_description($thold_data['name'], $thold_data['local_data_id']);
	}

	return $thold_data['name_cache'];
}

function thold_str_replace($search, $replace, $subject) {
	if (empty($replace) || $replace === 0) {
		$replace = '';
	}

	return str_replace($search, $replace, $subject);
}

function thold_template_import($xml_data) {
	global $config;

	$debug_data = array();

	if ($xml_data != '') {
		/* obtain debug information if it's set */
		$xml_array = xml2array($xml_data);

		if (cacti_sizeof($xml_array)) {
			foreach ($xml_array as $template => $contents) {
				$error = false;
				$save  = array();

				if (cacti_sizeof($contents)) {
					foreach ($contents as $name => $value) {
						switch($name) {
							case 'data_template_id':
							case 'data_template_hash':
								// See if the hash exists, if it doesn't, Error Out
								$found = db_fetch_cell_prepared('SELECT id
									FROM data_template
									WHERE hash = ?',
									array($value));

								if (!empty($found)) {
									$save['data_template_id'] = $found;
									$save['data_template_hash'] = $value;
								} else {
									$error = true;
									$debug_data['errors'][] = __('Threshold Template Subordinate Data Template Not Found!', 'thold');
								}

								break;
							case 'data_source_id':
								// See if the hash exists, if it doesn't, Error Out
								$found = db_fetch_cell_prepared('SELECT id
									FROM data_template_rrd
									WHERE hash = ?',
									array($value));

								if (!empty($found)) {
									$save['data_source_id'] = $found;
								} else {
									$error = true;
									$debug_data['errors'][] = __('Threshold Template Subordinate Data Source Not Found!', 'thold');
								}

								break;
							case 'hash':
								// See if the hash exists, if it does, update the thold
								$found = db_fetch_cell_prepared('SELECT id
									FROM thold_template
									WHERE hash = ?',
									array($value));

								if (!empty($found)) {
									$save['hash'] = $value;
									$save['id']   = $found;
								} else {
									$save['hash'] = $value;
									$save['id']   = 0;
								}

								break;
							case 'name':
								$tname = $value;
								$save['name'] = $value;

								break;
							default:
								if (db_column_exists('thold_template', $name)) {
									$save[$name] = $value;
								}

								break;
						}
					}
				}

				if (!validate_template_import_columns($save)) {
					$debug_data['errors'][] = __('Threshold Template import columns do not match the database schema', 'thold');
					$error = true;
				}

				if (!$error) {
					$id = sql_save($save, 'thold_template');

					if ($id) {
						if ($config['is_web']) {
							$debug_data['success'][] = __esc('Threshold Template \'%s\' %s!', $tname, ($save['id'] > 0 ? __('Updated', 'thold'):__('Imported', 'thold')), 'thold');
						} else {
							$debug_data['success'][] = __('Threshold Template \'%s\' %s!', $tname, ($save['id'] > 0 ? __('Updated', 'thold'):__('Imported', 'thold')), 'thold');
						}
					} else {
						if ($config['is_web']) {
							$debug_data['failure'][] = __esc('Threshold Template \'%s\' %s Failed!', $tname, ($save['id'] > 0 ? __('Update', 'thold'):__('Import', 'thold')), 'thold');
						} else {
							$debug_data['failure'][] = __('Threshold Template \'%s\' %s Failed!', $tname, ($save['id'] > 0 ? __('Update', 'thold'):__('Import', 'thold')), 'thold');
						}
					}
				} else {
					$debug_data['failure'][] = __('Errors encountered while attempting to import Threshold Template data.', 'thold');
				}
			}
		} else {
			$debug_data['failure'][] = __('Threshold Template Import data was not found to be XML data.', 'thold');
		}
	} else {
		$debug_data['failure'][] = __('Threshold Template Import data was not correct while importing Threshold Template.', 'thold');
	}

	return $debug_data;
}

function validate_template_import_columns($template) {
	if (cacti_sizeof($template)) {
		foreach($template as $column => $data) {
			if (!db_column_exists('thold_template', $column)) {
				cacti_log('Template column \'' . $column . '\' is not valid for a threshold template.', false, 'THOLD');

				return false;
			}
		}
	} else {
		return false;
	}

	return true;
}

function thold_error_handler($errno, $errmsg, $filename, $linenum, $vars = []) {
	if (read_config_option('log_verbosity') >= POLLER_VERBOSITY_DEBUG) {
		/* define all error types */
		$errortype = array(
			E_ERROR             => 'Error',
			E_WARNING           => 'Warning',
			E_PARSE             => 'Parsing Error',
			E_NOTICE            => 'Notice',
			E_CORE_ERROR        => 'Core Error',
			E_CORE_WARNING      => 'Core Warning',
			E_COMPILE_ERROR     => 'Compile Error',
			E_COMPILE_WARNING   => 'Compile Warning',
			E_USER_ERROR        => 'User Error',
			E_USER_WARNING      => 'User Warning',
			E_USER_NOTICE       => 'User Notice',
			E_STRICT            => 'Runtime Notice'
		);

		if (defined('E_RECOVERABLE_ERROR')) {
			$errortype[E_RECOVERABLE_ERROR] = 'Catchable Fatal Error';
		}

		if (defined('E_DEPRECATED')) {
			$errortype[E_DEPRECATED] = 'Deprecated Warning';
		}

		/* create an error string for the log */
		$err = "ERRNO:'"  . $errno   . "' TYPE:'"    . $errortype[$errno] .
			"' MESSAGE:'" . $errmsg  . "' IN FILE:'" . $filename .
			"' LINE NO:'" . $linenum . "'";

		/* let's ignore some lesser issues */
		if (substr_count($errmsg, 'date_default_timezone')) return;
		if (substr_count($errmsg, 'Only variables')) return;

		/* log the error to the Cacti log */
		cacti_log('PROGERR: ' . $err, false, 'THOLD');
	}

	return;
}

/**
 * thold_get_rrd_statistics - given the local_data_id, the data source name, the interval and
 *   consolidation function.  Return some statistics to the caller.
 *
 * @param $local_data_id  - (int) The local_data_id of the data source
 * @param $current_time - (int) The end time for the RRDtool call
 * @param $prev_time - (int) The start time for the RRDtool call
 * @param $avg_of_cf - (string) Get the Average of the chosen CF
 *
 * @return - (boolean|array) The value requested by the caller
 */
function thold_get_rrd_statistics($local_data_id, $current_time, $prev_time, $avg_of_cf) {
	global $config;

	$use_proxy = (read_config_option('storage_location') ? true : false);

	$rrdfile = db_fetch_cell_prepared('SELECT rrd_path
		FROM poller_item
		WHERE local_data_id = ?',
		array($local_data_id));

	if ($rrdfile == '') {
		return false;
	}

	if ($use_proxy) {
		$file_exists = rrdtool_execute("file_exists $rrdfile", true, RRDTOOL_OUTPUT_BOOLEAN, false, 'THOLD');
	} else {
		clearstatcache();
		$file_exists = file_exists($rrdfile);
	}

	/* don't attempt to get information if the file does not exist */
	if ($file_exists) {
		$info = rrdtool_execute("info $rrdfile", false, RRDTOOL_OUTPUT_STDOUT, false, 'THOLD');

		/* don't do anything if RRDfile did not return data */
		if ($info != '') {
			$info_array = explode("\n", $info);

			$average = false;
			$max     = false;
			$min     = false;
			$last    = false;
			$dsnames = array();

			/* figure out what is in this RRDfile.  Assume CF Uniformity as Cacti does not allow async rrdfiles.
			 * also verify the consolidation functions in the RRDfile for average and max calculations.
			 */
			if (cacti_sizeof($info_array)) {
				foreach ($info_array as $line) {
					if (substr_count($line, 'ds[')) {
						$parts  = explode(']', $line);
						$parts2 = explode('[', $parts[0]);

						$dsnames[trim($parts2[1])] = 1;
					} elseif (substr_count($line, '.cf')) {
						$parts = explode('=', $line);

						if (substr_count($parts[1], 'AVERAGE')) {
							$average = true;
						} elseif (substr_count($parts[1], 'MAX')) {
							$max = true;
						} elseif (substr_count($parts[1], 'MIN')) {
							$min = true;
						} elseif (substr_count($parts[1], 'LAST')) {
							$last = true;
						}
					} elseif (substr_count($line, 'step')) {
						$parts = explode('=', $line);

						$poller_interval = trim($parts[1]);
					}
				}
			}

			/* create the command syntax to get data */
			/* assume that an RRDfile has not more than 62 data sources */
			$defs     = 'abcdefghijklmnopqrstuvwxyz012345789ABCDEFGHIJKLMNOPQRSTUVWXYZ';
			$i        = 0;
			$j        = 0;
			$def      = '';
			$command  = '';
			$dsvalues = array();

			/* escape the file name if on Windows */
			if ($config['cacti_server_os'] != 'unix') {
				$rrdfile = str_replace(':', "\\:", $rrdfile);
			}

			/* setup the graph command by parsing through the internal data source names */
			if (cacti_sizeof($dsnames)) {
				foreach ($dsnames as $dsname => $present) {
					if ($average) {
						$def .= 'DEF:' . $defs[$j] . $defs[$i] . "=\"" . $rrdfile . "\":" . $dsname . ':AVERAGE ';
						$command .= ' VDEF:' . $defs[$j] . $defs[$i] . '_out=' . $defs[$j] . $defs[$i] . ',AVERAGE PRINT:' . $defs[$j] . $defs[$i] . '_out:%lf';
						$i++;
					}

					if ($max) {
						$def .= 'DEF:' . $defs[$j] . $defs[$i] . "=\"" . $rrdfile . "\":" . $dsname . ':MAX ';
						$command .= ' VDEF:' . $defs[$j] . $defs[$i] . '_out=' . $defs[$j] . $defs[$i] . ',MAXIMUM PRINT:' . $defs[$j] . $defs[$i] . '_out:%lf';
						$i++;
					}

					if ($min) {
						$def .= 'DEF:' . $defs[$j] . $defs[$i] . "=\"" . $rrdfile . "\":" . $dsname . ':MIN ';
						$command .= ' VDEF:' . $defs[$j] . $defs[$i] . '_out=' . $defs[$j] . $defs[$i] . ',MINIMUM PRINT:' . $defs[$j] . $defs[$i] . '_out:%lf';
						$i++;
					}

					if ($last) {
						$def .= 'DEF:' . $defs[$j] . $defs[$i] . "=\"" . $rrdfile . "\":" . $dsname . ':LAST ';
						$command .= ' VDEF:' . $defs[$j] . $defs[$i] . '_out=' . $defs[$j] . $defs[$i] . ',LAST PRINT:' . $defs[$j] . $defs[$i] . '_out:%lf';
						$i++;
					}

					if ($i > 50) {
						$j++;
						$i = 0;
					}
				}
			}

			if ($avg_of_cf != '') {
				switch($avg_of_cf) {
					case 'MIN':
						if ($min) {
							$def .= 'DEF:' . $defs[$j] . $defs[$i] . "=\"" . $rrdfile . "\":" . $dsname . ':MIN ';
							$command .= ' VDEF:' . $defs[$j] . $defs[$i] . '_cfa=' . $defs[$j] . $defs[$i] . ',AVERAGE PRINT:' . $defs[$j] . $defs[$i] . '_cfa:%lf';
							$i++;
						}

						break;
					case 'MAX':
						if ($max) {
							$def .= 'DEF:' . $defs[$j] . $defs[$i] . "=\"" . $rrdfile . "\":" . $dsname . ':MAX ';
							$command .= ' VDEF:' . $defs[$j] . $defs[$i] . '_cfa=' . $defs[$j] . $defs[$i] . ',AVERAGE PRINT:' . $defs[$j] . $defs[$i] . '_cfa:%lf';
							$i++;
						}

						break;
					case 'AVG':
						if ($avg) {
							$def .= 'DEF:' . $defs[$j] . $defs[$i] . "=\"" . $rrdfile . "\":" . $dsname . ':AVERAGE ';
							$command .= ' VDEF:' . $defs[$j] . $defs[$i] . '_cfa=' . $defs[$j] . $defs[$i] . ',AVERAGE PRINT:' . $defs[$j] . $defs[$i] . '_cfa:%lf';
							$i++;
						}

						break;
					case 'LAST':
						if ($last) {
							$def .= 'DEF:' . $defs[$j] . $defs[$i] . "=\"" . $rrdfile . "\":" . $dsname . ':LAST ';
							$command .= ' VDEF:' . $defs[$j] . $defs[$i] . '_cfa=' . $defs[$j] . $defs[$i] . ',AVERAGE PRINT:' . $defs[$j] . $defs[$i] . '_cfa:%lf';
							$i++;
						}

						break;
				}
			}

			/* now execute the graph command */
			$stats_cmd = 'graph x --start ' . $prev_time . ' --end ' . $current_time . ' ' . trim($def) . ' ' . trim($command);

			/* uncomment for diagnostics */
			//cacti_log($stats_cmd, false, 'THOLD');

			$xport_data = rrdtool_execute($stats_cmd, false, RRDTOOL_OUTPUT_STDOUT, false, 'THOLD');

			$position   = array();
			$position[] = array('RETURN' => 'RETURN');

			/* initialize the array of return values */
			foreach($dsnames as $dsname => $present) {
				$dsvalues[$dsname]['AVG']  = 0;
				$dsvalues[$dsname]['MAX']  = 0;
				$dsvalues[$dsname]['MIN']  = 0;
				$dsvalues[$dsname]['LAST'] = 0;

				if ($avg_of_cf != '') {
					$dsvalues[$dsname]['CFA'] = 0;
				}

				if ($average) {
					$position[] = array($dsname => 'AVG');
				}

				if ($max) {
					$position[] = array($dsname => 'MAX');
				}

				if ($min) {
					$position[] = array($dsname => 'MIN');
				}

				if ($last) {
					$position[] = array($dsname => 'LAST');
				}

				if ($avg_of_cf != '') {
					$position[] = array($dsname => 'CFA');
				}
			}

			/* process the xport array and return average and peak values */
			if ($xport_data != '') {
				$xport_array = explode("\n", $xport_data);
				//print_r($xport_array);

				if (cacti_sizeof($xport_array)) {
					foreach($xport_array as $index => $line) {
						if ($line == '') {
							continue;
						}

						if ($index > 0) {
							// Catch the last line
							if (substr($line, 0, 2) == 'OK') {
								$line  = trim($line, ' OK');
								$parts = explode(' ', $line);
								//print $line . PHP_EOL;

								foreach($parts as $line) {
									$sparts = explode(':', $line);

									switch($sparts[0]) {
										case 'u':
											$user_time = $sparts[1];
											break;
										case 's':
											$system_time = $sparts[1];
											break;
										case 'r':
											$real_time = $sparts[1];
											break;
									}
								}

								break;
							} else {
								foreach($position[$index] as $dsname => $stat) {
									$dsvalues[$dsname][$stat] = trim($line);
								}
							}
						}
					}

					return $dsvalues;
				}
			}
		}
	}

	return false;
}
