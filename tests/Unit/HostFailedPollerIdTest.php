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
 * Failed-host rows record the poller that wrote them.
 *
 * With remote storage, thold_update_host_status() reads and deletes
 * plugin_thold_host_failed rows WHERE poller_id = this poller. The rebuild at
 * the end of the run inserted only host_id, so every row took the column
 * default of 1: on any other poller its rows were never read back, recovery
 * notices were lost, and the rows piled up.
 */
final class HostFailedPollerIdTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/functions.php');
		self::loadPluginSource('includes/polling.php');
		self::loadPluginSourceAlways('includes/arrays.php');
		self::loadPluginConstants();
	}

	/**
	 * $config['poller_id'] as it was before the test, or null when unset.
	 *
	 * @var mixed
	 */
	private $savedPollerId;

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		$this->savedPollerId = $GLOBALS['config']['poller_id'] ?? null;
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		if ($this->savedPollerId === null) {
			unset($GLOBALS['config']['poller_id']);
		} else {
			$GLOBALS['config']['poller_id'] = $this->savedPollerId;
		}

		parent::tearDown();
	}

	/**
	 * Run one host-status pass on poller $poller with device 77 down, then
	 * return the SQL of the failed-host rebuild.
	 *
	 * @param int $poller
	 *
	 * @return string
	 */
	private function rebuildOn($poller) {
		$GLOBALS['config']['poller_id'] = $poller;

		CactiStubs::$configOptions['alert_deadnotify']      = 'on';
		CactiStubs::$configOptions['remote_storage_method'] = 1;

		// No device is newly down, and device 77 is still down when the table is rebuilt.
		CactiStubs::willReturnFor('db_fetch_assoc_prepared', 'availability_method', []);
		CactiStubs::willReturnFor('db_fetch_assoc_prepared', 'SELECT id, status, hostname', [
			['id' => 77, 'status' => HOST_DOWN, 'hostname' => 'dev77'],
		]);

		set_error_handler(static fn (): bool => true);
		ob_start();

		try {
			thold_update_host_status();
		} finally {
			ob_end_clean();
			restore_error_handler();
		}

		foreach (CactiStubs::callsTo('db_execute') as $call) {
			if (strpos($call['sql'], 'INSERT INTO plugin_thold_host_failed') !== false) {
				return preg_replace('/\s+/', ' ', $call['sql']);
			}
		}

		$this->fail('the failed-host table was not rebuilt');
	}

	/**
	 * A remote poller's rows carry its own id, so its next pass finds them.
	 *
	 * @return void
	 */
	public function testARemotePollerRecordsItsOwnId(): void {
		$sql = $this->rebuildOn(3);

		$this->assertStringContainsString('(poller_id, host_id)', $sql);
		$this->assertStringContainsString('VALUES (3, 77)', $sql);
	}

	/**
	 * The main poller records 1, as the column default did.
	 *
	 * @return void
	 */
	public function testTheMainPollerRecordsOne(): void {
		$this->assertStringContainsString('VALUES (1, 77)', $this->rebuildOn(1));
	}
}
