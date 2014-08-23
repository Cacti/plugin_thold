<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2011 The Cacti Group                                      |
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
	if(!read_config_option("thold_daemon_enable")) {
	
	/* record the start time */
	list($micro,$seconds) = split(" ", microtime());
	$start = $seconds + $micro;

	/* perform all thold checks */
	$tholds = thold_check_all_thresholds ();
	$nhosts = thold_update_host_status ();
	thold_cleanup_log ();

	/* record the end time */
	list($micro,$seconds) = split(" ", microtime());
	$end = $seconds + $micro;

	$total_hosts = db_fetch_cell("SELECT count(*) FROM host WHERE disabled=''");
	$down_hosts  = db_fetch_cell("SELECT count(*) FROM host WHERE status=1 AND disabled=''");

	/* log statistics */
	$thold_stats = sprintf("Time:%01.4f Tholds:%s TotalHosts:%s DownHosts:%s NewDownHosts:%s", $end - $start, $tholds, $total_hosts, $down_hosts, $nhosts);
	cacti_log('THOLD STATS: ' . $thold_stats, false, 'SYSTEM');
	db_execute("REPLACE INTO settings (name, value) VALUES ('stats_thold', '$thold_stats')");
	}else {
		/* collect some stats */
		$current_time = time();
		$max_concurrent_processes = read_config_option("thold_max_concurrent_processes");
		$stats = db_fetch_row("SELECT 
									COUNT(*) as completed, 
									SUM(processed_items) as processed_items, 
									MAX(`end`-`start`) as max_processing_time,
									SUM(`end`-`start`) as total_processing_time 
								FROM `plugin_thold_daemon_processes` 
								WHERE `start` != 0 AND `end` != 0 AND `end` <=" . $current_time . " AND `processed_items` != '-1'");
								
		$broken_processes = db_fetch_cell("SELECT COUNT(*) FROM `plugin_thold_daemon_processes` WHERE `processed_items` = '-1'");
		$running_processes = db_fetch_cell("SELECT COUNT(*) FROM `plugin_thold_daemon_processes` WHERE `start` != 0 AND `end` = 0");

		/* system clean up */
		db_execute("DELETE FROM `plugin_thold_daemon_processes` WHERE `end` != 0 AND `end` <=" . $current_time);
		
		/* host_status processed by thold server */
		$nhosts = thold_update_host_status ();
		thold_cleanup_log ();
		
		$total_hosts = db_fetch_cell("SELECT count(*) FROM host WHERE disabled=''");
		$down_hosts  = db_fetch_cell("SELECT count(*) FROM host WHERE status=1 AND disabled=''");
		
		/* log statistics */
		$thold_stats = sprintf("CPUTime:%u MaxRuntime:%u Tholds:%u TotalHosts:%u DownHosts:%u NewDownHosts:%u Processes: %u completed, %u running, %u broken", $stats['total_processing_time'], $stats['max_processing_time'], $stats['processed_items'], $total_hosts, $down_hosts, $nhosts, $stats['completed'], $running_processes, $broken_processes);
		cacti_log('THOLD STATS: ' . $thold_stats, false, 'SYSTEM');
		db_execute("REPLACE INTO settings (name, value) VALUES ('stats_thold', '$thold_stats')");
	}
}

function thold_cleanup_log () {
	$daysToStoreLogs = read_config_option('thold_log_storage');
	$t = time() - (86400 * $daysToStoreLogs); // Delete Logs over a month old
	db_execute("DELETE FROM plugin_thold_log WHERE time<$t");
}

function thold_poller_output (&$rrd_update_array) {
	global $config, $debug;
	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
	include_once($config['library_path'] . '/snmp.php');

	$rrd_reindexed = array();
	$rrd_time_reindexed = array();
	$rra_ids = '';
	$x = 0;
	foreach($rrd_update_array as $item) {
		if (isset($item['times'][key($item['times'])])) {
			if ($x) {
				$rra_ids .= ',';
			}
			$rra_ids .= $item['local_data_id'];
			$rrd_reindexed[$item['local_data_id']] = $item['times'][key($item['times'])];
			$rrd_time_reindexed[$item['local_data_id']] = key($item['times']);
			$x++;
		}
	}

	if ($rra_ids != '') {

		if(read_config_option("thold_daemon_enable")) {
		
			/* assign a new process id */
			$thold_pid = time() . '_' . rand();
		
			$thold_items = db_fetch_assoc("SELECT id, rra_id FROM thold_data WHERE thold_daemon_pid = '' AND thold_data.rra_id IN ($rra_ids)");

			if($thold_items) {
				/* avoid that concurrent processes will work on the same thold items */
				db_execute("UPDATE thold_data SET thold_data.thold_daemon_pid = '$thold_pid' WHERE thold_daemon_pid = '' AND thold_data.rra_id IN ($rra_ids);");
			
				/* cache required polling data. prefer bulk inserts for performance reasons - start with chunks of 1000 items*/
				$sql_max_inserts = 1000;
				$thold_items = array_chunk($thold_items, $sql_max_inserts);
				
				$sql_insert = "INSERT INTO `plugin_thold_daemon_data` ( id, pid, rrd_reindexed, rrd_time_reindexed ) VALUES ";
				$sql_values = "";
				foreach($thold_items as $packet) {
					foreach($packet as $thold_item) {
						$sql_values .= "('" . $thold_item['id'] . "','" . $thold_pid . "','" . serialize($rrd_reindexed[$thold_item['rra_id']]) . "','" . $rrd_time_reindexed[$thold_item['rra_id']] . "'),";
					}
					db_execute($sql_insert . substr($sql_values, 0, -1));
					$sql_values = "";
				}

				/* queue a new thold process */
				db_execute("INSERT INTO `plugin_thold_daemon_processes` ( pid ) VALUES('$thold_pid')");	
			}
			return $rrd_update_array;
		}
	
		/* hold data of all CDEFs in memory to reduce the number of SQL queries to minimum */
		$cdefs = array();
		$cdefs_tmp = db_fetch_assoc("SELECT cdef_id, sequence, type, value FROM cdef_items ORDER BY cdef_id, sequence");
		if($cdefs_tmp & sizeof($cdefs_tmp)>0) {
			foreach($cdefs_tmp as $cdef_tmp) {
				$cdefs[$cdef_tmp["cdef_id"]][] = $cdef_tmp;
			}
		}
		unset($cdefs_tmp);
	
		$thold_items = db_fetch_assoc("SELECT thold_data.id, thold_data.name AS thold_name, thold_data.graph_id,
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
			ON ( data_template_data.local_data_id = thold_data.rra_id )
			WHERE data_template_rrd.data_source_name!=''
			AND thold_data.rra_id IN($rra_ids)", false);
	} else {
		return $rrd_update_array;
	}

	if (sizeof($thold_items)) {
	foreach ($thold_items as $t_item) {
		thold_debug("Checking Threshold:'" . $t_item["thold_name"] . "', Graph:'" . $t_item["graph_id"] . "'");
		$item = array();
		$currenttime = 0;
		$currentval = thold_get_currentval($t_item, $rrd_reindexed, $rrd_time_reindexed, $item, $currenttime);

		switch ($t_item['data_type']) {
		case 0:
			break;
		case 1:
			if ($t_item['cdef'] != 0) {
					$currentval = thold_build_cdef( $cdefs[$t_item['cdef']], $currentval, $t_item['rra_id'], $t_item['data_id']);
			}
			break;
		case 2:
			if ($t_item['percent_ds'] != '') {
				$currentval = thold_calculate_percent($t_item, $currentval, $rrd_reindexed);
			}
			break;
		case 3:
			if ($t_item['expression'] != '') {
				$currentval = thold_calculate_expression($t_item, $currentval, $rrd_reindexed, $rrd_time_reindexed);
			}
			break;
		}

		if (is_numeric($currentval)) {
			$currentval = round($currentval, 4);
		}else{
			$currentval = '';
		}

		db_execute("UPDATE thold_data SET tcheck=1, lastread='$currentval',
			lasttime='" . date("Y-m-d H:i:s", $currenttime) . "',
			oldvalue='" . $item[$t_item['name']] . "'
			WHERE rra_id = " . $t_item['rra_id'] . "
			AND data_id = " . $t_item['data_id']);
	}
	}

	return $rrd_update_array;
}

function thold_check_all_thresholds () {
	global $config;
	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	$sql_query = "SELECT
					thold_data.data_id,
					thold_data.rra_id,
					thold_data.lastread,
					thold_data.cdef,
					data_template_rrd.data_source_name
				FROM thold_data
				LEFT JOIN data_template_rrd ON
					data_template_rrd.id = thold_data.data_id
				WHERE thold_data.thold_enabled='on' AND thold_data.tcheck=1";

	$tholds = do_hook_function('thold_get_live_hosts', db_fetch_assoc($sql_query));
	$total_tholds = sizeof($tholds);
	foreach ($tholds as $thold) {
		thold_check_threshold ($thold['rra_id'], $thold['data_id'], $thold['data_source_name'], $thold['lastread'], $thold['cdef']);
	}
	db_execute('UPDATE thold_data SET tcheck=0');

	return $total_tholds;
}

function thold_update_host_status () {
	global $config;
	// Return if we aren't set to notify
	$deadnotify = (read_config_option('alert_deadnotify') == 'on');
	if (!$deadnotify) return 0;
	include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

	if (api_plugin_is_enabled('maint')) {
		include_once($config["base_path"] . '/plugins/maint/functions.php');
	}

	$alert_email = read_config_option('alert_email');
	$ping_failure_count = read_config_option('ping_failure_count');

	// Lets find hosts that were down, but are now back up
	$failed = db_fetch_assoc("SELECT * FROM plugin_thold_host_failed");
	if (sizeof($failed)) {
		foreach($failed as $fh) {
			if (!empty($fh['host_id'])) {
				if (api_plugin_is_enabled('maint')) {
					if (plugin_maint_check_cacti_host ($fh['host_id'])) {
						continue;
					}
				}
				$host = db_fetch_row('SELECT * FROM host WHERE id = ' . $fh['host_id']);
				if ($host['status'] == HOST_UP) {
					$snmp_system = '';
					$snmp_hostname = '';
					$snmp_location = '';
					$snmp_contact = '';
					$snmp_uptime = '';
					$uptimelong = '';
					$downtimemsg = '';

					if (($host["snmp_community"] == "" && $host["snmp_username"] == "") || $host["snmp_version"] == 0) {
						// SNMP not in use
						$snmp_system = 'SNMP not in use';
					} else {
						$snmp_system = cacti_snmp_get($host["hostname"], $host["snmp_community"], ".1.3.6.1.2.1.1.1.0", $host["snmp_version"],
										$host["snmp_username"], $host["snmp_password"],
										$host["snmp_auth_protocol"], $host["snmp_priv_passphrase"], $host["snmp_priv_protocol"],
										$host["snmp_context"], $host["snmp_port"], $host["snmp_timeout"], read_config_option("snmp_retries"), SNMP_WEBUI);

						if (substr_count($snmp_system, "00:")) {
							$snmp_system = str_replace("00:", "", $snmp_system);
							$snmp_system = str_replace(":", " ", $snmp_system);
						}
						if ($snmp_system != "") {
							$snmp_uptime   = cacti_snmp_get($host['hostname'], $host['snmp_community'], ".1.3.6.1.2.1.1.3.0", $host['snmp_version'],
											$host['snmp_username'], $host['snmp_password'],
											$host['snmp_auth_protocol'], $host['snmp_priv_passphrase'], $host['snmp_priv_protocol'],
											$host['snmp_context'], $host['snmp_port'], $host['snmp_timeout'], read_config_option("snmp_retries"), SNMP_WEBUI);
							 $snmp_hostname = cacti_snmp_get($host["hostname"], $host["snmp_community"], ".1.3.6.1.2.1.1.5.0", $host["snmp_version"],
											$host["snmp_username"], $host["snmp_password"],
											$host["snmp_auth_protocol"], $host["snmp_priv_passphrase"], $host["snmp_priv_protocol"],
											$host["snmp_context"], $host["snmp_port"], $host["snmp_timeout"], read_config_option("snmp_retries"), SNMP_WEBUI);
							 $snmp_location = cacti_snmp_get($host["hostname"], $host["snmp_community"], ".1.3.6.1.2.1.1.6.0", $host["snmp_version"],
											$host["snmp_username"], $host["snmp_password"],
											$host["snmp_auth_protocol"], $host["snmp_priv_passphrase"], $host["snmp_priv_protocol"],
											$host["snmp_context"], $host["snmp_port"], $host["snmp_timeout"], read_config_option("snmp_retries"), SNMP_WEBUI);
							 $snmp_contact  = cacti_snmp_get($host["hostname"], $host["snmp_community"], ".1.3.6.1.2.1.1.4.0", $host["snmp_version"],
											$host["snmp_username"], $host["snmp_password"],
											$host["snmp_auth_protocol"], $host["snmp_priv_passphrase"], $host["snmp_priv_protocol"],
											$host["snmp_context"], $host["snmp_port"], $host["snmp_timeout"], read_config_option("snmp_retries"), SNMP_WEBUI);
							$days      = intval($snmp_uptime / (60*60*24*100));
							$remainder = $snmp_uptime % (60*60*24*100);
							$hours     = intval($remainder / (60*60*100));
							$remainder = $remainder % (60*60*100);
							$minutes   = intval($remainder / (60*100));
							$uptimelong = $days . "d " . $hours . "h " . $minutes . "m";
						}
						$downtime = time() - strtotime($host['status_fail_date']);
						$downtime_days = floor($downtime/86400);
						$downtime_hours = floor(($downtime - ($downtime_days * 86400))/3600);
						$downtime_minutes = floor(($downtime - ($downtime_days * 86400) - ($downtime_hours * 3600))/60);
						$downtime_seconds = $downtime - ($downtime_days * 86400) - ($downtime_hours * 3600) - ($downtime_minutes * 60);
						if ($downtime_days > 0 ) {
							$downtimemsg = $downtime_days . "d " . $downtime_hours . "h " . $downtime_minutes . "m " . $downtime_seconds . "s ";
						} elseif ($downtime_hours > 0 ) {
							$downtimemsg = $downtime_hours . "h " . $downtime_minutes . "m " . $downtime_seconds . "s";
						} elseif ($downtime_minutes > 0 ) {
							$downtimemsg = $downtime_minutes . "m " . $downtime_seconds . "s";
						} else {
							$downtimemsg = $downtime_seconds . "s ";
						}
					}


					$subject = read_config_option('thold_up_subject');
					if ($subject == '') {
						$subject = 'Host Notice: <DESCRIPTION> (<HOSTNAME>) returned from DOWN state';
					}
					$subject = str_replace('<HOSTNAME>', $host['hostname'], $subject);
					$subject = str_replace('<DESCRIPTION>', $host['description'], $subject);
					$subject = str_replace('<DOWN/UP>', 'UP', $subject);
					$subject = strip_tags($subject);

					$msg = read_config_option('thold_up_text');
					if ($msg == '') {
						$msg = '<br>System <DESCRIPTION> (<HOSTNAME>) status: <DOWN/UP><br><br>Current ping response: <CUR_TIME> ms<br>Average system response : <AVG_TIME> ms<br>System availability: <AVAILABILITY><br>Total Checks Since Clear: <TOT_POLL><br>Total Failed Checks: <FAIL_POLL><br>Last Date Checked UP: <LAST_FAIL><br>Host Previously DOWN for: <DOWNTIME><br><br>Snmp Info:<br>Name - <SNMP_HOSTNAME><br>Location - <SNMP_LOCATION><br>Uptime - <UPTIMETEXT> (<UPTIME> ms)<br>System - <SNMP_SYSTEM><br><br>NOTE: <NOTES>';
					}
					$msg = str_replace('<SUBJECT>', $subject, $msg);
					$msg = str_replace('<HOSTNAME>', $host['hostname'], $msg);
					$msg = str_replace('<DESCRIPTION>', $host['description'], $msg);
					$msg = str_replace('<UPTIME>', $snmp_uptime, $msg);
					$msg = str_replace('<UPTIMETEXT>', $uptimelong, $msg);

					$msg = str_replace('<DOWNTIME>', $downtimemsg, $msg);
					$msg = str_replace('<MESSAGE>', '', $msg);
					$msg = str_replace('<DOWN/UP>', 'UP', $msg);

					$msg = str_replace('<SNMP_HOSTNAME>', $snmp_hostname, $msg);
					$msg = str_replace('<SNMP_LOCATION>', $snmp_location, $msg);
					$msg = str_replace('<SNMP_CONTACT>', $snmp_contact, $msg);
					$msg = str_replace('<SNMP_SYSTEM>', html_split_string($snmp_system), $msg);
					$msg = str_replace('<LAST_FAIL>', $host["status_fail_date"], $msg);
					$msg = str_replace('<AVAILABILITY>', round(($host["availability"]), 2) . ' %', $msg);
					$msg = str_replace('<TOT_POLL>', $host["total_polls"], $msg);
					$msg = str_replace('<FAIL_POLL>', $host["failed_polls"], $msg);
					$msg = str_replace('<CUR_TIME>', round(($host["cur_time"]), 2), $msg);
					$msg = str_replace('<AVG_TIME>', round(($host["avg_time"]), 2), $msg);
					$msg = str_replace('<NOTES>', $host["notes"], $msg);
					$msg = str_replace("\n", '<br>', $msg);
					switch($host['thold_send_email']) {
						case '0': // Disabled
							$alert_email = '';
							break;
						case '1': // Global List
							break;
						case '2': // Host List Only
							$alert_email = get_thold_notification_emails($host['thold_host_email']);
							break;
						case '3': // Global and Host List
							$alert_email = $alert_email . ',' . get_thold_notification_emails($host['thold_host_email']);
							break;
					}

					if ($alert_email == '' && $host['thold_send_email'] > 0) {
						cacti_log('THOLD: Can not send a Host Recovering Email since the \'Alert Email\' setting is not set !', true, 'POLLER');
					} elseif ($host['thold_send_email'] == '0') {
						cacti_log('THOLD: Did not send a Host Recovering Email, disabled per host setting : ' . $host['description'] . ' !', true, 'POLLER');
					} elseif ($alert_email != '') {
						thold_mail($alert_email, '', $subject, $msg, '');
					}
				}
			}
		}
	}

	// Lets find hosts that are down
	$hosts = db_fetch_assoc('SELECT *
		FROM host
		WHERE disabled=""
		AND status=' . HOST_DOWN . '
		AND status_event_count=' . $ping_failure_count);

	$total_hosts = sizeof($hosts);
	if (count($hosts)) {
		foreach($hosts as $host) {
			if (api_plugin_is_enabled('maint')) {
				if (plugin_maint_check_cacti_host ($host['id'])) {
					continue;
				}
			}

			$downtime = time() - strtotime($host['status_rec_date']);
			$downtime_days = floor($downtime/86400);
			$downtime_hours = floor(($downtime - ($downtime_days * 86400))/3600);
			$downtime_minutes = floor(($downtime - ($downtime_days * 86400) - ($downtime_hours * 3600))/60);
			$downtime_seconds = $downtime - ($downtime_days * 86400) - ($downtime_hours * 3600) - ($downtime_minutes * 60);
			if ($downtime_days > 0 ) {
				$downtimemsg = $downtime_days . "d " . $downtime_hours . "h " . $downtime_minutes . "m " . $downtime_seconds . "s ";
			} elseif ($downtime_hours > 0 ) {
				$downtimemsg = $downtime_hours . "h " . $downtime_minutes . "m " . $downtime_seconds . "s";
			} elseif ($downtime_minutes > 0 ) {
				$downtimemsg = $downtime_minutes . "m " . $downtime_seconds . "s";
			} else {
				$downtimemsg = $downtime_seconds . "s ";
			}

			$subject = read_config_option('thold_down_subject');
			if ($subject == '') {
				$subject = 'Host Error: <DESCRIPTION> (<HOSTNAME>) is DOWN';
			}
			$subject = str_replace('<HOSTNAME>', $host['hostname'], $subject);
			$subject = str_replace('<DESCRIPTION>', $host['description'], $subject);
			$subject = str_replace('<DOWN/UP>', 'DOWN', $subject);
			$subject = strip_tags($subject);

			$msg = read_config_option('thold_down_text');
			if ($msg == '') {
				$msg = 'System Error : <DESCRIPTION> (<HOSTNAME>) is <DOWN/UP><br>Reason: <MESSAGE><br><br>Average system response : <AVG_TIME> ms<br>System availability: <AVAILABILITY><br>Total Checks Since Clear: <TOT_POLL><br>Total Failed Checks: <FAIL_POLL><br>Last Date Checked DOWN : <LAST_FAIL><br>Host Previously UP for: <DOWNTIME><br>NOTE: <NOTES>';
			}
			$msg = str_replace('<SUBJECT>', $subject, $msg);
			$msg = str_replace('<HOSTNAME>', $host['hostname'], $msg);
			$msg = str_replace('<DESCRIPTION>', $host['description'], $msg);
			$msg = str_replace('<UPTIME>', '', $msg);
			$msg = str_replace('<DOWNTIME>', $downtimemsg, $msg);
			$msg = str_replace('<MESSAGE>', $host['status_last_error'], $msg);
			$msg = str_replace('<DOWN/UP>', 'DOWN', $msg);
			$msg = str_replace('<SNMP_HOSTNAME>', '', $msg);
			$msg = str_replace('<SNMP_LOCATION>', '', $msg);
			$msg = str_replace('<SNMP_CONTACT>', '', $msg);
			$msg = str_replace('<SNMP_SYSTEM>', '', $msg);
			$msg = str_replace('<LAST_FAIL>', $host["status_fail_date"], $msg);
			$msg = str_replace('<AVAILABILITY>', round(($host["availability"]), 2) . ' %', $msg);
			$msg = str_replace('<CUR_TIME>', round(($host["cur_time"]), 2), $msg);
			$msg = str_replace('<TOT_POLL>', $host["total_polls"], $msg);
			$msg = str_replace('<FAIL_POLL>', $host["failed_polls"], $msg);
			$msg = str_replace('<AVG_TIME>', round(($host["avg_time"]), 2), $msg);
			$msg = str_replace('<NOTES>', $host["notes"], $msg);
			$msg = str_replace("\n", '<br>', $msg);
			switch($host['thold_send_email']) {
				case '0': // Disabled
					$alert_email = '';
					break;
				case '1': // Global List
					break;
				case '2': // Host List Only
					$alert_email = get_thold_notification_emails($host['thold_host_email']);
					break;
				case '3': // Global and Host List
					$alert_email = $alert_email . ',' . get_thold_notification_emails($host['thold_host_email']);
					break;
			}

			if ($alert_email == '' && $host['thold_send_email'] > 0) {
				cacti_log('THOLD: Can not send a Host Down Email since the \'Alert Email\' setting is not set !', true, 'POLLER');
			} elseif ($host['thold_send_email'] == '0') {
				cacti_log('THOLD: Did not send a Host Down Email, disabled per host setting : ' . $host['description'] . ' !', true, 'POLLER');
			} elseif ($alert_email != '') {
				thold_mail($alert_email, '', $subject, $msg, '');
			}
		}
	}

	// Now lets record all failed hosts
	db_execute("TRUNCATE TABLE plugin_thold_host_failed");
	$hosts = db_fetch_assoc('SELECT id
		FROM host
		WHERE disabled=""
		AND status!=' . HOST_UP);

	$failed = '';
	if (sizeof($hosts)) {
		foreach ($hosts as $host) {
			if (api_plugin_is_enabled('maint')) {
				if (plugin_maint_check_cacti_host ($host['id'])) {
					continue;
				}
			}
			$failed .= (strlen($failed) ? "), (":"(") . $host['id'];
		}
		$failed .= ")";

		db_execute("INSERT INTO plugin_thold_host_failed (host_id) VALUES $failed");
	}

	return $total_hosts;
}
