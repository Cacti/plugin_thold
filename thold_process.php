<?php
/*
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

/* tick use required as of PHP 4.3.0 to accomodate signal handling */
declare(ticks = 1);

/* sig_handler - provides a generic means to catch exceptions to the Cacti log.
   @arg $signo - (int) the signal that was thrown by the interface.
   @returns - null */
function sig_handler($signo) {
	switch ($signo) {
		case SIGTERM:
		case SIGINT:
			cacti_log("WARNING: Thold Sub Process terminated by user", FALSE, "thold");

			/* tell the main poller that we are done */
			//db_execute("REPLACE INTO settings (name, value) VALUES ('dsstats_poller_status', 'terminated - end time:" . date("Y-m-d G:i:s") ."')");

			exit;
			break;
		default:
			/* ignore all other signals */
	}
}

/* do NOT run this script through a web browser */
if (!isset($_SERVER["argv"][0]) || isset($_SERVER['REQUEST_METHOD'])  || isset($_SERVER['REMOTE_ADDR'])) {
	die("<br><strong>This script is only meant to run at the command line.</strong>");
}

/* We are not talking to the browser */
$no_http_headers = TRUE;

chdir(dirname(__FILE__));
chdir('../../');

require_once("./include/global.php");
require_once($config['base_path'] . '/plugins/thold/thold_functions.php');
require_once($config['library_path'] . '/snmp.php');

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);


/* install signal handlers for UNIX only */
if (function_exists("pcntl_signal")) {
	pcntl_signal(SIGTERM, "sig_handler");
	pcntl_signal(SIGINT, "sig_handler");
}

/* take time and log performance data */
list($micro,$seconds) = split(" ", microtime());
$start = $seconds + $micro;

/* process calling arguments */
$parms = $_SERVER["argv"];
array_shift($parms);
$pid			= false;
$debug          = false;

foreach($parms as $parameter) {
	@list($arg, $value) = @explode("=", $parameter);

	switch ($arg) {
	case "-d":
	case "--debug":
		$debug = TRUE;
		break;
	case "-pid":
	case "--pid":
		@list($partA, $partB) = @explode("_", $value);
		if(is_numeric($partA) && is_numeric($partB)) {
			$pid = $value;
		}else {
			print "ERROR: Invalid Process ID " . $arg . "\n\n";
			display_help();
			exit;
		}
		break;
	case "-v":
	case "--version":
	case "-V":
	case "--help":
	case "-h":
	case "-H":
		//display_help();
		exit;
	default:
		print "ERROR: Invalid Parameter " . $parameter . "\n\n";
		display_help();
	}
}

if($pid === false) {
	display_help();
}else {
	db_execute("UPDATE `plugin_thold_daemon_processes` SET `start` = " . time() . " WHERE `pid` = '" . $pid . "'");
}

$sql_query = "SELECT plugin_thold_daemon_data.id, plugin_thold_daemon_data.rrd_reindexed, plugin_thold_daemon_data.rrd_time_reindexed,
					thold_data.name AS thold_name, thold_data.graph_id,
					thold_data.percent_ds, thold_data.expression,
					thold_data.data_type, thold_data.cdef, thold_data.rra_id,
					thold_data.data_id, thold_data.lastread,
					UNIX_TIMESTAMP(thold_data.lasttime) AS lasttime, thold_data.oldvalue,
					data_template_rrd.data_source_name as name,
					data_template_rrd.data_source_type_id, data_template_data.rrd_step,
					data_template_rrd.rrd_maximum
				FROM plugin_thold_daemon_data
				INNER JOIN
					thold_data
					ON ( thold_data.id = plugin_thold_daemon_data.id)
				LEFT JOIN data_template_rrd
					ON (data_template_rrd.id = thold_data.data_id)
				LEFT JOIN data_template_data
					ON ( data_template_data.local_data_id = thold_data.rra_id )
				WHERE plugin_thold_daemon_data.pid = '$pid'
					AND data_template_rrd.data_source_name!=''";

$thold_items = db_fetch_assoc($sql_query, false);

if (sizeof($thold_items)) {

	/* hold data of all CDEFs in memory to reduce the number of SQL queries to minimum */
	$cdefs = array();
	$cdefs_tmp = db_fetch_assoc("SELECT cdef_id, sequence, type, value FROM cdef_items ORDER BY cdef_id, sequence");
	if($cdefs_tmp & sizeof($cdefs_tmp)>0) {
		foreach($cdefs_tmp as $cdef_tmp) {
			$cdefs[$cdef_tmp["cdef_id"]][] = $cdef_tmp;
		}
	}
	unset($cdefs_tmp);

	$rrd_reindexed = array();
	$rrd_time_reindexed = array();

	foreach ($thold_items as $t_item) {
		thold_debug("Checking Threshold:'" . $t_item["thold_name"] . "', Graph:'" . $t_item["graph_id"] . "'");
		$item = array();
		$rrd_reindexed[$t_item['rra_id']] = unserialize($t_item['thold_server_rrd_reindexed']);
		$rrd_time_reindexed[$t_item['rra_id']] = $t_item['thold_server_rrd_time_reindexed'];
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

	/* check all thresholds */
	$sql_query = "SELECT
						thold_data.data_id,
						thold_data.rra_id,
						thold_data.lastread,
						thold_data.cdef,
						data_template_rrd.data_source_name
					FROM plugin_thold_daemon_data
					INNER JOIN thold_data
						ON ( thold_data.id = plugin_thold_daemon_data.id)
					LEFT JOIN data_template_rrd ON
						data_template_rrd.id = thold_data.data_id
					WHERE plugin_thold_daemon_data.pid = '$pid' AND thold_data.thold_enabled='on' AND thold_data.tcheck=1";

	$tholds = do_hook_function('thold_get_live_hosts', db_fetch_assoc($sql_query));

	$total_tholds = sizeof($tholds);
	foreach ($tholds as $thold) {
		thold_check_threshold ($thold['rra_id'], $thold['data_id'], $thold['data_source_name'], $thold['lastread'], $thold['cdef']);
	}

	db_execute("UPDATE thold_data SET thold_data.thold_server_pid = '', tcheck=0 WHERE thold_data.thold_server_pid = '$pid'");
	db_execute("DELETE FROM `plugin_thold_daemon_data` WHERE `pid` = '$pid'");
	db_execute("UPDATE `plugin_thold_daemon_processes` SET `end` = " . time() . ", `processed_items` = " . $total_tholds);
}

function display_help() {
	print "tbd  ... blablabla ...";
	exit;
}