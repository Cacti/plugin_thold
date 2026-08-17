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
 * thold_rpn() applies one CDEF operation to a pair of values.
 *
 * Its sentinel for an operation that cannot produce a number is the empty
 * string; the caller checks for that. A numeric sentinel would be compared
 * against the threshold bounds as though it were a reading.
 */
final class TholdRpnCdefTest extends TestCase {
	const ADD = 1;
	const SUB = 2;
	const MUL = 3;
	const DIV = 4;
	const MOD = 5;

	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @return array<string, array{0: float|int, 1: float|int, 2: int, 3: float|int}>
	 */
	public static function arithmeticProvider() {
		return [
			'addition'         => [8, 2, self::ADD, 10],
			'subtraction'      => [8, 2, self::SUB, 6],
			'multiplication'   => [8, 2, self::MUL, 16],
			'division'         => [8, 2, self::DIV, 4],
			'modulo'           => [8, 3, self::MOD, 2],
			'float division'   => [5, 2, self::DIV, 2.5],
			'negative operand' => [-8, 2, self::DIV, -4],
		];
	}

	/**
	 * @dataProvider arithmeticProvider
	 *
	 * @param float|int $x
	 * @param float|int $y
	 * @param int       $op
	 * @param float|int $expected
	 *
	 * @return void
	 */
	public function testArithmeticOperations($x, $y, $op, $expected): void {
		$this->assertEqualsWithDelta($expected, thold_rpn($x, $y, $op), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testDivisionByZeroReturnsTheInvalidSentinel(): void {
		$this->assertSame('', thold_rpn(8, 0, self::DIV));
	}

	/**
	 * @return void
	 */
	public function testModuloByZeroReturnsTheInvalidSentinelInsteadOfThrowing(): void {
		$this->assertSame('', thold_rpn(8, 0, self::MOD));
	}

	/**
	 * @return array<string, array{0: mixed, 1: mixed}>
	 */
	public static function nonNumericProvider() {
		return [
			'text first operand'  => ['abc', 2],
			'text second operand' => [8, 'abc'],
		];
	}

	/**
	 * @dataProvider nonNumericProvider
	 *
	 * @param mixed $x
	 * @param mixed $y
	 *
	 * @return void
	 */
	public function testNonNumericOperandsReturnTheInvalidSentinel($x, $y): void {
		$this->assertSame('', thold_rpn($x, $y, self::ADD));
		$this->assertNotEmpty(CactiStub::$log);
	}

	/**
	 * An unknown sample is coerced to zero rather than rejected, so an
	 * expression over a gappy data source still produces a number.
	 *
	 * @return void
	 */
	public function testUnknownOperandsAreTreatedAsZero(): void {
		$this->assertSame(2, thold_rpn('U', 2, self::ADD));
		$this->assertSame(2, thold_rpn(2, 'U', self::ADD));
	}

	/**
	 * @return void
	 */
	public function testUnrecognisedOperationReturnsTheInvalidSentinel(): void {
		$this->assertSame('', thold_rpn(8, 2, 99));
	}
}
