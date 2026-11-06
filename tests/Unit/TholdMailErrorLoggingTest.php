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
 * A failed send is logged in the default delivery mode (#754).
 *
 * thold_mail() returns mailer()'s error, but none of its callers look at the
 * return value. The send-separately mode logged each failure itself; the
 * default mode, which sends one message to every recipient, did not, so an
 * SMTP failure left no trace in the Cacti log.
 */
final class TholdMailErrorLoggingTest extends TestCase {
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

		CactiStubs::$configOptions['thold_from_email'] = 'cacti@example.org';
		CactiStubs::$configOptions['thold_from_name']  = 'Cacti';
	}

	/**
	 * Send one alert to two recipients in the default mode.
	 *
	 * @return string thold_mail()'s return value.
	 */
	private function send() {
		$host = ['id' => 2];

		return thold_mail('ops@example.org,noc@example.org', '', '', 'ALERT: CPU', 'CPU is high', [], [], 0, $host);
	}

	/**
	 * Log lines reporting a failed send.
	 *
	 * @return array<int, string>
	 */
	private function failureLog() {
		return array_values(array_filter(CactiStubs::$log, function ($line) {
			return strpos($line, 'Sending Email Failed') !== false;
		}));
	}

	/**
	 * @return void
	 */
	public function testAFailedSendIsLogged(): void {
		CactiStubs::willReturn('mailer', 'SMTP Error: Could not connect to SMTP host.');

		$this->assertSame('SMTP Error: Could not connect to SMTP host.', $this->send());
		$this->assertSame(
			["ERROR: Sending Email Failed To:ops@example.org,noc@example.org Subject:ALERT: CPU.  Error was:'SMTP Error: Could not connect to SMTP host.'"],
			$this->failureLog()
		);
	}

	/**
	 * @return void
	 */
	public function testASuccessfulSendLogsNoFailure(): void {
		$this->assertSame('', $this->send());
		$this->assertSame([], $this->failureLog());
	}
}
