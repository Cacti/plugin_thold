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
				$sql = $call['sql'];

				foreach ($call['params'] as $param) {
					$placeholder = strpos($sql, '?');

					if ($placeholder === false) {
						break;
					}

					$sql = substr($sql, 0, $placeholder) . (int) $param . substr($sql, $placeholder + 1);
				}

				$queries[] = preg_replace('/\s+/', ' ', $sql);
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
	public function testAClaimTakesOnlyUnheldRows(): void {
		$this->assertSame(0, thold_notification_claim(0));
		CactiStubs::willReturn('db_affected_rows', 3);

		$this->assertSame(3, thold_notification_claim(4242));

		$calls = array_values(array_filter(CactiStubs::$calls, static function ($call) {
			return $call['fn'] === 'db_execute_prepared';
		}));

		$this->assertCount(1, $calls);
		$this->assertStringContainsString('AND process_id = 0', $calls[0]['sql']);
		$this->assertSame([4242], $calls[0]['params']);
	}

	/**
	 * @return void
	 */
	public function testAReleaseReturnsOnlyTheWorkersUnfinishedRows(): void {
		$this->assertTrue(thold_notification_release_claim(0));
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
	public function testDatabaseLeaseOperationsAreConnectionScoped(): void {
		CactiStubs::willReturn('db_fetch_cell_prepared', 1);
		CactiStubs::willReturn('db_fetch_cell_prepared', 1);
		CactiStubs::willReturn('db_fetch_cell_prepared', 1);

		$this->assertSame('thold_notify_child_2', thold_notification_lock_name(2));
		$this->assertTrue(thold_notification_acquire_lock(2));
		$this->assertTrue(thold_notification_owns_lock(2));
		$this->assertTrue(thold_notification_release_lock(2));

		$this->assertSame(
			[
				"SELECT GET_LOCK(CONCAT(DATABASE(), ':', ?), 0)",
				"SELECT IS_USED_LOCK(CONCAT(DATABASE(), ':', ?)) = CONNECTION_ID()",
				"SELECT RELEASE_LOCK(CONCAT(DATABASE(), ':', ?))",
			],
			array_column(CactiStubs::$calls, 'sql')
		);

		foreach (CactiStubs::$calls as $call) {
			$this->assertSame(['thold_notify_child_2'], $call['params']);
		}
	}

	/**
	 * @return void
	 */
	public function testRegistrationRequiresTheLeaseAndReclaimsItsStaleRow(): void {
		$this->assertFalse(thold_notification_register_process(2, 300, static function () {
			return false;
		}));
		$this->assertSame([], CactiStubs::$calls);
		$this->assertNotEmpty(CactiStubs::$log);

		CactiStubs::reset();
		CactiStubs::willReturn('db_fetch_row_prepared', false);

		$this->assertFalse(thold_notification_register_process(2, 300, static function () {
			return true;
		}));
		$this->assertSame(
			['db_fetch_row_prepared', 'db_fetch_cell_prepared'],
			array_column(CactiStubs::$calls, 'fn')
		);

		CactiStubs::reset();
		CactiStubs::willReturn('db_fetch_row_prepared', []);
		$this->assertTrue(thold_notification_register_process(2, 300, static function () {
			return true;
		}));
		$this->assertSame(
			['db_fetch_row_prepared', 'register_process_start'],
			array_column(CactiStubs::$calls, 'fn')
		);

		CactiStubs::reset();
		CactiStubs::willReturn('db_fetch_row_prepared', ['pid' => 42]);

		$this->assertTrue(thold_notification_register_process(2, 300, static function () {
			return true;
		}));
		$this->assertSame(
			['db_fetch_row_prepared', 'db_execute_prepared', 'unregister_process', 'register_process_start'],
			array_column(CactiStubs::$calls, 'fn')
		);

		foreach ([[], ['pid' => 42]] as $process) {
			CactiStubs::reset();
			CactiStubs::willReturn('db_fetch_row_prepared', $process);
			CactiStubs::willReturn('register_process_start', false);

			$this->assertFalse(thold_notification_register_process(2, 300, static function () {
				return true;
			}));
			$last = end(CactiStubs::$calls);
			$this->assertSame('db_fetch_cell_prepared', $last['fn']);
			$this->assertStringContainsString('RELEASE_LOCK', $last['sql']);
		}
	}

	/**
	 * @return void
	 */
	public function testASuspendedRunReleasesItsClaimAndRemainsScoped(): void {
		CactiStubs::$configOptions['thold_notification_suspended'] = '1';
		CactiStubs::willReturn('db_affected_rows', 2);
		$heartbeats = 0;

		$this->assertSame(2, thold_notification_run(77, 'all', static function () use (&$heartbeats) {
			$heartbeats++;
		}));
		$this->assertSame(5, $heartbeats);

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

	/**
	 * @return void
	 */
	public function testAHeartbeatFailureCannotSkipClaimRelease(): void {
		CactiStubs::$configOptions['thold_notification_suspended'] = '1';
		$heartbeats                                                = 0;

		try {
			thold_notification_run(77, 'all', static function () use (&$heartbeats) {
				$heartbeats++;

				if ($heartbeats === 5) {
					throw new RuntimeException('heartbeat failed');
				}
			});

			$this->fail('Expected the heartbeat failure to propagate.');
		} catch (RuntimeException $error) {
			$this->assertSame('heartbeat failed', $error->getMessage());
		}

		$calls   = CactiStubs::$calls;
		$release = end($calls);
		$this->assertSame('db_execute_prepared', $release['fn']);
		$this->assertStringContainsString('SET process_id = 0', $release['sql']);
		$this->assertSame([77], $release['params']);
	}

	/**
	 * @return void
	 */
	public function testQueueLoopsHeartbeatForEveryRecord(): void {
		$records = [
			['id' => 91, 'topic' => 'unknown-device'],
			['id' => 92, 'topic' => 'unknown-device'],
		];
		CactiStubs::willReturn('db_fetch_assoc_prepared', $records);
		CactiStubs::willReturn('db_fetch_assoc_prepared', $records);
		$heartbeats = 0;
		$heartbeat  = static function () use (&$heartbeats) {
			$heartbeats++;
		};

		process_device_notifications(77, 'all', 0, $heartbeat);
		process_non_device_notifications(77, 'all', 0, $heartbeat);

		$this->assertSame(4, $heartbeats);

		$terminal = array_values(array_filter(CactiStubs::$calls, static function ($call) {
			return $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'Unsupported notification topic') === false;
		}));
		$this->assertCount(4, $terminal);

		foreach ($terminal as $call) {
			$this->assertStringContainsString('event_processed = 1', $call['sql']);
			$this->assertSame(77, $call['params'][2]);
		}
	}

	/**
	 * @return void
	 */
	public function testDeviceCommandAndGroupedMailComplete(): void {
		CactiStubs::$configOptions['alert_deadnotify_one_mail'] = 'on';
		CactiStubs::$configOptions['alert_deadnotify_subject']  = 'Device alerts';
		CactiStubs::willReturn('mailer', 'delivery failed');
		CactiStubs::willReturn('db_fetch_assoc_prepared', [
			[
				'id'         => 101,
				'topic'      => 'thold_dhost_cmd',
				'event_data' => json_encode([
					'environment' => ['THOLD_DEVICE_TEST=1'],
					'command'     => '/bin/true',
					'data'        => ['id' => 7],
				]),
			],
			[
				'id'         => 102,
				'topic'      => 'thold_dhost_mail',
				'event_data' => json_encode([
					'from'        => ['sender@example.com'],
					'to'          => 'recipient@example.com',
					'cc'          => '',
					'bcc'         => '',
					'replyto'     => '',
					'subject'     => 'Device down',
					'body'        => '<body>Down</body>',
					'body_text'   => 'Down',
					'attachments' => [],
					'headers'     => [],
					'html'        => true,
				]),
			],
		]);
		$heartbeats = 0;

		process_device_notifications(77, 'all', 0, static function () use (&$heartbeats) {
			$heartbeats++;
		});
		putenv('THOLD_DEVICE_TEST');

		$updates = array_values(array_filter(CactiStubs::$calls, static function ($call) {
			return $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'event_processed = 1') !== false;
		}));

		$this->assertCount(2, $updates);
		$this->assertSame(101, $updates[0]['params'][3]);
		$this->assertSame(1, $updates[1]['params'][0]);
		$this->assertSame(3, $heartbeats);
	}

	/**
	 * @return void
	 */
	public function testNonDeviceCommandCompletesWithItsEnvironment(): void {
		CactiStubs::willReturn('db_fetch_assoc_prepared', [[
			'id'         => 103,
			'topic'      => 'thold_cmd',
			'event_data' => json_encode([
				'environment' => ['THOLD_COMMAND_TEST=1'],
				'command'     => '/bin/true',
				'data'        => ['id' => 8],
			]),
		]]);

		process_non_device_notifications(77, 'all', 0);
		putenv('THOLD_COMMAND_TEST');

		$call = end(CactiStubs::$calls);
		$this->assertSame('db_execute_prepared', $call['fn']);
		$this->assertStringContainsString('event_processed = 1', $call['sql']);
		$this->assertSame(103, $call['params'][3]);
	}

	/**
	 * @return void
	 */
	public function testCleanupReleasesAndUnregistersOnlyOnce(): void {
		$registered = true;
		CactiStubs::willReturn('db_fetch_cell_prepared', 1);

		$this->assertTrue(thold_notification_cleanup(77, 2, $registered));
		$this->assertFalse($registered);
		$this->assertSame(
			['db_execute_prepared', 'unregister_process', 'db_fetch_cell_prepared'],
			array_column(CactiStubs::$calls, 'fn')
		);

		$this->assertTrue(thold_notification_cleanup(77, 2, $registered));
		$this->assertCount(3, CactiStubs::$calls);
	}

	/**
	 * @return void
	 */
	public function testNamedShutdownIsIdempotentAndInstalledBeforeRegistration(): void {
		$GLOBALS['notification_registered'] = true;
		$GLOBALS['pid']                     = 77;
		$GLOBALS['thread']                  = 2;
		CactiStubs::willReturn('db_fetch_cell_prepared', 1);

		thold_notification_shutdown();
		thold_notification_shutdown();

		$this->assertFalse($GLOBALS['notification_registered']);
		$this->assertCount(3, CactiStubs::$calls);

		$source   = file_get_contents(dirname(__DIR__, 2) . '/thold_notify.php');
		$shutdown = strpos($source, "register_shutdown_function('thold_notification_shutdown')");
		$register = strpos($source, 'thold_notification_register_process($thread, $timeout)');

		$this->assertNotFalse($shutdown);
		$this->assertNotFalse($register);
		$this->assertLessThan($register, $shutdown);
	}
}
