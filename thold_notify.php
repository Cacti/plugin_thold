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

global $thread;

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

if (!register_process_start('thold_notify', 'child', $thread, $timeout)) {
	$pid = db_fetch_cell_prepared('SELECT pid
		FROM processes
		WHERE tasktype = "thold_notify"
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
			unregister_process('thold_notify', 'child', $thread);
			register_process('thold_notify', 'child', $thread, $timeout);
		}
    }
}

while (true) {
	if (db_check_reconnect()) {
		// Prime the 'thold_daemon_debug' value
		$daemon_debug = read_config_option('thold_daemon_debug', true);

		// Fix issues with microtime skew
		usleep(1);

		$start = microtime(true);

		// Do something

		$end = microtime(true);
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
			thold_cacti_log('WARNING: Thold Daemon Notifcation Child Process with PID[' . getmypid() . '] terminated by user', $thread);
			unregister_process('thold_notify', 'child', $thread);

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

/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print PHP_EOL . 'usage: thold_notify.php --thread=N [--debug]' . PHP_EOL . PHP_EOL;
	print 'The Threshold Notification Processor for the Thold Plugin.' . PHP_EOL;
}

