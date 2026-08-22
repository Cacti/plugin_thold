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
	public function testEmitsTheOperatorAndAQuotedOperand(): void {
		$this->assertSame("RLIKE 'router'", thold_rlike_clause('router'));
	}

	/**
	 * @return void
	 */
	public function testQuotesInTheFilterAreEscaped(): void {
		$this->assertSame("RLIKE ''' OR 1=1 -- '", thold_rlike_clause("' OR 1=1 -- "));
	}

	/**
	 * @return void
	 */
	public function testCoreHelperIsPreferredWhenAvailable(): void {
		$this->assertSame(db_qstr_rlike('x'), thold_rlike_clause('x'));
	}
}
