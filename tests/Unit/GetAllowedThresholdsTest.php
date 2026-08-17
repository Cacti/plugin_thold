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
 * Query construction in get_allowed_thresholds() and
 * get_allowed_threshold_logs().
 *
 * $graph_id used to be interpolated straight into the WHERE clause. It is now
 * a bound placeholder, and these tests assert both that no caller value ever
 * reaches the SQL text and that the bound values stay in placeholder order.
 */
final class GetAllowedThresholdsTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function accessorProvider() {
		return array(
			'thresholds' => array('get_allowed_thresholds'),
			'logs'       => array('get_allowed_threshold_logs'),
		);
	}

	/**
	 * @dataProvider accessorProvider
	 *
	 * @param string $function
	 *
	 * @return void
	 */
	public function testGraphIdIsBoundRatherThanInterpolated($function): void {
		$total = 0;
		$function('', 'td.name', '', $total, -1, 42);

		$call = CactiStub::callsTo('db_fetch_assoc_prepared')[0];

		$this->assertStringContainsString('gl.id = ?', $call['sql']);
		$this->assertStringNotContainsString('42', $call['sql']);
		$this->assertSame(array(42), $call['params']);
	}

	/**
	 * A caller injecting SQL through $graph_id must end up with the payload as
	 * an inert bound value, never as query text.
	 *
	 * @dataProvider accessorProvider
	 *
	 * @param string $function
	 *
	 * @return void
	 */
	public function testMaliciousGraphIdNeverReachesQueryText($function): void {
		$payload = '1 UNION SELECT password FROM user_auth';
		$total   = 0;
		$function('', 'td.name', '', $total, -1, $payload);

		foreach (CactiStub::$calls as $call) {
			$this->assertStringNotContainsString('UNION', $call['sql']);
		}
	}

	/**
	 * The $graph_id placeholder is appended after the caller's fragment, so the
	 * caller's own values have to come first in $sql_params for the binding to
	 * line up.
	 *
	 * @dataProvider accessorProvider
	 *
	 * @param string $function
	 *
	 * @return void
	 */
	public function testCallerParametersAreBoundBeforeTheGraphIdParameter($function): void {
		$total = 0;
		$function('td.thold_type = ?', 'td.name', '', $total, -1, 7, array(3));

		$call = CactiStub::callsTo('db_fetch_assoc_prepared')[0];

		$this->assertSame(array(3, 7), $call['params']);
		$this->assertStringContainsString('td.thold_type = ? AND  gl.id = ?', $call['sql']);
	}

	/**
	 * @dataProvider accessorProvider
	 *
	 * @param string $function
	 *
	 * @return void
	 */
	public function testNoWhereClauseIsEmittedWhenNothingFiltersTheQuery($function): void {
		$total = 0;
		$function('', 'td.name', '', $total, -1, 0);

		$call = CactiStub::callsTo('db_fetch_assoc_prepared')[0];

		$this->assertStringNotContainsString('WHERE', $call['sql']);
		$this->assertSame(array(), $call['params']);
	}

	/**
	 * The row-count query reuses the same WHERE clause, so it has to receive
	 * the same bound values.
	 *
	 * @dataProvider accessorProvider
	 *
	 * @param string $function
	 *
	 * @return void
	 */
	public function testRowCountQueryBindsTheSameParameters($function): void {
		$total = 0;
		$function('td.thold_type = ?', 'td.name', '', $total, -1, 7, array(3));

		$count = CactiStub::callsTo('db_fetch_cell_prepared')[0];

		$this->assertSame(array(3, 7), $count['params']);
	}

	/**
	 * @dataProvider accessorProvider
	 *
	 * @param string $function
	 *
	 * @return void
	 */
	public function testOrderByAndLimitAreAppliedToTheQuery($function): void {
		$total = 0;
		$function('', 'td.id DESC', '0,30', $total, -1, 0);

		$call = CactiStub::callsTo('db_fetch_assoc_prepared')[0];

		$this->assertStringContainsString('ORDER BY td.id DESC', $call['sql']);
		$this->assertStringContainsString('LIMIT 0,30', $call['sql']);
	}

	/**
	 * @dataProvider accessorProvider
	 *
	 * @param string $function
	 *
	 * @return void
	 */
	public function testResultRowsAreReturnedToTheCaller($function): void {
		CactiStub::willReturn('db_fetch_assoc_prepared', array(array('id' => 5)));

		$total = 0;
		$rows  = $function('', 'td.name', '', $total, -1, 0);

		$this->assertSame(array(array('id' => 5)), $rows);
	}

	/**
	 * @dataProvider accessorProvider
	 *
	 * @param string $function
	 *
	 * @return void
	 */
	public function testTotalRowsIsSetByReference($function): void {
		CactiStub::willReturn('db_fetch_cell_prepared', 17);

		$total = 0;
		$function('', 'td.name', '', $total, -1, 3);

		$this->assertSame(17, $total);
	}

	/**
	 * With authentication on and no session, there is no user to resolve
	 * permissions against, so the query must not run at all.
	 *
	 * @dataProvider accessorProvider
	 *
	 * @param string $function
	 *
	 * @return void
	 */
	public function testNoQueryRunsWhenAuthenticationIsOnAndNoUserIsResolved($function): void {
		CactiStub::$configOptions['auth_method'] = 1;
		unset($_SESSION['sess_user_id']);

		$total = 0;
		$rows  = $function('', 'td.name', '', $total, 0, 0);

		$this->assertSame(array(), $rows);
		$this->assertSame(array(), CactiStub::callsTo('db_fetch_assoc_prepared'));
	}

	/**
	 * @dataProvider accessorProvider
	 *
	 * @param string $function
	 *
	 * @return void
	 */
	public function testPolicyWhereIsAppliedWhenPermissionsAreNotSimple($function): void {
		CactiStub::$configOptions['auth_method'] = 1;
		CactiStub::willReturn('get_simple_graph_perms', false);
		CactiStub::willReturn('get_policy_where', 'WHERE policy_applied = 1');
		$_SESSION['sess_user_id'] = 9;

		$total = 0;
		$function('', 'td.name', '', $total, 0, 0);

		unset($_SESSION['sess_user_id']);

		$call = CactiStub::callsTo('db_fetch_assoc_prepared')[0];

		$this->assertStringContainsString('policy_applied = 1', $call['sql']);
	}
}
