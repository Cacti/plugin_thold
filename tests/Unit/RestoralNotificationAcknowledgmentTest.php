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
 * Restoral notifications under the acknowledgment options (#747).
 *
 * acknowledgment = "on" means a breach is waiting for the operator. Most
 * restoral sends also required '', so a threshold that returned to normal
 * before anyone acknowledged it restored silently, while other restoral sends
 * did not check at all. Whether a restoral is mailed is now decided by the
 * "Disable Restoration Email" option alone.
 */
final class RestoralNotificationAcknowledgmentTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/functions.php');
		self::loadPluginSourceAlways('includes/arrays.php');
		self::loadPluginConstants();
	}

	/**
	 * A hi/low threshold that was in alert and now reads 50, mailing
	 * ops@example.org, still awaiting acknowledgment.
	 *
	 * @param array<string, mixed> $overrides
	 *
	 * @return ThresholdScenario
	 */
	private function restored(array $overrides = []) {
		return ThresholdScenario::threshold($overrides + [
			'lastread'         => 50,
			'thold_hi'         => 90,
			'thold_alert'      => STAT_HI,
			'thold_fail_count' => 3,
			'persist_ack'      => 'on',
			'acknowledgment'   => 'on',
		])->alertRecipient('ops@example.org');
	}

	/**
	 * Address the alert and warning notification lists separately, with alerts
	 * copied to the warning list.
	 *
	 * @param ThresholdScenario $scenario
	 *
	 * @return ThresholdScenario
	 */
	private function copiedToWarningList(ThresholdScenario $scenario) {
		CactiStubs::willReturnFor('db_fetch_cell_prepared', 'SELECT emails FROM plugin_notification_lists', 'list@example.org');

		return $scenario->option('thold_notify_alerts_to_warning_recipients', 'on');
	}

	/**
	 * Hi/low alert restoral, copied to the warning list.
	 *
	 * @return void
	 */
	public function testAlertRestoralIsMailedWhileAwaitingAcknowledgment(): void {
		$outcome = $this->copiedToWarningList($this->restored([
			'notify_alert'   => 1,
			'notify_warning' => 2,
		]))->poll();

		$this->assertSame(2, $outcome->mailCount());
		$this->assertSame(STAT_NORMAL, $outcome->persistedAlertState());
	}

	/**
	 * The same under Suspendible Notification.
	 *
	 * @return void
	 */
	public function testSuspendibleAlertRestoralIsMailedWhileAwaitingAcknowledgment(): void {
		$outcome = $this->restored([
			'persist_ack' => '',
			'reset_ack'   => 'on',
		])->poll();

		$this->assertSame(1, $outcome->mailCount());
	}

	/**
	 * Hi/low warning restoral.
	 *
	 * @return void
	 */
	public function testWarningRestoralIsMailedWhileAwaitingAcknowledgment(): void {
		$outcome = $this->restored([
			'thold_hi'                 => 1000,
			'thold_warning_hi'         => 90,
			'thold_fail_count'         => 0,
			'thold_warning_fail_count' => 3,
			'notify_warning_extra'     => 'warn@example.org',
		])->poll();

		$this->assertSame(1, $outcome->mailCount());
		$this->assertStringStartsWith('warn@example.org', $outcome->recipients()[0]);
	}

	/**
	 * "Disable Restoration Email" still suppresses the restoral.
	 *
	 * @return void
	 */
	public function testRestoralIsNotMailedWhenRestorationEmailIsDisabled(): void {
		$outcome = $this->restored(['restored_alert' => 'on'])->poll();

		$this->assertSame(0, $outcome->mailCount());
	}

	/**
	 * Baseline back inside its band (reference 50, 10% allowed above), copied
	 * to the warning list.
	 *
	 * @return void
	 */
	public function testBaselineRestoralIsMailedWhileAwaitingAcknowledgment(): void {
		$outcome = $this->copiedToWarningList($this->restored([
			'thold_type'     => 1,
			'bl_pct_up'      => 10,
			'bl_alert'       => STAT_HI,
			'bl_fail_count'  => 3,
			'notify_alert'   => 1,
			'notify_warning' => 2,
		]))->referenceStatistics(50, 50, 50, 50)->poll();

		$this->assertSame(2, $outcome->mailCount());
	}

	/**
	 * Time-based alert restoral copied to the warning list. One logged breach
	 * counts toward both windows: the alert count (1) meets its trigger and
	 * the warning count (2) is under its own, which selects the alert
	 * restoral.
	 *
	 * @return void
	 */
	public function testTimeBasedAlertRestoralIsMailedWhileAwaitingAcknowledgment(): void {
		$scenario = $this->copiedToWarningList($this->restored([
			'thold_type'                => 2,
			'time_hi'                   => 90,
			'time_fail_trigger'         => 1,
			'time_warning_fail_trigger' => 3,
			'notify_alert'              => 1,
			'notify_warning'            => 2,
		]));

		array_unshift(CactiStubs::$matchedReturns['db_fetch_cell_prepared'], ['SELECT COUNT(id)', 1]);

		$outcome = $scenario->poll();

		$this->assertSame(2, $outcome->mailCount());
	}
}
