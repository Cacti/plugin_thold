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
 * "Send Emails Separately" reaches every address on the list (#754).
 *
 * The list was split on commas without trimming, so " noc@example.org" failed
 * FILTER_VALIDATE_EMAIL and was skipped, with nothing logged.
 */
final class TholdMailSeparateRecipientsTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/functions.php');
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		CactiStubs::$configOptions['thold_from_email']            = 'cacti@example.org';
		CactiStubs::$configOptions['thold_from_name']             = 'Cacti';
		CactiStubs::$configOptions['thold_send_email_separately'] = 'on';
	}

	/**
	 * @param string $to
	 *
	 * @return void
	 */
	private function send($to) {
		$host = ['id' => 2];

		thold_mail($to, '', '', 'ALERT: CPU', 'CPU is high', [], [], 0, $host);
	}

	/**
	 * @return void
	 */
	public function testAddressesAfterACommaAndSpaceAreMailed(): void {
		$this->send('ops@example.org, noc@example.org,  oncall@example.org ,');

		$this->assertSame(
			['ops@example.org', 'noc@example.org', 'oncall@example.org'],
			array_column(CactiStubs::$mail, 'to')
		);
	}

	/**
	 * @return void
	 */
	public function testAnInvalidAddressIsLoggedNotSilentlyDropped(): void {
		$this->send('ops@example.org, not-an-address');

		$this->assertSame(['ops@example.org'], array_column(CactiStubs::$mail, 'to'));
		$this->assertContains("WARNING: Not sending Email to invalid address 'not-an-address' Subject:ALERT: CPU", CactiStubs::$log);
	}
}
