#!/usr/bin/php
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
 | This program is snmpagent in the hope that it will be useful,           |
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

/* allow the script to hang around waiting for connections. */
set_time_limit(0);

ini_set('memory_limit', '800M');
ini_set('max_execution_time', '-1');
ini_set('output_buffering', 'Off');

chdir(dirname(__FILE__));
chdir('../../');

include_once('./include/cli_check.php');
include_once($config['base_path'] . '/lib/poller.php');
include_once($config['base_path'] . '/plugins/thold/thold_functions.php');

/* install signal handlers for Linux/UNIX only */
if (function_exists('pcntl_signal')) {
	pcntl_signal(SIGTERM, 'sig_handler');
	pcntl_signal(SIGINT, 'sig_handler');
}

global $cnn_id, $config;

$debug      = false;
$foreground = false;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

if (sizeof($parms)) {
	foreach ($parms as $parameter) {
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
			case '-f':
			case '--foreground':
				$foreground = true;

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

/* redirect standard error to dev/null */
if ($config['cacti_server_os'] == 'unix') {
	fclose(STDERR);
	$STDERR = fopen('/dev/null', 'wb');
} else {
	fclose(STDERR);
	$STDERR = fopen('null', 'wb');
}

$timeout = 99999999;

/* enable thold daemon in the GUI */
thold_daemon_debug('Enabling Thold Daemon in Database.');

set_config_option('thold_daemon_enable', 'on');

/* check if poller daemon is already running */
if (!register_process_start('thold', 'parent', 0, $timeout)) {
	if ($config['cacti_server_os'] == 'unix') {
		exec('pgrep -a php | grep thold_daemon.php', $output);

		if (cacti_sizeof($output) > 0) {
			print 'The Thold Daemon is still running' . PHP_EOL;
			exit(1);
		} else {
			unregister_process('thold', 'parent', 0);
			register_process('thold', 'parent', 0, $timeout);
		}
	}
}

/* do not run the thold daemon on the remote server in central storage mode */
if (read_config_option('remote_storage_method') != 1 && $config['poller_id'] > 1) {
	print 'In Central Storage Mode, the thold_daemon only runs on the main data collector.' . PHP_EOL;
	exit(1);
}

print 'Starting Thold Daemon ... ';

if (!$foreground) {
	if (function_exists('pcntl_fork')) {
		// Close the database connection
		db_close($cnn_id);

		// Fork the current process to daemonize
		$pid = pcntl_fork();

		if ($pid == -1) {
			// oha ... something went wrong :(
			print '[FAILED]' . PHP_EOL;

			return false;
		} elseif ($pid == 0) {
			// We are the child
		} else {
			cacti_log('NOTE: Thold Daemon Started on ' . gethostname(), false, 'THOLD');

			// We are the parent, output and exit
			print '[OK]' . PHP_EOL;

	        exit;
		}
	} else {
		// Windows.... awesome! But no worries
		print '[WARNING] This system does not support forking.' . PHP_EOL;
	}
} else {
	print '[NOTE] The Thold Daemon is running in foreground mode.' . PHP_EOL;
}

sleep(2);

// The database connection looses state as parent, so reconnect regardless
$cnn_id = thold_db_reconnect($cnn_id);

$processes = read_config_option('thold_max_concurrent_processes');

thold_truncate_daemon_data();

thold_prime_distribution($processes);

thold_daemon_debug('Forking Thold Daemon Child Processes');

for($i = 1; $i <= $processes; $i++) {
	thold_launch_worker($i);
}

$prev_running = false;
$start_daemon_items = 0;
$counter = 0;

while (true) {
	if (thold_db_connection()) {
		$counter++;

		// force the check for the daemon debug
		$daemon_debug = read_config_option('thold_daemon_debug', true);

		if ($counter == 1) {
			thold_daemon_debug('Thold Thread Watchdog Start.');

			set_config_option('thold_daemon_heartbeat', microtime(true));
		}

		$running = thold_poller_running();

		if ($running && !$prev_running) {
			$start = microtime(true);

			thold_daemon_debug('Detected Cacti Poller Start at ' . date('Y-m-d H:i:s'));

			$start_items = db_fetch_cell('SELECT COUNT(*) FROM plugin_thold_daemon_data');
			$prev_running = true;
		} elseif (!$running && $prev_running) {
			$end_items = db_fetch_cell('SELECT COUNT(*) FROM plugin_thold_daemon_data');
			$prev_running = false;

			$tholds = db_fetch_cell('SELECT COUNT(*) FROM thold_data WHERE thold_enabled = "on"');

			thold_daemon_debug(sprintf('Detected Cacti Poller End.  TotalTholds:%u StartItems:%u, EndItems:%u', $tholds, $start_items, $end_items));

			$end = microtime(true);

			cacti_log(sprintf('THOLD DAEMON STATS: TotalTime:%3.2f TotalTholds:%u StartingItems:%u EndingItems:%u',
				$end - $start, $tholds, $start_items, $end_items), false, 'SYSTEM');
		} else {
			$prev_running = $running;
		}

		sleep(1);

		$cnn_id = thold_db_reconnect($cnn_id);

		if ($counter == 10) {
			$counter = 0;

			$new_processes = read_config_option('thold_max_concurrent_processes', true);

			if ($new_processes != $processes) {
				thold_prime_distribution($processes, true);
			} else {
				thold_prime_distribution($processes);
			}

			thold_heartbeat_processes($processes, $new_processes);

			$processes = $new_processes;

			thold_daemon_debug('Thold Thread Watchdog End.  Processed heartbeat.');

			heartbeat_process('thold', 'parent', 0);
		}

	} else {
		thold_daemon_debug('WARNING: No database connection.  Sleeping for 60 seconds.');

		sleep(60);
	}
}

function thold_truncate_daemon_data() {
	thold_daemon_debug('Truncating historical Threshold Daemon Data');

	db_execute('TRUNCATE TABLE plugin_thold_daemon_data');
}

function thold_poller_running() {
	return db_fetch_cell('SELECT COUNT(*) FROM poller_time WHERE end_time = "0000-00-00"');
}

/**
 * sig_handler - provides a generic means to catch exceptions to the Cacti log.
 *
 * @param $signo - (int) the signal that was thrown by the interface.
 *
 * @returns - null
 */
function sig_handler($signo) {
	global $config;

	switch ($signo) {
	case SIGTERM:
	case SIGINT:
		/* kill any child processes */
		$processes = db_fetch_assoc('SELECT * FROM processes WHERE tasktype = "thold" AND taskname = "child"');

		if (cacti_sizeof($processes)) {
			foreach($processes as $p) {
				thold_daemon_debug(sprintf('Killing Child Process with the pid of %u', $p['pid']));
				posix_kill($p['pid'], SIGTERM);
			}
		}

		thold_cacti_log('WARNING: Thold Daemon Parent Process with PID[' . getmypid() . '] terminated by user', 0);

		unregister_process('thold', 'parent', 0);

		exit;
	default:
		/* ignore all other signals */
	}
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

function thold_launch_worker($thread) {
	global $config, $debug;

	$path_php  = read_config_option('path_php_binary');

	$process   = $config['base_path']       .
		'/plugins/thold/thold_process.php ' .
		' --thread=' . $thread              .
		($debug ? ' --debug':'')            .
		' > /dev/null';

	thold_daemon_debug('Starting Process: ' . $path_php . ' ' . $process);

	exec_background($path_php, $process);
}

function thold_heartbeat_processes($processes, $new_processes) {
	global $config;

	$procs = db_fetch_assoc('SELECT *
		FROM processes
		WHERE tasktype = "thold"
		AND taskname = "child"
		ORDER BY taskid DESC');

	$running_processes = cacti_sizeof($procs);

	// Check for a crashed process
	$process_num = -1;
	foreach($procs as $id => $p) {
		// Check for crashed processes first
		if ($process_num != -1) {
			if ($process_num - 1 != $p['taskid']) {
				thold_daemon_debug(sprintf('WARNING: Detected Crashed Thold Thread.  Relaunching Crashed Thread %s', $process_num - 1));

				thold_launch_worker($process_num -1);

				$running_processes++;
			}
		} else {
			// Check for hung processes next
			$lastupdate = strtotime($p['last_update']);
			$now        = time();
			if ($lastupdate + 120 < $now) {
				thold_daemon_debug(sprintf('WARNING: Detected Hung Thold Thread.  Killing/Relaunching Hung Thread %s', $p['taskid']));

				posix_kill($p['pid'], SIGTERM);

				thold_launch_worker($p['taskid']);
			}
		}

		$process_num = $p['taskid'];
	}

	foreach($procs as $id => $p) {
		if (function_exists('posix_getpgid')) {
			$running = posix_getpgid($p['pid']);
		} elseif (function_exists('posix_kill')) {
			$running = posix_kill($p['pid'], 0);
		}

		if (!$running) {
			thold_daemon_debug(sprintf('WARNING: Thold Daemon Child[%s] Died!', $p['pid']));

			cacti_log(sprintf('WARNING: Thold Daemon Child[%s] Died!', $p['pid']), false, 'THOLD');

			$running_processes--;
			unset($procs[$id]);
		}
	}

	if ($running_processes != $new_processes) {
		if ($running_processes > $new_processes) {
			thold_daemon_debug(sprintf('Thold Thread Detected Process Count Change.  Reducing Process Count by %s', $running_processes - $new_processes));

			foreach($procs as $id => $p) {
				posix_kill($p['pid'], SIGTERM);
				$running_processes--;
				if ($running_processes == $new_processes) {
					break;
				}
			}
		} else {
			thold_daemon_debug(sprintf('Thold Thread Detected Process Count Change.  Increasing Process Count by %s', $new_processes - $running_processes));

			while($running_processes < $new_processes) {
				$running_processes++;

				thold_launch_worker($running_processes);
			}
		}
	}
}

function thold_prime_distribution($processes, $truncate = false) {
	thold_daemon_debug('Rebalancing Thread Allocation by Device');

	if ($truncate) {
		db_execute('UPDATE thold_data SET thread_id = 0');
	}

	$not_set_threads = db_fetch_cell('SELECT COUNT(id) FROM thold_data WHERE thread_id = 0');

	if ($not_set_threads > 0) {
		// Get the current distribution of threads
		$threads = array_rekey(
			db_fetch_assoc('SELECT thread_id, host_id
				FROM thold_data
				WHERE thread_id > 0'),
			'host_id', 'thread_id'
		);

		$tholds = db_fetch_assoc('SELECT id, host_id
			FROM thold_data
			WHERE thread_id = 0');

		$thread_num = 1;

		foreach($tholds as $t) {
			if (!isset($threads[$t['host_id']])) {
				$threads[$t['host_id']] = $thread_num;
				$thread_num++;
			}

			if ($thread_num > $processes) {
				$thread_num = 1;
			}
		}

		foreach($threads as $host_id => $thread) {
			db_execute_prepared('UPDATE thold_data
				SET thread_id = ?
				WHERE host_id = ?',
				array($thread, $host_id));
		}
	}

	thold_daemon_debug('Thread Rebalancing Allocation by Device Completed');
}

function thold_daemon_debug($string) {
	global $debug;

	// Get the cached value
	$daemon_debug = read_config_option('thold_daemon_debug');

	if ($debug || $daemon_debug) {
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
	print 'Threshold Daemon, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}


/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print PHP_EOL . 'usage: thold_daemon.php [ --foreground | -f ] [ --debug ]' . PHP_EOL . PHP_EOL;
	print 'The Threshold Daemon processor for the Thold Plugin.' . PHP_EOL;
}

