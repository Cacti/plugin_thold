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
 * thold_severity_sql() and the severity branch of thold_get_state_filter().
 *
 * The status-pill column sorts and filters on a severity rank computed in SQL;
 * these exercise the rank expression and the 101-107 severity filter mapping.
 */
final class TholdSeveritySqlTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('includes/functions.php');
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		CactiStubs::$requestVars = [];
	}

	/**
	 * @return void
	 */
	public function testSeveritySqlIsACaseExpressionCoveringEveryThresholdType(): void {
		$sql = thold_severity_sql();

		$this->assertStringContainsString('CASE', $sql);
		$this->assertStringContainsString('td.thold_type = 0', $sql);
		$this->assertStringContainsString('td.thold_type = 1', $sql);
		$this->assertStringContainsString('td.thold_type = 2', $sql);
	}

	/**
	 * A pill value of 101 (Alert) maps to severity rank 1.
	 *
	 * @return void
	 */
	public function testStateFilterMapsTheAlertSeverityToRankOne(): void {
		CactiStubs::$requestVars['state'] = 101;

		$filter = thold_get_state_filter(101);

		$this->assertStringContainsString(thold_severity_sql(), $filter);
		$this->assertStringContainsString('= 1)', $filter);
	}

	/**
	 * A pill value of 107 (Disabled) maps to severity rank 7.
	 *
	 * @return void
	 */
	public function testStateFilterMapsTheDisabledSeverityToRankSeven(): void {
		CactiStubs::$requestVars['state'] = 107;

		$filter = thold_get_state_filter(107);

		$this->assertStringContainsString('= 7)', $filter);
	}
}
