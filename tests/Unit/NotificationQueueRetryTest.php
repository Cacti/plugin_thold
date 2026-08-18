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
		thold_notification_record_delivery(42, 77, '', 0.25, 2);

		$call = $this->lastPreparedCall();
		$sql  = preg_replace('/\s+/', ' ', $call['sql']);

		$this->assertSame(0, $call['params'][0]);
		$this->assertSame('', $call['params'][1]);
		$this->assertStringContainsString('next_attempt = CASE id', $sql);
		$this->assertStringContainsString('THEN NULL', $sql);
		$this->assertSame([42, 3], array_slice($call['params'], 2, 2));
		$this->assertSame(1, $call['params'][7]);
		$this->assertSame([42, 0.25, 42, 77], array_slice($call['params'], -4));
		$this->assertStringContainsString('AND process_id = ?', $sql);
	}

	/**
	 * @return void
	 */
	public function testTransientFailureReleasesTheClaimAndSchedulesRetry(): void {
		thold_notification_record_delivery(42, 77, "smtp\ndown", 0.5);

		$call = $this->lastPreparedCall();
		$sql  = preg_replace('/\s+/', ' ', $call['sql']);

		$this->assertStringContainsString('THEN FROM_UNIXTIME', $sql);
		$this->assertStringContainsString('process_id = CASE id', $sql);
		$this->assertStringContainsString('THEN 0', $sql);
		$this->assertSame([1, 'smtp down', 42, 1, 42, 60], array_slice($call['params'], 0, 6));
		$this->assertSame(0, $call['params'][8]);
		$this->assertSame([42, 0.5, 42, 77], array_slice($call['params'], -4));
	}

	/**
	 * @return void
	 */
	public function testFifthFailureIsTerminal(): void {
		thold_notification_record_delivery(42, 77, 'permanent failure', 0.5, 4);

		$call = $this->lastPreparedCall();
		$sql  = preg_replace('/\s+/', ' ', $call['sql']);

		$this->assertStringContainsString('THEN NULL', $sql);
		$this->assertSame([1, 'permanent failure', 42, 5], array_slice($call['params'], 0, 4));
		$this->assertSame(1, $call['params'][7]);
		$this->assertSame([42, 0.5, 42, 77], array_slice($call['params'], -4));
	}

	/**
	 * @return void
	 */
	public function testFailureMessageFitsTheQueueColumn(): void {
		thold_notification_record_delivery(42, 77, str_repeat('x', 200), 0.5);

		$call = $this->lastPreparedCall();

		$this->assertSame(128, strlen($call['params'][1]));
	}

	/**
	 * @return void
	 */
	public function testClaimAndBothDrainsIgnoreRetriesThatAreNotReady(): void {
		thold_notification_claim(77);

		$claims = array_filter(CactiStubs::$calls, static function ($call) {
			return $call['fn'] === 'db_execute_prepared' &&
				strpos($call['sql'], 'SET process_id = ?') !== false;
		});

		$this->assertCount(1, $claims);
		$claim = reset($claims);
		$this->assertStringContainsString('(next_attempt IS NULL OR next_attempt <= NOW())', $claim['sql']);

		thold_notification_execute(77);

		$queries = array_filter(CactiStubs::$calls, static function ($call) {
			return $call['fn'] === 'db_fetch_assoc_prepared' &&
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
		CactiStubs::willReturnFor('db_fetch_assoc_prepared', "topic IN ('thold_dhost_mail'", [
			$this->mailRow(51, 'thold_dhost_mail', 2),
		]);
		CactiStubs::willReturn('mailer', 'temporary SMTP failure');

		process_device_notifications(77, 'all', 0);

		$call = $this->lastPreparedCall();

		$this->assertSame([1, 'temporary SMTP failure', 51, 3, 51, 240], array_slice($call['params'], 0, 6));
		$this->assertSame([51, $call['params'][10], 51, 77], array_slice($call['params'], -4));
		$this->assertSame(0, $call['params'][8]);
		$this->assertStringContainsString('process_id = CASE id', $call['sql']);
	}

	/**
	 * @return void
	 */
	public function testGroupedDeviceMailRecordsEveryAttempt(): void {
		CactiStubs::$configOptions['alert_deadnotify_one_mail'] = 'on';
		CactiStubs::$configOptions['alert_deadnotify_subject']  = 'Device alerts';
		CactiStubs::willReturnFor('db_fetch_assoc_prepared', "topic IN ('thold_dhost_mail'", [
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
		$this->assertSame([61, 63, 62, 64, 77], array_slice($calls[0]['params'], -5));
		$this->assertSame('temporary SMTP failure', $calls[0]['params'][1]);
	}

	/**
	 * @return void
	 */
	public function testGroupedDeliveryHandlesEmptyInvalidAndSuccessfulBatches(): void {
		$this->assertTrue(thold_notification_record_deliveries([], 77, '', 0.25));
		$this->assertTrue(thold_notification_record_deliveries([-1 => 0], 77, '', 0.25));
		$this->assertSame([], CactiStubs::$calls);

		$this->assertTrue(thold_notification_record_deliveries([81 => 0, 82 => 4], 77, '', 0.25));

		$calls = array_values(array_filter(CactiStubs::$calls, static function ($call) {
			return $call['fn'] === 'db_execute_prepared' && strpos($call['sql'], 'attempt_count = CASE id') !== false;
		}));

		$this->assertCount(1, $calls);
		$this->assertSame(0, $calls[0]['params'][0]);
		$this->assertSame('', $calls[0]['params'][1]);
		$this->assertSame([81, 82, 77], array_slice($calls[0]['params'], -3));
		$this->assertStringNotContainsString('FROM_UNIXTIME', $calls[0]['sql']);
		$this->assertStringContainsString('process_id = CASE id', $calls[0]['sql']);
		$this->assertStringContainsString('THEN process_id', $calls[0]['sql']);
		$this->assertStringContainsString('THEN NOW()', $calls[0]['sql']);
		$this->assertSame([81, 1, 82, 1], array_slice($calls[0]['params'], 10, 4));
	}

	/**
	 * @return void
	 */
	public function testGroupedTerminalFailuresStayClaimedAndComplete(): void {
		$this->assertTrue(thold_notification_record_deliveries([91 => 4, 92 => 5], 77, 'permanent failure', 0.5));

		$call = $this->lastPreparedCall();

		$this->assertSame([91, 5, 92, 6], array_slice($call['params'], 2, 4));
		$this->assertSame([91, 1, 92, 1], array_slice($call['params'], 10, 4));
		$this->assertStringNotContainsString('FROM_UNIXTIME', $call['sql']);
		$this->assertStringContainsString('THEN process_id', $call['sql']);
		$this->assertStringContainsString('THEN NOW()', $call['sql']);
	}

	/**
	 * @return void
	 */
	public function testNonDeviceMailFailureUsesTheRetryRecorder(): void {
		CactiStubs::willReturnFor('db_fetch_assoc_prepared', "topic NOT IN ('thold_dhost_mail'", [
			$this->mailRow(71, 'thold_mail', 1),
		]);
		CactiStubs::willReturn('mailer', 'temporary SMTP failure');

		process_non_device_notifications(77, 'all', 0);

		$call = $this->lastPreparedCall();

		$this->assertSame([1, 'temporary SMTP failure', 71, 2, 71, 120], array_slice($call['params'], 0, 6));
		$this->assertSame([71, $call['params'][10], 71, 77], array_slice($call['params'], -4));
		$this->assertSame(0, $call['params'][8]);
		$this->assertStringContainsString('process_id = CASE id', $call['sql']);
	}
}
