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
 * Breach notifications under the acknowledgment options (#729, #742).
 *
 * acknowledgment = "on" means the breach is waiting for the operator; the
 * Acknowledge action clears it to ''. Breach sends used to require '' as
 * well, so with either acknowledgment option a re-alert could never be
 * mailed: before the operator acknowledged, the send check refused it; after,
 * the Suspendible Notification check did. Under Persistent Acknowledgment the
 * first alert of a later breach was refused too, because a restoral does not
 * clear the flag.
 */
final class BreachNotificationAcknowledgmentTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/functions.php');
		self::loadPluginSourceAlways('includes/arrays.php');
		self::loadPluginConstants();
	}

	/**
	 * A hi/low threshold in breach at 95, mailing ops@example.org.
	 *
	 * @param array<string, mixed> $overrides
	 *
	 * @return ThresholdScenario
	 */
	private function breached(array $overrides = []) {
		return ThresholdScenario::threshold($overrides + [
			'lastread'           => 95,
			'thold_hi'           => 90,
			'thold_fail_trigger' => 1,
			'repeat_alert'       => 2,
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
	 * Time-based history: logged breaches inside the window, and how long ago
	 * the last alert mail went out (0 for never). Takes precedence over the
	 * scenario's defaults.
	 *
	 * @param int $failures
	 * @param int $lastAlert
	 *
	 * @return void
	 */
	private function timeBasedHistory($failures, $lastAlert) {
		array_unshift(CactiStubs::$matchedReturns['db_fetch_cell_prepared'],
			['SELECT COUNT(id)', $failures],
			['SELECT time', $lastAlert ? time() - $lastAlert : false]
		);
	}

	/**
	 * Suspendible Notification, breach not yet acknowledged: the re-alert has
	 * to go out. This is the case #729 reports.
	 *
	 * @return void
	 */
	public function testSuspendibleReAlertIsMailedWhileAwaitingAcknowledgment(): void {
		$outcome = $this->breached([
			'reset_ack'        => 'on',
			'acknowledgment'   => 'on',
			'thold_alert'      => STAT_HI,
			'thold_fail_count' => 1,
		])->poll();

		$this->assertSame(1, $outcome->mailCount());
		$this->assertSame([ST_NOTIFYRA], $outcome->logStatuses());
	}

	/**
	 * Suspendible Notification after the operator acknowledged: suspended.
	 *
	 * @return void
	 */
	public function testSuspendibleReAlertIsSuppressedOnceAcknowledged(): void {
		$outcome = $this->breached([
			'reset_ack'        => 'on',
			'acknowledgment'   => '',
			'thold_alert'      => STAT_HI,
			'thold_fail_count' => 1,
		])->poll();

		$this->assertSame(0, $outcome->mailCount());
	}

	/**
	 * Persistent Acknowledgment keeps re-alerting while awaiting acknowledgment.
	 *
	 * @return void
	 */
	public function testPersistentReAlertIsMailedWhileAwaitingAcknowledgment(): void {
		$outcome = $this->breached([
			'persist_ack'      => 'on',
			'acknowledgment'   => 'on',
			'thold_alert'      => STAT_HI,
			'thold_fail_count' => 1,
		])->poll();

		$this->assertSame(1, $outcome->mailCount());
	}

	/**
	 * Persistent Acknowledgment: the flag survives a restoral, so the next
	 * breach starts with acknowledgment "on". Its first alert must still go.
	 *
	 * @return void
	 */
	public function testPersistentFirstAlertOfANewBreachIsMailed(): void {
		$outcome = $this->breached([
			'persist_ack'    => 'on',
			'acknowledgment' => 'on',
		])->poll();

		$this->assertSame(1, $outcome->mailCount());
		$this->assertSame([ST_NOTIFYAL], $outcome->logStatuses());
	}

	/**
	 * A hi/low re-alert copied to the warning list reaches both lists.
	 *
	 * @return void
	 */
	public function testReAlertCopiedToTheWarningListIsMailedWhileAwaitingAcknowledgment(): void {
		$outcome = $this->copiedToWarningList($this->breached([
			'reset_ack'        => 'on',
			'acknowledgment'   => 'on',
			'thold_alert'      => STAT_HI,
			'thold_fail_count' => 1,
			'notify_alert'     => 1,
			'notify_warning'   => 2,
		]))->poll();

		$this->assertSame(2, $outcome->mailCount());
	}

	/**
	 * Hi/low alert falling back to warning while awaiting acknowledgment, with
	 * the alert copied to the warning list.
	 *
	 * @return void
	 */
	public function testAlertToWarningIsMailedWhileAwaitingAcknowledgment(): void {
		$outcome = $this->copiedToWarningList($this->breached([
			'thold_hi'                 => 1000,
			'thold_warning_hi'         => 90,
			'reset_ack'                => 'on',
			'acknowledgment'           => 'on',
			'thold_alert'              => STAT_HI,
			'thold_fail_count'         => 1,
			'thold_warning_fail_count' => 1,
			'repeat_alert'             => 0,
			'notify_alert'             => 1,
			'notify_warning'           => 2,
		]))->poll();

		$this->assertSame(2, $outcome->mailCount());
	}

	/**
	 * Baseline re-alert while awaiting acknowledgment. The reference maximum
	 * is 50 and the band allows 10% above it, so 95 is a breach.
	 *
	 * @return void
	 */
	public function testBaselineReAlertIsMailedWhileAwaitingAcknowledgment(): void {
		$outcome = $this->copiedToWarningList($this->breached([
			'thold_type'     => 1,
			'bl_pct_up'      => 10,
			'reset_ack'      => 'on',
			'acknowledgment' => 'on',
			'bl_alert'       => STAT_HI,
			'bl_fail_count'  => 1,
			'notify_alert'   => 1,
			'notify_warning' => 2,
		]))->referenceStatistics(50, 50, 50, 50)->poll();

		$this->assertSame(2, $outcome->mailCount());
	}

	/**
	 * Time-based re-alert while awaiting acknowledgment.
	 *
	 * @return void
	 */
	public function testTimeBasedReAlertIsMailedWhileAwaitingAcknowledgment(): void {
		$scenario = $this->copiedToWarningList($this->breached([
			'thold_type'     => 2,
			'time_hi'        => 90,
			'reset_ack'      => 'on',
			'acknowledgment' => 'on',
			'thold_alert'    => STAT_HI,
			'notify_alert'   => 1,
			'notify_warning' => 2,
		]));

		$this->timeBasedHistory(2, 1000);

		$outcome = $scenario->poll();

		$this->assertSame(2, $outcome->mailCount());
		$this->assertSame([ST_NOTIFYRA], $outcome->logStatuses());
	}

	/**
	 * Time-based warning: the first warning of a new breach under Persistent
	 * Acknowledgment.
	 *
	 * @return void
	 */
	public function testTimeBasedWarningIsMailedWhileAwaitingAcknowledgment(): void {
		$outcome = $this->breached([
			'thold_type'           => 2,
			'time_hi'              => 1000,
			'time_warning_hi'      => 90,
			'persist_ack'          => 'on',
			'acknowledgment'       => 'on',
			'notify_warning_extra' => 'warn@example.org',
		])->poll();

		$this->assertSame(1, $outcome->mailCount());
		$this->assertSame([ST_NOTIFYWA], $outcome->logStatuses());
	}
}
