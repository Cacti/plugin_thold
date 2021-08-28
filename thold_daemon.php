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
$path_php  = read_config_option('path_php_binary');

thold_prime_distribution($processes);

thold_debug('Forking Thold Daemon Child Processes');

for($i = 1; $i <= $processes; $i++) {
	$process = '-q ' . $config['base_path'] . '/plugins/thold/thold_process.php --thread=' . $i . ' > /dev/null';
	thold_debug('Starting Threshold process: ' . $path_php . ' -q ' . $process);
	exec_background($path_php, $process);
}

$prev_running = false;
$start_daemon_items = 0;
$counter = 0;

while (true) {
	if (thold_db_connection()) {
		$counter++;

		if ($counter == 1) {
			thold_debug('Thold Thread Watchdog Start.');

			set_config_option('thold_daemon_heartbeat', microtime(true));
		}

		$running = thold_poller_running();

		if ($running && !$prev_running) {
			$start = microtime(true);

			thold_debug('Detected Cacti Poller Start at ' . date('Y-m-d H:i:s'));

			$start_items = db_fetch_cell('SELECT COUNT(*) FROM plugin_thold_daemon_data');
			$prev_running = true;
		} elseif (!$running && $prev_running) {
			$end_items = db_fetch_cell('SELECT COUNT(*) FROM plugin_thold_daemon_data');
			$prev_running = false;

			$tholds = db_fetch_cell('SELECT COUNT(*) FROM thold_data WHERE thold_enabled = "on"');

			thold_debug(sprintf('Detected Cacti Poller End.  TotalTholds:%u StartItems:%u, EndItems:%u', $tholds, $start_items, $end_items));

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

			thold_debug('Thold Thread Watchdog End.  Processed heartbeat.');
		}
	} else {
		thold_debug('WARNING: No database connection.  Sleeping for 30 seconds.');
		sleep(30);
	}
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
		if (read_config_option('remote_storage_method') == 1) {
			db_execute_prepared('DELETE FROM plugin_thold_daemon_data
				WHERE poller_id = ?',
				array($config['poller_id']), false);

			if ($config['poller_id'] == 1) {
				db_execute('UPDATE thold_data AS td
					LEFT JOIN host AS h
					ON td.host_id = h.id
					SET td.thold_daemon_pid = ""
					WHERE (h.poller_id = 1 OR h.poller_id IS NULL)
					AND td.thold_daemon_pid != ""', false);
			} else {
				db_execute_prepared('UPDATE thold_data AS td
					LEFT JOIN host AS h
					ON td.host_id = h.id
					SET td.thold_daemon_pid = ""
					WHERE poller_id = ?
					AND td.thold_daemon_pid != ""',
					array($config['poller_id']), false);
			}
		} else {
			db_execute('TRUNCATE plugin_thold_daemon_data', false);

			db_execute('UPDATE thold_data
				SET thold_daemon_pid = ""
				WHERE thold_daemon_pid != ""', false);
		}

		cacti_log('WARNING: Thold Daemon Process (' . getmypid() . ') terminated by user', false, 'THOLD');

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
		set_error_handler('thold_db_error_handler');

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
	set_error_handler('thold_db_error_handler');

	// Connect to the database server
	$cnn_id = db_connect_real($database_hostname, $database_username, $database_password, $database_default, $database_type, $database_port, $database_ssl);

	// Restore Cacti's Error handler
	restore_error_handler();
	set_error_handler('CactiErrorHandler');

	return $cnn_id;
}

function thold_heartbeat_processes($processes, $new_processes) {
	global $config;

	$path_php  = read_config_option('path_php_binary');

	$procs = db_fetch_assoc('SELECT *
		FROM processes
		WHERE tasktype = "thold"
		AND taskname = "child"
		ORDER BY taskid DESC');

	$running_processes = cacti_sizeof($procs);

	// Check for a crashed process
	$process_num = -1;
	foreach($procs as $id => $p) {
		if ($process_num != -1) {
			if ($process_num - 1 != $p['taskid']) {
				thold_debug(sprintf('WARNING: Detected Crashed Thold Thread.  Relaunching Crashed Thread %s', $process_num - 1));

				$process = '-q ' . $config['base_path'] . '/plugins/thold/thold_process.php --thread=' . ($process_num -1) . ' > /dev/null';
				thold_debug('Starting Threshold process: ' . $path_php . ' -q ' . $process);
				exec_background($path_php, $process);

				$running_processes++;
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
			thold_debug(sprintf('WARNING: Thold Daemon Child[%s] Died!', $p['pid']));

			cacti_log(sprintf('WARNING: Thold Daemon Child[%s] Died!', $p['pid']), false, 'THOLD');

			$running_processes--;
			unset($procs[$id]);
		}
	}

	if ($running_processes != $new_processes) {
		if ($running_processes > $new_processes) {
			thold_debug(sprintf('Thold Thread Detected Process Count Change.  Reducing Process Count by %s', $running_processes - $new_processes));

			foreach($procs as $id => $p) {
				posix_kill($p['pid'], SIGTERM);
				$running_processes--;
				if ($running_processes == $new_processes) {
					break;
				}
			}
		} else {
			thold_debug(sprintf('Thold Thread Detected Process Count Change.  Increasing Process Count by %s', $new_processes - $running_processes));

			while($running_processes < $new_processes) {
				$running_processes++;

				$process = '-q ' . $config['base_path'] . '/plugins/thold/thold_process.php --thread=' . $running_processes . ' > /dev/null';
				thold_debug('Starting Threshold process: ' . $path_php . ' -q ' . $process);
				exec_background($path_php, $process);
			}
		}
	}
}

function thold_prime_distribution($processes, $truncate = false) {
	thold_debug('Rebalancing Thread Allocation by Device');

	// Perform column checks
	if (db_column_exists('thold_data', 'thold_daemon_pid')) {
		db_execute('ALTER TABLE thold_data DROP COLUMN thold_daemon_pid');
	}

	if (!db_column_exists('thold_data', 'thread_id')) {
		db_execute('ALTER TABLE thold_data
			ADD COLUMN thread_id int UNSIGNED NOT NULL default "0" AFTER id');
	}

	if (!db_column_exists('plugin_thold_daemon_data', 'time')) {
		db_execute('ALTER TABLE plugin_thold_daemon_data
			ADD COLUMN time TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP');
	}

	if (db_column_exists('plugin_thold_daemon_data', 'pid')) {
		db_execute('ALTER TABLE plugin_thold_daemon_data DROP COLUMN pid');
	}

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

	thold_debug('Thread Rebalancing Allocation by Device Completed');
}

function thold_db_error_handler() {
	return true;
}

function thold_debug($string) {
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
	print 'Threshold Daemon, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}


/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print PHP_EOL . 'usage: thold_daemon.php [ --foreground | -f ] [ --debug ]' . PHP_EOL . PHP_EOL;
	print 'The Threshold Daemon processor for the Thold Plugin.' . PHP_EOL;
}

