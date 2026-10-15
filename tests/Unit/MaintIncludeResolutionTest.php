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
 * Both maintenance-aware entry points resolve the maint plugin the same way:
 * the functions.php that ships at the plugin root, the includes/functions.php
 * of the newer layout, or an error log when neither is present. These tests
 * drive all three outcomes for thold_check_threshold() and
 * thold_update_host_status() so a future change to the lookup is caught.
 */
final class MaintIncludeResolutionTest extends TestCase {
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
	 * @param array<string, mixed> $overrides
	 *
	 * @return ThresholdScenario
	 */
	private function baseline(array $overrides = []) {
		return ThresholdScenario::threshold($overrides + [
			'thold_type'        => 1,
			'bl_type'           => 0,
			'bl_pct_up'         => 10,
			'bl_pct_down'       => 10,
			'bl_ref_time_range' => 3600,
			'bl_fail_trigger'   => 1,
		])
			->alertRecipient('ops@example.org')
			->referenceStatistics(100, 100, 100, 100);
	}

	/**
	 * @param string $name
	 *
	 * @return string
	 */
	private function fixtureRoot($name) {
		return dirname(__DIR__) . '/Fixtures/' . $name;
	}

	/**
	 * The newer maint layout keeps functions.php under includes/; a device in a
	 * maintenance window still resolves it and suppresses the notification.
	 *
	 * @return void
	 */
	public function testCheckThresholdResolvesMaintFromIncludesLayout(): void {
		$outcome = $this->baseline(['lastread' => 500])
			->inMaintenance($this->fixtureRoot('cacti-root-maint-includes'))
			->poll();

		$this->assertSame(0, $outcome->mailCount());
	}

	/**
	 * With neither maint layout present the include is skipped and the failure
	 * is logged rather than fatal.
	 *
	 * @return void
	 */
	public function testCheckThresholdLogsWhenMaintFunctionsMissing(): void {
		$this->baseline(['lastread' => 500])
			->inMaintenance($this->fixtureRoot('cacti-root-no-maint'))
			->poll();

		$this->assertContains('ERROR: cannot include maint functions', CactiStubs::$log);
	}

	/**
	 * Drive thold_update_host_status() far enough to run the maint include
	 * block. Downstream work needs a live Cacti, so engine messages are
	 * swallowed and any throw past the block is ignored.
	 *
	 * @param string $cactiRoot
	 *
	 * @return void
	 */
	private function runHostStatus($cactiRoot) {
		CactiStubs::willAlwaysReturn('api_plugin_is_enabled', true);
		CactiStubs::$configOptions['alert_deadnotify'] = 'on';
		$GLOBALS['config']['base_path'] = $cactiRoot;

		set_error_handler(static fn (): bool => true);
		ob_start();

		try {
			thold_update_host_status();
		} catch (\Throwable $e) {
			// Reaching the maint include block is the point.
		} finally {
			ob_end_clean();
			restore_error_handler();
		}
	}

	/**
	 * @return void
	 */
	public function testHostStatusResolvesMaintFromPluginRoot(): void {
		$this->runHostStatus($this->fixtureRoot('cacti-root'));

		$this->assertNotContains('ERROR: cannot include maint functions', CactiStubs::$log);
	}

	/**
	 * @return void
	 */
	public function testHostStatusResolvesMaintFromIncludesLayout(): void {
		$this->runHostStatus($this->fixtureRoot('cacti-root-maint-includes'));

		$this->assertNotContains('ERROR: cannot include maint functions', CactiStubs::$log);
	}

	/**
	 * @return void
	 */
	public function testHostStatusLogsWhenMaintFunctionsMissing(): void {
		$this->runHostStatus($this->fixtureRoot('cacti-root-no-maint'));

		$this->assertContains('ERROR: cannot include maint functions', CactiStubs::$log);
	}
}
