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
	public function testAClaimRecoversOrphansThenTakesOnlyUnheldRows(): void {
		$this->assertSame(0, thold_notification_claim(0));
		CactiStubs::willReturn('db_fetch_cell_prepared', 1);
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
	public function testAClaimSkipsTheRecoveryUpdateWhenNoOrphanExists(): void {
		thold_notification_claim(4242);
		$updates = CactiStubs::callsTo('db_execute_prepared');

		$this->assertCount(1, $updates);
		$this->assertStringContainsString('SET process_id = ?', $updates[0]['sql']);
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
	public function testRegistrationRequiresTheLeaseAndHandlesQueryFailures(): void {
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
		CactiStubs::willReturn('db_fetch_row_prepared', []);
		CactiStubs::willReturn('register_process_start', false);

		$this->assertFalse(thold_notification_register_process(2, 300, static function () {
			return true;
		}));
		$last = end(CactiStubs::$calls);
		$this->assertSame('db_fetch_cell_prepared', $last['fn']);
		$this->assertStringContainsString('RELEASE_LOCK', $last['sql']);
	}

	/**
	 * @return void
	 */
	public function testRegistrationUsesLivenessAndBoundedHeartbeatFallbacks(): void {
		$process = [
			'pid'               => 42,
			'started_at'        => 500,
			'heartbeat_at'      => 600,
			'current_timestamp' => 1000,
		];
		$lock = static function () {
			return true;
		};

		CactiStubs::willReturn('db_fetch_row_prepared', $process);
		$this->assertFalse(thold_notification_register_process(2, 300, $lock, static function () {
			return true;
		}));

		CactiStubs::reset();
		$fresh                 = $process;
		$fresh['heartbeat_at'] = 900;
		CactiStubs::willReturn('db_fetch_row_prepared', $fresh);
		$this->assertFalse(thold_notification_register_process(2, 300, $lock, static function () {
			return null;
		}));

		CactiStubs::reset();
		CactiStubs::willReturn('db_fetch_row_prepared', $fresh);
		$this->assertTrue(thold_notification_register_process(2, 300, $lock, static function () {
			return false;
		}));

		CactiStubs::reset();
		CactiStubs::willReturn('db_fetch_row_prepared', $process);
		$this->assertTrue(thold_notification_register_process(2, 300, $lock, static function () {
			return null;
		}));

		CactiStubs::reset();
		$expired                      = $process;
		$expired['heartbeat_at']      = 100;
		$expired['current_timestamp'] = 2000;
		CactiStubs::willReturn('db_fetch_row_prepared', $expired);
		$this->assertFalse(thold_notification_register_process(2, 300, $lock, static function () {
			return true;
		}));

		CactiStubs::reset();
		CactiStubs::willReturn('db_fetch_row_prepared', $process);
		$this->assertTrue(thold_notification_register_process(2, 300, $lock, static function () {
			return false;
		}));
		$this->assertSame(
			['db_fetch_row_prepared', 'db_execute_prepared', 'unregister_process', 'register_process_start'],
			array_column(CactiStubs::$calls, 'fn')
		);
		$this->assertSame(['thold_notify', 'child', 2, 42], CactiStubs::$calls[2]['params']);
	}

	/**
	 * @return void
	 */
	public function testProcessProbeDistinguishesDeathFromPermissionErrors(): void {
		$missing = static function () {
			return false;
		};

		$this->assertTrue(thold_notification_probe_process(42, static function () {
			return 42;
		}));
		$this->assertTrue(thold_notification_probe_process(42, $missing, static function () {
			return true;
		}));
		$this->assertTrue(thold_notification_probe_process(42, $missing, $missing, static function () {
			return 1;
		}));
		$this->assertFalse(thold_notification_probe_process(42, $missing, $missing, static function () {
			return 3;
		}));
		$this->assertNull(thold_notification_probe_process(42, $missing, false, false));
		$this->assertNull(thold_notification_probe_process(42, $missing, $missing, false));
		$this->assertNull(thold_notification_probe_process(42, $missing, $missing, static function () {
			return 99;
		}));
		$this->assertTrue(thold_notification_probe_process(getmypid()));
		$this->assertTrue(thold_notification_probe_process(getmypid(), $missing));
		$this->assertContains(thold_notification_probe_process(2147483647, $missing, $missing), [false, null], true);
		$this->assertFalse(thold_notification_probe_process(0));
	}

	/**
	 * @return void
	 */
	public function testDefaultUnixProbeKeepsALiveWorkerRegistered(): void {
		CactiStubs::willReturn('db_fetch_row_prepared', [
			'pid'               => getmypid(),
			'started_at'        => 500,
			'heartbeat_at'      => 600,
			'current_timestamp' => 1000,
		]);

		$this->assertFalse(thold_notification_register_process(2, 300, static function () {
			return true;
		}));
		$this->assertSame(
			['db_fetch_row_prepared', 'db_fetch_cell_prepared'],
			array_column(CactiStubs::$calls, 'fn')
		);
	}

	/**
	 * @return void
	 */
	public function testRegistrationFailureAfterReclaimReleasesTheLease(): void {
		CactiStubs::willReturn('db_fetch_row_prepared', [
			'pid'               => 42,
			'started_at'        => 500,
			'heartbeat_at'      => 600,
			'current_timestamp' => 1000,
		]);
		CactiStubs::willReturn('register_process_start', false);

		$this->assertFalse(thold_notification_register_process(2, 300, static function () {
			return true;
		}, static function () {
			return false;
		}));
		$last = end(CactiStubs::$calls);
		$this->assertSame('db_fetch_cell_prepared', $last['fn']);
		$this->assertStringContainsString('RELEASE_LOCK', $last['sql']);
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
			if (strpos($sql, 'SELECT *') !== false) {
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
			return $call['fn'] === 'db_execute_prepared'
				&& strpos($call['sql'], 'error_code = 1, error_message') !== false;
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
	public function testUnknownTopicMessageIsBoundAndTruncated(): void {
		$this->assertTrue(thold_notification_reject_unknown_topic(91, 77, str_repeat('é', 200)));
		$call = end(CactiStubs::$calls);

		$this->assertSame('db_execute_prepared', $call['fn']);
		$this->assertSame(128, mb_strlen($call['params'][0], 'UTF-8'));
		$this->assertSame([91, 77], array_slice($call['params'], 1));
		$this->assertStringContainsString('AND process_id = ?', $call['sql']);
	}

	/**
	 * @return void
	 */
	public function testRevokedSingleRowClaimIsLoggedAndAbortsTheDrain(): void {
		CactiStubs::willReturn('db_affected_rows', 0);

		try {
			thold_notification_complete(
				'UPDATE notification_queue SET event_processed = 1 WHERE id = ? AND process_id = ?',
				[91, 77],
				[91],
				77
			);
			$this->fail('Expected revoked ownership to stop the drain.');
		} catch (RuntimeException $error) {
			$this->assertStringContainsString('queue row(s) 91', $error->getMessage());
		}

		$this->assertStringContainsString('process 77', end(CactiStubs::$log));
	}

	/**
	 * @return void
	 */
	public function testPartiallyRevokedGroupedClaimIsLoggedAndAbortsTheDrain(): void {
		CactiStubs::willReturn('db_affected_rows', 1);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('queue row(s) 91, 92');
		thold_notification_complete(
			'UPDATE notification_queue SET event_processed = 1 WHERE id IN (91, 92) AND process_id = ?',
			[77],
			[91, 92],
			77
		);
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
		$this->assertSame(77, $updates[0]['params'][4]);
		$this->assertSame(1, $updates[1]['params'][0]);
		$this->assertSame(77, $updates[1]['params'][3]);
		$this->assertStringContainsString('AND process_id = ?', $updates[0]['sql']);
		$this->assertStringContainsString('AND process_id = ?', $updates[1]['sql']);
		$this->assertSame(3, $heartbeats);
	}

	/**
	 * @return void
	 */
	public function testIndividualDeviceMailCompletionRequiresItsOwner(): void {
		CactiStubs::willReturn('db_fetch_assoc_prepared', [[
			'id'         => 104,
			'topic'      => 'thold_dhost_mail',
			'event_data' => json_encode([
				'from'        => ['sender@example.com'],
				'to'          => 'recipient@example.com',
				'bcc'         => '',
				'replyto'     => '',
				'subject'     => 'Device down',
				'body'        => '<body>Down</body>',
				'body_text'   => 'Down',
				'attachments' => [['attachment' => base64_encode('attachment')]],
				'headers'     => [],
				'html'        => true,
			]),
		]]);

		process_device_notifications(77, 'all', 0);
		$call = end(CactiStubs::$calls);

		$this->assertStringContainsString('AND process_id = ?', $call['sql']);
		$this->assertSame(104, $call['params'][3]);
		$this->assertSame(77, $call['params'][4]);
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
		$this->assertSame(77, $call['params'][4]);
		$this->assertStringContainsString('AND process_id = ?', $call['sql']);
	}

	/**
	 * @return void
	 */
	public function testNonDeviceMailCompletionRequiresItsOwner(): void {
		CactiStubs::willReturn('db_fetch_assoc_prepared', [[
			'id'         => 105,
			'topic'      => 'thold_mail',
			'event_data' => json_encode([
				'from'        => ['sender@example.com'],
				'to'          => 'recipient@example.com',
				'cc'          => '',
				'bcc'         => '',
				'replyto'     => '',
				'subject'     => 'Threshold alert',
				'body'        => '<body>Alert</body>',
				'body_text'   => 'Alert',
				'attachments' => [],
				'headers'     => [],
				'html'        => true,
			]),
		]]);

		process_non_device_notifications(77, 'all', 0);
		$call = end(CactiStubs::$calls);

		$this->assertStringContainsString('AND process_id = ?', $call['sql']);
		$this->assertSame(105, $call['params'][3]);
		$this->assertSame(77, $call['params'][4]);
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
	public function testCleanupReportsAClaimReleaseFailure(): void {
		$registered = true;
		CactiStubs::willReturn('db_execute_prepared', false);

		$this->assertFalse(thold_notification_cleanup(77, 2, $registered));
		$this->assertFalse($registered);
		$this->assertNotEmpty(CactiStubs::$log);
	}

	/**
	 * @return void
	 */
	public function testNamedShutdownIsIdempotent(): void {
		$GLOBALS['notification_registered'] = true;
		$GLOBALS['pid']                     = 77;
		$GLOBALS['thread']                  = 2;
		CactiStubs::willReturn('db_fetch_cell_prepared', 1);

		thold_notification_shutdown();
		thold_notification_shutdown();

		$this->assertFalse($GLOBALS['notification_registered']);
		$this->assertCount(3, CactiStubs::$calls);
	}

	/**
	 * @return void
	 */
	public function testMainInstallsShutdownBeforeRegistrationAndFailsClosed(): void {
		$events = [];

		$this->assertFalse(thold_notification_main(
			2,
			300,
			77,
			static function ($callback) use (&$events) {
				$events[] = 'shutdown:' . $callback;
			},
			static function ($thread, $timeout) use (&$events) {
				$events[] = "register:$thread:$timeout";

				return false;
			},
			static function () use (&$events) {
				$events[] = 'run';

				return 0;
			}
		));
		$this->assertSame(['shutdown:thold_notification_shutdown', 'register:2:300'], $events);
		$this->assertFalse($GLOBALS['notification_registered']);
	}

	/**
	 * @return void
	 */
	public function testMainRunsTheLeaseHeartbeatAndAlwaysCleansUp(): void {
		$events = [];
		CactiStubs::willReturn('db_fetch_cell_prepared', 1);
		CactiStubs::willReturn('db_fetch_cell_prepared', 1);

		$this->assertTrue(thold_notification_main(
			2,
			300,
			77,
			static function ($callback) use (&$events) {
				$events[] = 'shutdown:' . $callback;
			},
			static function ($thread, $timeout) use (&$events) {
				$events[] = "register:$thread:$timeout";

				return true;
			},
			static function ($pid, $limit, $heartbeat) use (&$events) {
				$events[] = "run:$pid:$limit";
				$heartbeat();

				return 4;
			}
		));
		$this->assertSame(
			['shutdown:thold_notification_shutdown', 'register:2:300', 'run:77:all'],
			$events
		);
		$this->assertFalse($GLOBALS['notification_registered']);
		$this->assertSame(2, $GLOBALS['thread']);
		$this->assertContains('heartbeat_process', array_column(CactiStubs::$calls, 'fn'));
		$this->assertStringContainsString('Notifications:4', end(CactiStubs::$log));
	}

	/**
	 * @return void
	 */
	public function testHeartbeatThrowsAsSoonAsTheLeaseIsLost(): void {
		CactiStubs::willReturn('db_fetch_cell_prepared', 0);

		$this->expectException(RuntimeException::class);
		$this->expectExceptionMessage('Notification worker lease was lost.');
		thold_notification_heartbeat(2);
	}

	/**
	 * @return void
	 */
	public function testMainLogsLeaseLossAndReturnsFailureAfterCleanup(): void {
		CactiStubs::willReturn('db_fetch_cell_prepared', 1);

		$this->assertFalse(thold_notification_main(
			2,
			300,
			77,
			static function () {
			},
			static function () {
				return true;
			},
			static function () {
				throw new RuntimeException('lease lost');
			}
		));
		$this->assertFalse($GLOBALS['notification_registered']);
		$this->assertStringContainsString('lease lost', end(CactiStubs::$log));
	}
}
