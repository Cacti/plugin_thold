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
	include_once($config['library_path'] . '/snmp.php');

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
		$thold_items = db_fetch_assoc("SELECT thold_data.percent_ds, thold_data.data_type, thold_data.cdef, thold_data.rra_id, thold_data.data_id, thold_data.lastread, thold_data.oldvalue, data_template_rrd.data_source_name as name, data_template_rrd.data_source_type_id, data_template_data.rrd_step
							FROM thold_data
							LEFT JOIN data_template_rrd on (data_template_rrd.id = thold_data.data_id)
							LEFT JOIN data_template_data ON ( data_template_data.local_data_id = thold_data.rra_id )
							WHERE data_template_rrd.data_source_name != '' AND thold_data.rra_id IN ($rra_ids)", false);
	} else {
		return $rrd_update_array;
	}
	if (sizeof($thold_items)) {
	foreach ($thold_items as $t_item) {
		$polling_interval = $t_item['rrd_step'];
		if (isset($rrd_update_array_reindexed[$t_item['rra_id']])) {
			$item = $rrd_update_array_reindexed[$t_item['rra_id']];
			if (isset($item[$t_item['name']])) {
				switch ($t_item['data_source_type_id']) {
					case 2:	// COUNTER
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
				}
				db_execute("UPDATE thold_data SET tcheck = 1, lastread = '$currentval', oldvalue = '" . $item[$t_item['name']] . "' WHERE rra_id = " . $t_item['rra_id'] . " AND data_id = " . $t_item['data_id']);
				//thold_check_threshold ($t_item['rra_id'], $t_item['data_id'], $t_item['name'], $currentval, $t_item['cdef']);
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

	if (api_plugin_is_enabled('maint')) {
		include_once($config["base_path"] . '/plugins/maint/functions.php');
	}

	$alert_email = read_config_option('alert_email');
	$ping_failure_count = read_config_option('ping_failure_count');
	// Lets find hosts that were down, but are now back up
	$failed = read_config_option('thold_failed_hosts', true);
	$failed = explode(',', $failed);
	if (!empty($failed)) {
		foreach($failed as $id) {
			if ($id != '') {
				if (api_plugin_is_enabled('maint')) {
					if (plugin_maint_check_cacti_host ($id)) {
						continue;
					}
				}
				$host = db_fetch_row('SELECT * FROM host WHERE id = ' . $id);
				if ($host['status'] == HOST_UP) {
					$snmp_system = '';
					$snmp_hostname = '';
					$snmp_location = '';
					$snmp_contact = '';
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
						$msg = $msg . "<br><br>Host was down for ";
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

					$msg = read_config_option('thold_down_text');
					if ($msg == '') {
						$msg = 'Host: <DESCRIPTION> (<HOSTNAME>)<br>Status: <DOWN/UP><br>Message: <MESSAGE><br><br>Uptime: <UPTIMETEXT><br>Availiability: <AVAILABILITY><br>Response: <CUR_TIME> ms<br>Down Since: <LAST_FAIL><br>NOTE: <NOTES>';
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
					$msg = str_replace('<AVAILABILITY>', $host["availability"] . '%', $msg);
					$msg = str_replace('<CUR_TIME>', $host["cur_time"], $msg);
					$msg = str_replace('<AVR_TIME>', $host["avg_time"], $msg);
					$msg = str_replace('<NOTES>', $host["notes"], $msg);
					$msg = str_replace("\n", '<br>', $msg);
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
	$hosts = db_fetch_assoc('SELECT * FROM host WHERE disabled="" AND status=' . HOST_DOWN . ' AND status_event_count=' . $ping_failure_count);
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

			$subject = read_config_option('thold_up_subject');
			if ($subject == '') {
				$subject = 'Host Error: <DESCRIPTION> (<HOSTNAME>) is DOWN';
			}
			$subject = str_replace('<HOSTNAME>', $host['hostname'], $subject);
			$subject = str_replace('<DESCRIPTION>', $host['description'], $subject);
			$subject = str_replace('<DOWN/UP>', 'UP', $subject);
			$subject = strip_tags($subject);

			$msg = read_config_option('thold_down_text');
			if ($msg == '') {
				$msg = 'Host: <DESCRIPTION> (<HOSTNAME>)<br>Status: <DOWN/UP><br>Message: <MESSAGE><br><br>Uptime:<UPTIMETEXT><br>Availiability: <AVAILABILITY><br>Response: <CUR_TIME> ms<br>Down Since: <LAST_FAIL><br>NOTE: <NOTES>';
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
			$msg = str_replace('<AVAILABILITY>', $host["availability"], $msg);
			$msg = str_replace('<CUR_TIME>', '', $msg);
			$msg = str_replace('<AVR_TIME>', $host["avg_time"], $msg);
			$msg = str_replace('<NOTES>', $host["notes"], $msg);
			$msg = str_replace("\n", '<br>', $msg);
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
			if (api_plugin_is_enabled('maint')) {
				if (plugin_maint_check_cacti_host ($host['id'])) {
					continue;
				}
			}
			$failed[] = $host['id'];
		}
	}
	$failed = implode(',', $failed);
	db_execute("REPLACE INTO settings (name, value) VALUES ('thold_failed_hosts', '$failed')");

	return $total_hosts;
}