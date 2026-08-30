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
 * thold_rlike_clause() quoting.
 *
 * The bootstrap does not define db_qstr_rlike(), so these exercise the
 * fallback path taken on Cacti 1.2.25 through 1.2.30. The 1.2.31+ path
 * delegates to core and is core's to test.
 */
final class TholdRlikeClauseTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @return void
	 */
	public function testFallbackEmitsTheOperatorAndAQuotedOperand(): void {
		$this->assertSame("RLIKE 'router'", thold_rlike_clause('router'));
	}

	/**
	 * A quote in the filter has to be escaped, or it closes the string literal
	 * and the rest of the filter becomes SQL.
	 *
	 * @return void
	 */
	public function testQuotesInTheFilterAreEscaped(): void {
		$clause = thold_rlike_clause("' OR 1=1 -- ");

		$this->assertSame("RLIKE ''' OR 1=1 -- '", $clause);
	}

	/**
	 * @return void
	 */
	public function testEmptyFilterStillProducesAValidClause(): void {
		$this->assertSame("RLIKE ''", thold_rlike_clause(''));
	}

	/**
	 * @return void
	 */
	public function testCoreHelperIsPreferredWhenAvailable(): void {
		if (!function_exists('db_qstr_rlike')) {
			$this->assertSame("RLIKE 'x'", thold_rlike_clause('x'));

			return;
		}

		$this->assertSame(db_qstr_rlike('x'), thold_rlike_clause('x'));
	}
}
