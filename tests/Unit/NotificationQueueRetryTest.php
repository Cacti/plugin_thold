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

final class NotificationQueueRetryTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @return array<string, mixed>
	 */
	private function lastPreparedCall() {
		$calls = array_values(array_filter(CactiStubs::$calls, static function ($call) {
			return $call['fn'] === 'db_execute_prepared';
		}));

		$this->assertNotEmpty($calls);

		return end($calls);
	}

	/**
	 * @return void
	 */
	public function testRetryDelayUsesBoundedExponentialBackoff(): void {
		$this->assertSame(60, thold_notification_retry_delay(1));
		$this->assertSame(120, thold_notification_retry_delay(2));
		$this->assertSame(480, thold_notification_retry_delay(4));
		$this->assertSame(3600, thold_notification_retry_delay(7));
	}

	/**
	 * @return void
	 */
	public function testSuccessfulDeliveryIsTerminal(): void {
		thold_notification_record_delivery(42, '', 0.25, 2);

		$call = $this->lastPreparedCall();
		$sql  = preg_replace('/\s+/', ' ', $call['sql']);

		$this->assertStringContainsString('error_code = 0', $sql);
		$this->assertStringContainsString('next_attempt = NULL', $sql);
		$this->assertStringContainsString('event_processed = 1', $sql);
		$this->assertSame([3, 0.25, 42], $call['params']);
	}

	/**
	 * @return void
	 */
	public function testTransientFailureReleasesTheClaimAndSchedulesRetry(): void {
		thold_notification_record_delivery(42, "smtp\ndown", 0.5);

		$call = $this->lastPreparedCall();
		$sql  = preg_replace('/\s+/', ' ', $call['sql']);

		$this->assertStringContainsString('next_attempt = FROM_UNIXTIME', $sql);
		$this->assertStringContainsString('process_id = 0', $sql);
		$this->assertStringContainsString('event_processed = 0', $sql);
		$this->assertSame(['smtp down', 1, 60, 0.5, 42], $call['params']);
	}

	/**
	 * @return void
	 */
	public function testFifthFailureIsTerminal(): void {
		thold_notification_record_delivery(42, 'permanent failure', 0.5, 4);

		$call = $this->lastPreparedCall();
		$sql  = preg_replace('/\s+/', ' ', $call['sql']);

		$this->assertStringContainsString('error_code = 1', $sql);
		$this->assertStringContainsString('next_attempt = NULL', $sql);
		$this->assertStringContainsString('event_processed = 1', $sql);
		$this->assertSame(['permanent failure', 5, 0.5, 42], $call['params']);
	}

	/**
	 * @return void
	 */
	public function testFailureMessageFitsTheQueueColumn(): void {
		thold_notification_record_delivery(42, str_repeat('x', 200), 0.5);

		$call = $this->lastPreparedCall();

		$this->assertSame(128, strlen($call['params'][0]));
	}

	/**
	 * @return void
	 */
	public function testClaimAndBothDrainsIgnoreRetriesThatAreNotReady(): void {
		$notify = file_get_contents(dirname(__DIR__, 2) . '/thold_notify.php');

		$this->assertStringContainsString('(next_attempt IS NULL OR next_attempt <= NOW())', $notify);

		thold_notification_execute(77);

		$queries = array_filter(CactiStubs::$calls, static function ($call) {
			return $call['fn'] === 'db_fetch_assoc' &&
				strpos($call['sql'], 'notification_queue') !== false;
		});

		$this->assertCount(2, $queries);

		foreach ($queries as $call) {
			$this->assertStringContainsString('(next_attempt IS NULL OR next_attempt <= NOW())', $call['sql']);
		}
	}
}
