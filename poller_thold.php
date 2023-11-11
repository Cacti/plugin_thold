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
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/* let PHP run just as long as it has to and to chew up memory too */
ini_set('max_execution_time', '-1');
ini_set('memory_limit', '-1');

$dir = dirname(__FILE__);
chdir($dir);

if (strpos($dir, 'plugins') !== false) {
	chdir('../../');
}

include('./include/cli_check.php');
include_once($config['base_path'] . '/plugins/thold/setup.php');
include_once($config['base_path'] . '/plugins/thold/thold_functions.php');
include_once($config['base_path'] . '/plugins/thold/includes/polling.php');
include_once($config['base_path'] . '/lib/rrd.php');
include_once($config['base_path'] . '/lib/poller.php');

/* process calling arguments */
$parms = $_SERVER['argv'];
array_shift($parms);

$debug = false;
$force = false;
$start = microtime(true);

global $debug;

$poller_id = $config['poller_id'];

if (cacti_sizeof($parms)) {
	foreach($parms as $parameter) {
		if (strpos($parameter, '=')) {
			list($arg, $value) = explode('=', $parameter);
		} else {
			$arg = $parameter;
			$value = '';
		}

		switch ($arg) {
			case '-f':
			case '--force':
				$force = true;
				break;
			case '-d':
			case '--debug':
				$debug = true;
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
			default:
				print "ERROR: Invalid Parameter " . $parameter . "\n\n";
				display_help();
				exit;
		}
	}
}

print "Starting the Thold Poller Process\n";

/* silently end if the registered process is still running, or process table missing */
if (!register_process_start('thold', 'master', $config['poller_id'], read_config_option('poller_interval'))) {
	exit(0);
}

/* perform all key thold functions */
perform_thold_processes();

unregister_process('thold', 'master', $config['poller_id']);

function perform_thold_processes() {
	global $config;

	/* record the start time */
	$start = microtime(true);

	if (read_config_option('thold_empty_if_speed_default') == '') {
		set_config_option('thold_empty_if_speed_default', '10000');
		$empty_value = read_config_option('thold_empty_if_speed_default', true);
	}

	// Force upgrade the database if there is a problem
	if (!db_column_exists('thold_data', 'name_cache')) {
		thold_debug('Upgrading Thold database now.');

		include_once($config['base_path'] . '/plugins/thold/includes/database.php');

		thold_upgrade_database(true);
	} else {
		thold_debug('Not upgrading Thold database.');
	}

	/* handle changes in deadnotify */
	thold_debug('Pruning stale dead host notifications.');

	$deadnotify = (read_config_option('alert_deadnotify') == 'on');
	if (!$deadnotify) {
		db_execute('TRUNCATE plugin_thold_host_failed');
	} else {
		db_execute('DELETE FROM plugin_thold_host_failed WHERE host_id NOT IN (SELECT id FROM host)');
	}

	/* launch the notification background process if required */
	$notification_queue  = read_config_option('thold_notification_queue');
	$notification_daemon = read_config_option('thold_notification_daemon');

	if ($notification_queue == 'on' && $notification_daemon == '') {
		thold_debug('Launching Thold notification process.');

		$command_string = cacti_escapeshellcmd(read_config_option('path_php_binary'));
		$file_path      = $config['base_path'] . '/plugins/thold/thold_notify.php';
		if (file_exists($file_path)) {
			exec_background($command_string, $file_path);
		}
	}

	if (read_config_option('thold_daemon_enable') == '') {
		thold_debug('Thold daemon not enabled.  Preparing to perform checks.');

		/* perform all thold checks */
		thold_debug('Thold checks started.');
		$tholds = thold_check_all_thresholds();
		thold_debug('Thold checks finished.');

		thold_debug('Down device checks started.');
		$nhosts = thold_update_host_status();
		thold_debug('Down device checks finished.');

		thold_debug('Thold Log Cleanup started.');
		thold_cleanup_log();
		thold_debug('Thold Log Cleanup finished.');

		if (read_config_option('remote_storage_method') == 1) {
			$total_hosts = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM host
				WHERE disabled = ""
				AND poller_id = ?',
				array($config['poller_id']));

			$down_hosts = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM host
				WHERE status = 1
				AND disabled = ""
				AND poller_id = ?',
				array($config['poller_id']));
		} else {
			$total_hosts = db_fetch_cell('SELECT COUNT(*)
				FROM host
				WHERE disabled = ""');

			$down_hosts = db_fetch_cell('SELECT COUNT(*)
				FROM host
				WHERE status = 1
				AND disabled = ""');
		}

		thold_debug('Prune old data started.');
		thold_prune_old_data();
		thold_debug('Prune old data finished.');

		/* record the end time */
		$end = microtime(true);

		/* log statistics */
		$thold_stats = sprintf('Time:%0.2f Tholds:%d TotalDevices:%d DownDevices:%d NewDownDevices:%d', $end - $start, $tholds, $total_hosts, $down_hosts, $nhosts);

		cacti_log('THOLD STATS: ' . $thold_stats, false, 'SYSTEM');

		set_config_option('stats_thold', $thold_stats);
	} else {
		/* collect some stats */
		$now = microtime(true);

		/* get the last update from the daemon */
		$heartbeat       = read_config_option('thold_daemon_heartbeat');
		$poller_interval = read_config_option('poller_interval');
		$curtime         = time();
		$frequency       = read_config_option('thold_daemon_dead_notification');

		if ($frequency > 0) {
			if (empty($heartbeat)) {
				$last_notification = read_config_option('thold_daemon_down_notify_time');

				if ($curtime - $last_notification > $frequency) {
					admin_email('Thold Daemon Not Started', 'WARNING: You have elected to use the Thold Daemon, but it appears not to be running.  Please correct this right away');
					set_config_option('thold_daemon_down_notify_time', $curtime);
				}
			} elseif ($now - $heartbeat > 3 * $poller_interval) {
				$last_notification = read_config_option('thold_daemon_down_notify_time');

				if ($curtime - $last_notification > $frequency) {
					admin_email('Thold Daemon Down', 'WARNING: You have elected to use the Thold Daemon, but it appears have stopped running.  Please correct this right away');
					set_config_option('thold_daemon_down_notify_time', $curtime);
				}
			}
		}

		$threads = read_config_option('thold_max_concurrent_processes');

		if (read_config_option('remote_storage_method') == 1) {
			/* host_status processed by thold server */
			$nhosts = thold_update_host_status();

			thold_cleanup_log();

			$total_hosts = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM host
				WHERE disabled = ""
				AND poller_id = ?',
				array($config['poller_id']));

			$down_hosts = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM host
				WHERE status = 1
				AND disabled = ""
				AND poller_id = ?',
				array($config['poller_id']));

			$thresholds = db_fetch_cell_prepared('SELECT COUNT(*)
				FROM thold_data
				INNER JOIN host
				ON host.id = thold_data.host_id
				WHERE poller_id = ?
				AND disabled = ""',
				array($config['poller_id']));
		} else {
			/* host_status processed by thold server */
			$nhosts = thold_update_host_status();

			thold_cleanup_log();

			$total_hosts = db_fetch_cell('SELECT COUNT(*)
				FROM host
				WHERE disabled=""');

			$down_hosts = db_fetch_cell('SELECT COUNT(*)
				FROM host
				WHERE status = 1
				AND disabled = ""');

			$thresholds = db_fetch_cell('SELECT COUNT(*)
				FROM thold_data
				INNER JOIN host
				ON host.id = thold_data.host_id
				WHERE disabled = ""');
		}

		thold_prune_old_data();

		/* record the end time */
		$end = microtime(true);

		/* log statistics */
		$thold_stats = sprintf('Time:%0.2f TotalDevices:%u DownDevices:%u NewDownDevices:%u Threads:%u Thresholds:%u',
			$end - $start, $total_hosts, $down_hosts, $nhosts, $threads, $thresholds);

		cacti_log('THOLD POLLER STATS: ' . $thold_stats, false, 'SYSTEM');

		set_config_option('stats_thold_' . $config['poller_id'], $thold_stats);
	}
}

/**
 * display_version - displays version information
 */
function display_version() {
	global $config;

	if (!function_exists('plugin_thold_version')) {
		include_once($config['base_path'] . '/plugins/thold/setup.php');
	}

    $info = plugin_thold_version();

    print "Cacti Thold Master Process, Version " . $info['version'] . ", " . COPYRIGHT_YEARS . "\n";
}

/**
 * display_help - displays the usage of the function
 */
function display_help () {
    display_version();

    print "\nusage: poller_thold.php [--debug] [--force]\n\n";
	print "This binary run various Threshold data collection and\n";
	print "Management function.\n\n";
    print "--force    - Force all the service checks to run now\n";
    print "--debug    - Display verbose output during execution\n\n";
}

