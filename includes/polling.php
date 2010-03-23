<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2009 The Cacti Group                                      |
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

function thold_poller_bottom () {
	/* record the start time */
	list($micro,$seconds) = split(" ", microtime());
	$start = $seconds + $micro;

	/* perform all thold checks */
	$tholds = thold_check_all_thresholds ();
	$hosts  = thold_update_host_status ();
	thold_cleanup_log ();

	/* record the end time */
	list($micro,$seconds) = split(" ", microtime());
	$end = $seconds + $micro;

	/* log statistics */
	$thold_stats = sprintf("Time:%01.4f Tholds:%s Hosts:%s", $end - $start, $tholds, $hosts);
	cacti_log('THOLD STATS: ' . $thold_stats, false, 'SYSTEM');
	db_execute("REPLACE INTO settings (name, value) VALUES ('stats_thold', '$thold_stats')");
}

function thold_cleanup_log () {
	$t = time() - (86400 * 31); // Delete Logs over a month old
	db_execute("DELETE FROM plugin_thold_log WHERE time < $t");
}

function thold_poller_output ($rrd_update_array) {
	global $config;

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	$rrd_update_array_reindexed = array();
	$rra_ids = '';
	$x = 0;
	foreach($rrd_update_array as $item) {
		if (isset($item['times'][key($item['times'])])) {
			if ($x) {
				$rra_ids .= ',';
			}
			$rra_ids .= $item['local_data_id'];
			$rrd_update_array_reindexed[$item['local_data_id']] = $item['times'][key($item['times'])];
			$x++;
		}
	}

	if ($rra_ids != '') {
		$thold_items = db_fetch_assoc("SELECT * FROM thold_data WHERE rra_id IN($rra_ids)", false);
	} else {
		return $rrd_update_array;
	}
	if (sizeof($thold_items)) {
	foreach ($thold_items as $t_item) {
		$polling_interval = $t_item['rrd_step'];
		if (isset($rrd_update_array_reindexed[$t_item['rra_id']])) {
			$item = $rrd_update_array_reindexed[$t_item['rra_id']];
			if (isset($item[$t_item['data_source_name']])) {
				switch ($t_item['data_source_type_id']) {
					case 2:	// COUNTER
						if ($item[$t_item['data_source_name']] >= $t_item['oldvalue']) {
							// Everything is normal
							$currentval = $item[$t_item['data_source_name']] - $t_item['oldvalue'];
						} else {
							// Possible overflow, see if its 32bit or 64bit
							if ($t_item['oldvalue'] > 4294967295) {
								$currentval = (18446744073709551615 - $t_item['oldvalue']) + $item[$t_item['data_source_name']];
							} else {
								$currentval = (4294967295 - $t_item['oldvalue']) + $item[$t_item['data_source_name']];
							}
						}
						$currentval = $currentval / $polling_interval;
						break;
					case 3:	// DERIVE
						$currentval = ($item[$t_item['data_source_name']] - $t_item['oldvalue']) / $polling_interval;
						break;
					case 4:	// ABSOLUTE
						$currentval = $item[$t_item['data_source_name']] / $polling_interval;
						break;
					case 1:	// GAUGE
					default:
						$currentval = $item[$t_item['data_source_name']];
						break;
				}
				switch ($t_item['data_type']) {
					case 0:
						$currentval = round($currentval, 4);
						break;
					case 1:
						if ($t_item['cdef'] != 0) {
							$currentval = thold_build_cdef($t_item['cdef'], $currentval, $t_item['rra_id'], $t_item['data_id']);
						}
						$currentval = round($currentval, 4);
						break;
					case 2:
						if ($t_item['percent_ds'] != '') {
							$currentval = thold_calculate_percent($t_item, $currentval, $rrd_update_array_reindexed);
						}
						$currentval = round($currentval, 4);
						break;
					case 3:
						if ($t_item['expression'] != '') {
							$currentval = thold_calculate_expression($t_item, $currentval, $rrd_update_array_reindexed);
						}
						$currentval = round($currentval, 4);
						break;
				}
				db_execute("UPDATE thold_data SET tcheck = 1, lastread = '$currentval', oldvalue = '" . $item[$t_item['data_source_name']] . "' WHERE id = " . $t_item['id']);
			}
		}
	}
	}

	return $rrd_update_array;
}

function thold_check_all_thresholds () {
	global $config;
	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
	$tholds = do_hook_function('thold_get_live_hosts', db_fetch_assoc("SELECT * FROM thold_data WHERE thold_enabled = 'on' AND tcheck = 1"));
	$total_tholds = sizeof($tholds);
	foreach ($tholds as $thold) {
		$ds = db_fetch_cell('SELECT data_source_name FROM data_template_rrd WHERE id=' . $thold['data_id']);
		thold_check_threshold ($thold['rra_id'], $thold['data_id'], $ds, $thold['lastread'], $thold['cdef']);
	}
	db_execute('UPDATE thold_data SET tcheck = 0');

	return $total_tholds;
}

function thold_update_host_status () {
	global $config;
	// Return if we aren't set to notify
	$deadnotify = (read_config_option('alert_deadnotify') == 'on');
	if (!$deadnotify) return 0;
	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	$alert_email = read_config_option('alert_email');
	$ping_failure_count = read_config_option('ping_failure_count');
	// Lets find hosts that were down, but are now back up
	$failed = read_config_option('thold_failed_hosts', true);
	$failed = explode(',', $failed);
	if (!empty($failed)) {
		foreach($failed as $id) {
			if ($id != '') {
				$host = db_fetch_row('SELECT id, status, description, hostname FROM host WHERE id = ' . $id);
				if ($host['status'] == HOST_UP) {
					$subject = 'Host Notice : ' . $host['description'] . ' (' . $host['hostname'] . ') returned from DOWN state';
					$msg = $subject;
					if ($logset) {
						plugin_thold_syslog_host_status($subject);
					}
					if ($alert_email == '') {
						cacti_log('THOLD: Can not send Host Recovering email since the \'Alert e-mail\' setting is not set!', true, 'POLLER');
					} else {
						thold_mail($alert_email, '', $subject, $msg, '');
					}
				}
			}
		}
	}

	// Lets find hosts that are down
	$hosts = db_fetch_assoc('SELECT id, description, hostname, status_last_error FROM host WHERE disabled="" AND status=' . HOST_DOWN . ' AND status_event_count=' . $ping_failure_count);
	$total_hosts = sizeof($hosts);
	if (count($hosts)) {
		foreach($hosts as $host) {
			$subject = 'Host Error : ' . $host['description'] . ' (' . $host['hostname'] . ') is DOWN';
			$msg = 'Host Error : ' . $host['description'] . ' (' . $host['hostname'] . ') is DOWN<br>Message : ' . $host['status_last_error'];
			if ($logset) {
				plugin_thold_syslog_host_status($subject);
			}
			if ($alert_email == '') {
				cacti_log('THOLD: Can not send Host Down email since the \'Alert e-mail\' setting is not set!', true, 'POLLER');
			} else {
				thold_mail($alert_email, '', $subject, $msg, '');
			}
		}
	}

	// Now lets record all failed hosts
	$hosts = db_fetch_assoc('SELECT id FROM host WHERE status != ' . HOST_UP);
	$failed = array();
	if (!empty($hosts)) {
		foreach ($hosts as $host) {
			$failed[] = $host['id'];
		}
	}
	$failed = implode(',', $failed);
	db_execute("REPLACE INTO settings (name, value) VALUES ('thold_failed_hosts', '$failed')");

	return $total_hosts;
}
