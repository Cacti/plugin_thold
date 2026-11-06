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
 * The time-based warning path keeps the re-alert flag it computed (#744).
 *
 * $ra is worked out from the re-alert window (time since the last warning
 * mail), then was replaced by $warning_failures > $warning_trigger. On a poll
 * that mails, both expressions agree, so mail and the thold_action hook were
 * unaffected; the replacement only changed the subject recorded in the log on
 * polls that do not mail. The hi/low and time-based alert paths use the
 * computed flag throughout, and so does this path now.
 */
final class TimeBasedWarningRealertFlagTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/functions.php');
		self::loadPluginSourceAlways('includes/arrays.php');
		self::loadPluginConstants();
	}

	/**
	 * A time-based warning at 95 with one logged breach in the window, which
	 * puts it past its trigger of 1.
	 *
	 * @param int $lastWarning Seconds since the last warning mail, 0 for none.
	 *
	 * @return ThresholdOutcome
	 */
	private function poll($lastWarning) {
		$scenario = ThresholdScenario::threshold([
			'thold_type'           => 2,
			'lastread'             => 95,
			'time_hi'              => 1000,
			'time_warning_hi'      => 90,
			'repeat_alert'         => 2,
			'notify_warning_extra' => 'warn@example.org',
		]);

		array_unshift(CactiStubs::$matchedReturns['db_fetch_cell_prepared'],
			['SELECT COUNT(id)', 1],
			['SELECT time', $lastWarning ? time() - $lastWarning : false]
		);

		return $scenario->poll();
	}

	/**
	 * Descriptions of the rows thold_log() wrote.
	 *
	 * @return array<int, string>
	 */
	private function loggedDescriptions() {
		$descriptions = [];

		foreach (CactiStubs::callsTo('sql_save') as $call) {
			if ($call['sql'] === 'plugin_thold_log') {
				$descriptions[] = $call['params']['description'];
			}
		}

		return $descriptions;
	}

	/**
	 * Past the trigger but no warning mail on record, so this is not a
	 * re-alert: nothing is mailed and the log does not call it one.
	 *
	 * @return void
	 */
	public function testWarningPastItsTriggerWithoutARealertIsNotLoggedAsOne(): void {
		$outcome = $this->poll(0);

		$this->assertSame(0, $outcome->mailCount());
		$this->assertCount(1, $this->loggedDescriptions());
		$this->assertStringContainsString(' went above ', $this->loggedDescriptions()[0]);
	}

	/**
	 * A due re-alert is still mailed as one.
	 *
	 * @return void
	 */
	public function testDueWarningRealertIsMailedAsARealert(): void {
		$outcome = $this->poll(1000);

		$this->assertSame(1, $outcome->mailCount());
		$this->assertStringContainsString(' is still above ', $outcome->subjects()[0]);
		$this->assertSame([ST_NOTIFYRAW], $outcome->logStatuses());
	}
}
