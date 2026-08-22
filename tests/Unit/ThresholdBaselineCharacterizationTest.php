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
 * What a baseline threshold does over one poll.
 *
 * This arm compares the reading against statistics rrdtool reports for a
 * reference window, so the scenario supplies those statistics rather than the
 * bounds the other two arms use. A reference average of 100 with a ten percent
 * band puts the normal range at 90 to 110.
 */
final class ThresholdBaselineCharacterizationTest extends TestCase {
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
	 * @return void
	 */
	public function testReadingInsideTheBandEmitsNothing(): void {
		$outcome = $this->baseline(['lastread' => 100])->poll();

		$this->assertTrue($outcome->isSilent());
		$this->assertSame(0, $outcome->thold['bl_alert']);
	}

	/**
	 * @return void
	 */
	public function testReadingAboveTheBandAlerts(): void {
		$outcome = $this->baseline(['lastread' => 500])->poll();

		$this->assertSame(STAT_HI, $outcome->thold['bl_alert']);
		$this->assertSame([ST_NOTIFYAL], $outcome->logStatuses());
		$this->assertSame(1, $outcome->mailCount());
	}

	/**
	 * @return void
	 */
	public function testReadingBelowTheBandAlerts(): void {
		$outcome = $this->baseline(['lastread' => 1])->poll();

		$this->assertSame(STAT_LO, $outcome->thold['bl_alert']);
		$this->assertSame([ST_NOTIFYAL], $outcome->logStatuses());
		$this->assertSame(1, $outcome->mailCount());
	}

	/**
	 * @return void
	 */
	public function testReturningToTheBandNotifiesTheRestoral(): void {
		$outcome = $this->baseline([
			'lastread'      => 100,
			'bl_alert'      => STAT_HI,
			'bl_fail_count' => 3,
		])->poll();

		$this->assertSame(0, $outcome->thold['bl_alert']);
		$this->assertSame([ST_RESTORAL], $outcome->logStatuses());
		$this->assertSame(1, $outcome->mailCount());
	}

	/**
	 * When rrdtool returns no reference statistics the arm cannot decide
	 * anything, so it reports -1 and leaves the threshold alone. This is the
	 * state a newly created baseline threshold sits in until its reference
	 * window has filled.
	 *
	 * @return void
	 */
	public function testMissingReferenceStatisticsEmitsNothing(): void {
		$outcome = ThresholdScenario::threshold([
			'thold_type'  => 1,
			'bl_type'     => 0,
			'bl_pct_up'   => 10,
			'bl_pct_down' => 10,
			'lastread'    => 50,
		])->alertRecipient('ops@example.org')->poll();

		$this->assertSame(-1, $outcome->thold['bl_alert']);
		$this->assertTrue($outcome->isSilent());
	}

	/**
	 * @return void
	 */
	public function testBreachBelowTheTriggerDoesNotNotify(): void {
		$outcome = $this->baseline([
			'lastread'        => 500,
			'bl_fail_trigger' => 3,
			'bl_fail_count'   => 0,
		])->poll();

		$this->assertSame(0, $outcome->mailCount());
	}

	/**
	 * @return void
	 */
	public function testMaintenanceWindowSuppressesNotification(): void {
		$outcome = $this->baseline(['lastread' => 500])->inMaintenance()->poll();

		$this->assertSame(0, $outcome->mailCount());
	}

	/**
	 * @return void
	 */
	public function testAcknowledgedThresholdDoesNotMailOnBreach(): void {
		$outcome = $this->baseline([
			'lastread'       => 500,
			'acknowledgment' => 'on',
		])->poll();

		$this->assertSame(0, $outcome->mailCount());
	}

	/**
	 * An absolute-deviation baseline adds the configured amount to the
	 * reference rather than a percentage of it, so 100 with a band of 10 gives
	 * the same 90 to 110 range for a very different configuration.
	 *
	 * @return void
	 */
	public function testAbsoluteDeviationUsesTheBandAsAnAmount(): void {
		$inside = $this->baseline([
			'bl_type'     => 2,
			'lastread'    => 105,
			'bl_pct_up'   => 10,
			'bl_pct_down' => 10,
		])->poll();

		$this->assertSame(0, $inside->thold['bl_alert']);
	}

	/**
	 * @return void
	 */
	public function testAbsoluteDeviationAlertsOutsideTheAmount(): void {
		$outcome = $this->baseline([
			'bl_type'     => 2,
			'lastread'    => 200,
			'bl_pct_up'   => 10,
			'bl_pct_down' => 10,
		])->poll();

		$this->assertSame(STAT_HI, $outcome->thold['bl_alert']);
	}
}
