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
		self::loadPluginConstants();
	}

	/**
	 * @return void
	 */
	protected function tearDown(): void {
		CactiStubs::$requestVars = [];
	}

	/**
	 * get_thold_severity()'s THOLD_SEVERITY_* result mapped to the pill's
	 * legend-order rank, the same mapping thold_severity_sql() encodes.
	 *
	 * @return array<int, int>
	 */
	private function rankBySeverity(): array {
		return [
			THOLD_SEVERITY_ALERT    => 1,
			THOLD_SEVERITY_BASELINE => 2,
			THOLD_SEVERITY_WARNING  => 3,
			THOLD_SEVERITY_NOTICE   => 4,
			THOLD_SEVERITY_NORMAL   => 5,
			THOLD_SEVERITY_ACKREQ   => 6,
			THOLD_SEVERITY_DISABLED => 7,
		];
	}

	/**
	 * A fully-populated, enabled, within-bounds hi/low threshold.
	 *
	 * @return array<string, mixed>
	 */
	private function baseline(): array {
		return [
			'template_enabled'           => 'on',
			'thold_enabled'              => 'on',
			'thold_per_enabled'          => 'on',
			'thold_type'                 => 0,
			'thold_alert'                => 0,
			'thold_hi'                   => '',
			'thold_low'                  => '',
			'thold_fail_count'           => 0,
			'thold_fail_trigger'         => 0,
			'lastread'                   => 50,
			'thold_warning_hi'           => '',
			'thold_warning_low'          => '',
			'thold_warning_fail_count'   => 0,
			'thold_warning_fail_trigger' => 0,
			'acknowledgment'             => '',
			'bl_alert'                   => 0,
			'bl_fail_count'              => 0,
			'bl_fail_trigger'            => 0,
			'time_hi'                    => '',
			'time_low'                   => '',
			'time_fail_trigger'          => 0,
			'time_warning_hi'            => '',
			'time_warning_low'           => '',
			'time_warning_fail_trigger'  => 0,
		];
	}

	/**
	 * Evaluate thold_severity_sql() over a single row with SQLite so the SQL
	 * ranking can be compared against the PHP result without MySQL.
	 *
	 * @param array<string, mixed> $td
	 *
	 * @return int
	 */
	private function sqlRank(array $td): int {
		$columns = [];

		foreach ($td as $name => $value) {
			if (is_int($value) || is_float($value)) {
				$columns[] = $value . ' AS ' . $name;
			} else {
				$columns[] = "'" . str_replace("'", "''", (string) $value) . "' AS " . $name;
			}
		}

		$pdo = new PDO('sqlite::memory:');
		$sql = 'SELECT ' . thold_severity_sql() . ' AS severity FROM (SELECT ' . implode(', ', $columns) . ') AS td';

		return (int) $pdo->query($sql)->fetchColumn();
	}

	/**
	 * @return array<string, array{0: array<string, mixed>}>
	 */
	public static function scenarioProvider(): array {
		return [
			'within bounds'           => [[]],
			'per-threshold disabled'  => [['thold_per_enabled' => '']],
			'template disabled'       => [['thold_enabled' => 'off']],
			'hi/low at trigger'       => [['thold_alert' => 1, 'thold_hi' => '90', 'thold_fail_count' => 2, 'thold_fail_trigger' => 1]],
			'hi/low warning'          => [['thold_alert' => 1, 'thold_hi' => '90', 'thold_fail_trigger' => 3, 'thold_warning_hi' => '80', 'thold_warning_fail_count' => 2, 'thold_warning_fail_trigger' => 1, 'lastread' => 85]],
			'ack required'            => [['acknowledgment' => 'on']],
			'baseline triggered'      => [['thold_type' => 1, 'bl_alert' => 1, 'bl_fail_count' => 2, 'bl_fail_trigger' => 1]],
			'baseline below trigger'  => [['thold_type' => 1, 'bl_alert' => 1, 'bl_fail_count' => 0, 'bl_fail_trigger' => 3]],
			'time-based breach'       => [['thold_type' => 2, 'thold_alert' => 1, 'time_hi' => '90', 'time_fail_trigger' => 1, 'thold_fail_count' => 2]],
			// time_warning_low of '0' is falsy in PHP and must not open the warning branch.
			'time warning_low is 0'   => [['thold_type' => 2, 'thold_alert' => 1, 'time_warning_low' => '0']],
		];
	}

	/**
	 * @dataProvider scenarioProvider
	 *
	 * @param array<string, mixed> $overrides
	 *
	 * @return void
	 */
	public function testSqlRankMatchesGetTholdSeverity(array $overrides): void {
		if (!extension_loaded('pdo_sqlite')) {
			$this->markTestSkipped('pdo_sqlite is required to evaluate the severity CASE');
		}

		$td = $overrides + $this->baseline();

		$expected = $this->rankBySeverity()[get_thold_severity($td)];

		$this->assertSame($expected, $this->sqlRank($td));
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
