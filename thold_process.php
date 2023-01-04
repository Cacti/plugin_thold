<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2023 The Cacti Group                                 |
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

if (function_exists('pcntl_async_signals')) {
	pcntl_async_signals(true);
} else {
	declare(ticks = 100);
}

chdir(__DIR__);
chdir('../../');

require_once('./include/cli_check.php');
require_once($config['base_path'] . '/lib/rrd.php');
require($config['base_path'] . '/plugins/thold/includes/arrays.php');
require_once($config['base_path'] . '/plugins/thold/thold_functions.php');
require_once($config['library_path'] . '/snmp.php');

/* install signal handlers for Linux/UNIX only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

/* help with microtime(true) */
#ini_set('precision', 16);

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$thread = false;
$debug  = false;

global $thread, $cnn_id;

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list ($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-d':
			case '--debug':
				$debug = true;
				break;
			case '--thread':
				$thread = $value;

				if (!is_numeric($thread) || $thread <= 0) {
					print 'FATAL: The Thread ID must be numeric and greater than 0.' . PHP_EOL;
					display_help();
					exit(1);
				}
				break;
			case '-v':
			case '--version':
			case '-V':
				display_version();
				exit;
			case '--help':
			case '-h':
			case '-H':
				display_help();
				exit;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
		}
	}
}

// Record start time for the pid's processing
$start = microtime(true);

if ($thread === false) {
	print 'FATAL: The Thread ID must be numeric and greater than 0.' . PHP_EOL;
	display_help();
	exit(1);
}

$timeout = 99999999;

if (!register_process_start('thold', 'child', $thread, $timeout)) {
	$pid = db_fetch_cell_prepared('SELECT pid
		FROM processes
		WHERE tasktype = "thold"
		AND taskname = "child"
		AND taskid = ?',
		array($thread));

	if ($config['cacti_server_os'] == 'unix') {
        if (function_exists('posix_getpgid')) {
            $running = posix_getpgid($pid);
        } elseif (function_exists('posix_kill')) {
            $running = posix_kill($pid, 0);
        }

		if ($running) {
			exit(1);
		} else {
			unregister_process('thold', 'child', $thread);
			register_process('thold', 'child', $thread, $timeout);
		}
    }
}

db_close($cnn_id);

$cnn_id = thold_db_reconnect($cnn_id);

while (true) {
	if (thold_db_connection()) {
		// Prime the 'thold_daemon_debug' value
		$daemon_debug = read_config_option('thold_daemon_debug', true);

		// Fix issues with microtime skew
		usleep(1);

		$start_time = time();

		$tholds = thold_get_thresholds_precheck($thread, $start_time);

		$total_tholds = cacti_sizeof($tholds);

		thold_daemon_debug(sprintf('Found %u Thresholds to check for current values.', $total_tholds), $thread);

		if (cacti_sizeof($tholds)) {
			$rrd_reindexed      = array();
			$rrd_time_reindexed = array();

			thold_daemon_debug('Getting current values and normalizing multi-item Data Sources for Thresholds', $thread);

			foreach ($tholds as $thold_data) {
				$item = array();

				if (substr($thold_data['rrd_reindexed'], 0, 1) == 'a') {
					$rrd_reindexed[$thold_data['local_data_id']] = unserialize($thold_data['rrd_reindexed']);
				} else {
					$rrd_reindexed[$thold_data['local_data_id']] = json_decode($thold_data['rrd_reindexed'], true);
				}

				$rrd_time_reindexed[$thold_data['local_data_id']] = $thold_data['rrd_time_reindexed'];

				$currenttime = 0;
				$currentval  = thold_get_currentval($thold_data, $rrd_reindexed, $rrd_time_reindexed, $item, $currenttime);

				switch ($thold_data['data_type']) {
				case 0:
					break;
				case 1:
					if ($thold_data['cdef'] > 0) {
						$currentval = thold_build_cdef($thold_data['cdef'], $currentval, $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
					}
					break;
				case 2:
					if ($thold_data['percent_ds'] != '') {
						$currentval = thold_calculate_percent($thold_data, $currentval, $rrd_reindexed);
					}
					break;
				case 3:
					if ($thold_data['expression'] != '') {
						$currentval = thold_calculate_expression($thold_data, $currentval, $rrd_reindexed, $rrd_time_reindexed);
					}
					break;
				case 4:
					if ($thold_data['upper_ds'] != '') {
						$currentval = thold_calculate_lower_upper($thold_data, $currentval, $rrd_reindexed);
					}
					break;
				}

				if (is_numeric($currentval)) {
					$currentval = round($currentval, 4);
				} else {
					$currentval = '';
				}

				if (isset($item[$thold_data['name']])) {
					$lasttime = $item[$thold_data['name']];
				} else {
					$lasttime = $currenttime - $thold_data['rrd_step'];
				}

				thold_daemon_debug(sprintf('Checked Name:%s, Graph:%s, Value:%s, Time:%s', $thold_data['thold_name'], $thold_data['local_graph_id'], $currentval, $currenttime), $thread);

				db_execute_prepared('UPDATE thold_data
					SET tcheck = 1, lastread = ?,
					lasttime = FROM_UNIXTIME(?), oldvalue = ?
					WHERE id = ?',
					array($currentval, $currenttime, $lasttime, $thold_data['thold_id'])
				);
			}

			$tholds = thold_get_thresholds_tholdcheck($thread, $start_time);

			$total_tholds = cacti_sizeof($tholds);

			thold_daemon_debug(sprintf('Found %u Thresholds to check for breech.', $total_tholds), $thread);

			if ($total_tholds) {
				foreach ($tholds as $thold) {
					thold_check_threshold($thold);

					db_execute_prepared('UPDATE thold_data
						SET tcheck = 0
						WHERE id = ?',
						array($thold['id'])
					);
				}
			}

			$end = microtime(true);

			if (read_config_option('remote_storage_method') == 1) {
				db_execute_prepared('DELETE ptdd
					FROM plugin_thold_daemon_data AS ptdd
					INNER JOIN thold_data AS td
					ON ptdd.id = td.id
					WHERE ptdd.poller_id = ?
					AND td.thread_id = ?
					AND ptdd.time <= FROM_UNIXTIME(?)',
					array($config['poller_id'], $thread, $start_time));
			} else {
				db_execute_prepared('DELETE ptdd
					FROM plugin_thold_daemon_data AS ptdd
					INNER JOIN thold_data AS td
					ON ptdd.id = td.id
					WHERE td.thread_id = ?
					AND ptdd.time <= FROM_UNIXTIME(?)',
					array($thread, $start_time));
			}
		} else {
			sleep(5);

			$cnn_id = thold_db_reconnect($cnn_id);

			heartbeat_process('thold', 'child', $thread);
		}
	} else {
		thold_daemon_debug('WARNING: Thold Database Connection Down.  Sleeping 60 Seconds', $thread);
		sleep(60);
	}
}

/**
 * sig_handler - provides a generic means to catch exceptions to the Cacti log.
 *
 * @param $signo - (int) the signal that was thrown by the interface.
 *
 * @returns - null
 */
function sig_handler($signo) {
	global $thread;

	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			thold_cacti_log('WARNING: Thold Daemon Child Process with PID[' . getmypid() . '] terminated by user', $thread);
			unregister_process('thold', 'child', $thread);

			exit;
			break;
		default:
			/* ignore all other signals */
	}
}

function thold_daemon_debug($message, $thread) {
	global $debug;

	$daemon_debug = read_config_option('thold_daemon_debug');

	if ($debug || $daemon_debug) {
		thold_cacti_log($message, $thread);
	}
}

function thold_get_thresholds_tholdcheck($thread, $start_time) {
	global $config;

	/* check all thresholds */
	if (read_config_option('remote_storage_method') == 1) {
		$sql_query = "SELECT td.*, h.hostname,
			h.description, h.notes AS dnotes, h.snmp_engine_id
			FROM plugin_thold_daemon_data AS tdd
			INNER JOIN thold_data AS td
			ON td.id = tdd.id
			LEFT JOIN data_template_rrd AS dtr
			ON dtr.id = td.data_template_rrd_id
			LEFT JOIN host as h
			ON td.host_id = h.id
			WHERE td.thread_id = ?
			AND tdd.poller_id = ?
			AND tdd.time <= FROM_UNIXTIME(?)
			AND td.thold_enabled = 'on'
			AND td.tcheck = 1
			AND h.status = 3";

		$tholds = api_plugin_hook_function('thold_get_live_hosts',
			db_fetch_assoc_prepared($sql_query, array($thread, $config['poller_id'], $start_time))
		);
	} else {
		$sql_query = "SELECT td.*, h.hostname,
			h.description, h.notes AS dnotes, h.snmp_engine_id
			FROM plugin_thold_daemon_data AS tdd
			INNER JOIN thold_data AS td
			ON td.id = tdd.id
			LEFT JOIN data_template_rrd AS dtr
			ON dtr.id = td.data_template_rrd_id
			LEFT JOIN host as h
			ON td.host_id = h.id
			WHERE td.thread_id = ?
			AND tdd.time <= FROM_UNIXTIME(?)
			AND td.thold_enabled = 'on'
			AND td.tcheck = 1
			AND h.status = 3";

		$tholds = api_plugin_hook_function('thold_get_live_hosts',
			db_fetch_assoc_prepared($sql_query, array($thread, $start_time))
		);
	}

	return $tholds;
}

function thold_get_thresholds_precheck($thread, $start_time) {
	global $config;

	if (read_config_option('remote_storage_method') == 1) {
		$sql_query = "SELECT tdd.id, tdd.rrd_reindexed, tdd.rrd_time_reindexed,
			td.id AS thold_id, td.name_cache AS thold_name, td.local_graph_id,
			td.percent_ds, td.expression, td.upper_ds, td.data_type, td.cdef, td.local_data_id,
			td.data_template_rrd_id, td.lastread,
			UNIX_TIMESTAMP(td.lasttime) AS lasttime, td.oldvalue,
			dtr.data_source_name AS name, dtr.data_source_type_id,
			dtd.rrd_step, dtr.rrd_maximum
			FROM plugin_thold_daemon_data AS tdd
			INNER JOIN thold_data AS td
			ON td.id = tdd.id
			LEFT JOIN data_template_rrd AS dtr
			ON dtr.id = td.data_template_rrd_id
			LEFT JOIN data_template_data AS dtd
			ON dtd.local_data_id = td.local_data_id
			WHERE td.thread_id = ?
			AND tdd.poller_id = ?
			AND tdd.time <= FROM_UNIXTIME(?)
			AND dtr.data_source_name != ''";

		$tholds = db_fetch_assoc_prepared($sql_query, array($thread, $config['poller_id'], $start_time));
	} else {
		$sql_query = "SELECT tdd.id, tdd.rrd_reindexed, tdd.rrd_time_reindexed,
			td.id AS thold_id, td.name_cache AS thold_name, td.local_graph_id,
			td.percent_ds, td.expression, td.upper_ds, td.data_type, td.cdef, td.local_data_id,
			td.data_template_rrd_id, td.lastread,
			UNIX_TIMESTAMP(td.lasttime) AS lasttime, td.oldvalue,
			dtr.data_source_name AS name, dtr.data_source_type_id,
			dtd.rrd_step, dtr.rrd_maximum
			FROM plugin_thold_daemon_data AS tdd
			INNER JOIN thold_data AS td
			ON td.id = tdd.id
			LEFT JOIN data_template_rrd AS dtr
			ON dtr.id = td.data_template_rrd_id
			LEFT JOIN data_template_data AS dtd
			ON dtd.local_data_id = td.local_data_id
			WHERE td.thread_id = ?
			AND tdd.time <= FROM_UNIXTIME(?)
			AND dtr.data_source_name != ''";

		$tholds = db_fetch_assoc_prepared($sql_query, array($thread, $start_time));
	}

	return $tholds;
}

function thold_db_connection(){
	global $cnn_id;

	if (is_object($cnn_id)) {
		// Avoid showing errors
		restore_error_handler();
		set_error_handler('thold_error_handler');

		$cacti_version = db_fetch_cell('SELECT cacti FROM version');

		// Restore Cacti's Error handler
		restore_error_handler();
		set_error_handler('CactiErrorHandler');

		return is_null($cacti_version) ? false : true;
	}

	return false;
}

function thold_db_reconnect($cnn_id = null) {
	chdir(dirname(__FILE__));

	include('../../include/config.php');

	if (is_object($cnn_id)) {
		db_close($cnn_id);
	}

	// Avoid showing errors
	restore_error_handler();
	set_error_handler('thold_error_handler');

	// Connect to the database server
	$cnn_id = db_connect_real($database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port, $database_ssl);

	// Restore Cacti's Error handler
	restore_error_handler();
	set_error_handler('CactiErrorHandler');

	return $cnn_id;
}

function thold_cli_debug($string) {
	global $debug;

	if ($debug) {
		$output = date('Y-m-d H:i:s') . ' DEBUG: ' . trim($string);

		print $output . PHP_EOL;
	}
}

function display_version() {
	global $config;

	if (!function_exists('plugin_thold_version')) {
		include_once($config['base_path'] . '/plugins/thold/setup.php');
	}

	$info = plugin_thold_version();
	print 'Threshold Processor, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print PHP_EOL . 'usage: thold_process.php --thread=N [--debug]' . PHP_EOL . PHP_EOL;
	print 'The main Threshold Processor for the Thold Plugin.' . PHP_EOL;
}

