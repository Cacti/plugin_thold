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
 * Drives one threshold through one poll of thold_check_threshold().
 *
 * The function has no return value: everything it decides is expressed as a
 * side effect, through seven global Cacti functions. This builds the fixture
 * those functions need, runs one poll, and hands back a ThresholdOutcome so a
 * test can assert on what was emitted rather than on how it was computed.
 */
final class ThresholdScenario {
	/**
	 * @var array<string, mixed>
	 */
	private $thold;

	/**
	 * A threshold row with every column the function reads, set to values that
	 * on their own produce no breach and no notification.
	 *
	 * @param array<string, mixed> $overrides Columns to change.
	 */
	private function __construct(array $overrides) {
		$this->thold = $overrides + [
			'id'                        => 1,
			'name'                      => 'CPU utilisation',
			'name_cache'                => 'CPU utilisation',
			'host_id'                   => 2,
			'local_data_id'             => 4,
			'local_graph_id'            => 7,
			'data_template_rrd_id'      => 9,
			'data_source_name'          => 'traffic_in',
			'thold_type'                => 0,
			'data_type'                 => 0,
			'lastread'                  => 50,
			'oldvalue'                  => 50,
			'lasttime'                  => 0,
			'rrd_step'                  => 300,

			'thold_hi'                   => '',
			'thold_low'                  => '',
			'thold_warning_hi'           => '',
			'thold_warning_low'          => '',
			'thold_fail_trigger'         => 1,
			'thold_warning_fail_trigger' => 1,
			'thold_fail_count'           => 0,
			'thold_warning_fail_count'   => 0,
			'thold_alert'                => 0,
			'repeat_alert'               => 0,

			'time_hi'                   => '',
			'time_low'                  => '',
			'time_warning_hi'           => '',
			'time_warning_low'          => '',
			'time_fail_trigger'         => 1,
			'time_warning_fail_trigger' => 1,
			'time_fail_length'          => 300,
			'time_warning_fail_length'  => 300,

			'bl_fail_count'             => 0,
			'bl_alert'                  => 0,
			'bl_pct_down'               => '',
			'bl_pct_up'                 => '',
			'bl_fail_trigger'           => 1,
			'bl_ref_time_range'         => 3600,
			'bl_type'                   => 0,
			'bl_cf'                     => 'AVG',
			'bl_thold_valid'            => 0,
			'cdef'                      => 0,

			'notify_warning'            => 0,
			'notify_alert'              => 0,
			'notify_extra'              => '',
			'notify_warning_extra'      => '',
			'persist_ack'               => '',
			'reset_ack'                 => '',
			'acknowledgment'            => '',
			'exempt'                    => '',

			'syslog_enabled'            => '',
			'syslog_priority'           => 5,
			'syslog_facility'           => 1,
			'snmp_event_severity'       => 3,
			'snmp_event_description'    => '',
			'snmp_engine_id'            => '',

			'trigger_cmd_high'          => '',
			'trigger_cmd_low'           => '',
			'trigger_cmd_norm'          => '',

			'notes'                     => '',
			'dnotes'                    => '',
			'external_id'               => '',
			'email_subject'             => '',
			'email_subject_warn'        => '',
			'email_subject_restoral'    => '',
			'restored_alert'            => '',
			'graph_timespan'            => 7,
			'show_units'                => '',
			'units_suffix'              => '',
			'decimals'                  => 2,
			'format_file'               => '',
			'thold_enabled'             => 'on',
			'thold_daemon_id'           => 0,
		];
	}

	/**
	 * @param array<string, mixed> $overrides
	 *
	 * @return self
	 */
	public static function threshold(array $overrides = []) {
		$scenario = new self($overrides);

		$scenario->device();

		/*
		 * The time-based arm multiplies this into a window bound; an empty
		 * value is a fatal on PHP 8 rather than a missing step.
		 */
		CactiStubs::willReturnFor('db_fetch_cell_prepared', 'SELECT rrd_step', 300);

		// Counts of prior log rows; the arms add these together arithmetically.
		CactiStubs::willReturnFor('db_fetch_cell_prepared', 'SELECT COUNT(id)', 0);

		return $scenario;
	}

	/**
	 * Program the device row the function loads for the threshold's host.
	 *
	 * @param array<string, mixed> $overrides
	 *
	 * @return self
	 */
	public function device(array $overrides = []) {
		CactiStubs::willReturnFor('db_fetch_row_prepared', 'FROM host WHERE id = ?', $overrides + [
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

		return $this;
	}

	/**
	 * Give the threshold a legacy alert contact, which is what makes the alert
	 * recipient list non-empty.
	 *
	 * @param string $address
	 *
	 * @return self
	 */
	public function alertRecipient($address) {
		CactiStubs::willReturnFor('db_fetch_assoc_prepared', 'FROM plugin_thold_contacts', [['data' => $address]]);

		return $this;
	}

	/**
	 * @param string $name
	 * @param mixed  $value
	 *
	 * @return self
	 */
	public function option($name, $value) {
		CactiStubs::$configOptions[$name] = $value;

		return $this;
	}

	/**
	 * Give the RRD a set of reference statistics for the baseline arm.
	 *
	 * thold reaches these through three rrdtool calls: file_exists, info to
	 * discover the data sources and consolidation functions, then a graph
	 * command whose PRINT output is decoded by position. The doubles below
	 * answer all three in the shapes that decode expects.
	 *
	 * @param float|int $average
	 * @param float|int $max
	 * @param float|int $min
	 * @param float|int $last
	 * @param string    $dsname
	 *
	 * @return self
	 */
	public function referenceStatistics($average, $max, $min, $last, $dsname = 'traffic_in') {
		/*
		 * With a storage location set, thold asks rrdtool whether the file
		 * exists rather than touching the filesystem, which keeps the fixture
		 * off disk.
		 */
		CactiStubs::$configOptions['storage_location'] = 1;

		CactiStubs::willReturnFor('db_fetch_cell_prepared', 'SELECT rrd_path', '/var/lib/cacti/rra/test.rrd');
		CactiStubs::willReturnFor('rrdtool_execute', 'file_exists', true);

		/*
		 * One rra line per consolidation function: the info parser sets a
		 * single flag per line, and the number of flags set has to match the
		 * number of values the graph command below prints.
		 */
		CactiStubs::willReturnFor('rrdtool_execute', 'info ', implode("\n", [
			'ds[' . $dsname . '].type = "COUNTER"',
			'rra[0].cf = "AVERAGE"',
			'rra[1].cf = "MAX"',
			'rra[2].cf = "MIN"',
			'rra[3].cf = "LAST"',
			'step = 300',
		]));

		/*
		 * First line is the graph size and is skipped; then one value per
		 * PRINT in AVG, MAX, MIN, LAST order; then the timing line.
		 */
		CactiStubs::willReturnFor('rrdtool_execute', 'graph x --start', implode("\n", [
			'0x0',
			(string) $average,
			(string) $max,
			(string) $min,
			(string) $last,
			'OK u:0.01 s:0.00 r:0.01',
		]));

		return $this;
	}

	/**
	 * Put the device into a maintenance window.
	 *
	 * @return self
	 */
	public function inMaintenance() {
		// Asked more than once per poll, so a queued value would run out.
		CactiStubs::willAlwaysReturn('api_plugin_is_enabled', true);
		CactiStubs::willAlwaysReturn('plugin_maint_check_cacti_host', true);

		/*
		 * thold include_once()s the maint plugin when it reports enabled. Point
		 * base_path at a tracked, repository-contained Cacti root fixture so a
		 * unit test never writes into the caller's Cacti checkout.
		 */
		$GLOBALS['config']['base_path'] = dirname(__DIR__) . '/fixtures/cacti-root';

		return $this;
	}

	/**
	 * Run one poll.
	 *
	 * @return ThresholdOutcome
	 */
	public function poll() {
		$thold = $this->thold;

		thold_check_threshold($thold);

		return new ThresholdOutcome($thold);
	}
}
