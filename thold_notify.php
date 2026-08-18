<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
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

$notification_registered = false;
$pid                     = 0;
$thread                  = false;

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
require_once($config['base_path'] . '/lib/time.php');

// install signal handlers for Linux/UNIX only
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

// help with microtime(true)
// ini_set('precision', 16);

// process calling arguments
$parms = $_SERVER['argv'];
array_shift($parms);

$debug  = false;

global $thread;

if (sizeof($parms)) {
	foreach ($parms as $parameter) {
		if (strpos($parameter, '=')) {
			[$arg, $value] = explode('=', $parameter);
		} else {
			$arg   = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-d':
			case '--debug':
				$debug = true;

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

// This is where we can parallelize
$collector = ($thread === false);

if ($collector) {
	thold_cli_debug('Thold Notification Main Collector Started');

	$thread = 1;
} else {
	thold_cli_debug("Thold Notification Child Thread $thread Started");
}

$timeout = 3600;

if (!thold_notification_main($thread, $timeout)) {
	exit(1);
}

exit(0);

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
			thold_cacti_log('WARNING: Thold Daemon Notification Child Process with PID[' . getmypid() . '] terminated by user', $thread);
			thold_notification_shutdown();

			exit;

			break;
		default:
			// ignore all other signals
	}
}

function thold_daemon_debug($message, $thread) {
	global $debug;

	$daemon_debug = read_config_option('thold_daemon_debug');

	if ($debug || $daemon_debug) {
		thold_cacti_log($message, $thread);
	}
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
	print 'Threshold Notification Processor, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}

// display_help - displays the usage of the function
function display_help() {
	display_version();

	print PHP_EOL . 'usage: thold_notify.php [--debug]' . PHP_EOL . PHP_EOL;
	print 'The Threshold Notification Processor for the Thold Plugin.' . PHP_EOL;
}
