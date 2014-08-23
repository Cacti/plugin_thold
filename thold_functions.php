<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2014 The Cacti Group                                      |
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

function thold_debug($txt) {
	global $debug;

	if (read_config_option('thold_log_debug') == 'on' || $debug) {
		thold_cacti_log($txt);
	}
}

function thold_initialize_rusage() {
	global $thold_start_rusage;

	if (function_exists("getrusage")) {
		$thold_start_rusage = getrusage();
	}

	$thold_start_rusage["microtime"] = microtime();
}

function thold_display_rusage() {
	global $colors, $thold_start_rusage;

	if (function_exists("getrusage")) {
		$dat = getrusage();

		html_start_box("", "100%", $colors["header"], "3", "left", "");
		print "<tr>";

		if (!isset($thold_start_rusage)) {
			print "<td colspan='10'>ERROR: Can not display RUSAGE please call thold_initialize_rusage first</td>";
		} else {
			$i_u_time = $thold_start_rusage["ru_utime.tv_sec"] + ($thold_start_rusage["ru_utime.tv_usec"] * 1E-6);
			$i_s_time = $thold_start_rusage["ru_stime.tv_sec"] + ($thold_start_rusage["ru_stime.tv_usec"] * 1E-6);
			$s_s      = $thold_start_rusage["ru_nswap"];
			$s_pf     = $thold_start_rusage["ru_majflt"];

			list($micro,$seconds) = split(" ", $thold_start_rusage["microtime"]);
			$start_time = $seconds + $micro;
			list($micro,$seconds) = split(" ", microtime());
			$end_time   = $seconds + $micro;

			$utime    = ($dat["ru_utime.tv_sec"] + ($dat["ru_utime.tv_usec"] * 1E-6)) - $i_u_time;
			$stime    = ($dat["ru_stime.tv_sec"] + ($dat["ru_stime.tv_usec"] * 1E-6)) - $i_s_time;
			$swaps    = $dat["ru_nswap"] - $s_s;
			$pages    = $dat["ru_majflt"] - $s_pf;

			print "<td colspan='10' width='1%' style='text-align:left;'>";
			print "<b>Time:</b>&nbsp;" . round($end_time - $start_time,2) . " seconds, ";
			print "<b>User:</b>&nbsp;" . round($utime,2) . " seconds, ";
			print "<b>System:</b>&nbsp;" . round($stime,2) . " seconds, ";
			print "<b>Swaps:</b>&nbsp;" . ($swaps) . " swaps, ";
			print "<b>Pages:</b>&nbsp;" . ($pages) . " pages";
			print "</td>";
		}

		print "</tr>";
		html_end_box(false);
	}

}

function thold_legend() {
	global $colors, $thold_bgcolors;

	html_start_box("", "100%", $colors["header"], "3", "center", "");
	print "<tr>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_bgcolors['red'] . ";'><b>Alarm</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_bgcolors['orange'] . ";'><b>Baseline Alarm</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_bgcolors['warning'] . ";'><b>Warning</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_bgcolors['yellow'] . ";'><b>Notice</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_bgcolors['green'] . ";'><b>Ok</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_bgcolors['grey'] . ";'><b>Disabled</b></td>";
	print "</tr>";
	html_end_box(false);
}

function host_legend() {
	global $colors, $host_colors, $disabled_color, $notmon_color;

	html_start_box("", "100%", $colors["header"], "3", "center", "");
	print "<tr>";
	print "<td width='10%' style='text-align:center;background-color:#" . $host_colors[HOST_DOWN] . ";'><b>Down</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $host_colors[HOST_UP] . ";'><b>Up</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $host_colors[HOST_RECOVERING] . ";'><b>Recovering</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $host_colors[HOST_UNKNOWN] . ";'><b>Unknown</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $notmon_color . ";'><b>Not Monitored</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $disabled_color . ";'><b>Disabled</b></td>";
	print "</tr>";
	html_end_box(false);
}

function log_legend() {
	global $colors;

	$thold_log = array(
		'alarm'     => 'F21924',
		'warning'   => 'FB4A14',
		'retrigger' => 'FF7A30',
		'trigger'   => 'FAFD9E',
		'restoral'  => 'CCFFCC',
		'restore'   => 'CDCFC4');

	$thold_status = array(
		'0' => 'restore',
		'1' => 'trigger',
		'2' => 'retrigger',
		'3' => 'warning',
		'4' => 'alarm',
		'5' => 'restoral');

	html_start_box("", "100%", $colors["header"], "3", "center", "");
	print "<tr>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_log['alarm'] . ";'><b>Alarm Notify</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_log['warning'] . ";'><b>Warning Notify</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_log['retrigger'] . ";'><b>Retrigger Notify</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_log['trigger'] . ";'><b>Trigger Event</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_log['restoral'] . ";'><b>Restoral Notify</b></td>";
	print "<td width='10%' style='text-align:center;background-color:#" . $thold_log['restore'] . ";'><b>Restoral Event</b></td>";
	print "</tr>";
	html_end_box(false);
}

// Update automatically 'alert_base_url' if not set and if we are called from the browser
// so that check-thold can pick it up
if (isset($_SERVER['HTTP_HOST']) && isset($_SERVER['PHP_SELF']) && read_config_option('alert_base_url') == '') {
	$dir = dirname($_SERVER['PHP_SELF']);
	if (strpos($dir, '/plugins/') !== false)
		$dir = substr($dir, 0, strpos($dir, '/plugins/'));
	db_execute("replace into settings (name,value) values ('alert_base_url', '" . ("http://" . $_SERVER['HTTP_HOST'] . $dir . "/") . "')");

	/* reset local settings cache so the user sees the new settings */
	kill_session_var('sess_config_array');
}

function thold_expression_rpn_pop(&$stack) {
	global $rpn_error;

	if (sizeof($stack)) {
		return array_pop($stack);
	} else {
		$rpn_error = true;
		return false;
	}
}

function thold_expression_math_rpn($operator, &$stack) {
	global $rpn_error;

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

		if (!$rpn_error) {
			@eval("\$v3 = " . $v2 . ' ' . $operator . ' ' . $v1 . ';');
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
			eval("\$v2 = " . $operator . "(" . $v1 . ");");
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
	}elseif ($operator == 'ISINF') {
		$v1 = thold_expression_rpn_pop($stack);
		if ($v1 == 'INF' || $v1 == 'NEGINF') {
			array_push($stack, '1');
		} else {
			array_push($stack, '0');
		}
	}elseif ($operator == 'AND') {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		if ($v1 > 0 && $v2 > 0) {
			array_push($stack, '1');
		} else {
			array_push($stack, '0');
		}
	}elseif ($operator == 'OR') {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		if ($v1 > 0 || $v2 > 0) {
			array_push($stack, '1');
		} else {
			array_push($stack, '0');
		}
	}elseif ($operator == 'IF') {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);
		$v3 = thold_expression_rpn_pop($stack);

		if ($v3 == 0) {
			array_push($stack, $v1);
		} else {
			array_push($stack, $v2);
		}
	} else {
		$v1 = thold_expression_rpn_pop($stack);
		$v2 = thold_expression_rpn_pop($stack);

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
		}elseif (in_array('NEGINF', $v)) {
			array_push($stack, 'NEGINF');
		}elseif (in_array('U', $v)) {
			array_push($stack, 'U');
		}elseif (in_array('NAN', $v)) {
			array_push($stack, 'NAN');
		}elseif ($operator == 'MAX') {
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
		}elseif (($v1 == 'INF' || $v1 == 'NEGINF') ||
			($v2 == 'INF' || $v2 == 'NEGINF') ||
			($v3 == 'INF' || $v3 == 'NEGINF')) {
			array_push($stack, 'U');
		}elseif ($v1 < $v2) {
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
	}elseif ($operator == 'INF') {
		array_push($stack, 'INF');
	}elseif ($operator == 'NEGINF') {
		array_push($stack, 'NEGINF');
	}elseif ($operator == 'COUNT') {
		array_push($stack, $count);
	}elseif ($operator == 'PREV') {
		/* still have to figure this out */
	}
}

function thold_expression_stackops_rpn($operator, &$stack) {
	global $rpn_error;

	if ($operator == 'DUP') {
		$v1 = thold_expression_rpn_pop($stack);
		array_push($stack, $v1);
		array_push($stack, $v1);
	}elseif ($operator == 'POP') {
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
	}elseif ($operator == 'TIME') {
		/* still need to figure this one out */
	}elseif ($operator == 'LTIME') {
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

			foreach($v as $val) {
				array_push($stack, $val);
			}
		}
	}elseif ($operator == 'REV') {
		$count = thold_expression_rpn_pop($stack);
		$v     = array();
		if ($count > 0) {
			for($i = 0; $i < $count; $i++) {
				$v[] = thold_expression_rpn_pop($stack);
			}

			$v = array_reverse($v);

			foreach($v as $val) {
				array_push($stack, $val);
			}
		}
	}elseif ($operator == 'AVG') {
		$count = thold_expression_rpn_pop($stack);
		if ($count > 0) {
			$total  = 0;
			$inf    = false;
			$neginf = false;
			for($i = 0; $i < $count; $i++) {
				$v = thold_expression_rpn_pop($stack);
				if ($v == 'INF') {
					$inf = true;
				}elseif ($v == 'NEGINF') {
					$neginf = true;
				} else {
					$total += $v;
				}
			}

			if ($inf) {
				array_push($stack, 'INF');
			}elseif ($neginf) {
				array_push($stack, 'NEGINF');
			} else {
				array_push($stack, $total/$count);
			}
		}
	}
}

function thold_expression_ds_value($operator, &$stack, $data_sources) {
	global $rpn_error;

	if (sizeof($data_sources)) {
	foreach($data_sources as $rrd_name => $value) {
		if (strtoupper($rrd_name) == $operator) {
			array_push($stack, $value);
			return;
		}
	}
	}

	array_push($stack, 0);
}

function thold_expression_specialtype_rpn($operator, &$stack, $rra_id, $currentval) {
	switch ($operator) {
	case 'CURRENT_DATA_SOURCE':
		array_push($stack, $currentval);
		break;
	case 'CURRENT_GRAPH_MAXIMUM_VALUE':
		array_push(get_current_value($rra_id, 'upper_limit', 0));
		break;
	case 'CURRENT_GRAPH_MINIMUM_VALUE':
		array_push(get_current_value($rra_id, 'lower_limit', 0));
		break;
	case 'CURRENT_DS_MINIMUM_VALUE':
		array_push(get_current_value($rra_id, 'rrd_minimum', 0));
		break;
	case 'CURRENT_DS_MAXIMUM_VALUE':
		array_push($stack, get_current_value($rra_id, 'rrd_maximum', 0));
		break;
	case 'VALUE_OF_HDD_TOTAL':
		array_push($stack, get_current_value($rra_id, 'hdd_total', 0));
		break;
	case 'ALL_DATA_SOURCES_NODUPS':
	case 'ALL_DATA_SOURCES_DUPS':
		$v1 = 0;
		$all_dsns = array();
		$all_dsns = db_fetch_assoc("SELECT data_source_name FROM data_template_rrd WHERE local_data_id = " . $rra_id);
		if (is_array($all_dsns)) {
			foreach ($all_dsns as $dsn) {
				$v1 += get_current_value($rra_id, $dsn['data_source_name'], 0);
			}
		}

		array_push($stack, $v1);
		break;
	default:
		cacti_log('WARNING: CDEF property not implemented yet: ' . $operator, false, 'THOLD');
		array_push($stack, $currentval);
		break;
	}
}

function thold_get_currentval(&$t_item, &$rrd_reindexed, &$rrd_time_reindexed, &$item, &$currenttime) {
	/* adjust the polling interval by the last read, if applicable */
	$currenttime = $rrd_time_reindexed[$t_item['rra_id']];
	if ($t_item['lasttime'] > 0) {
		$polling_interval = $currenttime - $t_item['lasttime'];
	} else {
		$polling_interval = $t_item['rrd_step'];
	}

	$currentval = 0;

	if (isset($rrd_reindexed[$t_item['rra_id']])) {
		$item = $rrd_reindexed[$t_item['rra_id']];
		if (isset($item[$t_item['name']])) {
			switch ($t_item['data_source_type_id']) {
			case 2:	// COUNTER
				if ($t_item['oldvalue'] != 0) {
					if ($item[$t_item['name']] >= $t_item['oldvalue']) {
						// Everything is normal
						$currentval = $item[$t_item['name']] - $t_item['oldvalue'];
					} else {
						// Possible overflow, see if its 32bit or 64bit
						if ($t_item['oldvalue'] > 4294967295) {
							$currentval = (18446744073709551615 - $t_item['oldvalue']) + $item[$t_item['name']];
						} else {
							$currentval = (4294967295 - $t_item['oldvalue']) + $item[$t_item['name']];
						}
					}

					$currentval = $currentval / $polling_interval;

					/* assume counter reset if greater than max value */
					if ($t_item['rrd_maximum'] > 0 && $currentval > $t_item['rrd_maximum']) {
						$currentval = $item[$t_item['name']] / $polling_interval;
					}elseif ($t_item['rrd_maximum'] == 0 && $currentval > 4.25E+9) {
						$currentval = $item[$t_item['name']] / $polling_interval;
					}
				} else {
					$currentval = 0;
				}
				break;
			case 3:	// DERIVE
				$currentval = ($item[$t_item['name']] - $t_item['oldvalue']) / $polling_interval;
				break;
			case 4:	// ABSOLUTE
				$currentval = $item[$t_item['name']] / $polling_interval;
				break;
			case 1:	// GAUGE
			default:
				$currentval = $item[$t_item['name']];
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
	$data_sources = $rrd_reindexed[$thold['rra_id']];
	if (sizeof($data_sources)) {
		foreach($data_sources as $key => $value) {
			$key = strtolower($key);
			$nds[$key] = $value;
		}
		$data_sources = $nds;
	}

	/* replace all data tabs in the rpn with values */
	if (sizeof($expression)) {
	foreach($expression as $key => $item) {
		if (substr_count($item, "|ds:")) {
			$dsname = strtolower(trim(str_replace("|ds:", "", $item), " |\n\r"));

			$thold_item = db_fetch_row("SELECT thold_data.id, thold_data.graph_id,
				thold_data.percent_ds, thold_data.expression,
				thold_data.data_type, thold_data.cdef, thold_data.rra_id,
				thold_data.data_id, thold_data.lastread,
				UNIX_TIMESTAMP(thold_data.lasttime) AS lasttime, thold_data.oldvalue,
				data_template_rrd.data_source_name as name,
				data_template_rrd.data_source_type_id, data_template_data.rrd_step,
				data_template_rrd.rrd_maximum
				FROM thold_data
				LEFT JOIN data_template_rrd
				ON (data_template_rrd.id = thold_data.data_id)
				LEFT JOIN data_template_data
				ON (data_template_data.local_data_id=thold_data.rra_id)
				WHERE data_template_rrd.data_source_name='$dsname'
				AND thold_data.rra_id=" . $thold['rra_id'], false);

			if (sizeof($thold_item)) {
				$item = array();
				$currenttime = 0;
				$expression[$key] = thold_get_currentval($thold_item, $rrd_reindexed, $rrd_time_reindexed, $item, $currenttime);
			} else {
				$value = '';
				if (api_plugin_is_enabled('dsstats') && read_config_option("dsstats_enable") == "on") {
					$value = db_fetch_cell("SELECT calculated
						FROM data_source_stats_hourly_last
						WHERE local_data_id=" . $thold['rrd_id'] . "
						AND rrd_name='$dsname'");
				}

				if (empty($value) || $value == '-90909090909') {
					$expression[$key] = get_current_value($thold['rra_id'], $dsname);
				} else {
					$expression[$key] = $value;
				}
				cacti_log($expression[$key]);
			}

			if ($expression[$key] == '') $expression[$key] = '0';
		}elseif (substr_count($item, "|")) {
			$gl = db_fetch_row("SELECT * FROM graph_local WHERE id=" . $thold["graph_id"]);

			if (sizeof($gl)) {
				$expression[$key] = thold_expand_title($thold, $gl["host_id"], $gl["snmp_query_id"], $gl["snmp_index"], $item);
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

	//cacti_log(implode(",", array_keys($data_sources)));
	//cacti_log(implode(",", $data_sources));
	//cacti_log(implode(",", $expression));

	/* now let's process the RPN stack */
	$x = count($expression);

	if ($x == 0) return $currentval;

	/* operation stack for RPN */
	$stack = array();

	/* current pointer in the RPN operations list */
	$cursor = 0;

	while($cursor < $x) {
		$operator = strtoupper(trim($expression[$cursor]));

		/* is the operator a data source */
		if (is_numeric($operator)) {
			//cacti_log("NOTE: Numeric '$operator'", false, "THOLD");
			array_push($stack, $operator);
		}elseif (array_key_exists($operator, $data_sources)) {
			//cacti_log("NOTE: DS Value '$operator'", false, "THOLD");
			thold_expression_ds_value($operator, $stack, $data_sources);
		}elseif (in_array($operator, $comparison)) {
			//cacti_log("NOTE: Compare '$operator'", false, "THOLD");
			thold_expression_compare_rpn($operator, $stack);
		}elseif (in_array($operator, $boolean)) {
			//cacti_log("NOTE: Boolean '$operator'", false, "THOLD");
			thold_expression_boolean_rpn($operator, $stack);
		}elseif (in_array($operator, $math)) {
			//cacti_log("NOTE: Math '$operator'", false, "THOLD");
			thold_expression_math_rpn($operator, $stack);
		}elseif (in_array($operator, $setops)) {
			//cacti_log("NOTE: SetOps '$operator'", false, "THOLD");
			thold_expression_setops_rpn($operator, $stack);
		}elseif (in_array($operator, $specvals)) {
			//cacti_log("NOTE: SpecVals '$operator'", false, "THOLD");
			thold_expression_specvals_rpn($operator, $stack, $cursor + 2);
		}elseif (in_array($operator, $stackops)) {
			//cacti_log("NOTE: StackOps '$operator'", false, "THOLD");
			thold_expression_stackops_rpn($operator, $stack);
		}elseif (in_array($operator, $time)) {
			//cacti_log("NOTE: Time '$operator'", false, "THOLD");
			thold_expression_time_rpn($operator, $stack);
		}elseif (in_array($operator, $spectypes)) {
			//cacti_log("NOTE: SpecialTypes '$operator'", false, "THOLD");
			thold_expression_specialtype_rpn($operator, $stack, $thold['rra_id'], $currentval);
		} else {
			cacti_log("WARNING: Unsupported Field '$operator'", false, "THOLD");
			$rpn_error = true;
		}

		$cursor++;

		if ($rpn_error) {
			cacti_log("ERROR: RPN Expression is invalid! THold:'" . $thold['thold_name'] . "', Value:'" . $currentval . "', Expression:'" . $thold['expression'] . "'", false, 'THOLD');
			return 0;
		}
	}

	return $stack[0];
}

function thold_expand_title($thold, $host_id, $snmp_query_id, $snmp_index, $string) {
	if (strstr($string, "|query_") && !empty($host_id)) {
		$value = thold_substitute_snmp_query_data($string, $host_id, $snmp_query_id, $snmp_index, read_config_option("max_data_query_field_length"));

		if ($value == '|query_ifHighSpeed|') {
			$value = thold_substitute_snmp_query_data('|query_ifSpeed|', $host_id, $snmp_query_id, $snmp_index, read_config_option("max_data_query_field_length")) / 1000000;
		}

		if (strstr($value, "|")) {
			cacti_log("WARNING: Expression Replacment for '$string' in THold '" . $thold["thold_name"] . "' Failed, A Reindex may be required!");
			return '0';
		}

		return $value;
	}elseif ((strstr($string, "|host_")) && (!empty($host_id))) {
		return thold_substitute_host_data($string, "|", "|", $host_id);
	}else{
		return $string;
	}
}

function thold_substitute_snmp_query_data($string, $host_id, $snmp_query_id, $snmp_index, $max_chars = 0) {
	$field_name = trim(str_replace("|query_", "", $string),"| \n\r");
	$snmp_cache_data = db_fetch_cell("SELECT field_value
		FROM host_snmp_cache
		WHERE host_id=$host_id
		AND snmp_query_id=$snmp_query_id
		AND snmp_index='$snmp_index'
		AND field_name='$field_name'");

	if ($snmp_cache_data != '') {
		return $snmp_cache_data;
	}else{
		return $string;
	}
}

function thold_substitute_host_data($string, $l_escape_string, $r_escape_string, $host_id) {
	$field_name = trim(str_replace("|host_", "", $string),"| \n\r");
	if (!isset($_SESSION["sess_host_cache_array"][$host_id])) {
		$host = db_fetch_row("select * from host where id=$host_id");
		$_SESSION["sess_host_cache_array"][$host_id] = $host;
	}

	if (isset($_SESSION["sess_host_cache_array"][$host_id][$field_name])) {
		return $_SESSION["sess_host_cache_array"][$host_id][$field_name];
	}

	$string = str_replace($l_escape_string . "host_management_ip" . $r_escape_string, $_SESSION["sess_host_cache_array"][$host_id]["hostname"], $string);
	$temp = api_plugin_hook_function('substitute_host_data', array('string' => $string, 'l_escape_string' => $l_escape_string, 'r_escape_string' => $r_escape_string, 'host_id' => $host_id));
	$string = $temp['string'];

	return $string;
}

function thold_calculate_percent($thold, $currentval, $rrd_reindexed) {
	$ds = $thold['percent_ds'];
	if (isset($rrd_reindexed[$thold['rra_id']][$ds])) {
		$t = $rrd_reindexed[$thold['rra_id']][$thold['percent_ds']];
		if ($t != 0) {
			$currentval = ($currentval / $t) * 100;
		} else {
			$currentval = 0;
		}
	} else {
		$currentval = '';
	}
	return $currentval;
}

function thold_user_auth_threshold ($rra) {
	$current_user = db_fetch_row("select policy_graphs,policy_hosts,policy_graph_templates from user_auth where id=" . $_SESSION["sess_user_id"]);
	$sql_where = 'WHERE ' . get_graph_permissions_sql($current_user['policy_graphs'], $current_user['policy_hosts'], $current_user['policy_graph_templates']);
	$graphs = db_fetch_assoc('SELECT DISTINCT graph_templates_graph.local_graph_id
		FROM data_template_rrd
		LEFT JOIN graph_templates_item ON graph_templates_item.task_item_id = data_template_rrd.id
		LEFT JOIN graph_local ON (graph_local.id=graph_templates_item.local_graph_id)
		LEFT JOIN host ON graph_local.host_id = host.id
		LEFT JOIN graph_templates_graph ON graph_templates_graph.local_graph_id = graph_local.id
		LEFT JOIN graph_templates ON (graph_templates.id=graph_templates_graph.graph_template_id)
		LEFT JOIN user_auth_perms on ((graph_templates_graph.local_graph_id=user_auth_perms.item_id and user_auth_perms.type=1 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (host.id=user_auth_perms.item_id and user_auth_perms.type=3 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . ') OR (graph_templates.id=user_auth_perms.item_id and user_auth_perms.type=4 and user_auth_perms.user_id=' . $_SESSION['sess_user_id'] . "))
		$sql_where
		AND data_template_rrd.local_data_id = $rra");
	if (!empty($graphs)) {
		return true;
	}
	return false;
}

function thold_log($save){
	$save['id'] = 0;
	if (read_config_option('thold_log_cacti') == 'on') {
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $save['threshold_id'], FALSE);
		$dt = db_fetch_cell('SELECT data_template_id FROM data_template_data WHERE local_data_id=' . $thold['rra_id'], FALSE);
		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $dt, FALSE);
		$ds = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_id'], FALSE);

		if ($save['status'] == 0) {
			$desc = "Threshold Restored  ID: " . $save['threshold_id'];
		} else {
			$desc = "Threshold Breached  ID: " . $save['threshold_id'];
		}
		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;

		$types = array('High/Low', 'Baseline Deviation', 'Time Based');
		$desc .= '  Type: ' . $types[$thold['thold_type']];
		$desc .= '  Enabled: ' . $thold['thold_enabled'];
		switch ($thold['thold_type']) {
		case 0:
			$desc .= '  Current: ' . $save['current'];
			$desc .= '  High: ' . $thold['thold_hi'];
			$desc .= '  Low: ' . $thold['thold_low'];
			$desc .= '  Trigger: ' . plugin_thold_duration_convert($thold['rra_id'], $thold['thold_fail_trigger'], 'alert');
			$desc .= '  Warning High: ' . $thold['thold_warning_hi'];
			$desc .= '  Warning Low: ' . $thold['thold_warning_low'];
			$desc .= '  Warning Trigger: ' . plugin_thold_duration_convert($thold['rra_id'], $thold['thold_warning_fail_trigger'], 'alert');
			break;
		case 1:
			$desc .= '  Current: ' . $save['current'];
			break;
		case 2:
			$desc .= '  Current: ' . $save['current'];
			$desc .= '  High: ' . $thold['time_hi'];
			$desc .= '  Low: ' . $thold['time_low'];
			$desc .= '  Trigger: ' . $thold['time_fail_trigger'];
			$desc .= '  Time: ' . plugin_thold_duration_convert($thold['rra_id'], $thold['time_fail_length'], 'time');
			$desc .= '  Warning High: ' . $thold['time_warning_hi'];
			$desc .= '  Warning Low: ' . $thold['time_warning_low'];
			$desc .= '  Warning Trigger: ' . $thold['time_warning_fail_trigger'];
			$desc .= '  Warning Time: ' . plugin_thold_duration_convert($thold['rra_id'], $thold['time_warning_fail_length'], 'time');
			break;
		}

		$desc .= '  SentTo: ' . $save['emails'];
		if ($save['status'] == ST_RESTORAL || $save['status'] == ST_NOTIFYRS) {
			thold_cacti_log($desc);
		}
	}
	unset($save['emails']);
	$id = sql_save($save, 'plugin_thold_log');
}

function plugin_thold_duration_convert($rra, $data, $type, $field = 'local_data_id') {
	/* handle a null data value */
	if ($data == '') {
		return '';
	}

	$step = db_fetch_cell("SELECT rrd_step FROM data_template_data WHERE $field=$rra");

	if ($step == 60) {
		$repeatarray = array(0 => 'Never', 1 => 'Every Minute', 2 => 'Every 2 Minutes', 3 => 'Every 3 Minutes', 4 => 'Every 4 Minutes', 5 => 'Every 5 Minutes', 10 => 'Every 10 Minutes', 15 => 'Every 15 Minutes', 20 => 'Every 20 Minutes', 30 => 'Every 30 Minutes', 45 => 'Every 45 Minutes', 60 => 'Every Hour', 120 => 'Every 2 Hours', 180 => 'Every 3 Hours', 240 => 'Every 4 Hours', 360 => 'Every 6 Hours', 480 => 'Every 8 Hours', 720 => 'Every 12 Hours', 1440 => 'Every Day', 2880 => 'Every 2 Days', 10080 => 'Every Week', 20160 => 'Every 2 Weeks', 43200 => 'Every Month');
		$alertarray  = array(0 => 'Never', 1 => '1 Minute', 2 => '2 Minutes', 3 => '3 Minutes', 4 => '4 Minutes', 5 => '5 Minutes', 10 => '10 Minutes', 15 => '15 Minutes', 20 => '20 Minutes', 30 => '30 Minutes', 45 => '45 Minutes', 60 => '1 Hour', 120 => '2 Hours', 180 => '3 Hours', 240 => '4 Hours', 360 => '6 Hours', 480 => '8 Hours', 720 => '12 Hours', 1440 => '1 Day', 2880 => '2 Days', 10080 => '1 Week', 20160 => '2 Weeks', 43200 => '1 Month');
		$timearray   = array(1 => '1 Minute', 2 => '2 Minutes', 3 => '3 Minutes', 4 => '4 Minutes', 5 => '5 Minutes', 10 => '10 Minutes', 15 => '15 Minutes', 20 => '20 Minutes', 30 => '30 Minutes', 45 => '45 Minutes', 60 => '1 Hour', 120 => '2 Hours', 180 => '3 Hours', 240 => '4 Hours', 360 => '6 Hours', 480 => '8 Hours', 720 => '12 Hours', 1440 => '1 Day', 2880 => '2 Days', 10080 => '1 Week', 20160 => '2 Weeks', 43200 => '1 Month');
	} else if ($step == 300) {
		$repeatarray = array(0 => 'Never', 1 => 'Every 5 Minutes', 2 => 'Every 10 Minutes', 3 => 'Every 15 Minutes', 4 => 'Every 20 Minutes', 6 => 'Every 30 Minutes', 8 => 'Every 45 Minutes', 12 => 'Every Hour', 24 => 'Every 2 Hours', 36 => 'Every 3 Hours', 48 => 'Every 4 Hours', 72 => 'Every 6 Hours', 96 => 'Every 8 Hours', 144 => 'Every 12 Hours', 288 => 'Every Day', 576 => 'Every 2 Days', 2016 => 'Every Week', 4032 => 'Every 2 Weeks', 8640 => 'Every Month');
		$alertarray  = array(0 => 'Never', 1 => '5 Minutes', 2 => '10 Minutes', 3 => '15 Minutes', 4 => '20 Minutes', 5 => '25 Minutes', 6 => '30 Minutes', 7 => '35 Minutes', 8 => '40 Minutes', 12 => '1 Hour', 24 => '2 Hours', 36 => '3 Hours', 48 => '4 Hours', 72 => '6 Hours', 96 => '8 Hours', 144 => '12 Hours', 288 => '1 Day', 576 => '2 Days', 2016 => '1 Week', 4032 => '2 Weeks', 8640 => '1 Month');
		$timearray   = array(1 => '5 Minutes', 2 => '10 Minutes', 3 => '15 Minutes', 4 => '20 Minutes', 6 => '30 Minutes', 8 => '45 Minutes', 12 => '1 Hour', 24 => '2 Hours', 36 => '3 Hours', 48 => '4 Hours', 72 => '6 Hours', 96 => '8 Hours', 144 => '12 Hours', 288 => '1 Day', 576 => '2 Days', 2016 => '1 Week', 4032 => '2 Weeks', 8640 => '1 Month');
	} else {
		$repeatarray = array(0 => 'Never', 1 => 'Every Polling', 2 => 'Every 2 Pollings', 3 => 'Every 3 Pollings', 4 => 'Every 4 Pollings', 6 => 'Every 6 Pollings', 8 => 'Every 8 Pollings', 12 => 'Every 12 Pollings', 24 => 'Every 24 Pollings', 36 => 'Every 36 Pollings', 48 => 'Every 48 Pollings', 72 => 'Every 72 Pollings', 96 => 'Every 96 Pollings', 144 => 'Every 144 Pollings', 288 => 'Every 288 Pollings', 576 => 'Every 576 Pollings', 2016 => 'Every 2016 Pollings');
		$alertarray  = array(0 => 'Never', 1 => '1 Polling', 2 => '2 Pollings', 3 => '3 Pollings', 4 => '4 Pollings', 6 => '6 Pollings', 8 => '8 Pollings', 12 => '12 Pollings', 24 => '24 Pollings', 36 => '36 Pollings', 48 => '48 Pollings', 72 => '72 Pollings', 96 => '96 Pollings', 144 => '144 Pollings', 288 => '288 Pollings', 576 => '576 Pollings', 2016 => '2016 Pollings');
		$timearray   = array(1 => '1 Polling', 2 => '2 Pollings', 3 => '3 Pollings', 4 => '4 Pollings', 6 => '6 Pollings', 8 => '8 Pollings', 12 => '12 Pollings', 24 => '24 Pollings', 36 => '36 Pollings', 48 => '48 Pollings', 72 => '72 Pollings', 96 => '96 Pollings', 144 => '144 Pollings', 288 => '288 Pollings', 576 => '576 Pollings', 2016 => '2016 Pollings');
	}

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
	$desc = '';

	if (read_config_option('thold_log_changes') != 'on') {
		return;
	}

	if (isset($_SESSION['sess_user_id'])) {
		$user = db_fetch_row('SELECT username FROM user_auth WHERE id = ' . $_SESSION['sess_user_id']);
		$user = $user['username'];
	} else {
		$user = 'Unknown';
	}

	switch ($changed) {
	case 'enabled_threshold':
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template']);
		$ds = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_id']);
		$desc = "Enabled Threshold  User: $user  ID: <a href='" . $config['url_path'] . "plugins/thold/thold.php?rra=" . $thold['rra_id'] . "&view_rrd=" . $thold['data_id'] . "'>$id</a>";
		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;
		break;
	case 'disabled_threshold':
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template']);
		$ds = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_id']);
		$desc = "Disabled Threshold  User: $user  ID: <a href='" . $config['url_path'] . "plugins/thold/thold.php?rra=" . $thold['rra_id'] . "&view_rrd=" . $thold['data_id'] . "'>$id</a>";
		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;
		break;
	case 'reapply_name':
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id=' . $id, FALSE);
		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template']);
		$ds = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_id']);
		$desc = "Reapply Threshold Name User: $user  ID: <a href='" . $config['url_path'] . "plugins/thold/thold.php?rra=" . $thold['rra_id'] . "&view_rrd=" . $thold['data_id'] . "'>$id</a>";
		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;
		break;
	case 'enabled_host':
		$host = db_fetch_row('SELECT * FROM host WHERE id = ' . $id);
		$desc = "User: $user  Enabled Host[$id] - " . $host['description'] . ' (' . $host['hostname'] . ')';
		break;
	case 'disabled_host':
		$host = db_fetch_row('SELECT * FROM host WHERE id = ' . $id);
		$desc = "User: $user  Disabled Host[$id] - " . $host['description'] . ' (' . $host['hostname'] . ')';
		break;
	case 'auto_created':
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template']);
		$ds = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_id']);
		$desc = "Auto-created Threshold  User: $user  ID: <a href='" . $config['url_path'] . "plugins/thold/thold.php?rra=" . $thold['rra_id'] . "&view_rrd=" . $thold['data_id'] . "'>$id</a>";
		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;
		break;
	case 'created':
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template']);
		$ds = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_id']);
		$desc = "Created Threshold  User: $user  ID: <a href='" . $config['url_path'] . "plugins/thold/thold.php?rra=" . $thold['rra_id'] . "&view_rrd=" . $thold['data_id'] . "'>$id</a>";
		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;
		break;
	case 'deleted':
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);
		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template']);
		$ds = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_id']);
		$desc = "Deleted Threshold  User: $user  ID: <a href='" . $config['url_path'] . "plugins/thold/thold.php?rra=" . $thold['rra_id'] . "&view_rrd=" . $thold['data_id'] . "'>$id</a>";
		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;
		break;
	case 'deleted_template':
		$thold = db_fetch_row('SELECT * FROM thold_template WHERE id = ' . $id, FALSE);
		$desc = "Deleted Template  User: $user  ID: $id";
		$desc .= '  DataTemplate: ' . $thold['data_template_name'];
		$desc .= '  DataSource: ' . $thold['data_source_name'];
		break;
	case 'modified':
		$thold = db_fetch_row('SELECT * FROM thold_data WHERE id = ' . $id, FALSE);

		$rows = db_fetch_assoc('SELECT plugin_thold_contacts.data
			FROM plugin_thold_contacts, plugin_thold_threshold_contact
			WHERE plugin_thold_contacts.id=plugin_thold_threshold_contact.contact_id
			AND plugin_thold_threshold_contact.thold_id=' . $id);

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

		$alert_emails .= (strlen($alert_emails) ? ",":"") . get_thold_notification_emails($thold['notify_alert']);

		$warning_emails = '';
		if (read_config_option('thold_disable_legacy') != 'on') {
			$warning_emails = $thold['notify_warning_extra'];
		}

		if ($message['id'] > 0) {
			$desc = "Modified Threshold  User: $user  ID: <a href='" . $config['url_path'] . "plugins/thold/thold.php?rra=" . $thold['rra_id'] . "&view_rrd=" . $thold['data_id'] . "'>$id</a>";
		} else {
			$desc = "Created Threshold  User: $user  ID:  <a href='" . $config['url_path'] . "plugins/thold/thold.php?rra=" . $thold['rra_id'] . "&view_rrd=" . $thold['data_id'] . "'>$id</a>";
		}

		$tname = db_fetch_cell('SELECT name FROM data_template WHERE id=' . $thold['data_template']);
		$ds = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_id']);

		$desc .= '  DataTemplate: ' . $tname;
		$desc .= '  DataSource: ' . $ds;

		if ($message['template_enabled'] == 'on') {
			$desc .= '  Use Template: On';
		} else {
			$types = array('High/Low', 'Baseline Deviation', 'Time Based');
			$desc .= '  Type: ' . $types[$message['thold_type']];
			$desc .= '  Enabled: ' . $message['thold_enabled'];
			switch ($message['thold_type']) {
			case 0:
				$desc .= '  High: ' . $message['thold_hi'];
				$desc .= '  Low: ' . $message['thold_low'];
				$desc .= '  Trigger: ' . plugin_thold_duration_convert($thold['rra_id'], $message['thold_fail_trigger'], 'alert');
				$desc .= '  Warning High: ' . $message['thold_warning_hi'];
				$desc .= '  Warning Low: ' . $message['thold_warning_low'];
				$desc .= '  Warning Trigger: ' . plugin_thold_duration_convert($thold['rra_id'], $message['thold_warning_fail_trigger'], 'alert');

				break;
			case 1:
				$desc .= '  Range: ' . $message['bl_ref_time_range'];
				$desc .= '  Dev Up: ' . $message['bl_pct_up'];
				$desc .= '  Dev Down: ' . $message['bl_pct_down'];
				$desc .= '  Trigger: ' . $message['bl_fail_trigger'];

				break;
			case 2:
				$desc .= '  High: ' . $message['time_hi'];
				$desc .= '  Low: ' . $message['time_low'];
				$desc .= '  Trigger: ' . $message['time_fail_trigger'];
				$desc .= '  Time: ' . plugin_thold_duration_convert($thold['rra_id'], $message['time_fail_length'], 'time');
				$desc .= '  Warning High: ' . $message['time_warning_hi'];
				$desc .= '  Warning Low: ' . $message['time_warning_low'];
				$desc .= '  Warning Trigger: ' . $message['time_warning_fail_trigger'];
				$desc .= '  Warning Time: ' . plugin_thold_duration_convert($thold['rra_id'], $message['time_warning_fail_length'], 'time');

				break;
			}
			$desc .= '  CDEF: ' . $message['cdef'];
			$desc .= '  ReAlert: ' . plugin_thold_duration_convert($thold['rra_id'], $message['repeat_alert'], 'alert');
			$desc .= '  Alert Emails: ' . $alert_emails;
			$desc .= '  Warning Emails: ' . $warning_emails;
		}

		break;
	case 'modified_template':
		$thold = db_fetch_row('SELECT * FROM thold_template WHERE id = ' . $id, FALSE);

		$rows = db_fetch_assoc('SELECT plugin_thold_contacts.data
			FROM plugin_thold_contacts, plugin_thold_template_contact
			WHERE plugin_thold_contacts.id=plugin_thold_template_contact.contact_id
			AND plugin_thold_template_contact.template_id=' . $id);

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

		$alert_emails .= (strlen($alert_emails) ? ",":"") . get_thold_notification_emails($thold['notify_alert']);

		$warning_emails = '';
		if (read_config_option('thold_disable_legacy') != 'on') {
			$warning_emails = $thold['notify_warning_extra'];
		}

		if ($message['id'] > 0) {
			$desc = "Modified Template  User: $user  ID: <a href='" . $config['url_path'] . "plugins/thold/thold_templates.php?action=edit&id=$id'>$id</a>";
		} else {
			$desc = "Created Template  User: $user  ID:  <a href='" . $config['url_path'] . "plugins/thold/thold_templates.php?action=edit&id=$id'>$id</a>";
		}

		$desc .= '  DataTemplate: ' . $thold['data_template_name'];
		$desc .= '  DataSource: ' . $thold['data_source_name'];

		$types = array('High/Low', 'Baseline Deviation', 'Time Based');
		$desc .= '  Type: ' . $types[$message['thold_type']];
		$desc .= '  Enabled: ' . $message['thold_enabled'];

		switch ($message['thold_type']) {
		case 0:
			$desc .= '  High: ' . (isset($message['thold_hi']) ? $message['thold_hi'] : '');
			$desc .= '  Low: ' . (isset($message['thold_low']) ? $message['thold_low'] : '');
			$desc .= '  Trigger: ' . plugin_thold_duration_convert($thold['data_template_id'], (isset($message['thold_fail_trigger']) ? $message['thold_fail_trigger'] : ''), 'alert', 'data_template_id');
			$desc .= '  Warning High: ' . (isset($message['thold_warning_hi']) ? $message['thold_warning_hi'] : '');
			$desc .= '  Warning Low: ' . (isset($message['thold_warning_low']) ? $message['thold_warning_low'] : '');
			$desc .= '  Warning Trigger: ' . plugin_thold_duration_convert($thold['data_template_id'], (isset($message['thold_warning_fail_trigger']) ? $message['thold_fail_trigger'] : ''), 'alert', 'data_template_id');

			break;
		case 1:
			$desc .= '  Range: ' . $message['bl_ref_time_range'];
			$desc .= '  Dev Up: ' . (isset($message['bl_pct_up'])? $message['bl_pct_up'] : "" );
			$desc .= '  Dev Down: ' . (isset($message['bl_pct_down'])? $message['bl_pct_down'] : "" );
			$desc .= '  Trigger: ' . $message['bl_fail_trigger'];

			break;
		case 2:
			$desc .= '  High: ' . $message['time_hi'];
			$desc .= '  Low: ' . $message['time_low'];
			$desc .= '  Trigger: ' . $message['time_fail_trigger'];
			$desc .= '  Time: ' . plugin_thold_duration_convert($thold['data_template_id'], $message['time_fail_length'], 'alert', 'data_template_id');
			$desc .= '  Warning High: ' . $message['time_warning_hi'];
			$desc .= '  Warning Low: ' . $message['time_warning_low'];
			$desc .= '  Warning Trigger: ' . $message['time_warning_fail_trigger'];
			$desc .= '  Warning Time: ' . plugin_thold_duration_convert($thold['data_template_id'], $message['time_warning_fail_length'], 'alert', 'data_template_id');

			break;
		}

		$desc .= '  CDEF: ' . (isset($message['cdef']) ? $message['cdef']: '');
		$desc .= '  ReAlert: ' . plugin_thold_duration_convert($thold['data_template_id'], $message['repeat_alert'], 'alert', 'data_template_id');
		$desc .= '  Alert Emails: ' . $alert_emails;
		$desc .= '  Warning Emails: ' . $warning_emails;

		break;
	}

	if ($desc != '') {
		thold_cacti_log($desc);
	}
}

function thold_check_threshold ($rra_id, $data_id, $name, $currentval, $cdef) {
	global $config, $plugins, $debug;

	thold_debug("Checking Threshold:  DS:$name RRA_ID:$rra_id DATA_ID:$data_id VALUE:$currentval");
	$debug = false;

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

	/* Get all the info about the item from the database */
	$item = db_fetch_assoc("SELECT * FROM thold_data WHERE thold_enabled='on' AND data_id=" . $data_id);

	/* return if the item doesn't exist, which means its disabled */
	if (!isset($item[0])) {
		thold_debug('Threshold is disabled');
		return;
	}
	$item = $item[0];

	/* check for the weekend exemption on the threshold level */
	if (($weekday == 'Saturday' || $weekday == 'Sunday') && $item['exempt'] == 'on') {
		thold_debug('Threshold checking is disabled by global weekend exemption');
		return;
	}

	/* don't alert for this host if it's selected for maintenance */
	if (api_plugin_is_enabled('maint') || in_array('maint', $plugins)) {
		include_once($config["base_path"] . '/plugins/maint/functions.php');
		if (plugin_maint_check_cacti_host ($item['host_id'])) {
			thold_debug('Threshold checking is disabled by maintenance schedule');
			return;
		}
	}

	$graph_id = $item['graph_id'];

	/* only alert if Host is in UP mode (not down, unknown, or recovering) */
	$h = db_fetch_row('SELECT * FROM host WHERE id=' . $item['host_id']);
	if ($h['status'] != 3) {
		thold_debug('Threshold checking halted by Host Status (' . $h['status'] . ')' );
		return;
	}

	/* pull the cached name, if not present, it means that the graph hasn't polled yet */
	$t = db_fetch_assoc('SELECT id, name, name_cache
		FROM data_template_data
		WHERE local_data_id = ' . $rra_id . '
		ORDER BY id
		LIMIT 1');

	/* pull a few default settings */
	$global_alert_address = read_config_option('alert_email');
	$global_notify_enabled = (read_config_option('alert_notify_default') == 'on');
	$logset = (read_config_option('alert_syslog') == 'on');
	$deadnotify = (read_config_option('alert_deadnotify') == 'on');
	$realert = read_config_option('alert_repeat');
	$alert_trigger = read_config_option('alert_trigger');
	$alert_bl_trigger = read_config_option('alert_bl_trigger');
	$httpurl = read_config_option('alert_base_url');
	$thold_show_datasource = read_config_option('thold_show_datasource');
	$thold_send_text_only = read_config_option('thold_send_text_only');
	$thold_alert_text = read_config_option('thold_alert_text');
	$thold_warning_text = read_config_option('thold_warning_text');

	$thold_snmp_traps = (read_config_option('thold_alert_snmp') == 'on');
	$thold_snmp_warning_traps = (read_config_option('thold_alert_snmp_warning') != 'on');
	$thold_snmp_normal_traps = (read_config_option('thold_alert_snmp_normal') != 'on');
	$thold_snmp_event_description = read_config_option('thold_snmp_event_description');
	$cacti_polling_interval = read_config_option('poller_interval');

	/* remove this after adding an option for it */
	$thold_show_datasource = true;

	$trigger = ($item['thold_fail_trigger'] == '' ? $alert_trigger : $item['thold_fail_trigger']);
	$warning_trigger = ($item['thold_warning_fail_trigger'] == '' ? $alert_trigger : $item['thold_warning_fail_trigger']);
	$alertstat = $item['thold_alert'];

	/* make sure the alert text has been set */
	if (!isset($thold_alert_text) || $thold_alert_text == '') {
		$thold_alert_text = "<html><body>An alert has been issued that requires your attention.<br><br><strong>Host</strong>: <DESCRIPTION> (<HOSTNAME>)<br><strong>URL</strong>: <URL><br><strong>Message</strong>: <SUBJECT><br><br><GRAPH></body></html>";
	}
	/* make sure the warning text has been set */
	if (!isset($thold_warning_text) || $thold_warning_text == '') {
		$thold_warning_text = "<html><body>A warning has been issued that requires your attention.<br><br><strong>Host</strong>: <DESCRIPTION> (<HOSTNAME>)<br><strong>URL</strong>: <URL><br><strong>Message</strong>: <SUBJECT><br><br><GRAPH></body></html>";
	}

	$hostname = db_fetch_row('SELECT description, hostname from host WHERE id = ' . $item['host_id']);

	$rows = db_fetch_assoc('SELECT plugin_thold_contacts.data
		FROM plugin_thold_contacts, plugin_thold_threshold_contact
		WHERE plugin_thold_contacts.id=plugin_thold_threshold_contact.contact_id
		AND plugin_thold_threshold_contact.thold_id = ' . $item['id']);

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
			$alert_emails .= ',' . $item['notify_extra'];
		} else {
			$alert_emails = $item['notify_extra'];
		}
	}

	$alert_emails .= (strlen($alert_emails) ? ",":"") . get_thold_notification_emails($item['notify_alert']);

	$warning_emails = '';
	if (read_config_option('thold_disable_legacy') != 'on') {
		$warning_emails = $item['notify_warning_extra'];
	}

	$warning_emails .= (strlen($warning_emails) ? ",":"") . get_thold_notification_emails($item['notify_warning']);

	$types = array('High/Low', 'Baseline Deviation', 'Time Based');

	// Do some replacement of variables
	$thold_alert_text = str_replace('<DESCRIPTION>', $hostname['description'], $thold_alert_text);
	$thold_alert_text = str_replace('<HOSTNAME>', $hostname['hostname'], $thold_alert_text);
	$thold_alert_text = str_replace('<TIME>', time(), $thold_alert_text);
	$thold_alert_text = str_replace('<GRAPHID>', $graph_id, $thold_alert_text);
	$thold_alert_text = str_replace('<URL>', "<a href='$httpurl/graph.php?local_graph_id=$graph_id&rra_id=1'>$httpurl/graph.php?local_graph_id=$graph_id&rra_id=1</a>", $thold_alert_text);
	$thold_alert_text = str_replace('<CURRENTVALUE>', $currentval, $thold_alert_text);
	$thold_alert_text = str_replace('<THRESHOLDNAME>', $item['name'], $thold_alert_text);
	$thold_alert_text = str_replace('<DSNAME>', $name, $thold_alert_text);
	$thold_alert_text = str_replace('<THOLDTYPE>', $types[$item['thold_type']], $thold_alert_text);
	$thold_alert_text = str_replace('<HI>', ($item['thold_type'] == 0 ? $item['thold_hi'] : ($item['thold_type'] == 2 ? $item['time_hi'] : '')), $thold_alert_text);
	$thold_alert_text = str_replace('<LOW>', ($item['thold_type'] == 0 ? $item['thold_low'] : ($item['thold_type'] == 2 ? $item['time_low'] : '')), $thold_alert_text);
	$thold_alert_text = str_replace('<TRIGGER>', ($item['thold_type'] == 0 ? $item['thold_fail_trigger'] : ($item['thold_type'] == 2 ? $item['time_fail_trigger'] : '')), $thold_alert_text);
	$thold_alert_text = str_replace('<DURATION>', ($item['thold_type'] == 2 ? plugin_thold_duration_convert($item['rra_id'], $item['time_fail_length'], 'time') : ''), $thold_alert_text);
	$thold_alert_text = str_replace('<DATE_RFC822>', date(DATE_RFC822), $thold_alert_text);
	$thold_alert_text = str_replace('<DEVICENOTE>', $h['notes'], $thold_alert_text);

	// Do some replacement of variables
	$thold_warning_text = str_replace('<DESCRIPTION>', $hostname['description'], $thold_warning_text);
	$thold_warning_text = str_replace('<HOSTNAME>', $hostname['hostname'], $thold_warning_text);
	$thold_warning_text = str_replace('<TIME>', time(), $thold_warning_text);
	$thold_warning_text = str_replace('<GRAPHID>', $graph_id, $thold_warning_text);
	$thold_warning_text = str_replace('<URL>', "<a href='$httpurl/graph.php?local_graph_id=$graph_id&rra_id=1'>$httpurl/graph.php?local_graph_id=$graph_id&rra_id=1</a>", $thold_warning_text);
	$thold_warning_text = str_replace('<CURRENTVALUE>', $currentval, $thold_warning_text);
	$thold_warning_text = str_replace('<THRESHOLDNAME>', $item['name'], $thold_warning_text);
	$thold_warning_text = str_replace('<DSNAME>', $name, $thold_warning_text);
	$thold_warning_text = str_replace('<THOLDTYPE>', $types[$item['thold_type']], $thold_warning_text);
	$thold_warning_text = str_replace('<HI>', ($item['thold_type'] == 0 ? $item['thold_hi'] : ($item['thold_type'] == 2 ? $item['time_warning_hi'] : '')), $thold_warning_text);
	$thold_warning_text = str_replace('<LOW>', ($item['thold_type'] == 0 ? $item['thold_low'] : ($item['thold_type'] == 2 ? $item['time_warning_low'] : '')), $thold_warning_text);
	$thold_warning_text = str_replace('<TRIGGER>', ($item['thold_type'] == 0 ? $item['thold_warning_fail_trigger'] : ($item['thold_type'] == 2 ? $item['time_warning_fail_trigger'] : '')), $thold_warning_text);
	$thold_warning_text = str_replace('<DURATION>', ($item['thold_type'] == 2 ? plugin_thold_duration_convert($item['rra_id'], $item['time_warning_fail_length'], 'time') : ''), $thold_warning_text);
	$thold_warning_text = str_replace('<DATE_RFC822>', date(DATE_RFC822), $thold_warning_text);
	$thold_warning_text = str_replace('<DEVICENOTE>', $h['notes'], $thold_warning_text);

	// Do some replacement of variables
	$thold_snmp_data = array(
		"eventDateRFC822"			=> date(DATE_RFC822),
		"eventClass"				=> 3,						// default - see CACTI-THOLD-MIB
		"eventSeverity"				=> 3,						// default - see CACTI-THOLD-MIB
		"eventCategory"				=> ($item['snmp_event_category'] ? $item['snmp_event_category'] : ''),
		"eventSource"				=> $item['name'],
		"eventDescription"			=> '',						// default - see CACTI-THOLD-MIB
		"eventDevice"				=> $hostname['hostname'],
		"eventDeviceIp"				=> gethostbyname($hostname['hostname']),
		"eventDataSource"			=> $name,
		"eventCurrentValue"			=> $currentval,
		"eventHigh"					=> ($item['thold_type'] == 0 ? $item['thold_hi'] : ($item['thold_type'] == 2 ? $item['time_warning_hi'] : '')),
		"eventLow"					=> ($item['thold_type'] == 0 ? $item['thold_low'] : ($item['thold_type'] == 2 ? $item['time_warning_low'] : '')),
		"eventThresholdType"		=> $types[$item['thold_type']] + 1,
		"eventNotificationType"		=> 5,						// default - see CACTI-THOLD-MIB
		"eventStatus"				=> 3,						// default - see CACTI-THOLD-MIB
		"eventRealertStatus"		=> 1,						// default - see CACTI-THOLD-MIB
		"eventFailDuration"			=> 0,						// default - see CACTI-THOLD-MIB
		"eventFailCount"			=> 0,						// default - see CACTI-THOLD-MIB
		"eventFailDurationTrigger"	=> 0,						// default - see CACTI-THOLD-MIB
		"eventFailCountTrigger"		=> 0,						// default - see CACTI-THOLD-MIB
	);
	$thold_snmp_event_description = str_replace('<THRESHOLDNAME>', $thold_snmp_data["eventSource"], $thold_snmp_event_description);
	$thold_snmp_event_description = str_replace('<HOSTNAME>', $thold_snmp_data["eventDevice"], $thold_snmp_event_description);
	$thold_snmp_event_description = str_replace('<HOSTIP>', $thold_snmp_data["eventDeviceIp"], $thold_snmp_event_description);
	$thold_snmp_event_description = str_replace('<TEMPLATE_ID>', ($item['template'] ? $item['template'] : 'none'), $thold_snmp_event_description);
	$thold_snmp_event_description = str_replace('<TEMPLATE_NAME>', (isset($item_template['name']) ? $item_template['name'] : 'none'), $thold_snmp_event_description);
	$thold_snmp_event_description = str_replace('<THR_TYPE>', $thold_snmp_data["eventThresholdType"], $thold_snmp_event_description);
	$thold_snmp_event_description = str_replace('<DS_NAME>', $thold_snmp_data["eventDataSource"], $thold_snmp_event_description);
	$thold_snmp_event_description = str_replace('<HI>', $thold_snmp_data["eventHigh"], $thold_snmp_event_description);
	$thold_snmp_event_description = str_replace('<LOW>', $thold_snmp_data["eventLow"], $thold_snmp_event_description);
	$thold_snmp_event_description = str_replace('<EVENT_CATEGORY>', $thold_snmp_data["eventCategory"], $thold_snmp_event_description);
	$thold_snmp_data["eventDescription"] = $thold_snmp_event_description;

	$msg = $thold_alert_text;
	$warn_msg = $thold_warning_text;

	if ($thold_send_text_only == 'on') {
		$file_array = '';
	} else {
		$file_array = array(0 => array('local_graph_id' => $graph_id, 'rra_id' => 0, 'file' => "$httpurl/graph_image.php?local_graph_id=$graph_id&rra_id=0&view_type=tree",'mimetype'=>'image/png','filename'=>$graph_id));
	}

	$url = $httpurl . "/graph.php?local_graph_id=" . $graph_id ."&rra_id=all";

	switch ($item['thold_type']) {
	case 0:	/* hi/low */
		if ($currentval != '') {
			$breach_up = ($item['thold_hi'] != '' && $currentval > $item['thold_hi']);
			$breach_down = ($item['thold_low'] != '' && $currentval < $item['thold_low']);
			$warning_breach_up = ($item['thold_warning_hi'] != '' && $currentval > $item['thold_warning_hi']);
			$warning_breach_down = ($item['thold_warning_low'] != '' && $currentval < $item['thold_warning_low']);
		} else {
			$breach_up = $breach_down = $warning_breach_up = $warning_breach_down = false;
		}

		/* is in alert status */
		if ($breach_up || $breach_down) {
			$notify = false;

			thold_debug('Threshold HI / Low check breached HI:' . $item['thold_hi'] . '  LOW:' . $item['thold_low'] . ' VALUE:' . $currentval);

			$item['thold_fail_count']++;
			$item['thold_alert'] = ($breach_up ? STAT_HI : STAT_LO);

			/* Re-Alert? */
			$ra = ($item['thold_fail_count'] > $trigger && $item['repeat_alert'] != 0 && $item['thold_fail_count'] % $item['repeat_alert'] == 0);

			if ($item['thold_fail_count'] == $trigger || $ra) {
				$notify = true;
			}

			$subject = "ALERT: " . $item['name'] . ($thold_show_datasource ? " [$name]" : '') . ' ' . ($ra ? 'is still' : 'went') . ' ' . ($breach_up ? 'above' : 'below') . ' threshold of ' . ($breach_up ? $item['thold_hi'] : $item['thold_low']) . " with $currentval";
			if ($notify) {
				thold_debug('Alerting is necessary');

				if ($logset == 1) {
					logger($item['name'], ($ra ? 'realert':'alert'), ($breach_up ? $item['thold_hi'] : $item['thold_low']), $currentval, $trigger, $item['thold_fail_count'], $url);
				}

				if (trim($alert_emails) != '') {
					thold_mail($alert_emails, '', $subject, $msg, $file_array);
				}

				if ($thold_snmp_traps) {
					$thold_snmp_data["eventClass"] = 3;
					$thold_snmp_data["eventSeverity"] = $item['snmp_event_severity'];
					$thold_snmp_data["eventStatus"] = $item['thold_alert']+1;
					$thold_snmp_data["eventRealertStatus"] = ($ra ? ($breach_up ? 3:2) :1);
					$thold_snmp_data["eventNotificationType"] = ($ra ? ST_NOTIFYRA:ST_NOTIFYAL)+1;
					$thold_snmp_data["eventFailCount"] = $item['thold_fail_count'];
					$thold_snmp_data["eventFailDuration"] = $item['thold_fail_count'] * $cacti_polling_interval;
					$thold_snmp_data["eventFailDurationTrigger"] = $trigger * $cacti_polling_interval;

					$thold_snmp_data["eventDescription"] = str_replace(
					    array('<FAIL_COUNT>', '<FAIL_DURATION>'),
					    array($thold_snmp_data["eventFailCount"], $thold_snmp_data["eventFailDuration"]),
					    $thold_snmp_data["eventDescription"]
					);
					thold_snmptrap($thold_snmp_data);
				}
				thold_log(array(
					'type' => 0,
					'time' => time(),
					'host_id' => $item['host_id'],
					'graph_id' => $graph_id,
					'threshold_id' => $item['id'],
					'threshold_value' => ($breach_up ? $item['thold_hi'] : $item['thold_low']),
					'current' => $currentval,
					'status' => ($ra ? ST_NOTIFYRA:ST_NOTIFYAL),
					'description' => $subject,
					'emails' => $alert_emails));
			}

			db_execute("UPDATE thold_data
				SET thold_alert=" . $item['thold_alert'] . ",
				thold_fail_count=" . $item['thold_fail_count'] . ",
				thold_warning_fail_count=0
				WHERE rra_id=$rra_id AND data_id=" . $item['data_id']);
		} elseif ($warning_breach_up || $warning_breach_down) {
			$notify = false;

			thold_debug('Threshold HI / Low Warning check breached HI:' . $item['thold_warning_hi'] . '  LOW:' . $item['thold_warning_low'] . ' VALUE:' . $currentval);

			$item['thold_warning_fail_count']++;
			$item['thold_alert'] = ($warning_breach_up ? STAT_HI:STAT_LO);

			/* re-alert? */
			$ra = ($item['thold_warning_fail_count'] > $warning_trigger && $item['repeat_alert'] != 0 && $item['thold_warning_fail_count'] % $item['repeat_alert'] == 0);

			if ($item['thold_warning_fail_count'] == $warning_trigger || $ra) {
				$notify = true;
			}

			$subject = ($notify ? "WARNING: ":"TRIGGER: ") . $item['name'] . ($thold_show_datasource ? " [$name]" : '') . ' ' . ($ra ? 'is still' : 'went') . ' ' . ($warning_breach_up ? 'above' : 'below') . ' threshold of ' . ($warning_breach_up ? $item['thold_warning_hi'] : $item['thold_warning_low']) . " with $currentval";

			if ($notify) {
				thold_debug('Alerting is necessary');

				if ($logset == 1) {
					logger($item['name'], ($ra ? 'rewarning':'warning'), ($warning_breach_up ? $item['thold_warning_hi'] : $item['thold_warning_low']), $currentval, $warning_trigger, $item['thold_warning_fail_count'], $url);
				}

				if (trim($warning_emails) != '') {
					thold_mail($warning_emails, '', $subject, $warn_msg, $file_array);
				}

				if ($thold_snmp_traps && $thold_snmp_warning_traps) {
					$thold_snmp_data["eventClass"] = 2;
					$thold_snmp_data["eventSeverity"] = $item['snmp_event_warning_severity'];
					$thold_snmp_data["eventStatus"] = $item['thold_alert']+1;
					$thold_snmp_data["eventRealertStatus"] = ($ra ? ($warning_breach_up ? 3:2) :1);
					$thold_snmp_data["eventNotificationType"] = ($ra ? ST_NOTIFYRA:ST_NOTIFYWA)+1;
					$thold_snmp_data["eventFailCount"] = $item['thold_warning_fail_count'];
					$thold_snmp_data["eventFailDuration"] = $item['thold_warning_fail_count'] * $cacti_polling_interval;
					$thold_snmp_data["eventFailDurationTrigger"] = $warning_trigger * $cacti_polling_interval;

					$thold_snmp_data["eventDescription"] = str_replace(
					    array('<FAIL_COUNT>', '<FAIL_DURATION>'),
					    array($thold_snmp_data["eventFailCount"], $thold_snmp_data["eventFailDuration"]),
					    $thold_snmp_data["eventDescription"]
					);
					thold_snmptrap($thold_snmp_data);
				}

				thold_log(array(
					'type' => 0,
					'time' => time(),
					'host_id' => $item['host_id'],
					'graph_id' => $graph_id,
					'threshold_id' => $item['id'],
					'threshold_value' => ($warning_breach_up ? $item['thold_warning_hi'] : $item['thold_warning_low']),
					'current' => $currentval,
					'status' => ($ra ? ST_NOTIFYRA:ST_NOTIFYWA),
					'description' => $subject,
					'emails' => $alert_emails));
			}elseif (($item['thold_warning_fail_count'] >= $warning_trigger) && ($item['thold_fail_count'] >= $trigger)) {
				$subject = "ALERT -> WARNING: ". $item['name'] . ($thold_show_datasource ? " [$name]" : '') . " Changed to Warning Threshold with Value $currentval";

				if (trim($alert_emails) != '') {
					thold_mail($alert_emails, '', $subject, $warn_msg, $file_array);
				}

				if ($thold_snmp_traps && $thold_snmp_warning_traps) {
					$thold_snmp_data["eventClass"] = 2;
					$thold_snmp_data["eventSeverity"] = $item['snmp_event_warning_severity'];
					$thold_snmp_data["eventStatus"] = $item['thold_alert']+1;
					$thold_snmp_data["eventNotificationType"] = ST_NOTIFYAW+1;
					$thold_snmp_data["eventFailCount"] = $item['thold_warning_fail_count'];
					$thold_snmp_data["eventFailDuration"] = $item['thold_warning_fail_count'] * $cacti_polling_interval;
					$thold_snmp_data["eventFailDurationTrigger"] = $trigger * $cacti_polling_interval;

					$thold_snmp_data["eventDescription"] = str_replace(
					    array('<FAIL_COUNT>', '<FAIL_DURATION>'),
					    array($thold_snmp_data["eventFailCount"], $thold_snmp_data["eventFailDuration"]),
					    $thold_snmp_data["eventDescription"]
					);

					thold_snmptrap($thold_snmp_data);
				}

				thold_log(array(
					'type' => 0,
					'time' => time(),
					'host_id' => $item['host_id'],
					'graph_id' => $graph_id,
					'threshold_id' => $item['id'],
					'threshold_value' => ($warning_breach_up ? $item['thold_warning_hi'] : $item['thold_warning_low']),
					'current' => $currentval,
					'status' => ST_NOTIFYAW,
					'description' => $subject,
					'emails' => $alert_emails));
			}

			db_execute("UPDATE thold_data
				SET thold_alert=" . $item['thold_alert'] . ",
				thold_warning_fail_count=" . $item['thold_warning_fail_count'] . ",
				thold_fail_count=0
				WHERE rra_id=$rra_id AND data_id=" . $item['data_id']);
		} else {
			thold_debug('Threshold HI / Low check is normal HI:' . $item['thold_hi'] . '  LOW:' . $item['thold_low'] . ' VALUE:' . $currentval);

			/* if we were at an alert status before */
			if ($alertstat != 0) {
				$subject = "NORMAL: ". $item['name'] . ($thold_show_datasource ? " [$name]" : '') . " Restored to Normal Threshold with Value $currentval";

				db_execute("UPDATE thold_data
					SET thold_alert=0, thold_fail_count=0, thold_warning_fail_count=0
					WHERE rra_id=$rra_id AND data_id=" . $item['data_id']);

				if ($item['thold_warning_fail_count'] >= $warning_trigger && $item['restored_alert'] != 'on') {
					if ($logset == 1) {
						logger($item['name'], 'ok', 0, $currentval, $warning_trigger, $item['thold_warning_fail_count'], $url);
					}

					if (trim($warning_emails) != '' && $item['restored_alert'] != 'on') {
						thold_mail($warning_emails, '', $subject, $warn_msg, $file_array);
					}

					if ($thold_snmp_traps && $thold_snmp_normal_traps) {
						$thold_snmp_data["eventClass"] = 1;
						$thold_snmp_data["eventSeverity"] = 1;
						$thold_snmp_data["eventStatus"] = 1;
						$thold_snmp_data["eventNotificationType"] = ST_NOTIFYRS+1;
						thold_snmptrap($thold_snmp_data);
					}

					thold_log(array(
						'type' => 0,
						'time' => time(),
						'host_id' => $item['host_id'],
						'graph_id' => $graph_id,
						'threshold_id' => $item['id'],
						'threshold_value' => '',
						'current' => $currentval,
						'status' => ST_NOTIFYRS,
						'description' => $subject,
						'emails' => $warning_emails));
				} elseif ($item['thold_fail_count'] >= $trigger && $item['restored_alert'] != 'on') {
					if ($logset == 1) {
						logger($item['name'], 'ok', 0, $currentval, $trigger, $item['thold_fail_count'], $url);
					}

					if (trim($alert_emails) != '' && $item['restored_alert'] != 'on') {
						thold_mail($alert_emails, '', $subject, $msg, $file_array);
					}

					if ($thold_snmp_traps && $thold_snmp_normal_traps) {
						$thold_snmp_data["eventClass"] = 1;
						$thold_snmp_data["eventSeverity"] = 1;
						$thold_snmp_data["eventStatus"] = 1;
						$thold_snmp_data["eventNotificationType"] = ST_NOTIFYRS+1;
						thold_snmptrap($thold_snmp_data);
					}

					thold_log(array(
						'type' => 0,
						'time' => time(),
						'host_id' => $item['host_id'],
						'graph_id' => $graph_id,
						'threshold_id' => $item['id'],
						'threshold_value' => '',
						'current' => $currentval,
						'status' => ST_NOTIFYRS,
						'description' => $subject,
						'emails' => $alert_emails));
				}
			}
		}

		break;
	case 1:	/* baseline */
		$bl_alert_prev = $item['bl_alert'];
		$bl_count_prev = $item['bl_fail_count'];
		$bl_fail_trigger = ($item['bl_fail_trigger'] == '' ? $alert_bl_trigger : $item['bl_fail_trigger']);
		$item['bl_alert'] = thold_check_baseline($rra_id, $name, $currentval, $item);

		switch($item['bl_alert']) {
		case -2:	/* exception is active, Future Release 'todo' */
			break;
		case -1:	/* reference value not available, Future Release 'todo' */
			break;
		case 0:		/* all clear */
			/* if we were at an alert status before */
			if ($alertstat != 0) {
				thold_debug('Threshold Baseline check is normal');

				if ($item['bl_fail_count'] >= $bl_fail_trigger && $item['restored_alert'] != 'on') {
					thold_debug('Threshold Baseline check returned to normal');

					if ($logset == 1) {
						logger($item['name'], 'ok', 0, $currentval, $item['bl_fail_trigger'], $item['bl_fail_count'], $url);
					}

					$subject = "NORMAL: " . $item['name'] . ($thold_show_datasource ? " [$name]" : '') . " restored to normal threshold with value $currentval";

					if (trim($alert_emails) != '') {
						thold_mail($alert_emails, '', $subject, $msg, $file_array);
					}

					if ($thold_snmp_traps && $thold_snmp_normal_traps) {
						$thold_snmp_data["eventClass"] = 1;
						$hold_snmp_data["eventSeverity"] = 1;
						$thold_snmp_data["eventStatus"] = 1;
						$thold_snmp_data["eventNotificationType"] = ST_NOTIFYRS+1;

						thold_snmptrap($thold_snmp_data);
					}

					thold_log(array(
						'type' => 1,
						'time' => time(),
						'host_id' => $item['host_id'],
						'graph_id' => $graph_id,
						'threshold_id' => $item['id'],
						'threshold_value' => '',
						'current' => $currentval,
						'status' => ST_NOTIFYRA,
						'description' => $subject,
						'emails' => $alert_emails));
				}
			}

			$item['bl_fail_count'] = 0;

			break;
		case 1: /* value is below calculated threshold */
		case 2: /* value is above calculated threshold */
			$item['bl_fail_count']++;
			$breach_up   = ($item['bl_alert'] == STAT_HI);
			$breach_down = ($item['bl_alert'] == STAT_LO);

			thold_debug('Threshold Baseline check breached');

			/* re-alert? */
			$ra = ($item['bl_fail_count'] > $bl_fail_trigger && ($item['bl_fail_count'] % ($item['repeat_alert'] == '' ? $realert : $item['repeat_alert'])) == 0);

			if ($item['bl_fail_count'] == $bl_fail_trigger || $ra) {
				thold_debug('Alerting is necessary');

				$subject = "ALERT: " . $item['name'] . ($thold_show_datasource ? " [$name]" : '') . ' ' . ($ra ? 'is still' : 'went') . ' ' . ($breach_up ? 'above' : 'below') . " calculated baseline threshold " . ($breach_up ? $item['thold_hi'] : $item['thold_low']) . " with $currentval";

				if ($logset == 1) {
					logger($item['name'], ($ra ? 'realert':'alert'), ($breach_up ? $item['thold_hi'] : $item['thold_low']), $currentval, $item['bl_fail_trigger'], $item['bl_fail_count'], $url);
				}

				if (trim($alert_emails) != '') {
					thold_mail($alert_emails, '', $subject, $msg, $file_array);
				}

				if ($thold_snmp_traps) {
					$thold_snmp_data["eventClass"] = 3;
					$thold_snmp_data["eventSeverity"] = $item['snmp_event_severity'];
					$thold_snmp_data["eventStatus"] = $item['bl_alert']+1;
					$thold_snmp_data["eventRealertStatus"] = ($ra ? ($breach_up ? 3:2) :1);
					$thold_snmp_data["eventNotificationType"] = ($ra ? ST_NOTIFYRA:ST_NOTIFYAL)+1;
					$thold_snmp_data["eventFailCount"] = $item['bl_fail_count'];
					$thold_snmp_data["eventFailDuration"] = $item['bl_fail_count'] * $cacti_polling_interval;
					$thold_snmp_data["eventFailCountTrigger"] = $bl_fail_trigger;

					$thold_snmp_data["eventDescription"] = str_replace(
					    array('<FAIL_COUNT>', '<FAIL_DURATION>'),
					    array($thold_snmp_data["eventFailCount"], $thold_snmp_data["eventFailDuration"]),
					    $thold_snmp_data["eventDescription"]
					);


					thold_snmptrap($thold_snmp_data);
				}

				thold_log(array(
					'type' => 1,
					'time' => time(),
					'host_id' => $item['host_id'],
					'graph_id' => $graph_id,
					'threshold_id' => $item['id'],
					'threshold_value' => ($breach_up ? $item['thold_hi'] : $item['thold_low']),
					'current' => $currentval,
					'status' => ($ra ? ST_NOTIFYRA:ST_NOTIFYAL),
					'description' => $subject,
					'emails' => $alert_emails));
			} else {
				thold_log(array(
					'type' => 1,
					'time' => time(),
					'host_id' => $item['host_id'],
					'graph_id' => $graph_id,
					'threshold_id' => $item['id'],
					'threshold_value' => ($breach_up ? $item['thold_hi'] : $item['thold_low']),
					'current' => $currentval,
					'status' => ST_TRIGGERA,
					'description' => $subject,
					'emails' => $alert_emails));
			}

			break;
		}

		db_execute("UPDATE thold_data SET thold_alert=0, thold_fail_count=0,
			bl_alert='" . $item['bl_alert'] . "',
			bl_fail_count='" . $item['bl_fail_count'] . "',
			thold_low='" . $item['thold_low'] . "',
			thold_hi='" . $item['thold_hi'] . "',
			bl_thold_valid='" . $item['bl_thold_valid'] . "'
			WHERE rra_id='$rra_id' AND data_id=" . $item['data_id']);

		break;
	case 2:	/* time based */
		if ($currentval != '') {
			$breach_up = ($item['time_hi'] != '' && $currentval > $item['time_hi']);
			$breach_down = ($item['time_low'] != '' && $currentval < $item['time_low']);
			$warning_breach_up = ($item['time_warning_hi'] != '' && $currentval > $item['time_warning_hi']);
			$warning_breach_down = ($item['time_warning_low'] != '' && $currentval < $item['time_warning_low']);
		} else {
			$breach_up = $breach_down = $warning_breach_up = $warning_breach_down = false;
		}

		$step = db_fetch_cell('SELECT rrd_step FROM data_template_data WHERE local_data_id = ' . $rra_id, FALSE);

		/* alerts */
		$trigger  = $item['time_fail_trigger'];
		$time     = time() - ($item['time_fail_length'] * $step);
		$failures = db_fetch_cell("SELECT count(id) FROM plugin_thold_log WHERE threshold_id=" . $item['id'] . " AND status IN (" . ST_TRIGGERA . "," . ST_NOTIFYRA . "," . ST_NOTIFYAL . ") AND time>" . $time);

		/* warnings */
		$warning_trigger  = $item['time_warning_fail_trigger'];
		$warning_time     = time() - ($item['time_warning_fail_length'] * $step);
		$warning_failures = db_fetch_cell("SELECT count(id) FROM plugin_thold_log WHERE threshold_id=" . $item['id'] . " AND status IN (" . ST_NOTIFYWA . "," . ST_TRIGGERW . ") AND time>" . $warning_time) + $failures;

		if ($breach_up || $breach_down) {
			$notify = false;

			thold_debug('Threshold Time Based check breached HI:' . $item['time_hi'] . ' LOW:' . $item['time_low'] . ' VALUE:'.$currentval);

			$item['thold_alert']      = ($breach_up ? STAT_HI:STAT_LO);
			$item['thold_fail_count'] = $failures;

			/* we should only re-alert X minutes after last email, not every 5 pollings, etc...
			   re-alert? */
			$realerttime   = ($item['repeat_alert']-1) * $step;
			$lastemailtime = db_fetch_cell("SELECT time
				FROM plugin_thold_log
				WHERE threshold_id=" . $item['id'] . "
				AND status IN (" . ST_NOTIFYRA . "," . ST_NOTIFYAL . ")
				ORDER BY time DESC
				LIMIT 1", FALSE);

			$ra = ($failures > $trigger && $item['repeat_alert'] && !empty($lastemailtime) && ($lastemailtime+$realerttime <= time()));

			$failures++;

			thold_debug("Alert Time:'$time', Alert Trigger:'$trigger', Alert Failures:'$failures', RealertTime:'$realerttime', LastTime:'$lastemailtime', RA:'$ra', Diff:'" . ($realerttime+$lastemailtime) . "'<'". time() . "'");


			if ($failures == $trigger || $ra) {
				$notify = true;
			}

			$subject = ($notify ? "ALERT: ":"TRIGGER: ") . $item['name'] . ($thold_show_datasource ? " [$name]" : '') . ' ' . ($failures > $trigger ? 'is still' : 'went') . ' ' . ($breach_up ? 'above' : 'below') . ' threshold of ' . ($breach_up ? $item['time_hi'] : $item['time_low']) . " with $currentval";

			if ($notify) {
				thold_debug('Alerting is necessary');

				if ($logset == 1) {
					logger($item['name'], ($failures > $trigger ? 'realert':'alert'), ($breach_up ? $item['time_hi'] : $item['time_low']), $currentval, $trigger, $failures, $url);
				}

				if (trim($alert_emails) != '') {
					thold_mail($alert_emails, '', $subject, $msg, $file_array);
				}

				if ($thold_snmp_traps) {
					$thold_snmp_data["eventClass"] = 3;
					$thold_snmp_data["eventSeverity"] = $item['snmp_event_severity'];
					$thold_snmp_data["eventStatus"] = $item['thold_alert']+1;
					$thold_snmp_data["eventRealertStatus"] = ($ra ? ($breach_up ? 3:2) :1);
					$thold_snmp_data["eventNotificationType"] = ($failures > $trigger ? ST_NOTIFYAL:ST_NOTIFYRA)+1;
					$thold_snmp_data["eventFailCount"] = $failures;
					$thold_snmp_data["eventFailCountTrigger"] = $trigger;

					$thold_snmp_data["eventDescription"] = str_replace('<FAIL_COUNT>', $thold_snmp_data["eventFailCount"], $thold_snmp_data["eventDescription"]);
					thold_snmptrap($thold_snmp_data);
				}

				thold_log(array(
					'type' => 2,
					'time' => time(),
					'host_id' => $item['host_id'],
					'graph_id' => $graph_id,
					'threshold_id' => $item['id'],
					'threshold_value' => ($breach_up ? $item['time_hi'] : $item['time_low']),
					'current' => $currentval,
					'status' => ($failures > $trigger ? ST_NOTIFYAL:ST_NOTIFYRA),
					'description' => $subject,
					'emails' => $alert_emails));
			} else {
				thold_log(array(
					'type' => 2,
					'time' => time(),
					'host_id' => $item['host_id'],
					'graph_id' => $graph_id,
					'threshold_id' => $item['id'],
					'threshold_value' => ($breach_up ? $item['time_hi'] : $item['time_low']),
					'current' => $currentval,
					'status' => ST_TRIGGERA,
					'description' => $subject,
					'emails' => $alert_emails));
			}

			db_execute("UPDATE thold_data
				SET thold_alert=" . $item['thold_alert'] . ",
				thold_fail_count=$failures
				WHERE rra_id=$rra_id AND data_id=" . $item['data_id']);
		} elseif ($warning_breach_up || $warning_breach_down) {
			$notify = false;

			$item['thold_alert'] = ($warning_breach_up ? STAT_HI:STAT_LO);
			$item['thold_warning_fail_count'] = $warning_failures;

			/* we should only re-alert X minutes after last email, not every 5 pollings, etc...
			   re-alert? */
			$realerttime   = ($item['time_warning_fail_length']-1) * $step;
			$lastemailtime = db_fetch_cell("SELECT time
				FROM plugin_thold_log
				WHERE threshold_id=" . $item['id'] . "
				AND status IN (" . ST_NOTIFYRA . "," . ST_NOTIFYWA . ")
				ORDER BY time DESC
				LIMIT 1", FALSE);

			$ra = ($warning_failures > $warning_trigger && $item['time_warning_fail_length'] && !empty($lastemailtime) && ($lastemailtime+$realerttime <= time()));

			$warning_failures++;

			thold_debug("Warn Time:'$warning_time', Warn Trigger:'$warning_trigger', Warn Failures:'$warning_failures', RealertTime:'$realerttime', LastTime:'$lastemailtime', RA:'$ra', Diff:'" . ($realerttime+$lastemailtime) . "'<'". time() . "'");

			if ($warning_failures == $warning_trigger || $ra) {
				$notify = true;;
			}

			$subject = ($notify ? "WARNING: ":"TRIGGER: ") . $item['name'] . ($thold_show_datasource ? " [$name]" : '') . ' ' . ($warning_failures > $warning_trigger ? 'is still' : 'went') . ' ' . ($warning_breach_up ? 'above' : 'below') . ' threshold of ' . ($warning_breach_up ? $item['time_warning_hi'] : $item['time_warning_low']) . " with $currentval";

			if ($notify) {
				if ($logset == 1) {
					logger($item['name'], ($warning_failures > $warning_trigger ? 'rewarning':'warning'), ($warning_breach_up ? $item['time_warning_hi'] : $item['time_warning_low']), $currentval, $warning_trigger, $warning_failures, $url);
				}

				if (trim($alert_emails) != '') {
					thold_mail($warning_emails, '', $subject, $warn_msg, $file_array);
				}

				if ($thold_snmp_traps && $thold_snmp_warning_traps) {
					$thold_snmp_data["eventClass"] = 2;
					$thold_snmp_data["eventSeverity"] = $item['snmp_event_warning_severity'];
					$thold_snmp_data["eventStatus"] = $item['thold_alert']+1;
					$thold_snmp_data["eventRealertStatus"] = ($ra ? ($warning_breach_up ? 3:2) :1);
					$thold_snmp_data["eventNotificationType"] = ($warning_failures > $warning_trigger ? ST_NOTIFYRA:ST_NOTIFYWA)+1;
					$thold_snmp_data["eventFailCount"] = $warning_failures;
					$thold_snmp_data["eventFailCountTrigger"] = $warning_trigger;

					$thold_snmp_data["eventDescription"] = str_replace('<FAIL_COUNT>', $thold_snmp_data["eventFailCount"], $thold_snmp_data["eventDescription"]);
					thold_snmptrap($thold_snmp_data);
				}

				thold_log(array(
					'type' => 2,
					'time' => time(),
					'host_id' => $item['host_id'],
					'graph_id' => $graph_id,
					'threshold_id' => $item['id'],
					'threshold_value' => ($breach_up ? $item['time_hi'] : $item['time_low']),
					'current' => $currentval,
					'status' => ($warning_failures > $warning_trigger ? ST_NOTIFYRA:ST_NOTIFYWA),
					'description' => $subject,
					'emails' => $alert_emails));
			} elseif ($alertstat != 0 && $warning_failures < $warning_trigger && $failures < $trigger) {
				$subject = "ALERT -> WARNING: ". $item['name'] . ($thold_show_datasource ? " [$name]" : '') . " restored to warning threshold with value $currentval";

				thold_log(array(
					'type' => 2,
					'time' => time(),
					'host_id' => $item['host_id'],
					'graph_id' => $graph_id,
					'threshold_id' => $item['id'],
					'threshold_value' => ($warning_breach_up ? $item['time_hi'] : $item['time_low']),
					'current' => $currentval,
					'status' => ST_NOTIFYAW,
					'description' => $subject,
					'emails' => $alert_emails));
			}else{
				thold_log(array(
					'type' => 2,
					'time' => time(),
					'host_id' => $item['host_id'],
					'graph_id' => $graph_id,
					'threshold_id' => $item['id'],
					'threshold_value' => ($warning_breach_up ? $item['time_hi'] : $item['time_low']),
					'current' => $currentval,
					'status' => ST_TRIGGERW,
					'description' => $subject,
					'emails' => $warning_emails));
			}

			db_execute("UPDATE thold_data
				SET thold_alert=" . $item['thold_alert'] . ",
				thold_warning_fail_count=$warning_failures,
				thold_fail_count=$failures
				WHERE rra_id=$rra_id AND data_id=" . $item['data_id']);
		} else {
			thold_debug('Threshold Time Based check is normal HI:' . $item['time_hi'] . ' LOW:' . $item['time_low'] . ' VALUE:'.$currentval);

			if ($alertstat != 0 && $warning_failures < $warning_trigger && $item['restored_alert'] != 'on') {
				if ($logset == 1) {
					logger($item['name'], 'ok', 0, $currentval, $warning_trigger, $item['thold_warning_fail_count'], $url);
				}

				$subject = "NORMAL: ". $item['name'] . ($thold_show_datasource ? " [$name]" : '') . " restored to normal threshold with value $currentval";

				if (trim($warning_emails) != '' && $item['restored_alert'] != 'on') {
					thold_mail($warning_emails, '', $subject, $msg, $file_array);
				}

				if ($thold_snmp_traps && $thold_snmp_normal_traps) {
					$thold_snmp_data["eventClass"] = 1;
					$thold_snmp_data["eventSeverity"] = 1;
					$thold_snmp_data["eventStatus"] = 1;
					$thold_snmp_data["eventNotificationType"] = ST_NOTIFYRS+1;
					thold_snmptrap($thold_snmp_data);
				}

				thold_log(array(
					'type' => 2,
					'time' => time(),
					'host_id' => $item['host_id'],
					'graph_id' => $graph_id,
					'threshold_id' => $item['id'],
					'threshold_value' => '',
					'current' => $currentval,
					'status' => ST_NOTIFYRS,
					'description' => $subject,
					'emails' => $warning_emails));

				db_execute("UPDATE thold_data
					SET thold_alert=0, thold_warning_fail_count=$warning_failures, thold_fail_count=$failures
					WHERE rra_id=$rra_id AND data_id=" . $item['data_id']);
			} elseif ($alertstat != 0 && $failures < $trigger && $item['restored_alert'] != 'on') {
				if ($logset == 1) {
					logger($item['name'], 'ok', 0, $currentval, $trigger, $item['thold_fail_count'], $url);
				}

				$subject = "NORMAL: ". $item['name'] . ($thold_show_datasource ? " [$name]" : '') . " restored to warning threshold with value $currentval";

				if (trim($alert_emails) != '' && $item['restored_alert'] != 'on') {
					thold_mail($alert_emails, '', $subject, $msg, $file_array);
				}

				if ($thold_snmp_traps && $thold_snmp_normal_traps) {
					$thold_snmp_data["eventClass"] = 1;
					$thold_snmp_data["eventSeverity"] = 1;
					$thold_snmp_data["eventStatus"] = 1;
					$thold_snmp_data["eventNotificationType"] = ST_NOTIFYRS+1;
					thold_snmptrap($thold_snmp_data);
				}

				thold_log(array(
					'type' => 2,
					'time' => time(),
					'host_id' => $item['host_id'],
					'graph_id' => $graph_id,
					'threshold_id' => $item['id'],
					'threshold_value' => '',
					'current' => $currentval,
					'status' => ST_NOTIFYRS,
					'description' => $subject,
					'emails' => $alert_emails));

				db_execute("UPDATE thold_data
					SET thold_alert=0, thold_warning_fail_count=$warning_failures, thold_fail_count=$failures
					WHERE rra_id=$rra_id AND data_id=" . $item['data_id']);
			} else {
				db_execute("UPDATE thold_data
					SET thold_fail_count=$failures,
					thold_warning_fail_count=$warning_failures
					WHERE rra_id=$rra_id AND data_id=" . $item['data_id']);
			}
		}

		break;
	}
}

function thold_format_number($value, $digits=5) {
	if ($value == '') {
		return '-';
	}elseif (strlen(round($value,0)) == strlen($value)) {
		return $value;
	} else {
		return rtrim(number_format($value, $digits), "0");
	}
}

function thold_format_name($template, $local_graph_id, $local_data_id, $data_source_name) {
	$desc = db_fetch_cell('SELECT name_cache
		FROM data_template_data
		WHERE local_data_id=' . $local_data_id . '
		LIMIT 1');

	if (substr_count($template["name"], '|')) {
		$gl = db_fetch_row("SELECT * FROM graph_local WHERE id=$local_graph_id");

		if (sizeof($gl)) {
			$name = expand_title($gl["host_id"], $gl["snmp_query_id"], $gl["snmp_index"], $template["name"]);
		} else {
			$name = $desc . ' [' . $data_source_name . ']';
		}
	} else {
		$name = $desc . ' [' . $data_source_name . ']';
	}

	return $name;
}

function get_reference_types($rra = 0, $step = 300) {
	if ($step == 60) {
		$timearray = array(
			1 => '1 Minute',
			2 => '2 Minutes',
			3 => '3 Minutes',
			4 => '4 Minutes',
			5 => '5 Minutes',
			6 => '6 Minutes',
			7 => '7 Minutes',
			8 => '8 Minutes',
			9 => '9 Minutes',
			10 => '10 Minutes',
			12 => '12 Minutes',
			15 => '15 Minutes',
			20 => '20 Minutes',
			24 => '24 Minutes',
			30 => '30 Minutes',
			45 => '45 Minutes',
			60 => '1 Hour',
			120 => '2 Hours',
			180 => '3 Hours',
			240 => '4 Hours',
			288 => '4.8 Hours',
			360 => '6 Hours',
			480 => '8 Hours',
			720 => '12 Hours',
			1440 => '1 Day',
			2880 => '2 Days',
			10080 => '1 Week',
			20160 => '2 Weeks',
			43200 => '1 Month'
		);
	} else if ($step == 300) {
		$timearray = array(
			1 => '5 Minutes',
			2 => '10 Minutes',
			3 => '15 Minutes',
			4 => '20 Minutes',
			6 => '30 Minutes',
			8 => '45 Minutes',
			12 => 'Hour',
			24 => '2 Hours',
			36 => '3 Hours',
			48 => '4 Hours',
			72 => '6 Hours',
			96 => '8 Hours',
			144 => '12 Hours',
			288 => '1 Day',
			576 => '2 Days',
			2016 => '1 Week',
			4032 => '2 Weeks',
			8640 => '1 Month'
		);
	} else {
		$timearray = array(
			1 => '1 Polling',
			2 => '2 Pollings',
			3 => '3 Pollings',
			4 => '4 Pollings',
			5 => '5 Pollings',
			6 => '6 Pollings',
			8 => '8 Pollings',
			12 => '12 Pollings',
			24 => '24 Pollings',
			36 => '36 Pollings',
			48 => '48 Pollings',
			72 => '72 Pollings',
			96 => '96 Pollings',
			144 => '144 Pollings',
			288 => '288 Pollings',
			576 => '576 Pollings',
			2016 => '2016 Pollings'
		);
	}

	$rra_steps = db_fetch_assoc("SELECT DISTINCT rra.steps
		FROM data_template_data d
		JOIN data_template_data_rra a
		ON d.id=a.data_template_data_id
		JOIN rra
		ON a.rra_id=rra.id
		WHERE rra.steps>1 " .
		($rra > 0 ? "AND d.local_data_id=$rra":"") . "
		ORDER BY steps");

	$reference_types = array();
	if (sizeof($rra_steps)) {
	foreach($rra_steps as $rra_step) {
		$seconds = $step * $rra_step['steps'];
		if (isset($timearray[$rra_step['steps']])) {
			$reference_types[$seconds] = $timearray[$rra_step['steps']] . " Average" ;
		}
	}
	}

	return $reference_types;
}

function thold_request_check_changed($request, $session) {
	if ((isset($_REQUEST[$request])) && (isset($_SESSION[$session]))) {
		if ($_REQUEST[$request] != $_SESSION[$session]) {
			return 1;
		}
	}
}

function logger($desc, $breach_up, $threshld, $currentval, $trigger, $triggerct, $urlbreach) {
	$syslog_level = read_config_option('thold_syslog_level');
	$syslog_facility = read_config_option('thold_syslog_facility');
	if (!isset($syslog_level)) {
		$syslog_level = LOG_WARNING;
	} else if (isset($syslog_level) && ($syslog_level > 7 || $syslog_level < 0)) {
		$syslog_level = LOG_WARNING;
	}
	if (!isset($syslog_facility)) {
		$syslog_facility = LOG_DAEMON;
	}

	openlog('CactiTholdLog', LOG_PID | LOG_PERROR, $syslog_facility);

	if (strval($breach_up) == 'ok') {
		syslog($syslog_level, $desc . ' restored to normal with ' . $currentval . ' at trigger ' . $trigger . ' out of ' . $triggerct . " - ". $urlbreach);
	} else {
		syslog($syslog_level, $desc . ' went ' . ($breach_up ? 'above' : 'below') . ' threshold of ' . $threshld . ' with ' . $currentval . ' at trigger ' . $trigger . ' out of ' . $triggerct . " - ". $urlbreach);
	}
}

function thold_cdef_get_usable () {
	$cdef_items = db_fetch_assoc("select * from cdef_items where value = 'CURRENT_DATA_SOURCE' order by cdef_id");
	$cdef_usable = array();
	if (sizeof($cdef_items)) {
		foreach ($cdef_items as $cdef_item) {
				$cdef_usable[] =  $cdef_item['cdef_id'];
		}
	}

	return $cdef_usable;
}

function thold_cdef_select_usable_names () {
	$ids = thold_cdef_get_usable();
	$cdefs = db_fetch_assoc('select id, name from cdef');
	$cdef_names[0] = '';
	if (sizeof($cdefs)) {
		foreach ($cdefs as $cdef) {
			if (in_array($cdef['id'], $ids)) {
				$cdef_names[$cdef['id']] =  $cdef['name'];
			}
		}
	}
	return $cdef_names;
}

function thold_build_cdef (&$cdefs, $value, $rra, $ds) {
	$oldvalue = $value;

	if (sizeof($cdefs)) {
	foreach ($cdefs as $cdef) {
		if ($cdef['type'] == 4) {
			$cdef['type'] = 6;

			switch ($cdef['value']) {
			case 'CURRENT_DATA_SOURCE':
				$cdef['value'] = $oldvalue; // get_current_value($rra, $ds, 0);
				break;
			case 'CURRENT_GRAPH_MAXIMUM_VALUE':
				$cdef['value'] = get_current_value($rra, 'upper_limit', 0);
				break;
			case 'CURRENT_GRAPH_MINIMUM_VALUE':
				$cdef['value'] = get_current_value($rra, 'lower_limit', 0);
				break;
			case 'CURRENT_DS_MINIMUM_VALUE':
				$cdef['value'] = get_current_value($rra, 'rrd_minimum', 0);
				break;
			case 'CURRENT_DS_MAXIMUM_VALUE':
				$cdef['value'] = get_current_value($rra, 'rrd_maximum', 0);
				break;
			case 'VALUE_OF_HDD_TOTAL':
				$cdef['value'] = get_current_value($rra, 'hdd_total', 0);
				break;
			case 'ALL_DATA_SOURCES_NODUPS': // you can't have DUPs in a single data source, really...
			case 'ALL_DATA_SOURCES_DUPS':
				$cdef['value'] = 0;
				$all_dsns = array();
				$all_dsns = db_fetch_assoc("SELECT data_source_name FROM data_template_rrd WHERE local_data_id = $rra");
				if (is_array($all_dsns)) {
					foreach ($all_dsns as $dsn) {
						$cdef['value'] += get_current_value($rra, $dsn['data_source_name'], 0);
					}
				}
				break;
			default:
				print 'CDEF property not implemented yet: ' . $cdef['value'];
				return $oldvalue;
				break;
			}
		} elseif ($cdef['type'] == 6) {
			$regresult = preg_match('/^\|query_([A-Za-z0-9_]+)\|$/', $cdef['value'], $matches);

			if ($regresult > 0) {
			
					$sql_query = "SELECT `host_snmp_cache`.`field_value` FROM `data_local` INNER JOIN `host_snmp_cache` ON 
							(
								`host_snmp_cache`.`host_id` = `data_local`.`host_id`
									AND
								`host_snmp_cache`.`snmp_query_id` = `data_local`.`snmp_query_id`
									AND 
								`host_snmp_cache`.`snmp_index` = `data_local`.`snmp_index`
							) 
							WHERE `data_local`.`id` = $rra AND `host_snmp_cache`.`field_name` = '" . $matches[1] . "'";
					
					$cdef['value'] = db_fetch_cell($sql_query);
			}
		}
		$cdef_array[] = $cdef;
	}
	}

	$x = count($cdef_array);

	if ($x == 0) return $oldvalue;

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
			$result = thold_rpn($v2['value'], $v1['value'], $cdef_array[$cursor]['value']);
			// put the result back on the stack.
			array_push($stack, array('type'=>6,'value'=>$result));

			break;
		default:
			print 'Unknown RPN type: ';
			print $cdef_array[$cursor]['type'];
			return($oldvalue);

			break;
		}

		$cursor++;
	}

	return $stack[0]['value'];
}

function thold_rpn ($x, $y, $z) {
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

function delete_old_thresholds () {
	$result = db_fetch_assoc('SELECT a.id, data_id, rra_id FROM thold_data a  LEFT JOIN data_template_rrd b  ON (b.id=a.data_id) WHERE data_source_name is null');
	if (sizeof($result)) {
		foreach ($result as $row) {
			db_execute('DELETE FROM thold_data WHERE id=' . $row['id']);
			db_execute('DELETE FROM plugin_thold_threshold_contact WHERE thold_id=' . $row['id']);
		}
	}
}

function thold_rrd_last($rra) {
	global $config;

	$last_time_entry = @rrdtool_execute('last ' . trim(get_data_source_path($rra, true)), false, RRDTOOL_OUTPUT_STDOUT);

	return trim($last_time_entry);
}

function get_current_value($rra, $ds, $cdef = 0) {
	global $config;

	/* get the information to populate into the rrd files */
	if (function_exists("boost_check_correct_enabled") && boost_check_correct_enabled()) {
		boost_process_poller_output(TRUE, $rra);
	}

	$last_time_entry = thold_rrd_last($rra);

	// This should fix and 'did you really mean month 899 errors', this is because your RRD has not polled yet
	if ($last_time_entry == -1) {
		$last_time_entry = time();
	}

	$data_template_data = db_fetch_row("SELECT * FROM data_template_data WHERE local_data_id=$rra");

	$step = $data_template_data['rrd_step'];

	// Round down to the nearest 100
	$last_time_entry = (intval($last_time_entry /100) * 100) - $step;
	$last_needed = $last_time_entry + $step;

	$result = rrdtool_function_fetch($rra, trim($last_time_entry), trim($last_needed));

	// Return Blank if the data source is not found (Newly created?)
	if (!isset( $result['data_source_names'])) return '';

	$idx = array_search($ds, $result['data_source_names']);

	// Return Blank if the value was not found (Cache Cleared?)
	if (!isset($result['values'][$idx][0])) {
		return '';
	}

	$value = $result['values'][$idx][0];
	if ($cdef != 0) {
		$value = thold_build_cdef($cdef, $value, $rra, $ds);
	}

	return round($value, 4);
}

function thold_get_ref_value($rra_id, $ds, $ref_time, $time_range) {
	global $config;

	$result = rrdtool_function_fetch($rra_id, $ref_time-$time_range, $ref_time-1, $time_range);

	$idx = array_search($ds, $result['data_source_names']);
	if (count($result['values'][$idx]) == 0) {
		return false;
	}

	return $result['values'][$idx];
}

/* thold_check_exception_periods
 @to-do: This function should check 'globally' declared exceptions, like
 holidays etc., as well as exceptions bound to the speciffic $rra_id. $rra_id
 should inherit exceptions that are assigned on the higher level (i.e. device).

*/
function thold_check_exception_periods($rra_id, $ref_time, $ref_range) {
	// TO-DO
	// Check if the reference time falls into global exceptions
	// Check if the current time falls into global exceptions
	// Check if $rra_id + $ds have an exception (again both reference time and current time)
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

/* thold_check_baseline -
 Should be called after hard limits have been checked and only when they are OK

 The function "goes back in time" $ref_time seconds and retrieves the data
 for $ref_range seconds. Then it finds minimum and maximum values and calculates
 allowed deviations from those values.

 @arg $rra_id - the data source to check the data
 @arg $ds - Index of the data_source in the RRD
 @arg $ref_time - Integer value representing reference offset in seconds
 @arg $ref_range - Integer value indicating reference time range in seconds
 @arg $current_value - Current "value" of the data source
 @arg $pct_down - Allowed baseline deviation in % - if set to false will not be considered
 @arg $pct_up - Allowed baseline deviation in % - if set to false will not be considered

 @returns (integer) - integer value that indicates status
   -2 if the exception is active
   -1 if the reference value is not available
   0 if the current value is within the boundaries
   1 if the current value is below the calculated threshold
   2 if the current value is above the calculated threshold
 */
function thold_check_baseline($rra_id, $ds, $current_value, &$item) {
	global $debug;

	$now = time();

	// See if we have a valid cached thold_high and thold_low value
	if ($item['bl_thold_valid'] && $now < $item['bl_thold_valid']) {
		if ($item['thold_hi'] && $current_value > $item['thold_hi']) {
			$failed = 2;
		} elseif ($item['thold_low'] && $current_value < $item['thold_low']) {
			$failed = 1;
		} else {
			$failed= 0;
		}
	} else {
		$midnight =  gmmktime(0,0,0);
		$t0 = $midnight + floor(($now - $midnight) / $item['bl_ref_time_range']) * $item['bl_ref_time_range'];

		$ref_values = thold_get_ref_value($rra_id, $ds, $t0, $item['bl_ref_time_range']);

		if (!is_array($ref_values) || sizeof($ref_values) == 0) {
			$item['thold_low'] = '';
			$item['thold_hi'] = '';
			$item['bl_thold_valid'] = $now;
			$returnvalue=-1;
			return $returnvalue; // Baseline reference value not yet established
		}

		$ref_value = $ref_values[0];
		if ($item['cdef'] != 0) {
			$ref_value = thold_build_cdef($item['cdef'], $ref_value, $item['rra_id'], $item['data_id']);
		}

		$blt_low  = '';
		$blt_high = '';

		if ($item['bl_pct_down'] != '') {
			$blt_low  = round($ref_value - abs($ref_value * $item['bl_pct_down'] / 100),2);
		}

		if ($item['bl_pct_up'] != '') {
			$blt_high = round($ref_value + abs($ref_value * $item['bl_pct_up'] / 100),2);
		}

		// Cache the calculated or empty values
		$item['thold_low'] = $blt_low;
		$item['thold_hi']  = $blt_high;
		$item['bl_thold_valid'] = $t0 + $item['bl_ref_time_range'];

		$failed = 0;

		// Check low boundary
		if ($blt_low != '' && $current_value < $blt_low) {
			$failed = 1;
		}

		// Check up boundary
		if ($failed == 0 && $blt_high != '' && $current_value > $blt_high) {
			$failed = 2;
		}
	}

	if ($debug) {
		echo "RRA: $rra_id : $ds\n";
		echo 'Ref. values count: ' . (isset($ref_values) ? count($ref_values):"N/A") . "\n";
		echo "Ref. value (min): " . (isset($ref_value_min) ? $ref_value_min:"N/A") . "\n";
		echo "Ref. value (max): " . (isset($ref_value_max) ? $ref_value_max:"N/A") . "\n";
		echo "Cur. value: $current_value\n";
		echo "Low bl thresh: " . (isset($blt_low) ? $blt_low:"N/A") . "\n";
		echo "High bl thresh: " . (isset($blt_high) ? $blt_high:"N/A") . "\n";
		echo 'Check against baseline: ';
		switch($failed) {
			case 0:
			echo 'OK';
			break;

			case 1:
			echo 'FAIL: Below baseline threshold!';
			break;

			case 2:
			echo 'FAIL: Above baseline threshold!';
			break;
		}
		echo "\n";
		echo "------------------\n";
	}

	return $failed;
}

function save_thold() {
	global $rra, $banner, $hostid;

	$template_enabled = isset($_POST['template_enabled']) && $_POST['template_enabled'] == 'on' ? $_POST['template_enabled'] : 'off';
	if ($template_enabled == 'on') {
		input_validate_input_number($_POST['rra']);
		input_validate_input_number($_POST['data_template_rrd_id']);

		$rra_id = $_POST['rra'];
		if (!thold_user_auth_threshold ($rra_id)) {
			$banner = '<font color=red><strong>Permission Denied</strong></font>';
			return;
		}
		$data_id = $_POST['data_template_rrd_id'];
		$data = db_fetch_row("SELECT id, template FROM thold_data WHERE rra_id = $rra_id AND data_id = $data_id");
		thold_template_update_threshold ($data['id'], $data['template']);
		$banner = '<font color=green><strong>Record Updated</strong></font>';
		plugin_thold_log_changes($data['id'], 'modified', array('id' => $data['id'], 'template_enabled' => 'on'));
		return true;
	}

	/* Make sure this is defined */
	$_POST['thold_enabled'] = isset($_POST['thold_enabled']) ? 'on' : 'off';
	$_POST['template_enabled'] = isset($_POST['template_enabled']) ? 'on' : 'off';

	/* Do Some error Checks */
	$banner = '<font color=red><strong>';
	if (($_POST['thold_type'] == 0 && (!isset($_POST['thold_hi']) || trim($_POST['thold_hi']) == '')) &&
		($_POST['thold_type'] == 0 && (!isset($_POST['thold_low']) || trim($_POST['thold_low']) == ''))) {
		$banner .= 'You must specify either &quot;High Threshold&quot; or &quot;Low Threshold&quot; or both!<br>RECORD NOT UPDATED!</strong></font>';
		return;
	}

	//if (($_POST['thold_type'] == 0) && (isset($_POST['thold_hi'])) &&
	//	(isset($_POST['thold_low'])) && (trim($_POST['thold_hi']) != '') &&
	//	(trim($_POST['thold_low']) != '') && (round($_POST['thold_low'],4) >= round($_POST['thold_hi'],4))) {
	//	$banner .= 'Impossible thresholds: &quot;High Threshold&quot; smaller than or equal to &quot;Low Threshold&quot;<br>RECORD NOT UPDATED!</strong></font>';
	//	return;
	//}

	if ($_POST['thold_type'] == 1) {
		$banner .= 'With baseline thresholds enabled ';
		if (!thold_mandatory_field_ok('bl_ref_time_range', 'Time reference in the past')) {
			return;
		}
		if ((!isset($_POST['bl_pct_down']) || trim($_POST['bl_pct_down']) == '') && (!isset($_POST['bl_pct_up']) || trim($_POST['bl_pct_up']) == '')) {
			$banner .= 'You must specify either &quot;Baseline Deviation UP&quot; or &quot;Baseline Deviation DOWN&quot; or both!<br>RECORD NOT UPDATED!</strong></font>';
			return;
		}
	}

	$existing = db_fetch_assoc('SELECT id FROM thold_data WHERE rra_id = ' . $rra . ' AND data_id = ' . $_POST['data_template_rrd_id']);
	$save = array();
	if (count($existing)) {
		$save['id'] = $existing[0]['id'];
	} else {
		$save['id'] = 0;
		$save['template'] = '';
	}

	input_validate_input_number(get_request_var_post('thold_hi'));
	input_validate_input_number(get_request_var_post('thold_low'));
	input_validate_input_number(get_request_var_post('thold_fail_trigger'));
	input_validate_input_number(get_request_var_post('thold_warning_hi'));
	input_validate_input_number(get_request_var_post('thold_warning_low'));
	input_validate_input_number(get_request_var_post('thold_warning_fail_trigger'));
	input_validate_input_number(get_request_var_post('repeat_alert'));
	input_validate_input_number(get_request_var_post('cdef'));
	input_validate_input_number($_POST['rra']);
	input_validate_input_number($_POST['data_template_rrd_id']);
	input_validate_input_number(get_request_var_post('thold_type'));
	input_validate_input_number(get_request_var_post('time_hi'));
	input_validate_input_number(get_request_var_post('time_low'));
	input_validate_input_number(get_request_var_post('time_fail_trigger'));
	input_validate_input_number(get_request_var_post('time_fail_length'));
	input_validate_input_number(get_request_var_post('time_warning_hi'));
	input_validate_input_number(get_request_var_post('time_warning_low'));
	input_validate_input_number(get_request_var_post('time_warning_fail_trigger'));
	input_validate_input_number(get_request_var_post('time_warning_fail_length'));
	input_validate_input_number(get_request_var_post('data_type'));
	input_validate_input_number(get_request_var_post('notify_warning'));
	input_validate_input_number(get_request_var_post('notify_alert'));
	input_validate_input_number(get_request_var_post('bl_ref_time_range'));
	input_validate_input_number(get_request_var_post('bl_pct_down'));
	input_validate_input_number(get_request_var_post('bl_pct_up'));
	input_validate_input_number(get_request_var_post('bl_fail_trigger'));

	if (isset($_POST['snmp_event_category'])) {
		$_POST['snmp_event_category'] = trim(str_replace(array("\\", "'", '"'), '', get_request_var_post('snmp_event_category')));
	}
	if (isset($_POST['snmp_event_severity'])) {
		input_validate_input_number(get_request_var_post('snmp_event_severity'));
	}
	if (isset($_POST['snmp_event_warning_severity'])) {
		input_validate_input_number(get_request_var_post('snmp_event_warning_severity'));
	}

	$_POST['name']          = str_replace(array("\\", '"', "'"), '', $_POST['name']);
	$save['name']           = (trim($_POST['name'])) == '' ? '' : $_POST['name'];
	$save['host_id']        = $hostid;
	$save['data_id']        = $_POST['data_template_rrd_id'];
	$save['rra_id']         = $_POST['rra'];
	$save['thold_enabled']  = isset($_POST['thold_enabled']) ? $_POST['thold_enabled'] : '';
	$save['exempt']         = isset($_POST['exempt']) ? $_POST['exempt'] : 'off';
	$save['restored_alert'] = isset($_POST['restored_alert']) ? $_POST['restored_alert'] : 'off';
	$save['thold_type']     = $_POST['thold_type'];
	// High / Low
	$save['thold_hi']           = (trim($_POST['thold_hi'])) == '' ? '' : round($_POST['thold_hi'],4);
	$save['thold_low']          = (trim($_POST['thold_low'])) == '' ? '' : round($_POST['thold_low'],4);
	$save['thold_fail_trigger'] = (trim($_POST['thold_fail_trigger'])) == '' ? read_config_option('alert_trigger') : $_POST['thold_fail_trigger'];
	// Time Based
	$save['time_hi']           = (trim($_POST['time_hi'])) == '' ? '' : round($_POST['time_hi'],4);
	$save['time_low']          = (trim($_POST['time_low'])) == '' ? '' : round($_POST['time_low'],4);
	$save['time_fail_trigger'] = (trim($_POST['time_fail_trigger'])) == '' ? read_config_option('thold_warning_time_fail_trigger') : $_POST['time_fail_trigger'];
	$save['time_fail_length']  = (trim($_POST['time_fail_length'])) == '' ? (read_config_option('thold_warning_time_fail_length') > 0 ? read_config_option('thold_warning_time_fail_length') : 1) : $_POST['time_fail_length'];
	// Warning High / Low
	$save['thold_warning_hi']  = (trim($_POST['thold_warning_hi'])) == '' ? '' : round($_POST['thold_warning_hi'],4);
	$save['thold_warning_low'] = (trim($_POST['thold_warning_low'])) == '' ? '' : round($_POST['thold_warning_low'],4);
	$save['thold_warning_fail_trigger'] = (trim($_POST['thold_warning_fail_trigger'])) == '' ? read_config_option('alert_trigger') : $_POST['thold_warning_fail_trigger'];
	// Warning Time Based
	$save['time_warning_hi']  = (trim($_POST['time_warning_hi'])) == '' ? '' : round($_POST['time_warning_hi'],4);
	$save['time_warning_low'] = (trim($_POST['time_warning_low'])) == '' ? '' : round($_POST['time_warning_low'],4);
	$save['time_warning_fail_trigger'] = (trim($_POST['time_warning_fail_trigger'])) == '' ? read_config_option('thold_warning_time_fail_trigger') : $_POST['time_warning_fail_trigger'];
	$save['time_warning_fail_length']  = (trim($_POST['time_warning_fail_length'])) == '' ? (read_config_option('thold_warning_time_fail_length') > 0 ? read_config_option('thold_warning_time_fail_length') : 1) : $_POST['time_warning_fail_length'];
	// Baseline
	$save['bl_thold_valid'] = '0';
	$save['bl_ref_time_range'] = (trim($_POST['bl_ref_time_range'])) == '' ? read_config_option('alert_bl_timerange_def') : $_POST['bl_ref_time_range'];
	$save['bl_pct_down'] = (trim($_POST['bl_pct_down'])) == '' ? '' : $_POST['bl_pct_down'];
	$save['bl_pct_up'] = (trim($_POST['bl_pct_up'])) == '' ? '' : $_POST['bl_pct_up'];
	$save['bl_fail_trigger'] = (trim($_POST['bl_fail_trigger'])) == '' ? read_config_option("alert_bl_trigger") : $_POST['bl_fail_trigger'];

	$save['repeat_alert'] = (trim($_POST['repeat_alert'])) == '' ? '' : $_POST['repeat_alert'];
	$save['notify_extra'] = (trim($_POST['notify_extra'])) == '' ? '' : $_POST['notify_extra'];
	$save['notify_warning_extra'] = (trim($_POST['notify_warning_extra'])) == '' ? '' : $_POST['notify_warning_extra'];
	$save['notify_warning'] = $_POST['notify_warning'];
	$save['notify_alert']   = $_POST['notify_alert'];
	$save['cdef']           = (trim($_POST['cdef'])) == '' ? '' : $_POST['cdef'];
	$save['template_enabled'] = $_POST['template_enabled'];

	$save['snmp_event_category'] = isset($_POST['snmp_event_category']) ? $_POST['snmp_event_category'] : '';
	$save['snmp_event_severity'] = isset($_POST['snmp_event_severity']) ? $_POST['snmp_event_severity'] : 4;
	$save['snmp_event_warning_severity'] = isset($_POST['snmp_event_warning_severity']) ? $_POST['snmp_event_warning_severity'] : 3;

	$save['data_type'] = $_POST['data_type'];
	if (isset($_POST['percent_ds'])) {
		$save['percent_ds'] = $_POST['percent_ds'];
	} else {
		$save['percent_ds'] = '';
	}

	if (isset($_POST['expression'])) {
		$save['expression'] = $_POST['expression'];
	} else {
		$save['expression'] = '';
	}

	/* Get the Data Template, Graph Template, and Graph */
	$rrdsql = db_fetch_row('SELECT id, data_template_id FROM data_template_rrd WHERE local_data_id=' . $save['rra_id'] . ' ORDER BY id');
	$rrdlookup = $rrdsql['id'];
	$grapharr = db_fetch_row("SELECT local_graph_id, graph_template_id FROM graph_templates_item WHERE task_item_id=$rrdlookup and local_graph_id <> '' LIMIT 1");

	$save['graph_id']       = $grapharr['local_graph_id'];
	$save['graph_template'] = $grapharr['graph_template_id'];
	$save['data_template']  = $rrdsql['data_template_id'];

	if (!thold_user_auth_threshold ($save['rra_id'])) {
		$banner = '<font color=red><strong>Permission Denied</strong></font>';
		return;
	}

	$id = sql_save($save , 'thold_data');

	if (isset($_POST['notify_accounts']) && is_array($_POST['notify_accounts'])) {
		thold_save_threshold_contacts ($id, $_POST['notify_accounts']);
	} elseif (!isset($_POST['notify_accounts'])) {
		thold_save_threshold_contacts ($id, array());
	}

	if ($id) {
		plugin_thold_log_changes($id, 'modified', $save);
		$thold = db_fetch_row("SELECT * FROM thold_data WHERE id=$id");
		$ds = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_id']);

		if ($thold["thold_type"] == 1) {
			thold_check_threshold ($thold['rra_id'], $thold['data_id'], $ds, $thold['lastread'], $thold['cdef']);
		}
	}

	$banner = '<font color=green><strong>Record Updated</strong></font>';
}

function thold_save_template_contacts ($id, $contacts) {
	db_execute('DELETE FROM plugin_thold_template_contact WHERE template_id = ' . $id);
	// ADD SOME SECURITY!!
	if (!empty($contacts)) {
		foreach ($contacts as $contact) {
			db_execute("INSERT INTO plugin_thold_template_contact (template_id, contact_id) VALUES ($id, $contact)");
		}
	}
}

function thold_save_threshold_contacts ($id, $contacts) {
	db_execute('DELETE FROM plugin_thold_threshold_contact WHERE thold_id = ' . $id);
	// ADD SOME SECURITY!!
	foreach ($contacts as $contact) {
		db_execute("INSERT INTO plugin_thold_threshold_contact (thold_id, contact_id) VALUES ($id, $contact)");
	}
}

function thold_mandatory_field_ok($name, $friendly_name) {
	global $banner;
	if (!isset($_POST[$name]) || (isset($_POST[$name]) && (trim($_POST[$name]) == '' || $_POST[$name] <= 0))) {
		$banner .= '&quot;' . $friendly_name . '&quot; must be set to positive integer value!<br>RECORD NOT UPDATED!</strong></font>';
		return false;
	}
	return true;
}

// Create tholds for all possible data elements for a host
function autocreate($hostid) {
	$c = 0;
	$message = '';

	$rralist = db_fetch_assoc("SELECT id, data_template_id FROM data_local where host_id='$hostid'");

	if (!count($rralist)) {
		$_SESSION['thold_message'] = '<font size=-2>No thresholds were created.</font>';
		return 0;
	}

	foreach ($rralist as $row) {
		$local_data_id = $row['id'];
		$data_template_id = $row['data_template_id'];
		$existing = db_fetch_assoc('SELECT id FROM thold_data WHERE rra_id = ' . $local_data_id . ' AND data_id = ' . $data_template_id);
		$template = db_fetch_assoc('SELECT * FROM thold_template WHERE data_template_id = ' . $data_template_id);

		if (count($existing) == 0 && count($template)) {
			$rrdlookup = db_fetch_cell("SELECT id FROM data_template_rrd WHERE local_data_id=$local_data_id order by id LIMIT 1");

			$grapharr = db_fetch_row("SELECT local_graph_id, graph_template_id FROM graph_templates_item WHERE task_item_id=$rrdlookup and local_graph_id <> '' LIMIT 1");
			$graph = (isset($grapharr['local_graph_id']) ? $grapharr['local_graph_id'] : '');

			if ($graph) {
				for ($y = 0; $y < count($template); $y++) {
					$data_source_name = $template[$y]['data_source_name'];
					$insert = array();

					$desc = db_fetch_cell('SELECT name_cache FROM data_template_data WHERE local_data_id=' . $local_data_id . ' LIMIT 1');

					$insert['name']               = $desc . ' [' . $data_source_name . ']';
					$insert['host_id']            = $hostid;
					$insert['rra_id']             = $local_data_id;
					$insert['graph_id']           = $graph;
					$insert['data_template']      = $data_template_id;
					$insert['graph_template']     = $grapharr['graph_template_id'];
					$insert['thold_warning_hi'] = $template[$y]['thold_warning_hi'];
					$insert['thold_warning_low'] = $template[$y]['thold_warning_low'];
					$insert['thold_warning_fail_trigger'] = $template[$y]['thold_warning_fail_trigger'];
					$insert['thold_hi']           = $template[$y]['thold_hi'];
					$insert['thold_low']          = $template[$y]['thold_low'];
					$insert['thold_fail_trigger'] = $template[$y]['thold_fail_trigger'];
					$insert['thold_enabled']      = $template[$y]['thold_enabled'];
					$insert['bl_ref_time_range']  = $template[$y]['bl_ref_time_range'];
					$insert['bl_pct_down']        = $template[$y]['bl_pct_down'];
					$insert['bl_pct_up']          = $template[$y]['bl_pct_up'];
					$insert['bl_fail_trigger']    = $template[$y]['bl_fail_trigger'];
					$insert['bl_alert']           = $template[$y]['bl_alert'];
					$insert['repeat_alert']       = $template[$y]['repeat_alert'];
					$insert['notify_extra']       = $template[$y]['notify_extra'];
					$insert['notify_warning_extra'] = $template[$y]['notify_warning_extra'];
					$insert['notify_warning']     = $template[$y]['notify_warning'];
					$insert['notify_alert']       = $template[$y]['notify_alert'];
					$insert['cdef']               = $template[$y]['cdef'];
					$insert['template']           = $template[$y]['id'];
					$insert['template_enabled']   = 'on';

					$rrdlist = db_fetch_assoc("SELECT id, data_input_field_id FROM data_template_rrd where local_data_id='$local_data_id' and data_source_name = '$data_source_name'");

					$int = array('id', 'data_template_id', 'data_source_id', 'thold_fail_trigger', 'bl_ref_time_range', 'bl_pct_down', 'bl_pct_up', 'bl_fail_trigger', 'bl_alert', 'repeat_alert', 'cdef');
					foreach ($rrdlist as $rrdrow) {
						$data_rrd_id=$rrdrow['id'];
						$insert['data_id'] = $data_rrd_id;
						$existing = db_fetch_assoc("SELECT id FROM thold_data WHERE rra_id='$local_data_id' AND data_id='$data_rrd_id'");
						if (count($existing) == 0) {
							$insert['id'] = 0;
							$id = sql_save($insert, 'thold_data');
							if ($id) {
								thold_template_update_threshold ($id, $insert['template']);

								$l = db_fetch_assoc("SELECT name FROM data_template where id=$data_template_id");
								$tname = $l[0]['name'];

								$name = $data_source_name;
								if ($rrdrow['data_input_field_id'] != 0) {
									$l = db_fetch_assoc('SELECT name FROM data_input_fields where id=' . $rrdrow['data_input_field_id']);
									$name = $l[0]['name'];
								}
								plugin_thold_log_changes($id, 'auto_created', " $tname [$name]");
								$message .= "Created threshold for the Graph '<i>$tname</i>' using the Data Source '<i>$name</i>'<br>";
								$c++;
							}
						} else {
							foreach($existing as $r) {
								$id = $r['id'];
								$l = db_fetch_assoc("SELECT name FROM thold_data WHERE id=$id");
								$name = $l[0]['name'];
								if ($name != $insert['name']) {
									db_execute("UPDATE thold_data SET name = '" . $insert['name'] . "' WHERE id=$id");
									plugin_thold_log_changes($id, "updated_name: $name => " . $insert['name']);
									$message .= "Updated threshold $id: changed name from '<i>$name</i>' to '<i>" . $insert['name'] . "</i>'<br>";
									$c++;
								}
							}
						}
					}
				}
			}
		}
	}
	$_SESSION['thold_message'] = "<font size=-2>$message</font>";
	return $c;
}

/* Sends a group of graphs to a user */
function thold_mail($to, $from, $subject, $message, $filename, $headers = '') {
	global $config;
	thold_debug('Preparing to send email');
	include_once($config['base_path'] . '/plugins/settings/include/mailer.php');
	include_once($config['base_path'] . '/plugins/thold/setup.php');

	$subject = trim($subject);

	$message = str_replace('<SUBJECT>', $subject, $message);

	$how = read_config_option('settings_how');
	if ($how < 0 && $how > 2)
		$how = 0;
	if ($how == 0) {
		$Mailer = new Mailer(array(
			'Type' => 'PHP'));
	} else if ($how == 1) {
		$sendmail = read_config_option('settings_sendmail_path');
		$Mailer = new Mailer(array(
			'Type' => 'DirectInject',
			'DirectInject_Path' => $sendmail));
	} else if ($how == 2) {
		$smtp_host = read_config_option('settings_smtp_host');
		$smtp_port = read_config_option('settings_smtp_port');
		$smtp_username = read_config_option('settings_smtp_username');
		$smtp_password = read_config_option('settings_smtp_password');

		$Mailer = new Mailer(array(
			'Type' => 'SMTP',
			'SMTP_Host' => $smtp_host,
			'SMTP_Port' => $smtp_port,
			'SMTP_Username' => $smtp_username,
			'SMTP_Password' => $smtp_password));
	}

	if ($from == '') {
		$from = read_config_option('thold_from_email');
		$fromname = read_config_option('thold_from_name');
		if ($from == '') {
			if (isset($_SERVER['HOSTNAME'])) {
				$from = 'Cacti@' . $_SERVER['HOSTNAME'];
			} else {
				$from = 'Cacti@localhost';
			}
		}
		if ($fromname == '')
			$fromname = 'Cacti'; $from = $Mailer->email_format($fromname, $from);
		if ($Mailer->header_set('From', $from) === false) {
			print 'ERROR: ' . $Mailer->error() . "\n";
			return $Mailer->error();
		}
	} else {
		$from = $Mailer->email_format('Cacti', $from);
		if ($Mailer->header_set('From', $from) === false) {
			print 'ERROR: ' . $Mailer->error() . "\n";
			return $Mailer->error();
		}
	}

	if ($to == '')
		return 'Mailer Error: No <b>TO</b> address set!!<br>If using the <i>Test Mail</i> link, please set the <b>Alert e-mail</b> setting.';
	$to = explode(',', $to);

	foreach($to as $t) {
		if (trim($t) != '' && !$Mailer->header_set('To', $t)) {
			print 'ERROR: ' . $Mailer->error() . "\n";
			return $Mailer->error();
		}
	}

	$wordwrap = read_config_option('settings_wordwrap');
	if ($wordwrap == '')
		$wordwrap = 76;
	if ($wordwrap > 9999)
		$wordwrap = 9999;
	if ($wordwrap < 0)
		$wordwrap = 76;

	$Mailer->Config['Mail']['WordWrap'] = $wordwrap;

	if (! $Mailer->header_set('Subject', $subject)) {
		print 'ERROR: ' . $Mailer->error() . "\n";
		return $Mailer->error();
	}

	if (is_array($filename) && !empty($filename) && strstr($message, '<GRAPH>') !==0) {
		foreach($filename as $val) {
			$graph_data_array = array('output_flag'=> RRDTOOL_OUTPUT_STDOUT);
			if (function_exists('imagecreatefrompng') && function_exists('imagejpeg')) {
				$data = @png2jpeg(rrdtool_function_graph($val['local_graph_id'], $val['rra_id'], $graph_data_array));
				$ext = 'jpg';
			} else {
				$data = @rrdtool_function_graph($val['local_graph_id'], $val['rra_id'], $graph_data_array);
				$ext = 'png';
			}
			if ($data != '') {
				$cid = $Mailer->content_id();
				if ($Mailer->attach($data, $val['filename'].".$ext", "image/$ext", 'inline', $cid) == false) {
					print 'ERROR: ' . $Mailer->error() . "\n";
					return $Mailer->error();
				}
				$message = str_replace('<GRAPH>', "<br><br><img src='cid:$cid'>", $message);
			} else {
				$message = str_replace('<GRAPH>', "<br><img src='" . $val['file'] . "'><br>Could not open!<br>" . $val['file'], $message);
			}
		}
	}
	$text = array('text' => '', 'html' => '');
	if ($filename == '') {
		$message = str_replace('<br>',  "\n", $message);
		$message = str_replace('<BR>',  "\n", $message);
		$message = str_replace('</BR>', "\n", $message);
		$text['text'] = strip_tags($message);
	} else {
		$text['html'] = $message . '<br>';
		$text['text'] = strip_tags(str_replace('<br>', "\n", $message));
	}

	$v = thold_version();
	$Mailer->header_set('X-Mailer', 'Cacti-Thold-v' . $v['version']);
	$Mailer->header_set('User-Agent', 'Cacti-Thold-v' . $v['version']);
	if (read_config_option('thold_email_prio') == 'on') {
		$Mailer->header_set('X-Priority', '1');
	}
	thold_debug("Sending email to '" . trim(implode(',',$to),',') . "'");
	if ($Mailer->send($text) == false) {
		print 'ERROR: ' . $Mailer->error() . "\n";
		return $Mailer->error();
	}

	return '';
}

function thold_template_update_threshold ($id, $template) {
	db_execute("UPDATE thold_data, thold_template
		SET
		thold_data.template_enabled = 'on',
		thold_data.thold_hi = thold_template.thold_hi,
		thold_data.thold_low = thold_template.thold_low,
		thold_data.thold_fail_trigger = thold_template.thold_fail_trigger,
		thold_data.time_hi = thold_template.time_hi,
		thold_data.time_low = thold_template.time_low,
		thold_data.time_fail_trigger = thold_template.time_fail_trigger,
		thold_data.time_fail_length = thold_template.time_fail_length,
		thold_data.thold_warning_hi = thold_template.thold_warning_hi,
		thold_data.thold_warning_low = thold_template.thold_warning_low,
		thold_data.thold_warning_fail_trigger = thold_template.thold_warning_fail_trigger,
		thold_data.time_warning_hi = thold_template.time_warning_hi,
		thold_data.time_warning_low = thold_template.time_warning_low,
		thold_data.time_warning_fail_trigger = thold_template.time_warning_fail_trigger,
		thold_data.time_warning_fail_length = thold_template.time_warning_fail_length,
		thold_data.thold_enabled = thold_template.thold_enabled,
		thold_data.thold_type = thold_template.thold_type,
		thold_data.bl_ref_time_range = thold_template.bl_ref_time_range,
		thold_data.bl_pct_down = thold_template.bl_pct_down,
		thold_data.bl_pct_up = thold_template.bl_pct_up,
		thold_data.bl_fail_trigger = thold_template.bl_fail_trigger,
		thold_data.bl_alert = thold_template.bl_alert,
		thold_data.bl_thold_valid = 0,
		thold_data.repeat_alert = thold_template.repeat_alert,
		thold_data.notify_extra = thold_template.notify_extra,
		thold_data.notify_warning_extra = thold_template.notify_warning_extra,
		thold_data.notify_warning = thold_template.notify_warning,
		thold_data.notify_alert = thold_template.notify_alert,
		thold_data.data_type = thold_template.data_type,
		thold_data.cdef = thold_template.cdef,
		thold_data.percent_ds = thold_template.percent_ds,
		thold_data.expression = thold_template.expression,
		thold_data.exempt = thold_template.exempt,
		thold_data.data_template = thold_template.data_template_id,
		thold_data.restored_alert = thold_template.restored_alert,
		thold_data.snmp_event_category = thold_template.snmp_event_category,
		thold_data.snmp_event_severity = thold_template.snmp_event_severity,
		thold_data.snmp_event_warning_severity = thold_template.snmp_event_warning_severity
		WHERE thold_data.id=$id AND thold_template.id=$template");
	db_execute('DELETE FROM plugin_thold_threshold_contact where thold_id = ' . $id);
	db_execute("INSERT INTO plugin_thold_threshold_contact (thold_id, contact_id) SELECT $id, contact_id FROM plugin_thold_template_contact WHERE template_id = $template");
}

function thold_template_update_thresholds ($id) {
	db_execute("UPDATE thold_data, thold_template
		SET thold_data.thold_hi = thold_template.thold_hi,
		thold_data.thold_low = thold_template.thold_low,
		thold_data.thold_fail_trigger = thold_template.thold_fail_trigger,
		thold_data.time_hi = thold_template.time_hi,
		thold_data.time_low = thold_template.time_low,
		thold_data.time_fail_trigger = thold_template.time_fail_trigger,
		thold_data.time_fail_length = thold_template.time_fail_length,
		thold_data.thold_warning_hi = thold_template.thold_warning_hi,
		thold_data.thold_warning_low = thold_template.thold_warning_low,
		thold_data.thold_warning_fail_trigger = thold_template.thold_warning_fail_trigger,
		thold_data.time_warning_hi = thold_template.time_warning_hi,
		thold_data.time_warning_low = thold_template.time_warning_low,
		thold_data.time_warning_fail_trigger = thold_template.time_warning_fail_trigger,
		thold_data.time_warning_fail_length = thold_template.time_warning_fail_length,
		thold_data.thold_enabled = thold_template.thold_enabled,
		thold_data.thold_type = thold_template.thold_type,
		thold_data.bl_ref_time_range = thold_template.bl_ref_time_range,
		thold_data.bl_pct_up = thold_template.bl_pct_up,
		thold_data.bl_pct_down = thold_template.bl_pct_down,
		thold_data.bl_pct_up = thold_template.bl_pct_up,
		thold_data.bl_fail_trigger = thold_template.bl_fail_trigger,
		thold_data.bl_alert = thold_template.bl_alert,
		thold_data.bl_thold_valid = 0,
		thold_data.repeat_alert = thold_template.repeat_alert,
		thold_data.notify_extra = thold_template.notify_extra,
		thold_data.notify_warning_extra = thold_template.notify_warning_extra,
		thold_data.notify_warning = thold_template.notify_warning,
		thold_data.notify_alert = thold_template.notify_alert,
		thold_data.data_type = thold_template.data_type,
		thold_data.cdef = thold_template.cdef,
		thold_data.percent_ds = thold_template.percent_ds,
		thold_data.expression = thold_template.expression,
		thold_data.exempt = thold_template.exempt,
		thold_data.data_template = thold_template.data_template_id,
		thold_data.restored_alert = thold_template.restored_alert,
		thold_data.snmp_event_category = thold_template.snmp_event_category,
		thold_data.snmp_event_severity = thold_template.snmp_event_severity,
		thold_data.snmp_event_warning_severity = thold_template.snmp_event_warning_severity
		WHERE thold_data.template=$id AND thold_data.template_enabled='on' AND thold_template.id=$id");
	$rows = db_fetch_assoc("SELECT id, template FROM thold_data WHERE thold_data.template=$id AND thold_data.template_enabled='on'");

	foreach ($rows as $row) {
		db_execute('DELETE FROM plugin_thold_threshold_contact where thold_id = ' . $row['id']);
		db_execute('INSERT INTO plugin_thold_threshold_contact (thold_id, contact_id) SELECT ' . $row['id'] . ', contact_id FROM plugin_thold_template_contact WHERE template_id = ' . $row['template']);
	}
}

function thold_cacti_log($string) {
	global $config;
	$environ = 'THOLD';
	/* fill in the current date for printing in the log */
	$date = date("m/d/Y h:i:s A");

	/* determine how to log data */
	$logdestination = read_config_option("log_destination");
	$logfile        = read_config_option("path_cactilog");

	/* format the message */
	$message = "$date - " . $environ . ": " . $string . "\n";

	/* Log to Logfile */
	if ((($logdestination == 1) || ($logdestination == 2)) && (read_config_option("log_verbosity") != POLLER_VERBOSITY_NONE)) {
		if ($logfile == "") {
			$logfile = $config["base_path"] . "/log/cacti.log";
		}

		/* echo the data to the log (append) */
		$fp = @fopen($logfile, "a");

		if ($fp) {
			@fwrite($fp, $message);
			fclose($fp);
		}
	}

	/* Log to Syslog/Eventlog */
	/* Syslog is currently Unstable in Win32 */
	if (($logdestination == 2) || ($logdestination == 3)) {
		$string = strip_tags($string);
		$log_type = "";
		if (substr_count($string,"ERROR:"))
			$log_type = "err";
		else if (substr_count($string,"WARNING:"))
			$log_type = "warn";
		else if (substr_count($string,"STATS:"))
			$log_type = "stat";
		else if (substr_count($string,"NOTICE:"))
			$log_type = "note";

		if (strlen($log_type)) {
			if ($config["cacti_server_os"] == "win32")
				openlog("Cacti", LOG_NDELAY | LOG_PID, LOG_USER);
			else
				openlog("Cacti", LOG_NDELAY | LOG_PID, LOG_SYSLOG);

			if (($log_type == "err") && (read_config_option("log_perror"))) {
				syslog(LOG_CRIT, $environ . ": " . $string);
			}

			if (($log_type == "warn") && (read_config_option("log_pwarn"))) {
				syslog(LOG_WARNING, $environ . ": " . $string);
			}

			if ((($log_type == "stat") || ($log_type == "note")) && (read_config_option("log_pstats"))) {
				syslog(LOG_INFO, $environ . ": " . $string);
			}

			closelog();
		}
	}
}

function thold_threshold_enable($id) {
	db_execute("UPDATE thold_data SET thold_enabled='on', thold_fail_count=0, thold_warning_fail_count=0, bl_fail_count=0, thold_alert=0, bl_alert=0 WHERE id=$id");
}

function thold_threshold_disable($id) {
	db_execute("UPDATE thold_data SET thold_enabled='off', thold_fail_count=0, thold_warning_fail_count=0, bl_fail_count=0, thold_alert=0, bl_alert=0 WHERE id=$id");
}

/**
 * This function is stolen from NECTAR
 * convert png images stream to jpeg using php-gd
 *
 * @param unknown_type $png_data    the png image as a stream
 * @return unknown                    the jpeg image as a stream
 */
function png2jpeg ($png_data) {
	global $config;
	$ImageData = '';
	if ($png_data != "") {
		$fn = "/tmp/" . time() . '.png';

		/* write rrdtool's png file to scratch dir */
		$f = fopen($fn, 'wb');
		fwrite($f, $png_data);
		fclose($f);

		/* create php-gd image object from file */
		$im = imagecreatefrompng($fn);
		if (!$im) {
			/* check for errors */
			$im = ImageCreate (150, 30);
			/* create an empty image */
			$bgc = ImageColorAllocate ($im, 255, 255, 255);
			$tc  = ImageColorAllocate ($im, 0, 0, 0);
			ImageFilledRectangle ($im, 0, 0, 150, 30, $bgc);
			/* print error message */
			ImageString($im, 1, 5, 5, "Error while opening: $imgname", $tc);
		}

		ob_start(); // start a new output buffer to capture jpeg image stream
		imagejpeg($im);    // output to buffer
		$ImageData = ob_get_contents(); // fetch image from buffer
		$ImageDataLength = ob_get_length();
		ob_end_clean(); // stop this output buffer
		imagedestroy($im); //clean up

		unlink($fn); // delete scratch file
	}
	return $ImageData;
}

function get_thold_notification_emails($id) {
	if (!empty($id)) {
		return trim(db_fetch_cell('SELECT emails FROM plugin_notification_lists WHERE id=' . $id));
	} else {
		return '';
	}
}

/* get_hash_thold_template - returns the current unique hash for a thold_template
   @arg $id - (int) the ID of the thold template to return a hash for
   @returns - a 128-bit, hexadecimal hash */
function get_hash_thold_template($id) {
    $hash = db_fetch_cell("SELECT hash FROM thold_template WHERE id=$id");

    if (preg_match("/[a-fA-F0-9]{32}/", $hash)) {
        return $hash;
    } else {
        return generate_hash();
    }
}

function ia2xml($array) {
	$xml = "";
	if (sizeof($array)) {
	foreach ($array as $key=>$value) {
		if (is_array($value)) {
			$xml .= "\t<$key>" . ia2xml($value) . "</$key>\n";
		} else {
			$xml .= "\t<$key>" . htmlspecialchars($value) . "</$key>\n";
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

function thold_snmptrap($varbinds){
	global $config;
	if (function_exists('snmpagent_notification')) {
		snmpagent_notification('tholdNotify', 'CACTI-THOLD-MIB', $varbinds);
	}else {
		cacti_log("ERROR: THOLD was unable to generate SNMP notifications. Cacti SNMPAgent plugin is current missing or inactive.");
	}
}