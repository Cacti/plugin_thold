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
 * The daemon child must reconnect on a connection of its own (#711).
 *
 * thold_daemon.php closes its connection, forks, and the child reconnects
 * while the parent exits. With persistent PDO connections (the include/
 * global.php default when config.php does not set $database_persist), the
 * child's "new" connection came back from the pool it inherited, i.e. the
 * parent's socket, which died with the parent: every query then failed with
 * "MySQL server has gone away". The script runs at top level, so this checks
 * its source.
 */
final class DaemonReconnectTest extends TestCase {
	/**
	 * The body of the forked-child branch in thold_daemon.php.
	 *
	 * @return string
	 */
	private function childBranch() {
		$source = file_get_contents(dirname(__DIR__, 2) . '/thold_daemon.php');

		$start = strpos($source, 'if ($pid == 0) {');
		$this->assertNotFalse($start, 'thold_daemon.php no longer has the forked-child branch');

		$end = strpos($source, '} else {', $start);
		$this->assertNotFalse($end);

		return substr($source, $start, $end - $start);
	}

	/**
	 * @return void
	 */
	public function testTheChildTurnsOffPersistentConnectionsBeforeReconnecting(): void {
		$branch = $this->childBranch();

		$persist   = strpos($branch, '$database_persist = false;');
		$reconnect = strpos($branch, 'db_connect_real(');

		$this->assertNotFalse($reconnect, 'the child no longer reconnects');
		$this->assertNotFalse($persist, 'the child reconnects without turning off persistent connections');
		$this->assertLessThan($reconnect, $persist);
	}
}
