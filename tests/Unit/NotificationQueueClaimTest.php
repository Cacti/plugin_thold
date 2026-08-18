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
	 * @return void
	 */
	public function testADrainWithoutAnIdentifierFailsClosed(): void {
		thold_notification_execute();

		$this->assertSame([], $this->queueQueries());
		$this->assertNotEmpty(CactiStubs::$log);
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
	 * @return void
	 */
	public function testAClaimRecoversOrphansThenTakesOnlyUnheldRows(): void {
		CactiStubs::willReturn('db_affected_rows', 3);

		$this->assertSame(3, thold_notification_claim(4242));

		$calls = array_values(array_filter(CactiStubs::$calls, static function ($call) {
			return $call['fn'] === 'db_execute_prepared';
		}));

		$this->assertCount(2, $calls);
		$this->assertStringContainsString('LEFT JOIN processes', $calls[0]['sql']);
		$this->assertStringContainsString('p.pid IS NULL', $calls[0]['sql']);
		$this->assertSame(['thold_notify', 'child'], $calls[0]['params']);
		$this->assertStringContainsString('AND process_id = 0', $calls[1]['sql']);
		$this->assertSame([4242], $calls[1]['params']);
	}

	/**
	 * @return void
	 */
	public function testAReleaseReturnsOnlyTheWorkersUnfinishedRows(): void {
		$this->assertTrue(thold_notification_release_claim(4242));

		$calls = CactiStubs::$calls;
		$call  = end($calls);

		$this->assertSame('db_execute_prepared', $call['fn']);
		$this->assertStringContainsString('SET process_id = 0', $call['sql']);
		$this->assertStringContainsString('AND event_processed = 0', $call['sql']);
		$this->assertSame([4242], $call['params']);
	}

	/**
	 * @return void
	 */
	public function testUnverifiableWorkersUseABoundedAgeFallback(): void {
		$fresh = ['pid' => 42, 'started_at' => 900, 'current_timestamp' => 1000];
		$stale = ['pid' => 42, 'started_at' => 600, 'current_timestamp' => 1000];

		$this->assertTrue(thold_notification_process_blocks_start($fresh, 300));
		$this->assertFalse(thold_notification_process_blocks_start($stale, 300));
		$this->assertTrue(thold_notification_process_blocks_start($stale, 300, true));
		$this->assertFalse(thold_notification_process_blocks_start($fresh, 300, false));
		$this->assertFalse(thold_notification_process_blocks_start(['pid' => 0], 300));
	}

	/**
	 * @return void
	 */
	public function testRegistrationFailsClosedOnAQueryErrorAndReclaimsAStaleSlot(): void {
		$GLOBALS['config']['cacti_server_os'] = 'win32';
		CactiStubs::willReturn('db_fetch_row_prepared', false);

		$this->assertFalse(thold_notification_register_process(2, 300));
		$this->assertCount(1, CactiStubs::$calls);

		CactiStubs::reset();
		CactiStubs::willReturn('db_fetch_row_prepared', [
			'pid'               => 42,
			'started_at'        => 600,
			'current_timestamp' => 1000,
		]);

		$this->assertTrue(thold_notification_register_process(2, 300));
		$this->assertSame(
			['db_fetch_row_prepared', 'unregister_process', 'register_process_start'],
			array_column(CactiStubs::$calls, 'fn')
		);
	}

	/**
	 * @return void
	 */
	public function testASuspendedRunReleasesItsClaimAndRemainsScoped(): void {
		CactiStubs::$configOptions['thold_notification_suspended'] = '1';
		CactiStubs::willReturn('db_affected_rows', 2);

		$this->assertSame(2, thold_notification_run(77));

		foreach ($this->queueQueries() as $sql) {
			if (strpos($sql, 'SELECT') !== false) {
				$this->assertStringContainsString('process_id = 77', $sql);
			}
		}

		$calls   = CactiStubs::$calls;
		$release = end($calls);
		$this->assertSame('db_execute_prepared', $release['fn']);
		$this->assertStringContainsString('SET process_id = 0', $release['sql']);
		$this->assertSame([77], $release['params']);
	}
}
