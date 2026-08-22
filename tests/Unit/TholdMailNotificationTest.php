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
 * Delivery of one notification to one recipient list.
 *
 * This was the block repeated at every send site in thold_check_threshold(),
 * so the rules it carries -- when a mail is skipped, which body is built --
 * could only be reached by driving a whole poll.
 */
final class TholdMailNotificationTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @param array<string, mixed> $overrides
	 *
	 * @return array<string, mixed>
	 */
	private function threshold(array $overrides = []) {
		return $overrides + [
			'id'                 => 1,
			'name_cache'         => 'CPU',
			'data_source_name'   => 'traffic_in',
			'lastread'           => 95,
			'local_graph_id'     => 7,
			'acknowledgment'     => '',
			'notes'              => '',
			'dnotes'             => '',
			'external_id'        => '',
			'thold_type'         => 0,
			'thold_hi'           => 90,
			'thold_low'          => 10,
			'thold_fail_trigger' => 3,
			'email_body'         => '',
		];
	}

	/**
	 * @return array<string, mixed>
	 */
	private function device() {
		return [
			'id'          => 2,
			'description' => 'core-switch-1',
			'hostname'    => '10.0.0.1',
			'location'    => 'rack 4',
			'site_id'     => 1,
		];
	}

	/**
	 * @param string               $recipients
	 * @param array<string, mixed> $overrides
	 * @param string               $type
	 *
	 * @return string
	 */
	private function deliver($recipients, array $overrides = [], $type = 'alert') {
		$thold  = $this->threshold($overrides);
		$device = $this->device();

		return thold_mail_notification($recipients, 'bcc@example.org', 'ALERT: CPU', $type, 4, [], $thold, $device);
	}

	/**
	 * @return void
	 */
	public function testRecipientsReceiveTheNotification(): void {
		$this->deliver('ops@example.org');

		$this->assertCount(1, CactiStubs::$mail);
		$this->assertSame('ops@example.org', CactiStubs::$mail[0]['to']);
		$this->assertSame('bcc@example.org', CactiStubs::$mail[0]['bcc']);
		$this->assertSame('ALERT: CPU', CactiStubs::$mail[0]['subject']);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function emptyRecipientProvider() {
		return [
			'empty string' => [''],
			'whitespace'   => ['   '],
		];
	}

	/**
	 * @dataProvider emptyRecipientProvider
	 *
	 * @param string $recipients
	 *
	 * @return void
	 */
	public function testNothingIsSentWithoutRecipients($recipients): void {
		$this->assertSame('', $this->deliver($recipients));
		$this->assertSame([], CactiStubs::$mail);
	}

	/**
	 * An acknowledged threshold has already been seen by an operator, so it
	 * stops mailing even while it keeps breaching.
	 *
	 * @return void
	 */
	public function testAcknowledgedThresholdIsNotMailed(): void {
		$this->assertSame('', $this->deliver('ops@example.org', ['acknowledgment' => 'on']));
		$this->assertSame([], CactiStubs::$mail);
	}

	/**
	 * Building the body queries, so an empty recipient list must not pay for a
	 * message nobody receives.
	 *
	 * @return void
	 */
	public function testNoMessageIsBuiltWhenThereIsNoOneToMail(): void {
		$this->deliver('');

		// Composing a body resolves the device's site; skipping it must not.
		$site_lookups = array_filter(CactiStubs::$calls, static function ($call) {
			return strpos($call['sql'], 'FROM sites') !== false;
		});

		$this->assertSame([], array_values($site_lookups));
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function textTypeProvider() {
		return [
			'alert'    => ['alert', 'thold_alert_text'],
			'warning'  => ['warning', 'thold_warning_text'],
			'restoral' => ['restoral', 'thold_restoral_text'],
		];
	}

	/**
	 * Each class of notification takes its body from its own setting.
	 *
	 * @dataProvider textTypeProvider
	 *
	 * @param string $type
	 * @param string $option
	 *
	 * @return void
	 */
	public function testEachNotificationClassUsesItsOwnBody($type, $option): void {
		CactiStubs::$configOptions[$option] = 'body for ' . $type;

		$this->assertSame('body for ' . $type, $this->deliver('ops@example.org', [], $type));
	}

	/**
	 * @return void
	 */
	public function testTheSentMessageIsReturnedForReuse(): void {
		CactiStubs::$configOptions['thold_alert_text'] = 'the body';

		$this->assertSame('the body', $this->deliver('ops@example.org'));
	}

	/**
	 * @return void
	 */
	public function testTheListFormatIsResolvedEvenWhenNothingIsSent(): void {
		$this->deliver('');

		$format_lookups = array_filter(CactiStubs::$calls, static function ($call) {
			return strpos($call['sql'], 'format_file') !== false;
		});

		$this->assertNotEmpty($format_lookups);
	}
}
