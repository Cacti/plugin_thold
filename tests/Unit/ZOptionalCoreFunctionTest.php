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
 * Behaviour when the running Cacti provides the functions the plugin treats as
 * optional.
 *
 * The file is sorted after the fallback-path tests so defining these optional
 * functions cannot change the code paths exercised by earlier tests.
 */
final class OptionalCoreFunctionTest extends TestCase {
	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		require_once dirname(__DIR__) . '/fixtures/optional-core-functions.php';
		require_once dirname(__DIR__, 2) . '/thold_functions.php';
	}

	/**
	 * @return void
	 */
	public function testRlikeClauseDelegatesToTheCoreHelper(): void {
		$this->assertSame(db_qstr_rlike('router'), thold_rlike_clause('router'));
	}

	/**
	 * Core's helper strips the alternation and quantifier characters that make
	 * a filter expensive to evaluate; the plugin must not bypass that.
	 *
	 * @return void
	 */
	public function testRlikeClauseInheritsTheCoreOperandRestrictions(): void {
		$clause = thold_rlike_clause('(a|a){9,}');

		$this->assertStringNotContainsString('|', $clause);
		$this->assertStringNotContainsString('{', $clause);
	}

	/**
	 * @return array<string, array{0: string, 1: string}>
	 */
	public static function accessorProvider() {
		return [
			'thresholds' => ['get_allowed_thresholds', 'thold'],
			'logs'       => ['get_allowed_threshold_logs', 'thold_log'],
		];
	}

	/**
	 * The cached row count is used only for the unfiltered listing; a per-graph
	 * query counts directly so the cache is not keyed per graph.
	 *
	 * @dataProvider accessorProvider
	 *
	 * @param string $function
	 * @param string $class
	 *
	 * @return void
	 */
	public function testRowCountUsesTheCoreCacheWhenNotFilteredByGraph($function, $class): void {
		CactiStubs::willReturn('get_total_row_data', 12);

		$total = 0;
		$function('', 'td.name', '', $total, -1, 0);

		$this->assertSame(12, $total);
		$this->assertSame([], CactiStubs::callsTo('db_fetch_cell_prepared'));
	}

	/**
	 * @dataProvider accessorProvider
	 *
	 * @param string $function
	 * @param string $class
	 *
	 * @return void
	 */
	public function testRowCountBypassesTheCacheForASingleGraph($function, $class): void {
		$total = 0;
		$function('', 'td.name', '', $total, -1, 5);

		$this->assertSame([], CactiStubs::callsTo('get_total_row_data'));
		$this->assertNotEmpty(CactiStubs::callsTo('db_fetch_cell_prepared'));
	}
}
