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

/* let PHP run just as long as it has to */
ini_set('max_execution_time', '0');

error_reporting('E_ALL');
$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

include('./include/cli_check.php');

include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

/* set the defaults */
$force     = false;
$debug     = false;
$gtemplate = 0;
$ttemplate = 0;
$hids      = '';
$gids      = '';

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '--auto-create':
			case '-auto':
				if (is_numeric($value)) {
					thold_cli_autocreate($value);
				} else {
					print 'ERROR: Invalid Device ID ' . $value . PHP_EOL . PHP_EOL;
					display_help();
					exit(-1);
				}
				exit;
			case '--debug':
			case '-d':
				$debug = true;
				break;
			case '--force':
			case '-f':
				$force = true;
				break;
			case '--graph-template':
			case '-gt':
				$gtemplate = $value;
				break;
			case '--thold-template':
			case '-tt':
				$ttemplate = $value;
				break;
			case '--graph-ids':
			case '-ids':
				$gids = $value;
				break;
			case '--host-ids':
				$hids = $value;
				break;
			case '--version':
			case '-V':
			case '-v':
				display_version();
				exit;
			case '--help':
			case '-H':
			case '-h':
				display_help();
				exit;
			exit(-1);
			default:
				print 'ERROR: Invalid Parameter ' . $parameter . PHP_EOL . PHP_EOL;
				display_help();
				exit(-1);
		}
	}
}

/* validate device ids */
if (strlen($hids)) {
	$hids = explode(' ', $hids);
	foreach($hids as $id) {
		if (!is_numeric($id)) {
			print 'ERROR: The Device ID \'' . $id . '\' is NOT numeric.  All Device IDs must be numeric.' . PHP_EOL . PHP_EOL;
			display_help();
			exit(-1);
		}
	}
}

/* validate values */
if (!is_numeric($ttemplate)) {
	print 'ERROR: The Thold Template must be numeric.' . PHP_EOL . PHP_EOL;
	display_help();
	exit(-1);
}

/* validate values */
if (!is_numeric($gtemplate)) {
	print 'ERROR: The Graph Template must be numeric.' . PHP_EOL . PHP_EOL;
	display_help();
	exit(-1);
}

/* validate graph ids */
if (strlen($gids)) {
	$gids = explode(' ', $gids);
	foreach($gids as $id) {
		if (!is_numeric($id)) {
			print 'ERROR: The Graph ID \'' . $id . '\' is NOT numeric.  All Graph IDs must be numeric.' . PHP_EOL . PHP_EOL;
			display_help();
			exit(-1);
		}
	}
}

/* perform some checks */
if ($ttemplate == 0 && $gtemplate == 0 && $hids == '' && $gids == '') {
	print 'ERROR: You must choose either --auto-create or a combination of Devices, Graphs,' . PHP_EOL;
	print 'a Graph Template of Threshold Template.' . PHP_EOL . PHP_EOL;
	display_help();
	exit(-1);
}

thold_cli_autocreate($hids, $gids, $gtemplate, $ttemplate);

function thold_cli_autocreate($hids = '', $gids = '', $gtemplate = 0, $ttemplate = 0) {
	print 'Auto Creating Thresholds' . PHP_EOL;

	autocreate($hids, $gids, $gtemplate, $ttemplate);

	if (isset($_SESSION['sess_messages'])) {
		foreach ($_SESSION['sess_messages'] as $message_id => $message) {
			if (strpos($message_id, 'thold_message') !== false) {
				print strip_tags(str_replace('<br>', PHP_EOL, $message['message'])) . PHP_EOL;
			}
		}
	}
}

function display_version() {
	global $config;
	if (!function_exists('plugin_thold_version')) {
		include_once($config['base_path'] . '/plugins/thold/setup.php');
	}

	$info = plugin_thold_version();
	print 'Threshold Command Line Interface, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}


/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print PHP_EOL;

	print 'usage: cli_thresholds.php --auto-create=N | [--host-ids=\'N1 N2 ...\'] [--graph-template=N] [--thold-template=N] [--graph-ids=\'N1 N2 ...\']' . PHP_EOL . PHP_EOL;

	print 'There are two usage methods:' . PHP_EOL . PHP_EOL;

	print 'The first requires you to specify the host id of the device and all existing Threshold templates' . PHP_EOL;
	print 'are applied to hosts.' . PHP_EOL . PHP_EOL;
	print '--auto-create=N         - Auto Create all Thresholds for this Device id using Templates associated' . PHP_EOL;
	print '                          the Devices Device Template.' . PHP_EOL . PHP_EOL;

	print 'The second requires you to specify either a series of Devices, a Graph Template, a Treshold Template' . PHP_EOL;
	print 'or a series of Graphs or any combination of the above.  However, at least one must be specified.' . PHP_EOL;
	print 'Threshold Template and Graph IDs of the Graphs to be impacted.' . PHP_EOL . PHP_EOL;
	print '--host-ids=\'N1 N2 ...\'  - The Devices ID to create Thresholds for' . PHP_EOL;
	print '--graph-template=N      - The Graph Template to create Thresholds for' . PHP_EOL;
	print '--thold-template=N      - The Threshold Template to use for creating Thresholds' . PHP_EOL;
	print '--graph-ids=\'N1 N2 ...\' - The Threshold Template to use for creating Thresholds' . PHP_EOL . PHP_EOL;
}

