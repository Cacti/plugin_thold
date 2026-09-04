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
 * What a time-based threshold does over one poll.
 *
 * This arm is a copy of the hi/low arm that has drifted, so several tests here
 * exist to record where the two now disagree. Those are marked, with the issue
 * that tracks the disagreement.
 */
final class ThresholdTimeBasedCharacterizationTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
		self::loadPluginSource('includes/arrays.php');
		self::loadPluginConstants();
	}

	/**
	 * @param array<string, mixed> $overrides
	 *
	 * @return ThresholdScenario
	 */
	private function bounded(array $overrides = []) {
		return ThresholdScenario::threshold($overrides + [
			'thold_type' => 2,
			'time_hi'    => 90,
			'time_low'   => 10,
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
		$outcome = $this->bounded(['lastread' => 95, 'time_fail_trigger' => 1])->poll();

		$this->assertSame(1, $outcome->mailCount());
		$this->assertSame([ST_NOTIFYAL], $outcome->logStatuses());
		$this->assertSame(STAT_HI, $outcome->persistedAlertState());
	}

	/**
	 * @return void
	 */
	public function testBreachBelowTheLowerBoundRecordsTheLowState(): void {
		$outcome = $this->bounded(['lastread' => 5, 'time_fail_trigger' => 1])->poll();

		$this->assertSame(STAT_LO, $outcome->persistedAlertState());
	}

	/**
	 * The hi/low arm mails on restoral. This one writes the restoral to the log
	 * and clears the state, but sends nothing, so an operator watching a
	 * time-based threshold sees the alert and never the all-clear.
	 *
	 * @return void
	 */
	public function testRestoralLogsButDoesNotMail(): void {
		$outcome = $this->bounded([
			'lastread'         => 50,
			'thold_alert'      => STAT_HI,
			'thold_fail_count' => 3,
		])->poll();

		$this->assertSame([ST_NOTIFYRS], $outcome->logStatuses());
		$this->assertSame(STAT_NORMAL, $outcome->persistedAlertState());
		$this->assertSame(0, $outcome->mailCount());
	}

	/**
	 * @return void
	 */
	public function testRestoralResetsTheFailCounts(): void {
		$outcome = $this->bounded([
			'lastread'                 => 50,
			'thold_alert'              => STAT_HI,
			'thold_fail_count'         => 3,
			'thold_warning_fail_count' => 2,
		])->poll();

		$this->assertSame(['alert' => 0, 'warning' => 0], $outcome->persistedFailCounts());
	}

	/**
	 * @return void
	 */
	public function testMaintenanceWindowSuppressesNotification(): void {
		$outcome = $this->bounded(['lastread' => 95, 'time_fail_trigger' => 1])
			->inMaintenance()
			->poll();

		$this->assertSame(0, $outcome->mailCount());
	}

	/**
	 * @return void
	 */
	public function testAcknowledgedThresholdDoesNotMailOnBreach(): void {
		$outcome = $this->bounded([
			'lastread'          => 95,
			'time_fail_trigger' => 1,
			'acknowledgment'    => 'on',
		])->poll();

		$this->assertSame(0, $outcome->mailCount());
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
	 * @return void
	 */
	public function testThresholdWithNoBoundsNeverBreaches(): void {
		$outcome = ThresholdScenario::threshold(['thold_type' => 2, 'lastread' => 99999])
			->alertRecipient('ops@example.org')
			->poll();

		$this->assertTrue($outcome->isSilent());
	}
}
