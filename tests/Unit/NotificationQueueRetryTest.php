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
	 * @return array<string, mixed>
	 * @param  mixed                $id
	 * @param  mixed                $topic
	 * @param  mixed                $attempts
	 */
	private function mailRow($id, $topic = 'thold_mail', $attempts = 0) {
		return [
			'id'            => $id,
			'topic'         => $topic,
			'attempt_count' => $attempts,
			'event_data'    => json_encode([
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
		];
	}

	/**
	 * @return void
	 */
	public function testRetryDelayUsesBoundedExponentialBackoff(): void {
		$this->assertSame(60, thold_notification_retry_delay(-1));
		$this->assertSame(60, thold_notification_retry_delay(0));
		$this->assertSame(60, thold_notification_retry_delay(1));
		$this->assertSame(120, thold_notification_retry_delay(2));
		$this->assertSame(480, thold_notification_retry_delay(4));
		$this->assertSame(1920, thold_notification_retry_delay(6));
		$this->assertSame(3600, thold_notification_retry_delay(7));
	}

	/**
	 * @return void
	 */
	public function testQueueStatusCellsFollowTheirHeaderOrder(): void {
		$cells = thold_notification_queue_status_cells([
			'event_processed'         => 1,
			'error_code'              => 1,
			'attempt_count'           => 4,
			'next_attempt'            => null,
			'event_processed_runtime' => 0.25,
		]);

		$this->assertSame(
			['event_processed', 'error_code', 'attempt_count', 'next_attempt', 'event_processed_runtime'],
			array_keys($cells)
		);
		$this->assertSame(['Done', 'Errored', 4, 'N/A', '0.25'], array_values($cells));

		$pending = thold_notification_queue_status_cells(['event_processed' => 0]);
		$this->assertSame(['Pending', 'N/A', 0, 'N/A', 'N/A'], array_values($pending));
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

	/**
	 * @return void
	 */
	public function testIndividualDeviceMailFailureUsesTheRetryRecorder(): void {
		CactiStubs::$configOptions['alert_deadnotify_one_mail'] = '';
		CactiStubs::willReturnFor('db_fetch_assoc', "topic IN ('thold_dhost_mail'", [
			$this->mailRow(51, 'thold_dhost_mail', 2),
		]);
		CactiStubs::willReturn('mailer', 'temporary SMTP failure');

		process_device_notifications(77, 'all', 0);

		$call = $this->lastPreparedCall();

		$this->assertSame(['temporary SMTP failure', 3, 240, $call['params'][3], 51], $call['params']);
		$this->assertStringContainsString('event_processed = 0', $call['sql']);
	}

	/**
	 * @return void
	 */
	public function testGroupedDeviceMailRecordsEveryAttempt(): void {
		CactiStubs::$configOptions['alert_deadnotify_one_mail'] = 'on';
		CactiStubs::$configOptions['alert_deadnotify_subject']  = 'Device alerts';
		CactiStubs::willReturnFor('db_fetch_assoc', "topic IN ('thold_dhost_mail'", [
			$this->mailRow(61, 'thold_dhost_mail', 0),
			$this->mailRow(63, 'thold_dhost_mail', 0),
			$this->mailRow(62, 'thold_uhost_mail', 3),
			$this->mailRow(64, 'thold_uhost_mail', 4),
		]);
		CactiStubs::willReturn('mailer', 'temporary SMTP failure');

		process_device_notifications(77, 'all', 0);

		$calls = array_values(array_filter(CactiStubs::$calls, static function ($call) {
			return $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'attempt_count') !== false;
		}));

		$this->assertCount(1, $calls);
		$this->assertStringContainsString('attempt_count = CASE id', $calls[0]['sql']);
		$this->assertStringContainsString('event_processed = CASE id', $calls[0]['sql']);
		$this->assertSame([61, 1, 63, 1, 62, 4, 64, 5], array_slice($calls[0]['params'], 2, 8));
		$this->assertSame([61, 60, 63, 60, 62, 480, 64], array_slice($calls[0]['params'], 10, 7));
		$this->assertSame([61, 63, 62, 64], array_slice($calls[0]['params'], 17, 4));
		$this->assertSame([61, 0, 63, 0, 62, 0, 64, 1], array_slice($calls[0]['params'], 21, 8));
		$this->assertSame([61, 63, 62, 64], array_slice($calls[0]['params'], 29, 4));
		$this->assertSame([61, 63, 62, 64], array_slice($calls[0]['params'], -4));
		$this->assertSame('temporary SMTP failure', $calls[0]['params'][1]);
	}

	/**
	 * @return void
	 */
	public function testGroupedDeliveryHandlesEmptyInvalidAndSuccessfulBatches(): void {
		$this->assertTrue(thold_notification_record_deliveries([], '', 0.25));
		$this->assertTrue(thold_notification_record_deliveries([-1 => 0], '', 0.25));
		$this->assertSame([], CactiStubs::$calls);

		$this->assertTrue(thold_notification_record_deliveries([81 => 0, 82 => 4], '', 0.25));

		$calls = array_values(array_filter(CactiStubs::$calls, static function ($call) {
			return $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'attempt_count = CASE id') !== false;
		}));

		$this->assertCount(1, $calls);
		$this->assertSame(0, $calls[0]['params'][0]);
		$this->assertSame('', $calls[0]['params'][1]);
		$this->assertSame([81, 82], array_slice($calls[0]['params'], -2));
		$this->assertStringNotContainsString('FROM_UNIXTIME', $calls[0]['sql']);
	}

	/**
	 * @return void
	 */
	public function testNonDeviceMailFailureUsesTheRetryRecorder(): void {
		CactiStubs::willReturnFor('db_fetch_assoc', "topic NOT IN ('thold_dhost_mail'", [
			$this->mailRow(71, 'thold_mail', 1),
		]);
		CactiStubs::willReturn('mailer', 'temporary SMTP failure');

		process_non_device_notifications(77, 'all', 0);

		$call = $this->lastPreparedCall();

		$this->assertSame(['temporary SMTP failure', 2, 120, $call['params'][3], 71], $call['params']);
		$this->assertStringContainsString('event_processed = 0', $call['sql']);
	}
}
