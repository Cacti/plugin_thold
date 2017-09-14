#!/usr/bin/php
<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2017 The Cacti Group                                 |
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

/* tick use required as of PHP 4.3.0 to accomodate signal handling */
declare(ticks = 1);

/* sig_handler - provides a generic means to catch exceptions to the Cacti log.
   @arg $signo - (int) the signal that was thrown by the interface.
   @returns - null */
function sig_handler($signo) {
	global $config;

	switch ($signo) {
	case SIGTERM:
	case SIGINT:
		db_execute_prepared('DELETE FROM plugin_thold_daemon_processes 
			WHERE poller_id = ?', 
			array($config['poller_id']));

		db_execute_prepared('DELETE FROM plugin_thold_daemon_data 
			WHERE poller_id = ?', 
			array($config['poller_id']));

		cacti_log('WARNING: Thold Daemon Process (' . getmypid() . ') terminated by user', false, 'THOLD');

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

/* we are not talking to the browser */
$no_http_headers = true;

/* let's report all errors */
error_reporting(E_ALL);

/* allow the script to hang around waiting for connections. */
set_time_limit(0);

/* we do not need so much memory */
ini_set('memory_limit', '256M');

chdir(dirname(__FILE__));
chdir('../../');

require_once('./include/global.php');
require_once($config['base_path'] . '/lib/poller.php');

$no_http_headers = true;

global $cnn_id, $config;

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);
$debug      = false;
$foreground = false;

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

/* check if poller daemon is already running */
exec('pgrep -a php | grep thold_daemon.php', $output);
if (sizeof($output) >= 2) {
	print 'The Thold Daemon is still running' . PHP_EOL;
    return;
}

print 'Starting Thold Daemon ... ';

if (!$foreground) {
	if (function_exists('pcntl_fork')) {
		// Close the database connection
		db_close($cnn_id);

		// Fork the current process to daemonize
		$pid = pcntl_fork();

		if ($pid == -1) {
			/* oha ... something went wrong :( */
			print '[FAILED]' . PHP_EOL;
			return false;
		} elseif ($pid == 0) {
			// We are the child
		} else {
			cacti_log('THOLD DAEMON Started on ' . gethostname(), false, 'SYSTEM');;

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

db_execute_prepared('DELETE FROM plugin_thold_daemon_processes 
	WHERE poller_id = ?', 
	array($config['poller_id']));

db_execute_prepared('DELETE FROM plugin_thold_daemon_data 
	WHERE poller_id = ?', 
	array($config['poller_id']));

// Poller 1 handles external the special case of external data sources
if ($config['poller_id'] == 1) {
	db_execute('UPDATE thold_data AS td
		LEFT JOIN host AS h
		ON td.host_id = h.id
		SET td.thold_daemon_pid = "" 
		WHERE (h.poller_id = 1 OR h.poller_id IS NULL)');
} else {
	db_execute_prepared('UPDATE thold_data AS td
		LEFT JOIN host AS h
		ON td.host_id = h.id
		SET td.thold_daemon_pid = "" 
		WHERE poller_id = ?', 
		array($config['poller_id']));
}

$path_php_binary = read_config_option('path_php_binary');
$queued_processes = 0;

while (true) {
	if (thold_db_connection()) {
		// Initiate concurrent background processes as long as we do not hit the limits 
		$queue = db_fetch_assoc_prepared('SELECT * 
			FROM plugin_thold_daemon_processes 
			WHERE start = 0 
			AND poller_id = ?
			ORDER BY pid',
			array($config['poller_id']));

		$queued_processes = sizeof($queue);

		cacti_log('Processes Queued:' . $queued_processes, false, 'THOLD', POLLER_VERBOSITY_LOW);

		if ($queued_processes) {
			$thold_max_concurrent_processes = read_config_option('thold_max_concurrent_processes');

			$running_processes = db_fetch_cell_prepared('SELECT COUNT(*) 
				FROM plugin_thold_daemon_processes 
				WHERE start != 0 
				AND end = 0
				AND poller_id = ?',
				array($config['poller_id']));

			$free_processes = $thold_max_concurrent_processes - $running_processes;

			if ($free_processes) {
				for ($i=0; $i<$free_processes; $i++) {
					if (isset($queue[$i])) {
						$pid = $queue[$i]['pid'];
						$process = ' -q ' . $config['base_path'] . '/plugins/thold/thold_process.php --pid=' . $pid . ' > /dev/null';
						cacti_log('DEBUG: Starting process: ' . $path_php_binary . ' ' . $process, false, 'THOLD', POLLER_VERBOSITY_LOW);
						exec_background($path_php_binary, $process);
					} else {
						break;
					}
				}
			}
		}
	} else {
		// try to reconnect if the test was no good
		$cnn_id = thold_db_reconnect($cnn_id);
	}

	if ($queued_processes == 0) {
		sleep(2);
	}
}

function thold_db_connection(){
	global $cnn_id;

	if (is_object($cnn_id)) {
		// Avoid showing errors
		restore_error_handler();
		set_error_handler('thold_db_error_handler');

		$cacti_version = db_fetch_cell('SELECT cacti FROM version');

		// Restore Cactis Error handler
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

	// Restore Cactis Error handler
	restore_error_handler();
	set_error_handler('CactiErrorHandler');

	return $cnn_id;
}

function thold_db_error_handler() {
	return true;
}

function display_version() {
	global $config;

	if (!function_exists('plugin_thold_version')) {
		include_once($config['base_path'] . '/plugins/thold/setup.php');
	}

	$info = plugin_thold_version();
	echo 'Threshold Daemon, Version ' . $info['version'] . ', ' . COPYRIGHT_YEARS . PHP_EOL;
}


/*	display_help - displays the usage of the function */
function display_help () {
	display_version();

	print PHP_EOL . 'usage: thold_daemon.php [ --foreground | -f ] [ --debug ]' . PHP_EOL . PHP_EOL;
	print 'The Threshold Daemon processor for the Thold Plugin.' . PHP_EOL;
}

