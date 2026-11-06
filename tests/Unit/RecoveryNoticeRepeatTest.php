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
 * A recovered device's failed-host row is dropped once its recovery has been
 * handled (#737).
 *
 * thold_update_host_status() announces every device in plugin_thold_host_failed
 * that is up again, and used to clear and rebuild the table only after all of
 * them were handled. Each device can block on SNMP gets, an up command and
 * mail, so a run cut short before the rebuild left every row in place and the
 * next cycle announced the same recoveries again.
 */
final class RecoveryNoticeRepeatTest extends TestCase {
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
	 * A device that is up again, mailing the global alert address.
	 *
	 * @param int $id
	 *
	 * @return array<string, mixed>
	 */
	private function recoveredDevice($id) {
		return [
			'id'                => $id,
			'hostname'          => 'dev' . $id,
			'description'       => 'Device ' . $id,
			'status'            => HOST_UP,
			'snmp_community'    => '',
			'snmp_username'     => '',
			'snmp_version'      => 0,
			'site_id'           => 0,
			'location'          => '',
			'notes'             => '',
			'status_fail_date'  => '0000-00-00 00:00:00',
			'status_rec_date'   => '0000-00-00 00:00:00',
			'status_last_error' => '',
			'availability'      => 100,
			'total_polls'       => 1,
			'failed_polls'      => 0,
			'cur_time'          => 0,
			'avg_time'          => 0,
			'thold_send_email'  => '1',
			'thold_host_email'  => 0,
		];
	}

	/**
	 * Two devices in the failed-host table, rows 5 and 6, both up again. The
	 * host lookup answers in turn from $devices; a Throwable stands for the run
	 * dying at that device.
	 *
	 * @param array<int, mixed> $devices
	 *
	 * @return void
	 */
	private function runHostStatus(array $devices) {
		CactiStubs::$configOptions['alert_deadnotify'] = 'on';
		CactiStubs::$configOptions['alert_email']      = 'ops@example.org';

		CactiStubs::willReturnFor('db_fetch_assoc', 'plugin_thold_host_failed', [
			['id' => 5, 'poller_id' => 1, 'host_id' => 42],
			['id' => 6, 'poller_id' => 1, 'host_id' => 43],
		]);

		CactiStubs::willReturnFor('db_fetch_row_prepared', 'FROM host WHERE id', function () use (&$devices) {
			$device = array_shift($devices);

			if ($device instanceof Throwable) {
				throw $device;
			}

			return $device;
		});

		set_error_handler(static fn (): bool => true);
		ob_start();

		try {
			thold_update_host_status();
		} finally {
			ob_end_clean();
			restore_error_handler();
		}
	}

	/**
	 * Failed-host rows deleted one at a time, by row id.
	 *
	 * @return array<int, mixed>
	 */
	private function rowsDeleted() {
		$ids = [];

		foreach (CactiStubs::callsTo('db_execute_prepared') as $call) {
			if (preg_match('/DELETE\s+FROM plugin_thold_host_failed\s+WHERE id = \?/', $call['sql'])) {
				$ids[] = $call['params'][0];
			}
		}

		return $ids;
	}

	/**
	 * The run dies at the second device. The first device's recovery was
	 * mailed, and its row is already gone, so the next cycle will not announce
	 * it again.
	 *
	 * @return void
	 */
	public function testAHandledRecoveryIsClearedBeforeTheNextDevice(): void {
		try {
			$this->runHostStatus([$this->recoveredDevice(42), new RuntimeException('poller run cut short')]);

			$this->fail('the run was expected to stop at the second device');
		} catch (RuntimeException $e) {
			$this->assertSame('poller run cut short', $e->getMessage());
		}

		$this->assertCount(1, CactiStubs::$mail);
		$this->assertSame([5], $this->rowsDeleted());
	}

	/**
	 * The run dies inside the first device's up command, as on a poller that
	 * outlasts its cycle while the command blocks. The notice already went
	 * out and the row is already gone, so the next cycle does not repeat it.
	 * (The queue path stands in for the command so nothing is executed.)
	 *
	 * @return void
	 */
	public function testARunThatDiesInTheUpCommandDoesNotRepeatTheNotice(): void {
		CactiStubs::$configOptions['thold_device_command']     = '/bin/true';
		CactiStubs::$configOptions['thold_notification_queue'] = 'on';

		// With the queue on, the recovery mail is queued first; the second
		// insert is the up command.
		$queued = 0;

		CactiStubs::willReturnFor('db_execute_prepared', 'INSERT INTO notification_queue', function () use (&$queued) {
			if (++$queued == 2) {
				throw new RuntimeException('poller run cut short in the up command');
			}

			return true;
		});

		try {
			$this->runHostStatus([$this->recoveredDevice(42), $this->recoveredDevice(43)]);

			$this->fail('the run was expected to stop in the first up command');
		} catch (RuntimeException $e) {
			$this->assertSame('poller run cut short in the up command', $e->getMessage());
		}

		$this->assertSame(2, $queued, 'the recovery mail and then the up command were queued');
		$this->assertSame([5], $this->rowsDeleted());
	}

	/**
	 * A full run clears each handled row as it goes.
	 *
	 * @return void
	 */
	public function testEveryHandledRecoveryIsCleared(): void {
		$this->runHostStatus([$this->recoveredDevice(42), $this->recoveredDevice(43)]);

		$this->assertCount(2, CactiStubs::$mail);
		$this->assertSame([5, 6], $this->rowsDeleted());
	}
}
