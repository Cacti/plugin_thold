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
 * How a drain of the notification queue scopes itself.
 *
 * thold_notify.php stamps its own identifier on the rows it intends to handle,
 * then drains. If the drain ignores that stamp, two overlapping runs mail the
 * same notifications.
 */
final class NotificationQueueClaimTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * Every query the drain issued against the queue.
	 *
	 * @return array<int, string>
	 */
	private function queueQueries() {
		$queries = [];

		foreach (CactiStubs::$calls as $call) {
			if (strpos($call['sql'], 'notification_queue') !== false) {
				$queries[] = preg_replace('/\s+/', ' ', $call['sql']);
			}
		}

		return $queries;
	}

	/**
	 * @return void
	 */
	public function testADrainWithAnIdentifierOnlyTakesThatProcessesRows(): void {
		thold_notification_execute(4242);

		$queries = $this->queueQueries();

		$this->assertNotEmpty($queries);

		foreach ($queries as $sql) {
			$this->assertStringContainsString('process_id = 4242', $sql);
		}
	}

	/**
	 * Passing nothing is what thold_notify.php used to do, and it selects
	 * every unprocessed row regardless of who claimed it.
	 *
	 * @return void
	 */
	public function testADrainWithoutAnIdentifierIsUnscoped(): void {
		thold_notification_execute();

		$queries = $this->queueQueries();

		$this->assertNotEmpty($queries);

		foreach ($queries as $sql) {
			$this->assertStringNotContainsString('process_id =', $sql);
		}
	}

	/**
	 * @return void
	 */
	public function testBothStagesThatReadTheQueueAreScoped(): void {
		thold_notification_execute(77);

		$queries = $this->queueQueries();

		// the non-device stage and the device stage each select from the queue
		$this->assertCount(2, $queries);

		foreach ($queries as $sql) {
			$this->assertStringContainsString('process_id = 77', $sql);
		}
	}

	/**
	 * @return void
	 */
	public function testTheDrainRespectsARecordLimit(): void {
		thold_notification_execute(5, 10);

		$limited = array_filter($this->queueQueries(), static function ($sql) {
			return strpos($sql, 'LIMIT 10') !== false;
		});

		$this->assertNotEmpty($limited);
	}

	/**
	 * The collector claims only rows nobody holds, so a second instance
	 * cannot take rows the first is already working on.
	 *
	 * @return void
	 */
	public function testTheClaimTakesOnlyUnheldRows(): void {
		$src = file_get_contents(dirname(__DIR__, 2) . '/thold_notify.php');

		$this->assertMatchesRegularExpression(
			'/SET process_id = \?\s+WHERE event_processed = 0\s+AND process_id = 0/',
			$src
		);
	}

	/**
	 * The claim has to follow the registration, or a second instance stamps
	 * its identifier over the first instance's rows before discovering that
	 * it should exit.
	 *
	 * @return void
	 */
	public function testTheClaimFollowsTheProcessRegistration(): void {
		$src = file_get_contents(dirname(__DIR__, 2) . '/thold_notify.php');

		$registered = strpos($src, "register_process_start('thold_notify'");
		$claimed    = strpos($src, 'SET process_id = ?');

		$this->assertNotFalse($registered);
		$this->assertNotFalse($claimed);
		$this->assertLessThan($claimed, $registered);
	}

	/**
	 * Without a way to ask whether the recorded process is still alive, the
	 * run must stand down rather than proceed beside it. It previously fell
	 * through and drained the queue a second time.
	 *
	 * @return void
	 */
	public function testAnInstanceThatCannotCheckForAPeerStandsDown(): void {
		$src = file_get_contents(dirname(__DIR__, 2) . '/thold_notify.php');

		$this->assertMatchesRegularExpression('/\$running = true;/', $src);
		$this->assertMatchesRegularExpression('/if \(\$running\) \{\s+exit\(1\);/', $src);
	}
}
