<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2020 The Cacti Group                                 |
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

function thold_poller_bottom() {
	global $config, $database_type, $database_default, $database_hostname;
	global $database_username, $database_password, $database_port, $database_ssl;

	if (read_config_option('thold_empty_if_speed_default') == '') {
		set_config_option('thold_empty_if_speed_default', '10000');
		$empty_value = read_config_option('thold_empty_if_speed_default', true);
	}

	// Force upgrade the database if there is a problem
	if (!db_column_exists('thold_data', 'name_cache')) {
		include_once($config['base_path'] . '/plugins/thold/includes/database.php');
		thold_upgrade_database(true);
	}

	/* record the start time */
	$start = microtime(true);

	if (read_config_option('thold_daemon_enable') == '') {
		/* perform all thold checks */
		$tholds = thold_check_all_thresholds();
		$nhosts = thold_update_host_status();

		thold_cleanup_log();

		if (read_config_option('remote_storage_method') == 1) {
			$total_hosts = db_fetch_cell_prepared('SELECT count(*)
				FROM host
				WHERE disabled=""
				AND poller_id = ?',
				array($config['poller_id']));

			$down_hosts = db_fetch_cell_prepared('SELECT count(*)
				FROM host
				WHERE status=1
				AND disabled=""
				AND poller_id = ?',
				array($config['poller_id']));
		} else {
			$total_hosts = db_fetch_cell('SELECT count(*)
				FROM host
				WHERE disabled=""');

			$down_hosts = db_fetch_cell('SELECT count(*)
				FROM host
				WHERE status=1
				AND disabled=""');
		}

		thold_prune_old_data();

		/* record the end time */
		$end = microtime(true);

		/* log statistics */
		$thold_stats = sprintf('Time:%01.4f Tholds:%d TotalDevices:%d DownDevices:%d NewDownDevices:%d', $end - $start, $tholds, $total_hosts, $down_hosts, $nhosts);

		cacti_log('THOLD STATS: ' . $thold_stats, false, 'SYSTEM');

		set_config_option('stats_thold', $thold_stats);
	} else {
		/* collect some stats */
		$now = microtime(true);

		/* get the last update from the daemon */
		$heartbeat       = read_config_option('thold_daemon_heartbeat');
		$poller_interval = read_config_option('poller_interval');
		$curtime         = time();
		$frequency       = read_config_option('thold_daemon_dead_notification');

		if ($frequency > 0) {
			if (empty($heartbeat)) {
				$last_notification = read_config_option('thold_daemon_down_notify_time');

				if ($curtime - $last_notification > $frequency) {
					admin_email('Thold Daemon Not Started', 'WARNING: You have elected to use the Thold Daemon, but it appears not to be running.  Please correct this right away');
					set_config_option('thold_daemon_down_notify_time', $curtime);
				}
			} elseif ($now - $heartbeat > 3 * $poller_interval) {
				$last_notification = read_config_option('thold_daemon_down_notify_time');

				if ($curtime - $last_notification > $frequency) {
					admin_email('Thold Daemon Down', 'WARNING: You have elected to use the Thold Daemon, but it appears have stopped running.  Please correct this right away');
					set_config_option('thold_daemon_down_notify_time', $curtime);
				}
			}
		}

		$threads = read_config_option('thold_max_concurrent_processes');

		/* begin transaction for repeatable read isolation level */
		$db_conn = db_connect_real($database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port, $database_ssl);
		$db_conn->beginTransaction();

		if (read_config_option('remote_storage_method') == 1) {
			/* host_status processed by thold server */
			$nhosts = thold_update_host_status();

			thold_cleanup_log();

			$total_hosts = db_fetch_cell_prepared('SELECT count(*)
				FROM host
				WHERE disabled = ""
				AND poller_id = ?',
				array($config['poller_id']));

			$down_hosts = db_fetch_cell_prepared('SELECT count(*)
				FROM host
				WHERE status = 1
				AND disabled = ""
				AND poller_id = ?',
				array($config['poller_id']));

			$thresholds = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM thold_data
				INNER JOIN host
				ON host.id = thold_data.host_id
				WHERE poller_id = ?
				AND disabled = ""',
				array($config['poller_id']));
		} else {
			/* host_status processed by thold server */
			$nhosts = thold_update_host_status();

			thold_cleanup_log();

			$total_hosts = db_fetch_cell('SELECT count(*)
				FROM host
				WHERE disabled=""');

			$down_hosts = db_fetch_cell('SELECT count(*)
				FROM host
				WHERE status = 1
				AND disabled = ""');

			$thresholds = db_fetch_cell('SELECT COUNT(*)
				FROM thold_data
				INNER JOIN host
				ON host.id = thold_data.host_id
				WHERE disabled = ""');
		}

		thold_prune_old_data();

		/* record the end time */
		$end = microtime(true);

		/* log statistics */
		$thold_stats = sprintf('TotalTime:%0.3f TotalDevices:%u DownDevices:%u NewDownDevices:%u Threads:%u Thresholds:%u',
			$end - $start, $total_hosts, $down_hosts, $nhosts, $threads, $thresholds);

		cacti_log('THOLD POLLER STATS: ' . $thold_stats, false, 'SYSTEM');

		set_config_option('stats_thold_' . $config['poller_id'], $thold_stats);

		$db_conn->commit();
	}
}

function thold_cleanup_log() {
	$daysToStoreLogs = read_config_option('thold_log_storage');

	if (empty($daysToStoreLogs)) {
		$daysToStoreLogs = 31;
		set_config_option('thold_log_storage', '31');
	}

	$t = time() - (86400 * $daysToStoreLogs); // Delete Logs over a month old

	db_execute_prepared('DELETE FROM plugin_thold_log
		WHERE time < ?',
		array($t));
}

function thold_poller_output(&$rrd_update_array) {
	global $config, $debug;

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
	include_once($config['library_path'] . '/snmp.php');

	$rrd_reindexed      = array();
	$rrd_time_reindexed = array();
	$local_data_ids     = '';

	foreach ($rrd_update_array as $item) {
		if (isset($item['times'][key($item['times'])])) {
			$local_data_ids .= ($local_data_ids != '' ? ', ':'') . $item['local_data_id'];
			$rrd_reindexed[$item['local_data_id']] = $item['times'][key($item['times'])];
			$rrd_time_reindexed[$item['local_data_id']] = key($item['times']);
		}
	}

	if ($local_data_ids != '') {
		if (read_config_option('thold_daemon_enable') == 'on') {
			$chunks = sizeof($rrd_update_array) / 50;
			if ($chunks < 1) {
				$chunks = 1;
			}

			$rrd_update_array_chunks = array_chunk($rrd_update_array, $chunks, true);

			foreach ($rrd_update_array_chunks as $rrd_update_array_chunk) {
				$rrd_reindexed      = array();
				$rrd_time_reindexed = array();
				$local_data_ids     = '';
				$thold_items        = array();

				foreach ($rrd_update_array_chunk as $item) {
					if (isset($item['times'][key($item['times'])])) {
						$local_data_ids .= ($local_data_ids != '' ? ', ':'') . $item['local_data_id'];

						$rrd_reindexed[$item['local_data_id']]	    = $item['times'][key($item['times'])];
						$rrd_time_reindexed[$item['local_data_id']] = key($item['times']);
					}
				}

				if ($local_data_ids != '') {
					$thold_items = db_fetch_assoc("SELECT id, local_data_id
						FROM thold_data
						WHERE thold_data.local_data_id IN ($local_data_ids)");
				}

				if (cacti_sizeof($thold_items)) {
					/* cache required polling data. prefer bulk inserts for
					 * performance reasons - start with chunks of 1000 items */
					$sql_max_inserts = 1000;
					$thold_items     = array_chunk($thold_items, $sql_max_inserts);

					$sql_insert = 'INSERT INTO plugin_thold_daemon_data
						(poller_id, id, rrd_reindexed, rrd_time_reindexed) VALUES ';

					foreach ($thold_items as $packet) {
						$sql_values = '';

						foreach ($packet as $thold_item) {
							$sql_values .= ($sql_values != '' ? ', ' : '') . '(' . $config['poller_id'] . ', ' . $thold_item['id'] . ",  " . db_qstr(json_encode($rrd_reindexed[$thold_item['local_data_id']])) . ', ' . $rrd_time_reindexed[$thold_item['local_data_id']] . ')';

						}

						db_execute($sql_insert . $sql_values);
					}
				}
			}

			return $rrd_update_array;
		}
	} else {
		return $rrd_update_array;
	}

	$tholds = db_fetch_assoc("SELECT td.id, td.name_cache AS thold_name,
		td.local_graph_id, td.percent_ds, td.expression, td.upper_ds, td.data_type,
		td.cdef, td.local_data_id, td.data_template_rrd_id, td.lastread,
		UNIX_TIMESTAMP(td.lasttime) AS lasttime, td.oldvalue,
		td.data_source_name AS name, dtr.data_source_type_id,
		dtd.rrd_step, dtr.rrd_maximum
		FROM thold_data AS td
		LEFT JOIN data_template_rrd AS dtr
		ON dtr.id = td.data_template_rrd_id
		LEFT JOIN data_template_data AS dtd
		ON dtd.local_data_id = td.local_data_id
		WHERE dtr.data_source_name != ''
		AND td.local_data_id IN($local_data_ids)");

	if (cacti_sizeof($tholds)) {
		$sql = array();
		foreach ($tholds as $thold_data) {
			thold_debug("Checking Threshold: Name: '" . $thold_data['thold_name'] . "', Graph: '" . $thold_data['local_graph_id'] . "'");

			$item        = array();
			$currenttime = 0;
			$postvalue   = thold_get_currentval($thold_data, $rrd_reindexed, $rrd_time_reindexed, $item, $currenttime);
			$currentval  = $postvalue;

			switch ($thold_data['data_type']) {
			case 0:

				break;
			case 1:
				if ($thold_data['cdef'] != 0) {
					$currentval = thold_build_cdef($thold_data['cdef'], $postvalue, $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
				}

				break;
			case 2:
				if ($thold_data['percent_ds'] != '') {
					$currentval = thold_calculate_percent($thold_data, $postvalue, $rrd_reindexed);
				}

				break;
			case 3:
				if ($thold_data['expression'] != '') {
					$currentval = thold_calculate_expression($thold_data, $postvalue, $rrd_reindexed, $rrd_time_reindexed);
				}

				break;
			case 4:
				if ($thold_data['upper_ds'] != '') {
					$currentval = thold_calculate_lower_upper($thold_data, $postvalue, $rrd_reindexed);
				}

				break;
			}

			if (!is_numeric($currentval)) {
				if (read_config_option('thold_consider_unknown_zero') == 'on') {
					$currentval = strtolower($currentval);
					if ($currentval == 'u' || $currentval == 'nan' || $currentval == '') {
						$currentval = 0;
					} else {
						$currentval = '';
					}
				} else {
					$currentval = '';
				}
			}

			// This stores the raw value into the data source and is important for
			// Counters, where calculating the difference is important.
			// The unset case is problematic and may lead to false triggering
			// events.  So, in those cases, we will store the 'oldvalue'.
			if (isset($item[$thold_data['name']])) {
				$rawvalue = $item[$thold_data['name']];
			} else {
				$rawvalue = $thold_data['oldvalue'];
			}

			$sql[] = '(' . $thold_data['id'] . ', 1, ' . db_qstr($currentval) . ', FROM_UNIXTIME(' . $currenttime . '), ' . db_qstr($rawvalue) . ')';
		}

		if (cacti_sizeof($sql)) {
			$chunks = array_chunk($sql, 400);
			foreach ($chunks as $c) {
				db_execute('INSERT INTO thold_data
					(id, tcheck, lastread, lasttime, oldvalue)
					VALUES ' . implode(', ', $c) . '
					ON DUPLICATE KEY UPDATE
						tcheck=VALUES(tcheck),
						lastread=VALUES(lastread),
						lasttime=VALUES(lasttime),
						oldvalue=VALUES(oldvalue)');
			}

			/* accomodate deleted tholds */
			db_execute('DELETE FROM thold_data WHERE local_data_id = 0');
		}
	}

	return $rrd_update_array;
}

if (!function_exists('array_column')) {
	function array_column($array, $column_name) {
		return array_map(function($element)
		use($column_name) {
			return $element[$column_name];
		}, $array);
	}
}

function thold_check_all_thresholds() {
	global $config;

	include($config['base_path'] . '/plugins/thold/includes/arrays.php');
	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	if (read_config_option('remote_storage_method') == 1) {
		if ($config['poller_id'] == 1) {
			$sql_query = "SELECT td.*, h.hostname,
				h.description, h.notes AS dnotes, h.snmp_engine_id
				FROM thold_data AS td
				LEFT JOIN host AS h
				ON h.id = td.host_id
				LEFT JOIN data_template_rrd AS dtr
				ON dtr.id = td.data_template_rrd_id
				WHERE td.thold_enabled = 'on'
				AND (h.poller_id = 1 OR h.poller_id IS NULL)
				AND td.tcheck = 1
				AND h.status = 3";
		} else {
			$sql_query = "SELECT td.*, h.hostname,
				h.description, h.notes AS dnotes, h.snmp_engine_id
				FROM thold_data AS td
				LEFT JOIN host AS h
				ON h.id = td.host_id
				LEFT JOIN data_template_rrd AS dtr
				ON dtr.id = td.data_template_rrd_id
				WHERE td.thold_enabled = 'on'
				AND h.poller_id = " . $config['poller_id'] . "
				AND td.tcheck = 1
				AND h.status = 3";
		}
	} else {
		$sql_query = "SELECT td.*, h.hostname,
			h.description, h.notes AS dnotes, h.snmp_engine_id
			FROM thold_data AS td
			LEFT JOIN data_template_rrd AS dtr
			ON dtr.id = td.data_template_rrd_id
			LEFT JOIN host as h
			ON td.host_id = h.id
			WHERE td.thold_enabled = 'on'
			AND td.tcheck = 1
			AND h.status = 3";
	}

	$tholds = api_plugin_hook_function('thold_get_live_hosts', db_fetch_assoc($sql_query));

	$total_tholds = sizeof($tholds);
	foreach ($tholds as $thold) {
		thold_check_threshold($thold);
	}

	if (read_config_option('remote_storage_method') == 1) {
		if ($config['poller_id'] == 1) {
			db_execute('UPDATE thold_data AS td
				LEFT JOIN host AS h
				ON td.host_id = h.id
				SET tcheck = 0
				WHERE h.poller_id = 1
				OR h.poller_id IS NULL');
		} else {
			db_execute_prepared('UPDATE thold_data AS td
				INNER JOIN host AS h
				ON td.host_id = h.id
				SET td.tcheck = 0
				WHERE h.poller_id = ?',
				array($config['poller_id']));
		}
	} else {
		db_execute('UPDATE thold_data AS td SET td.tcheck = 0');
	}

	return $total_tholds;
}

function thold_update_host_status() {
	global $config;

	// Return if we aren't set to notify
	$deadnotify = (read_config_option('alert_deadnotify') == 'on');

	if (!$deadnotify) {
		return 0;
	}

	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
	include_once($config['library_path'] . '/snmp.php');

	if (api_plugin_is_enabled('maint')) {
		include_once($config['base_path'] . '/plugins/maint/functions.php');
	}

	$ping_failure_count = read_config_option('ping_failure_count');

	// Lets find hosts that were down, but are now back up
	if (read_config_option('remote_storage_method') == 1) {
		$failed = db_fetch_assoc_prepared('SELECT *
			FROM plugin_thold_host_failed
			WHERE poller_id = ?',
			array($config['poller_id']));
	} else {
		$failed = db_fetch_assoc('SELECT *
			FROM plugin_thold_host_failed');
	}

	if (cacti_sizeof($failed)) {
		foreach ($failed as $fh) {
			$alert_email = read_config_option('alert_email');

			if (api_plugin_is_enabled('maint')) {
				if (plugin_maint_check_cacti_host($fh['host_id'])) {
					continue;
				}
			}

			$host = db_fetch_row_prepared('SELECT *
				FROM host
				WHERE id = ?',
				array($fh['host_id']));

			if (!sizeof($host)) {
				db_execute_prepared('DELETE
					FROM plugin_thold_host_failed
					WHERE host_id = ?',
					array($fh['host_id']));
			} elseif ($host['status'] == HOST_UP) {
				$snmp_system   = '';
				$snmp_hostname = '';
				$snmp_location = '';
				$snmp_contact  = '';
				$snmp_uptime   = '';
				$uptimelong    = '';
				$downtimemsg   = '';

				if (($host['snmp_community'] == '' && $host['snmp_username'] == '') || $host['snmp_version'] == 0) {
					// SNMP not in use
					$snmp_system = __('SNMP not in use', 'thold');
				} else {
					$snmp_system = cacti_snmp_get($host['hostname'], $host['snmp_community'], '.1.3.6.1.2.1.1.1.0', $host['snmp_version'], $host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'], $host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'], read_config_option('snmp_retries'), SNMP_WEBUI);

					if (substr_count($snmp_system, '00:')) {
						$snmp_system = str_replace('00:', '', $snmp_system);
						$snmp_system = str_replace(':', ' ', $snmp_system);
					}

					if ($snmp_system != '') {
						$snmp_uptime = cacti_snmp_get($host['hostname'], $host['snmp_community'], '.1.3.6.1.2.1.1.3.0', $host['snmp_version'], $host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'], $host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'], read_config_option('snmp_retries'), SNMP_WEBUI);

						$snmp_hostname = cacti_snmp_get($host['hostname'], $host['snmp_community'], '.1.3.6.1.2.1.1.5.0', $host['snmp_version'], $host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'], $host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'], read_config_option('snmp_retries'), SNMP_WEBUI);

						$snmp_location = cacti_snmp_get($host['hostname'], $host['snmp_community'], '.1.3.6.1.2.1.1.6.0', $host['snmp_version'], $host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'], $host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'], read_config_option('snmp_retries'), SNMP_WEBUI);

						$snmp_contact = cacti_snmp_get($host['hostname'], $host['snmp_community'], '.1.3.6.1.2.1.1.4.0', $host['snmp_version'], $host['snmp_username'], $host['snmp_password'], $host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'], $host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'], read_config_option('snmp_retries'), SNMP_WEBUI);

						if (is_numeric($snmp_uptime)) {
							$days       = intval($snmp_uptime / (60 * 60 * 24 * 100));
							$remainder  = $snmp_uptime % (60 * 60 * 24 * 100);
							$hours      = intval($remainder / (60 * 60 * 100));
							$remainder  = $remainder % (60 * 60 * 100);
							$minutes    = intval($remainder / (60 * 100));
							$uptimelong = $days . 'd ' . $hours . 'h ' . $minutes . 'm';
						} else {
							$days       = '0';
							$remainder  = '0';
							$hours      = '0';
							$remainder  = '0';
							$minutes    = '0';
							$uptimelong = $days . 'd ' . $hours . 'h ' . $minutes . 'm';
						}
					}
				}

				if ($host['status_fail_date'] != '0000-00-00 00:00:00') {
					$downtime         = time() - strtotime($host['status_fail_date']);
					$downtime_days    = floor($downtime / 86400);
					$downtime_hours   = floor(($downtime - ($downtime_days * 86400)) / 3600);
					$downtime_minutes = floor(($downtime - ($downtime_days * 86400) - ($downtime_hours * 3600)) / 60);
					$downtime_seconds = $downtime - ($downtime_days * 86400) - ($downtime_hours * 3600) - ($downtime_minutes * 60);

					if ($downtime_days > 0) {
						$downtimemsg = $downtime_days . 'd ' . $downtime_hours . 'h ' . $downtime_minutes . 'm ' . $downtime_seconds . 's ';
					} elseif ($downtime_hours > 0) {
						$downtimemsg = $downtime_hours . 'h ' . $downtime_minutes . 'm ' . $downtime_seconds . 's';
					} elseif ($downtime_minutes > 0) {
						$downtimemsg = $downtime_minutes . 'm ' . $downtime_seconds . 's';
					} else {
						$downtimemsg = $downtime_seconds . 's ';
					}
				} else {
					$downtimemsg = __('N/A', 'thold');
				}

				$subject = read_config_option('thold_up_subject');
				if ($subject == '') {
					$subject = __('Devices Notice: <DESCRIPTION> (<HOSTNAME>) returned from DOWN state', 'thold');
				}

				$subject = str_replace('<HOSTNAME>', $host['hostname'], $subject);
				$subject = str_replace('<DESCRIPTION>', $host['description'], $subject);
				$subject = str_replace('<DOWN/UP>', 'UP', $subject);
				$subject = strip_tags($subject);

				$msg = read_config_option('thold_up_text');
				if ($msg == '') {
					$msg = __('<br>System <DESCRIPTION> (<HOSTNAME>) status: <DOWN/UP><br><br>Current ping response: <CUR_TIME> ms<br>Average system response : <AVG_TIME> ms<br>System availability: <AVAILABILITY><br>Total Checks Since Clear: <TOT_POLL><br>Total Failed Checks: <FAIL_POLL><br>Last Date Checked UP: <LAST_FAIL><br>Devices Previously DOWN for: <DOWNTIME><br><br>SNMP Info:<br>Name - <SNMP_HOSTNAME><br>Location - <SNMP_LOCATION><br>Uptime - <UPTIMETEXT> (<UPTIME> ms)<br>System - <SNMP_SYSTEM><br><br>NOTE: <NOTES>', 'thold');
				}

				$msg = str_replace('<SUBJECT>', $subject, $msg);
				$msg = str_replace('<HOSTNAME>', $host['hostname'], $msg);
				$msg = str_replace('<HOST_ID>', $host['id'], $msg);
				$msg = str_replace('<DESCRIPTION>', $host['description'], $msg);
				$msg = str_replace('<UPTIME>', $snmp_uptime, $msg);
				$msg = str_replace('<UPTIMETEXT>', $uptimelong, $msg);

				$msg = str_replace('<TIME>', time(), $msg);
				$msg = str_replace('<DATE>', date(CACTI_DATE_TIME_FORMAT), $msg);
				$msg = str_replace('<DATE_RFC822>', date(DATE_RFC822), $msg);

				$msg = str_replace('<DOWNTIME>', $downtimemsg, $msg);
				$msg = str_replace('<MESSAGE>', '', $msg);
				$msg = str_replace('<DOWN/UP>', 'UP', $msg);

				$msg = str_replace('<SNMP_HOSTNAME>', $snmp_hostname, $msg);
				$msg = str_replace('<SNMP_LOCATION>', $snmp_location, $msg);
				$msg = str_replace('<SNMP_CONTACT>', $snmp_contact, $msg);
				$msg = str_replace('<SNMP_SYSTEM>', html_split_string($snmp_system), $msg);
				$msg = str_replace('<LAST_FAIL>', $host['status_fail_date'], $msg);
				$msg = str_replace('<AVAILABILITY>', number_format_i18n(($host['availability']), 2) . ' %', $msg);
				$msg = str_replace('<TOT_POLL>', number_format_i18n($host['total_polls']), $msg);
				$msg = str_replace('<FAIL_POLL>', number_format_i18n($host['failed_polls']), $msg);
				$msg = str_replace('<CUR_TIME>', number_format_i18n(($host['cur_time']), 2), $msg);
				$msg = str_replace('<AVG_TIME>', number_format_i18n(($host['avg_time']), 2), $msg);
				$msg = str_replace('<NOTES>', $host['notes'], $msg);
				$msg = str_replace("\n", '<br>', $msg);

				switch ($host['thold_send_email']) {
					case '0': // Disabled
						$alert_email = '';
						break;
					case '1': // Global List
						break;
					case '2': // Devices List Only
						$alert_email = get_thold_notification_emails($host['thold_host_email']);
						break;
					case '3': // Global and Devices List
						$alert_email = $alert_email . ',' . get_thold_notification_emails($host['thold_host_email']);
						break;
				}

				api_plugin_hook_function(
					'thold_device_recovering',
					array(
						'device'  => $host,
						'subject' => $subject,
						'message' => $msg,
						'email' => $alert_email
					)
				);

				cacti_log('WARNING: Device[' . $host['id'] . '] Hostname[' . $host['hostname'] . '] is recovering!', true, 'THOLD');

				if ($alert_email == '' && $host['thold_send_email'] > 0) {
					cacti_log('WARNING: Device[' . $host['id'] . '] Hostname[' . $host['hostname'] . '] can not send a Device recovering email for \'' . $host['description'] . '\' since the \'Alert Email\' setting is not set for Device!', true, 'THOLD');
				} elseif ($host['thold_send_email'] == '0') {
					cacti_log('NOTE: Device[' . $host['id'] . '] Hostname[' . $host['hostname'] . '] did not send a Device recovering email for \'' . $host['description'] . '\', disabled per Device setting!', true, 'THOLD');
				} elseif ($alert_email != '') {
					thold_mail($alert_email, '', $subject, $msg, '');
				}

				$command = read_config_option('thold_device_command');

				if ($command != '') {
					putenv('THOLD_SUBJECT='      . $subject);
					putenv('THOLD_HOSTNAME='      . $host['hostname']);
					putenv('THOLD_HOST_ID='       . $host['id']);
					putenv('THOLD_DESCRIPTION='   . $host['description']);
					putenv('THOLD_TIME='          . time());
					putenv('THOLD_DATE='          . date(CACTI_DATE_TIME_FORMAT));
					putenv('THOLD_DATE_RFC822='   . date(DATE_RFC822));

					putenv('THOLD_UPTIME='        . $snmp_uptime);
					putenv('THOLD_UPTIMETEXT='    . $uptimelong);
					putenv('THOLD_DOWNTIME='      . $downtimemsg);
					putenv('THOLD_MESSAGE='       . '');
					putenv('THOLD_DOWNUP='        . 'UP');

					putenv('THOLD_SNMP_HOSTNAME=' . $snmp_hostname);
					putenv('THOLD_SNMP_LOCATION=' . $snmp_location);
					putenv('THOLD_SNMP_CONTACT='  . $snmp_contact);
					putenv('THOLD_SNMP_SYSTEM='   . $snmp_system);
					putenv('THOLD_LAST_FAIL='     . $host['status_fail_date']);
					putenv('THOLD_AVAILABILITY='  . $host['availability']);
					putenv('THOLD_TOT_POLL='      . $host['total_polls']);
					putenv('THOLD_FAIL_POLL='     . $host['failed_polls']);
					putenv('THOLD_CUR_TIME='      . $host['cur_time']);
					putenv('THOLD_AVG_TIME='      . $host['avg_time']);
					putenv('THOLD_NOTES='         . $host['notes']);

					if (file_exists($command) && is_executable($command)) {
						$output = array();
						$return = 0;

						exec($command, $output, $return);

						cacti_log('Device Up Command for Device[' . $host['id'] . '] Command[' . $command . '] ExitStatus[' . $return . '] Output[' . implode(' ', $output) . ']', false, 'THOLD');
					} else {
						cacti_log('WARNING: Device Up Command for Device[' . $host['id'] . '] Command[' . $command . '] Is either Not found or Not executable!', false, 'THOLD');
					}
				}
			}
		}
	}

	// Lets find hosts that are down
	if (read_config_option('remote_storage_method') == 1) {
		$hosts = db_fetch_assoc_prepared('SELECT *
			FROM host
			WHERE disabled=""
			AND status = ?
			AND status_event_count = IF(thold_failure_count > 0, thold_failure_count, ?)
			AND poller_id = ?',
			array(HOST_DOWN, $ping_failure_count, $config['poller_id']));
	} else {
		$hosts = db_fetch_assoc_prepared('SELECT *
			FROM host
			WHERE disabled=""
			AND status = ?
			AND status_event_count = IF(thold_failure_count > 0, thold_failure_count, ?)',
			array(HOST_DOWN, $ping_failure_count));
	}

	$total_hosts = sizeof($hosts);
	if ($total_hosts) {
		foreach ($hosts as $host) {
			$alert_email = read_config_option('alert_email');

			if (api_plugin_is_enabled('maint')) {
				if (plugin_maint_check_cacti_host($host['id'])) {
					continue;
				}
			}

			$downtimemsg = get_timeinstate($host);

			$subject = read_config_option('thold_down_subject');
			if ($subject == '') {
				$subject = __('Devices Error: <DESCRIPTION> (<HOSTNAME>) is DOWN', 'thold');
			}

			$subject = str_replace('<HOSTNAME>', $host['hostname'], $subject);
			$subject = str_replace('<DESCRIPTION>', $host['description'], $subject);
			$subject = str_replace('<DOWN/UP>', __('DOWN', 'thold'), $subject);
			$subject = str_replace('<DOWNTIME>', $downtimemsg, $subject);
			$subject = strip_tags($subject);

			$msg = read_config_option('thold_down_text');
			if ($msg == '') {
				$msg = __('System Error : <DESCRIPTION> (<HOSTNAME>) is <DOWN/UP><br>Reason: <MESSAGE><br><br>Average system response : <AVG_TIME> ms<br>System availability: <AVAILABILITY><br>Total Checks Since Clear: <TOT_POLL><br>Total Failed Checks: <FAIL_POLL><br>Last Date Checked DOWN : <LAST_FAIL><br>Devices Previously UP for: <DOWNTIME><br>NOTE: <NOTES>', 'thold');
			}

			$msg = str_replace('<SUBJECT>', $subject, $msg);
			$msg = str_replace('<HOSTNAME>', $host['hostname'], $msg);
			$msg = str_replace('<HOST_ID>', $host['id'], $msg);
			$msg = str_replace('<DESCRIPTION>', $host['description'], $msg);
			$msg = str_replace('<UPTIME>', '', $msg);
			$msg = str_replace('<DOWNTIME>', $downtimemsg, $msg);
			$msg = str_replace('<MESSAGE>', $host['status_last_error'], $msg);
			$msg = str_replace('<DOWN/UP>', __('DOWN', 'thold'), $msg);
			$msg = str_replace('<SNMP_HOSTNAME>', '', $msg);
			$msg = str_replace('<SNMP_LOCATION>', '', $msg);
			$msg = str_replace('<SNMP_CONTACT>', '', $msg);
			$msg = str_replace('<SNMP_SYSTEM>', '', $msg);
			$msg = str_replace('<LAST_FAIL>', $host['status_fail_date'], $msg);
			$msg = str_replace('<AVAILABILITY>', round(($host['availability']), 2) . ' %', $msg);
			$msg = str_replace('<CUR_TIME>', round(($host['cur_time']), 2), $msg);
			$msg = str_replace('<TOT_POLL>', $host['total_polls'], $msg);
			$msg = str_replace('<FAIL_POLL>', $host['failed_polls'], $msg);
			$msg = str_replace('<AVG_TIME>', round(($host['avg_time']), 2), $msg);
			$msg = str_replace('<NOTES>', $host['notes'], $msg);
//
			$msg = str_replace('<TIME>', time(), $msg);
			$msg = str_replace('<DATE>', date(CACTI_DATE_TIME_FORMAT), $msg);
			$msg = str_replace('<DATE_RFC822>', date(DATE_RFC822), $msg);

			$msg = str_replace("\n", '<br>', $msg);

			switch ($host['thold_send_email']) {
				case '0': // Disabled
					$alert_email = '';
					break;
				case '1': // Global List
					break;
				case '2': // Devices List Only
					$alert_email = get_thold_notification_emails($host['thold_host_email']);
					break;
				case '3': // Global and Devices List
					$alert_email = $alert_email . ',' . get_thold_notification_emails($host['thold_host_email']);
					break;
			}

			api_plugin_hook_function(
				'thold_device_down',
				array(
					'device'  => $host,
					'subject' => $subject,
					'message' => $msg,
					'email' => $alert_email
				)
			);

			cacti_log('WARNING: Device[' . $host['id'] . '] Hostname[' . $host['hostname'] . '] is down!', true, 'THOLD');

			if ($alert_email == '' && $host['thold_send_email'] > 0) {
				cacti_log('WARNING: Device[' . $host['id'] . '] Hostname[' . $host['hostname'] . '] can not send a Device down email for \'' . $host['description'] . '\' since the \'Alert Email\' setting is not set for Device!', true, 'THOLD');
			} elseif ($host['thold_send_email'] == '0') {
				cacti_log('NOTE: Device[' . $host['id'] . '] Hostname[' . $host['hostname'] . '] did not send a Device down email for \'' . $host['description'] . '\', disabled per Device setting!', true, 'THOLD');
			} elseif ($alert_email != '') {
				thold_mail($alert_email, '', $subject, $msg, '');
			}

			$command = read_config_option('thold_device_command');

			if ($command != '') {
				putenv('THOLD_SUBJECT='      . $subject);
				putenv('THOLD_HOSTNAME='      . $host['hostname']);
				putenv('THOLD_HOST_ID='       . $host['id']);
				putenv('THOLD_DESCRIPTION='   . $host['description']);
				putenv('THOLD_TIME='          . time());
				putenv('THOLD_DATE='          . date(CACTI_DATE_TIME_FORMAT));
				putenv('THOLD_DATE_RFC822='   . date(DATE_RFC822));

				putenv('THOLD_UPTIME=');
				putenv('THOLD_DOWNTIME='      . $downtimemsg);
				putenv('THOLD_MESSAGE='       . $host['status_last_error']);
				putenv('THOLD_DOWNUP='        . 'DOWN');

				putenv('THOLD_SNMP_HOSTNAME=' . $host['snmp_sysName']);
				putenv('THOLD_SNMP_LOCATION=' . $host['snmp_sysLocation']);
				putenv('THOLD_SNMP_CONTACT='  . $host['snmp_sysContact']);
				putenv('THOLD_SNMP_SYSTEM=');
				putenv('THOLD_LAST_FAIL='     . $host['status_fail_date']);
				putenv('THOLD_AVAILABILITY='  . $host['availability']);
				putenv('THOLD_TOT_POLL='      . $host['total_polls']);
				putenv('THOLD_FAIL_POLL='     . $host['failed_polls']);
				putenv('THOLD_CUR_TIME='      . $host['cur_time']);
				putenv('THOLD_AVG_TIME='      . $host['avg_time']);
				putenv('THOLD_NOTES='         . $host['notes']);

				if (file_exists($command) && is_executable($command)) {
					$output = array();
					$return = 0;

					exec($command, $output, $return);

					cacti_log('Device Down Command for Device[' . $host['id'] . '] Command[' . $command . '] ExitStatus[' . $return . '] Output[' . implode(' ', $output) . ']', false, 'THOLD');
				} else {
					cacti_log('WARNING: Device Down Command for Device[' . $host['id'] . '] Command[' . $command . '] Is either Not found or Not executable!', false, 'THOLD');
				}
			}
		}
	}

	// Now lets record all failed hosts
	if (read_config_option('remote_storage_method') == 1) {
		db_execute_prepared('DELETE
			FROM plugin_thold_host_failed
			WHERE poller_id = ?',
			array($config['poller_id']));

		$hosts = db_fetch_assoc_prepared('SELECT id, status
			FROM host
			WHERE disabled = ""
			AND poller_id = ?
			AND ((status != ? AND status != ?)
			OR (status = ? AND status_event_count >= IF(thold_failure_count > 0, thold_failure_count, ?))) ',
			array($config['poller_id'], HOST_UP, HOST_DOWN, HOST_DOWN, $ping_failure_count));
	} else {
		db_execute('TRUNCATE plugin_thold_host_failed');

		$hosts = db_fetch_assoc_prepared('SELECT id, status
			FROM host
			WHERE disabled = ""
			AND ((status != ? AND status != ?)
			OR (status = ? AND status_event_count >= IF(thold_failure_count > 0, thold_failure_count, ?))) ',
			array(HOST_UP, HOST_DOWN, HOST_DOWN, $ping_failure_count));
	}

	if (cacti_sizeof($hosts)) {
		$failed_ids = '';

		foreach ($hosts as $host) {
			//hosts in recovery status record only if they was in failed status
			if (($host['status'] != HOST_RECOVERING) OR ($host['status'] == HOST_RECOVERING AND (array_search($host['id'], array_column($failed, 'host_id')) !== false))) {
				if (api_plugin_is_enabled('maint') && plugin_maint_check_cacti_host($host['id'])) {
					continue;
				}

				$failed_ids .= ($failed_ids != '' ? '), (':'(') . $host['id'];
			}
		}

		$failed_ids .= $failed_ids != '' ? ')':'';

 		if ($failed_ids != '') {
			db_execute("INSERT INTO plugin_thold_host_failed
				(host_id)
				VALUES $failed_ids");
		}
	}

	return $total_hosts;
}

