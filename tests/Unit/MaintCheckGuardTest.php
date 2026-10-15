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
 * thold_update_host_status() consults the maint plugin for both the
 * recovering-device and down-device notification loops. The call is now
 * guarded by function_exists('plugin_maint_check_cacti_host') so a maint
 * plugin that is enabled but whose functions file could not be included no
 * longer fatals with an undefined-function error. These tests drive both
 * maintenance-gated branches so that guard stays in place.
 */
final class MaintCheckGuardTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/functions.php');
		self::loadPluginSource('includes/polling.php');
		self::loadPluginSourceAlways('includes/arrays.php');
		self::loadPluginConstants();
	}

	/**
	 * @return string
	 */
	private function maintIncludesRoot() {
		return dirname(__DIR__) . '/Fixtures/cacti-root-maint-includes';
	}

	/**
	 * Run thold_update_host_status() against the newer maint layout, swallowing
	 * engine output and any throw past the maintenance-gated branch, which is
	 * all these tests need to reach.
	 *
	 * @return void
	 */
	private function runHostStatus() {
		CactiStubs::willAlwaysReturn('api_plugin_is_enabled', true);
		CactiStubs::willAlwaysReturn('plugin_maint_check_cacti_host', true);
		CactiStubs::$configOptions['alert_deadnotify'] = 'on';
		$GLOBALS['config']['base_path'] = $this->maintIncludesRoot();

		set_error_handler(static fn (): bool => true);
		ob_start();

		try {
			thold_update_host_status();
		} catch (\Throwable $e) {
			// Reaching the maintenance-gated branch is the point.
		} finally {
			ob_end_clean();
			restore_error_handler();
		}
	}

	/**
	 * A recovered device inside a maintenance window is logged, not alerted.
	 *
	 * @return void
	 */
	public function testRecoveringDeviceInMaintenanceIsLoggedNotAlerted(): void {
		CactiStubs::willReturnFor('db_fetch_assoc', 'plugin_thold_host_failed', [['host_id' => 42]]);
		CactiStubs::willReturnFor('db_fetch_row_prepared', 'FROM host WHERE id', [
			'id'               => 42,
			'hostname'         => 'dev42',
			'description'      => 'Device 42',
			'status'           => HOST_UP,
			'snmp_community'   => '',
			'snmp_username'    => '',
			'snmp_version'     => 0,
			'site_id'          => 0,
			'location'         => '',
			'notes'            => '',
			'status_fail_date' => '0000-00-00 00:00:00',
			'availability'     => 100,
			'total_polls'      => 1,
			'failed_polls'     => 0,
			'cur_time'         => 0,
			'avg_time'         => 0,
			'thold_send_email' => '0',
		]);

		$this->runHostStatus();

		$this->assertContains(
			'WARNING: Device[42] Hostname[dev42] is recovering! Only logging, maint device',
			CactiStubs::$log
		);
	}

	/**
	 * A down device inside a maintenance window is logged, not alerted.
	 *
	 * @return void
	 */
	public function testDownDeviceInMaintenanceIsLoggedNotAlerted(): void {
		CactiStubs::willReturnFor('db_fetch_assoc_prepared', 'availability_method', [[
			'id'       => 77,
			'hostname' => 'dev77',
		]]);

		$this->runHostStatus();

		$this->assertContains(
			'WARNING: Device[77] Hostname[dev77] is DOWN. Only logging, maint device',
			CactiStubs::$log
		);
	}
}
