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
 * The time-based warning path must not overwrite $trigger (#748).
 *
 * $trigger holds the alert fail trigger (time_fail_trigger). The warning path
 * reused the name for the boolean it passes to get_email_subject(), so the
 * later "has the alert dropped back to a warning" test compared the alert
 * failure count against true. Under PHP 8 that holds only for a count of
 * zero, so an alert that fell back to warning while it still had logged
 * failures was recorded as a plain warning trigger instead of ALERT > WARNING.
 * #793 fixed the same reuse in the alert path.
 */
final class TimeBasedWarningTriggerTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/functions.php');
		self::loadPluginSourceAlways('includes/arrays.php');
		self::loadPluginConstants();
	}

	/**
	 * A time-based threshold reading 95: past the warning level, under the
	 * alert level.
	 *
	 * @param array<string, mixed> $overrides
	 * @param int                  $failures  Logged breaches inside the window.
	 *
	 * @return ThresholdScenario
	 */
	private function warning(array $overrides, $failures) {
		$scenario = ThresholdScenario::threshold($overrides + [
			'thold_type'           => 2,
			'lastread'             => 95,
			'time_hi'              => 1000,
			'time_warning_hi'      => 90,
			'notify_warning_extra' => 'warn@example.org',
		])->alertRecipient('ops@example.org');

		array_unshift(CactiStubs::$matchedReturns['db_fetch_cell_prepared'], ['SELECT COUNT(id)', $failures]);

		return $scenario;
	}

	/**
	 * The threshold was in alert and the alert window holds one failure
	 * against a trigger of 3. That failure counts toward the warning window as
	 * well, so the warning count is 2 (3 after this poll), still under its
	 * trigger of 5: no mail, and the poll is logged as ALERT > WARNING.
	 *
	 * @return void
	 */
	public function testAlertFallingBackToWarningIsLoggedAsAlertToWarning(): void {
		$outcome = $this->warning([
			'time_fail_trigger'         => 3,
			'time_warning_fail_trigger' => 5,
			'thold_alert'               => STAT_HI,
		], 1)->poll();

		$this->assertSame(0, $outcome->mailCount());
		$this->assertSame([ST_NOTIFYRAW], $outcome->logStatuses());
	}

	/**
	 * Below its trigger with no earlier alert, a warning is logged as a
	 * trigger.
	 *
	 * @return void
	 */
	public function testWarningBelowItsTriggerIsLoggedAsATrigger(): void {
		$outcome = $this->warning([
			'time_fail_trigger'         => 3,
			'time_warning_fail_trigger' => 5,
		], 0)->poll();

		$this->assertSame(0, $outcome->mailCount());
		$this->assertSame([ST_TRIGGERW], $outcome->logStatuses());
	}

	/**
	 * A warning that reaches its trigger is mailed with a WARNING subject.
	 *
	 * @return void
	 */
	public function testWarningAtItsTriggerIsMailed(): void {
		$outcome = $this->warning([], 0)->poll();

		$this->assertSame(1, $outcome->mailCount());
		$this->assertStringStartsWith('WARNING:', $outcome->subjects()[0]);
		$this->assertSame([ST_NOTIFYWA], $outcome->logStatuses());
	}
}
