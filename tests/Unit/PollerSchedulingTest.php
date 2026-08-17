<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * Which thresholds a poller run evaluates, and what it does with the dirty
 * flag afterwards.
 */
final class PollerSchedulingTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
		self::loadPluginSource('includes/polling.php');
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$GLOBALS['config']['poller_id'] = 1;
		$GLOBALS['config']['base_path'] = sys_get_temp_dir() . '/thold-poller-scheduling';

		$plugins = $GLOBALS['config']['base_path'] . '/plugins';

		if (!is_dir($plugins)) {
			mkdir($plugins, 0777, true);
		}

		if (!file_exists($plugins . '/thold')) {
			symlink(dirname(__DIR__, 2), $plugins . '/thold');
		}

		/*
		 * includes/polling.php includes Cacti's lib/time.php at call time.
		 * Supply an empty one under the configured base path; nothing under
		 * test reads from it.
		 */
		$lib = $GLOBALS['config']['base_path'] . '/lib';

		if (!is_dir($lib)) {
			mkdir($lib, 0777, true);
		}

		if (!file_exists($lib . '/time.php')) {
			file_put_contents($lib . '/time.php', "<?php\n");
		}

		CactiStubs::willReturnFor('db_fetch_row_prepared', 'FROM host WHERE id = ?', [
			'id'                => 2,
			'description'       => 'core-switch-1',
			'hostname'          => '10.0.0.1',
			'location'          => 'rack 4',
			'site_id'           => 1,
			'status'            => 3,
			'status_fail_date'  => '2026-01-01 00:00:00',
			'status_rec_date'   => '2026-01-02 00:00:00',
			'status_last_error' => '',
			'snmp_engine_id'    => '',
			'notes'             => '',
		]);
	}

	/**
	 * A threshold row with every column the evaluator reads, set so that it
	 * evaluates quietly. Only the identifier varies between rows here; what is
	 * under test is which rows are visited, not what visiting them decides.
	 *
	 * @param int $id
	 *
	 * @return array<string, mixed>
	 */
	private function threshold($id) {
		return [
			'id'                   => $id, 'host_id' => 2, 'local_data_id' => 4, 'local_graph_id' => 9,
			'data_template_rrd_id' => 3, 'name' => 'CPU', 'name_cache' => 'CPU',
			'data_source_name'     => 'traffic_in', 'thold_type' => 0, 'data_type' => 0,
			'lastread'             => 50, 'oldvalue' => 50, 'lasttime' => 0, 'rrd_step' => 300,
			'thold_hi'             => '', 'thold_low' => '', 'thold_warning_hi' => '', 'thold_warning_low' => '',
			'thold_fail_trigger'   => 1, 'thold_warning_fail_trigger' => 1,
			'thold_fail_count'     => 0, 'thold_warning_fail_count' => 0,
			'thold_alert'          => 0, 'repeat_alert' => 0,
			'time_hi'              => '', 'time_low' => '', 'time_warning_hi' => '', 'time_warning_low' => '',
			'time_fail_trigger'    => 1, 'time_warning_fail_trigger' => 1,
			'time_fail_length'     => 300, 'time_warning_fail_length' => 300,
			'bl_fail_count'        => 0, 'bl_alert' => 0, 'bl_pct_down' => '', 'bl_pct_up' => '',
			'bl_fail_trigger'      => 1, 'bl_ref_time_range' => 3600, 'bl_type' => 0,
			'bl_cf'                => 'AVG', 'bl_thold_valid' => 0, 'cdef' => 0,
			'notify_warning'       => 0, 'notify_alert' => 0, 'notify_extra' => '',
			'notify_warning_extra' => '', 'persist_ack' => '', 'reset_ack' => '',
			'acknowledgment'       => '', 'exempt' => '', 'restored_alert' => '',
			'syslog_enabled'       => '', 'syslog_priority' => 5, 'syslog_facility' => 1,
			'snmp_event_severity'  => 3, 'snmp_event_description' => '', 'snmp_engine_id' => '',
			'trigger_cmd_high'     => '', 'trigger_cmd_low' => '', 'trigger_cmd_norm' => '',
			'notes'                => '', 'dnotes' => '', 'external_id' => '',
			'email_subject'        => '', 'email_subject_warn' => '', 'email_subject_restoral' => '',
			'graph_timespan'       => 7, 'show_units' => '', 'units_suffix' => '', 'decimals' => 2,
			'format_file'          => '', 'thold_enabled' => 'on', 'thold_daemon_id' => 0,
		];
	}

	/**
	 * Only the thresholds that were evaluated get their dirty flag cleared.
	 *
	 * Clearing by poller, or across the whole table, also cleared the ones
	 * whose data arrived after the select, so those readings were dropped
	 * without ever being checked.
	 *
	 * @return void
	 */
	public function testOnlyTheEvaluatedThresholdsAreCleared(): void {
		CactiStubs::willReturnFor('db_fetch_assoc', 'FROM thold_data', [
			$this->threshold(7),
			$this->threshold(9),
		]);

		thold_check_all_thresholds();

		$clears = array_values(array_filter(CactiStubs::callsTo('db_execute_prepared'), static function ($call) {
			return strpos($call['sql'], 'SET tcheck = 0') !== false;
		}));

		$this->assertCount(1, $clears);
		$this->assertSame([7, 9], $clears[0]['params']);
		$this->assertStringContainsString('WHERE id IN (?, ?)', $clears[0]['sql']);
	}

	/**
	 * @return void
	 */
	public function testNothingIsClearedWhenNothingWasEvaluated(): void {
		CactiStubs::willReturnFor('db_fetch_assoc', 'FROM thold_data', []);

		thold_check_all_thresholds();

		$clears = array_filter(CactiStubs::$calls, static function ($call) {
			return strpos($call['sql'], 'tcheck = 0') !== false;
		});

		$this->assertSame([], array_values($clears));
	}

	/**
	 * @return void
	 */
	public function testTheRunReportsHowManyThresholdsItEvaluated(): void {
		CactiStubs::willReturnFor('db_fetch_assoc', 'FROM thold_data', [
			$this->threshold(1),
			$this->threshold(2),
			$this->threshold(3),
		]);

		$this->assertSame(3, thold_check_all_thresholds());
	}

	/**
	 * The whole table is never cleared in one statement.
	 *
	 * @return void
	 */
	public function testTheDirtyFlagIsNeverClearedTableWide(): void {
		CactiStubs::willReturnFor('db_fetch_assoc', 'FROM thold_data', [
			$this->threshold(7),
		]);

		thold_check_all_thresholds();

		foreach (CactiStubs::$calls as $call) {
			if (strpos($call['sql'], 'tcheck = 0') !== false) {
				$this->assertStringContainsString('WHERE id IN', $call['sql']);
			}
		}
	}

	/**
	 * The daemon path batches readings before writing them, and the batch size
	 * is what array_chunk() takes. Passing a count meant 500 readings became
	 * 50 batches of 10 rather than 10 of 50, and each batch costs a round trip.
	 *
	 * @return void
	 */
	public function testTheDaemonWritesReadingsInBatchesOfFifty(): void {
		CactiStubs::$configOptions['thold_daemon_enable'] = 'on';

		$readings = [];

		for ($i = 1; $i <= 120; $i++) {
			$readings[] = ['local_data_id' => $i, 'times' => [1700000000 => ['traffic_in' => 5]]];
		}

		// One threshold per reading, answering with just the batch that was asked
		// about so each write matches the readings it was given.
		CactiStubs::willReturnFor('db_fetch_assoc', 'SELECT id, local_data_id, thread_id', static function ($sql) {
			preg_match('/IN \(([^)]*)\)/', $sql, $matches);

			return array_map(static function ($id) {
				$id = (int) trim($id);

				return ['id' => $id, 'local_data_id' => $id, 'thread_id' => 1];
			}, explode(',', $matches[1]));
		});

		thold_poller_output($readings);

		$inserts = array_values(array_filter(CactiStubs::$calls, static function ($call) {
			return strpos($call['sql'], 'plugin_thold_daemon_data') !== false;
		}));

		// 120 readings at 50 to a batch is three writes, not one per reading.
		$this->assertCount(3, $inserts);
	}

	/**
	 * A structural guard rather than a behavioural one: the defect is SQL
	 * operator precedence, and proving it needs a database to run the query
	 * against. Without the parentheses, AND binds tighter than OR and the
	 * clause reads as
	 *
	 *   (... AND h.poller_id = 1) OR (h.poller_id IS NULL AND tcheck = 1 AND status = 3)
	 *
	 * whose first branch carries neither the dirty-flag nor the device-status
	 * filter, so every enabled threshold is re-evaluated on every run.
	 *
	 * @return void
	 */
	public function testThePollerAlternationIsParenthesised(): void {
		$src = file_get_contents(dirname(__DIR__, 2) . '/includes/polling.php');

		$this->assertStringContainsString('AND (h.poller_id = 1 OR h.poller_id IS NULL)', $src);
		$this->assertStringNotContainsString("AND h.poller_id = 1 OR h.poller_id IS NULL\n", $src);
	}

	/**
	 * array_chunk() takes the size of each chunk. Passing a count meant 500
	 * readings became 50 chunks of 10 rather than 10 chunks of 50, and each
	 * chunk costs a round trip.
	 *
	 * @return void
	 */
	public function testTheDaemonBatchesReadingsFiftyAtATime(): void {
		$src = file_get_contents(dirname(__DIR__, 2) . '/includes/polling.php');

		$this->assertStringContainsString('array_chunk($rrd_update_array, 50, true)', $src);
		$this->assertStringNotContainsString('ceil(sizeof($rrd_update_array) / 50)', $src);
	}
}
