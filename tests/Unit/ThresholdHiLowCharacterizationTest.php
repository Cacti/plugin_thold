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
 * What a hi/low threshold does over one poll.
 *
 * These record present behaviour so the evaluator can be restructured without
 * changing it. Where present behaviour is a filed bug the assertion says so
 * and names the issue, so the fix is a deliberate edit here rather than a
 * silent change in a refactor.
 */
final class ThresholdHiLowCharacterizationTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
		self::loadPluginSource('includes/arrays.php');
		self::loadPluginConstants();
	}

	/**
	 * A threshold with an upper bound of 90 and a warning bound of 80.
	 *
	 * @param array<string, mixed> $overrides
	 *
	 * @return ThresholdScenario
	 */
	private function bounded(array $overrides = []) {
		return ThresholdScenario::threshold($overrides + [
			'thold_hi'          => 90,
			'thold_low'         => 10,
			'thold_warning_hi'  => 80,
			'thold_warning_low' => 20,
		])->alertRecipient('ops@example.org');
	}

	/**
	 * @return void
	 */
	public function testReadingInsideBothBoundsEmitsNothing(): void {
		$outcome = $this->bounded(['lastread' => 50])->poll();

		$this->assertTrue($outcome->isSilent());
	}

	/**
	 * @return void
	 */
	public function testBreachAtTriggerNotifiesAndLogsTheAlert(): void {
		$outcome = $this->bounded(['lastread' => 95, 'thold_fail_trigger' => 1])->poll();

		$this->assertSame(1, $outcome->mailCount());
		$this->assertSame([ST_NOTIFYAL], $outcome->logStatuses());
		$this->assertTrue($outcome->touchedLastChanged());

		/*
		 * The legacy contact list is joined with the global address and the
		 * device address whether or not those are set, so the To header carries
		 * trailing empty entries. Recorded, not endorsed.
		 */
		$this->assertSame(['ops@example.org,,'], $outcome->recipients());
	}

	/**
	 * @return void
	 */
	public function testBreachBelowTriggerCountsButDoesNotNotify(): void {
		$outcome = $this->bounded([
			'lastread'           => 95,
			'thold_fail_trigger' => 3,
			'thold_fail_count'   => 0,
		])->poll();

		$this->assertSame(0, $outcome->mailCount());

		/*
		 * No log row either. ST_TRIGGERA exists for this case but is only
		 * written by the time-based arm, so a hi/low threshold counting up to
		 * its trigger leaves no trace in the log.
		 */
		$this->assertSame([], $outcome->logStatuses());

		/*
		 * An alert breach also zeroes the warning counter, so a threshold that
		 * crosses the warning bound on its way up loses that progress.
		 */
		$this->assertSame(['alert' => 1, 'warning' => 0], $outcome->persistedFailCounts());
	}

	/**
	 * The alert state is recorded on the first breaching poll, before the
	 * trigger count is met, so the interface shows a threshold in alert that
	 * has not notified and may never do so.
	 *
	 * @return void
	 */
	public function testAlertStateIsRecordedBeforeTheTriggerIsMet(): void {
		$outcome = $this->bounded([
			'lastread'           => 95,
			'thold_fail_trigger' => 3,
		])->poll();

		$this->assertSame(STAT_HI, $outcome->persistedAlertState());
	}

	/**
	 * @return void
	 */
	public function testBreachBelowTheLowerBoundRecordsTheLowState(): void {
		$outcome = $this->bounded(['lastread' => 5])->poll();

		$this->assertSame(STAT_LO, $outcome->persistedAlertState());
		$this->assertSame([ST_NOTIFYAL], $outcome->logStatuses());
	}

	/**
	 * @return void
	 */
	public function testReadingBetweenWarningAndAlertBoundsNotifiesTheWarning(): void {
		$outcome = $this->bounded(['lastread' => 85])->poll();

		$this->assertSame([ST_NOTIFYWA], $outcome->logStatuses());
	}

	/**
	 * A threshold already in alert whose reading falls back into the warning
	 * band is a de-escalation, not a restoral, and gets its own notification.
	 *
	 * @return void
	 */
	public function testFallingFromAlertIntoTheWarningBandNotifiesTheDowngrade(): void {
		$outcome = $this->bounded([
			'lastread'                 => 85,
			'thold_alert'              => STAT_HI,
			'thold_fail_count'         => 5,
			'thold_warning_fail_count' => 5,
		])->poll();

		$this->assertSame([ST_NOTIFYAW], $outcome->logStatuses());
		$this->assertStringStartsWith('ALERT > WARNING', $outcome->subjects()[0]);
	}

	/**
	 * @return void
	 */
	public function testRestoralFromAlertNotifiesAndClearsTheState(): void {
		$outcome = $this->bounded([
			'lastread'         => 50,
			'thold_alert'      => STAT_HI,
			'thold_fail_count' => 3,
		])->poll();

		$this->assertSame([ST_NOTIFYRS], $outcome->logStatuses());
		$this->assertSame(STAT_NORMAL, $outcome->persistedAlertState());
		$this->assertSame(1, $outcome->mailCount());
	}

	/**
	 * @return void
	 */
	public function testRestoralResetsBothFailCounts(): void {
		$outcome = $this->bounded([
			'lastread'                 => 50,
			'thold_alert'              => STAT_HI,
			'thold_fail_count'         => 3,
			'thold_warning_fail_count' => 2,
		])->poll();

		$this->assertSame(['alert' => 0, 'warning' => 0], $outcome->persistedFailCounts());
	}

	/**
	 * A re-alert fires when the fail count passes the trigger and lands on a
	 * multiple of repeat_alert.
	 *
	 * @return void
	 */
	public function testRepeatAlertNotifiesAgainOnTheConfiguredInterval(): void {
		$outcome = $this->bounded([
			'lastread'           => 95,
			'thold_fail_trigger' => 1,
			'thold_fail_count'   => 3,
			'repeat_alert'       => 2,
		])->poll();

		$this->assertSame([ST_NOTIFYRA], $outcome->logStatuses());
	}

	/**
	 * @return void
	 */
	public function testRepeatAlertStaysQuietBetweenIntervals(): void {
		$outcome = $this->bounded([
			'lastread'           => 95,
			'thold_fail_trigger' => 1,
			'thold_fail_count'   => 1,
			'repeat_alert'       => 3,
		])->poll();

		$this->assertSame(0, $outcome->mailCount());
	}

	/**
	 * @return void
	 */
	public function testAcknowledgedThresholdDoesNotMailOnBreach(): void {
		$outcome = $this->bounded([
			'lastread'       => 95,
			'acknowledgment' => 'on',
		])->poll();

		$this->assertSame(0, $outcome->mailCount());
	}

	/**
	 * @return void
	 */
	public function testPersistAckSetsTheAcknowledgmentOnFirstNotification(): void {
		$outcome = $this->bounded([
			'lastread'    => 95,
			'persist_ack' => 'on',
		])->poll();

		$this->assertTrue($outcome->acknowledged());
	}

	/**
	 * A device in a maintenance window still evaluates, but must not notify
	 * and must not advance the fail count.
	 *
	 * @return void
	 */
	public function testMaintenanceWindowSuppressesNotification(): void {
		$outcome = $this->bounded(['lastread' => 95])->inMaintenance()->poll();

		$this->assertSame(0, $outcome->mailCount());
		$this->assertSame([], $outcome->logStatuses());
	}

	/**
	 * @return void
	 */
	public function testUnknownReadingEmitsNoAlert(): void {
		$outcome = $this->bounded([
			'lastread'         => 'U',
			'thold_alert'      => 2,
			'thold_fail_count' => 3,
		])->poll();

		$this->assertSame(0, $outcome->mailCount());
		$this->assertNull($outcome->persistedAlertState());
	}

	/**
	 * With no bounds configured the threshold can never breach, whatever the
	 * reading.
	 *
	 * @return void
	 */
	public function testThresholdWithNoBoundsNeverBreaches(): void {
		$outcome = ThresholdScenario::threshold(['lastread' => 99999])
			->alertRecipient('ops@example.org')
			->poll();

		$this->assertTrue($outcome->isSilent());
	}

	/**
	 * @return void
	 */
	public function testDisabledGloballyStopsBeforeAnyEvaluation(): void {
		$outcome = $this->bounded(['lastread' => 95])
			->option('thold_disable_all', 'on')
			->poll();

		$this->assertTrue($outcome->isSilent());
	}
}
