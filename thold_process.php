<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2019 The Cacti Group                                 |
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

/* tick use required as of PHP 4.3.0 to accomodate signal handling */
declare(ticks = 1);

/* sig_handler - provides a generic means to catch exceptions to the Cacti log.
   @arg $signo - (int) the signal that was thrown by the interface.
   @returns - null */
function sig_handler($signo) {
	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log('WARNING: Thold Sub Process terminated by user', FALSE, 'thold');

			exit;
			break;
		default:
			/* ignore all other signals */
	}
}

/* do NOT run this script through a web browser */
if (!isset($_SERVER['argv'][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die('<br><strong>This script is only meant to run at the command line.</strong>');
}

/* We are not talking to the browser */
$no_http_headers = true;

chdir(dirname(__FILE__));
chdir('../../');

require_once('./include/global.php');
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

$pid   = false;
$debug = false;

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
			case '-pid':
			case '--pid':
				$parts = explode('.', $value);

				if (isset($parts[0]) && isset($parts[1]) && is_numeric($parts[0]) && is_numeric($parts[1])) {
					$pid = $value;
				} else {
					print 'ERROR: Invalid Process ID ' . $arg . "\n\n";
					display_help();
					exit;
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
			exit;
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . "\n\n";
				display_help();
		}
	}
}

// Record start time for the pid's processing
$start = microtime(true);

if ($pid === false) {
	display_help();
} elseif (read_config_option('remote_storage_method') == 1) {
	db_execute_prepared('UPDATE plugin_thold_daemon_processes
		SET start = ?
		WHERE pid = ? AND poller_id = ?',
		array($start, $pid, $config['poller_id']));
} else {
	db_execute_prepared('UPDATE plugin_thold_daemon_processes
		SET start = ?
		WHERE pid = ?',
		array($start, $pid));
}

// Fix issues with microtime skew
usleep(1);

if (read_config_option('remote_storage_method') == 1) {
	$sql_query = "SELECT tdd.id, tdd.rrd_reindexed, tdd.rrd_time_reindexed,
		td.id AS thold_id, td.name_cache AS thold_name, td.local_graph_id,
		td.percent_ds, td.expression, td.data_type, td.cdef, td.local_data_id,
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
		WHERE tdd.pid = ?
		AND tdd.poller_id = ?
		AND dtr.data_source_name != ''";

	$tholds = db_fetch_assoc_prepared($sql_query, array($pid, $config['poller_id']), false);
} else {
	$sql_query = "SELECT tdd.id, tdd.rrd_reindexed, tdd.rrd_time_reindexed,
		td.id AS thold_id, td.name_cache AS thold_name, td.local_graph_id,
		td.percent_ds, td.expression, td.data_type, td.cdef, td.local_data_id,
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
		WHERE tdd.pid = ?
		AND dtr.data_source_name != ''";

	$tholds = db_fetch_assoc_prepared($sql_query, array($pid), false);
}

if (cacti_sizeof($tholds)) {
	$rrd_reindexed = array();
	$rrd_time_reindexed = array();

	foreach ($tholds as $thold_data) {
		thold_debug("Checking Threshold Name: '" . $thold_data['thold_name'] . "', Graph: '" . $thold_data['local_graph_id'] . "'");

		$item = array();
		$rrd_reindexed[$thold_data['local_data_id']] = unserialize($thold_data['rrd_reindexed']);
		$rrd_time_reindexed[$thold_data['local_data_id']] = $thold_data['rrd_time_reindexed'];
		$currenttime = 0;
		$currentval = thold_get_currentval($thold_data, $rrd_reindexed, $rrd_time_reindexed, $item, $currenttime);

		switch ($thold_data['data_type']) {
		case 0:
			break;
		case 1:
			if ($thold_data['cdef'] != 0) {
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

		if ($thold_data['data_type'] == 1 && !empty($thold_data['cdef'])) {
			$lasttime = thold_build_cdef($thold_data['cdef'], $lasttime, $thold_data['local_data_id'], $thold_data['data_template_rrd_id']);
		}

		db_execute_prepared('UPDATE thold_data
			SET tcheck = 1, lastread = ?, lasttime = ?, oldvalue = ?
			WHERE id = ?',
			array($currentval, date('Y-m-d H:i:s', $currenttime),  $lasttime, $thold_data['thold_id'])
		);
	}

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
			WHERE tdd.pid = ?
			AND tdd.poller_id = ?
			AND td.thold_enabled = 'on'
			AND td.tcheck = 1 AND h.status=3";

		$tholds = api_plugin_hook_function(
			'thold_get_live_hosts',
			db_fetch_assoc_prepared($sql_query,
				array($pid, $config['poller_id']))
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
			WHERE tdd.pid = ?
			AND td.thold_enabled = 'on'
			AND td.tcheck = 1 AND h.status=3";

		$tholds = api_plugin_hook_function(
			'thold_get_live_hosts',
			db_fetch_assoc_prepared($sql_query,
				array($pid))
		);
	}

	$total_tholds = sizeof($tholds);
	if (cacti_sizeof($tholds)) {
		foreach ($tholds as $thold) {
			thold_check_threshold($thold);
		}
	}

	db_execute_prepared('UPDATE thold_data
		SET thold_data.thold_daemon_pid = "", tcheck=0
		WHERE thold_data.thold_daemon_pid = ?',
		array($pid)
	);

	$end = microtime(true);

	if (read_config_option('remote_storage_method') == 1) {
		db_execute_prepared('DELETE FROM plugin_thold_daemon_data
			WHERE pid = ?
			AND poller_id = ?',
			array($pid, $config['poller_id']));

		db_execute_prepared('UPDATE plugin_thold_daemon_processes
			SET start = ?, end = ?, processed_items = ?
			WHERE pid = ?
			AND poller_id = ?',
			array($start, $end, $total_tholds, $pid, $config['poller_id']));
	} else {
		db_execute_prepared('DELETE FROM plugin_thold_daemon_data
			WHERE pid = ?',
			array($pid));

		db_execute_prepared('UPDATE plugin_thold_daemon_processes
			SET start = ?, end = ?, processed_items = ?
			WHERE pid = ?',
			array($start, $end, $total_tholds, $pid));
	}
}

function display_version() {
	global $config;

	if (!function_exists('plugin_thold_version')) {
		include_once($config['base_path'] . '/plugins/thold/setup.php');
	}

	$info = plugin_thold_version();
	print "Threshold Processor, Version " . $info['version'] . ", " . COPYRIGHT_YEARS . "\n";
}

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print "\nusage: thold_process.php --pid=N [--debug]\n\n";
	print "The main Threshold processor for the Thold Plugin.\n";
}

