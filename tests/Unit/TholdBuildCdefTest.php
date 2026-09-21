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
 * thold_build_cdef() evaluates a stored CDEF against the current sample.
 *
 * An unresolved `|query_...|` operand (host_snmp_cache lookup miss) is a
 * stand-in zero for the RPN math below it to run on, but the overall result
 * must still fail closed to '' rather than alert on that manufactured value.
 */
final class TholdBuildCdefTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @return void
	 */
	public function testFailsClosedWhenTheCurrentValueIsNotNumeric(): void {
		$this->assertSame('', thold_build_cdef(1, '', 4, 5));
	}

	/**
	 * @return void
	 */
	public function testEvaluatesALiteralAdditionCdef(): void {
		CactiStubs::willReturn('db_fetch_assoc_prepared', [
			['id' => 1, 'type' => 6, 'value' => 10],
			['id' => 2, 'type' => 6, 'value' => 5],
			['id' => 3, 'type' => 2, 'value' => 1],
		]);

		$this->assertSame(15, thold_build_cdef(1, 100, 4, 5));
	}

	/**
	 * @return void
	 */
	public function testFailsClosedWhenAQueryOperandDoesNotResolve(): void {
		CactiStubs::willReturn('db_fetch_assoc_prepared', [
			['id' => 1, 'type' => 6, 'value' => 10],
			['id' => 2, 'type' => 6, 'value' => '|query_ifOperStatus|'],
			['id' => 3, 'type' => 2, 'value' => 1],
		]);
		CactiStubs::willReturn('db_fetch_cell_prepared', '');

		$this->assertSame('', thold_build_cdef(1, 100, 4, 5));
	}

	/**
	 * @return void
	 */
	public function testEvaluatesWhenAQueryOperandResolves(): void {
		CactiStubs::willReturn('db_fetch_assoc_prepared', [
			['id' => 1, 'type' => 6, 'value' => 10],
			['id' => 2, 'type' => 6, 'value' => '|query_ifOperStatus|'],
			['id' => 3, 'type' => 2, 'value' => 1],
		]);
		CactiStubs::willReturn('db_fetch_cell_prepared', 5);

		$this->assertSame(15, thold_build_cdef(1, 100, 4, 5));
	}
}
