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
 * Behaviour of thold_expression_math_rpn(), the arithmetic half of the
 * threshold expression evaluator.
 *
 * These pin down the semantics that used to come from eval(). The evaluator
 * pops the right operand first, so a stack of [a, b] with operator OP
 * computes a OP b.
 */
final class TholdExpressionMathRpnTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * Evaluate one operator against a stack and return the resulting stack.
	 *
	 * @param array<int, mixed> $stack    Initial stack, bottom element first.
	 * @param string            $operator RPN operator token.
	 *
	 * @return array<int, mixed>
	 */
	private function evaluate(array $stack, $operator) {
		thold_expression_math_rpn($operator, $stack);

		return $stack;
	}

	/**
	 * @return array<string, array{0: array<int, mixed>, 1: string, 2: float|int}>
	 */
	public static function binaryOperatorProvider() {
		return [
			'addition'                 => [[8, 2], '+', 10],
			'subtraction keeps order'  => [[8, 2], '-', 6],
			'multiplication'           => [[8, 2], '*', 16],
			'division keeps order'     => [[8, 2], '/', 4],
			'modulo'                   => [[8, 3], '%', 2],
			'float addition'           => [[1.5, 2.25], '+', 3.75],
			'numeric string operands'  => [['8', '2'], '-', 6],
			'negative operands'        => [[-8, 2], '/', -4],
		];
	}

	/**
	 * @dataProvider binaryOperatorProvider
	 *
	 * @param array<int, mixed> $stack
	 * @param string            $operator
	 * @param float|int         $expected
	 *
	 * @return void
	 */
	public function testBinaryOperatorsComputeInStackOrder(array $stack, $operator, $expected): void {
		$this->assertSame([$expected], $this->evaluate($stack, $operator));
		$this->assertFalse($GLOBALS['rpn_error']);
	}

	/**
	 * PHP's ^ is bitwise XOR, not exponentiation. The pre-hardening evaluator
	 * built the string "$v2 ^ $v1" and eval'd it, so it computed XOR too. This
	 * test records the behaviour as-is; changing it to pow() would be a
	 * separate, breaking change to existing user thresholds.
	 *
	 * @return void
	 */
	public function testCaretOperatorIsIntegerXorNotExponentiation(): void {
		$this->assertSame([6], $this->evaluate([5, 3], '^'));
		$this->assertSame([1], $this->evaluate([2, 3], '^'));
	}

	/**
	 * @return void
	 */
	public function testCaretOperatorTruncatesFloatOperandsToIntegers(): void {
		$this->assertSame([6], $this->evaluate([5.9, 3.9], '^'));
	}

	/**
	 * @return void
	 */
	public function testModuloTruncatesFloatOperandsToIntegers(): void {
		$this->assertSame([1], $this->evaluate([7.9, 3.2], '%'));
	}

	/**
	 * Dividing zero by zero is defined as zero rather than an error so a
	 * counter that has not moved does not flag the whole threshold.
	 *
	 * @return void
	 */
	public function testZeroDividedByZeroYieldsZeroWithoutError(): void {
		$this->assertSame([0], $this->evaluate([0, 0], '/'));
		$this->assertFalse($GLOBALS['rpn_error']);
	}

	/**
	 * @return void
	 */
	public function testDivisionByZeroFlagsErrorAndPushesNothing(): void {
		$this->assertSame([], $this->evaluate([8, 0], '/'));
		$this->assertTrue($GLOBALS['rpn_error']);
	}

	/**
	 * @return void
	 */
	public function testModuloByZeroFlagsErrorInsteadOfThrowing(): void {
		$this->assertSame([], $this->evaluate([8, 0], '%'));
		$this->assertTrue($GLOBALS['rpn_error']);
	}

	/**
	 * @return array<string, array{0: array<int, mixed>, 1: string}>
	 */
	public static function nonNumericOperandProvider() {
		return [
			'unknown right operand' => [[8, 'U'], '+'],
			'unknown left operand'  => [['U', 8], '+'],
			'NaN right operand'     => [[8, 'NAN'], '*'],
			'text operand'          => [[8, 'abc'], '-'],
		];
	}

	/**
	 * @dataProvider nonNumericOperandProvider
	 *
	 * @param array<int, mixed> $stack
	 * @param string            $operator
	 *
	 * @return void
	 */
	public function testNonNumericOperandsFlagErrorAndPushNothing(array $stack, $operator): void {
		$this->assertSame([], $this->evaluate($stack, $operator));
		$this->assertTrue($GLOBALS['rpn_error']);
		$this->assertNotEmpty(CactiStub::$log);
	}

	/**
	 * @return array<string, array{0: float|int, 1: string, 2: float|int}>
	 */
	public static function unaryFunctionProvider() {
		return [
			'SIN'     => [0, 'SIN', 0.0],
			'COS'     => [0, 'COS', 1.0],
			'TAN'     => [0, 'TAN', 0.0],
			'ATAN'    => [0, 'ATAN', 0.0],
			'SQRT'    => [9, 'SQRT', 3.0],
			'FLOOR'   => [2.7, 'FLOOR', 2.0],
			'CEIL'    => [2.1, 'CEIL', 3.0],
			'DEG2RAD' => [180, 'DEG2RAD', M_PI],
			'RAD2DEG' => [M_PI, 'RAD2DEG', 180.0],
			'ABS'     => [-5, 'ABS', 5],
			'EXP'     => [0, 'EXP', 1.0],
			'LOG'     => [M_E, 'LOG', 1.0],
		];
	}

	/**
	 * @dataProvider unaryFunctionProvider
	 *
	 * @param float|int $operand
	 * @param string    $operator
	 * @param float|int $expected
	 *
	 * @return void
	 */
	public function testUnaryFunctionsDispatchToNativeMath($operand, $operator, $expected): void {
		$stack = $this->evaluate([$operand], $operator);

		$this->assertCount(1, $stack);
		$this->assertEqualsWithDelta($expected, $stack[0], 1.0e-9);
		$this->assertFalse($GLOBALS['rpn_error']);
	}

	/**
	 * @return void
	 */
	public function testUnaryFunctionRejectsNonNumericOperand(): void {
		$this->assertSame([], $this->evaluate(['U'], 'SQRT'));
		$this->assertTrue($GLOBALS['rpn_error']);
	}

	/**
	 * sqrt(-1) and log(0) have no usable numeric result, so the evaluator must
	 * flag an error rather than push NAN or -INF into a threshold comparison,
	 * where every comparison against them silently returns false.
	 *
	 * @return array<string, array{0: float|int, 1: string}>
	 */
	public static function undefinedResultProvider() {
		return [
			'square root of a negative' => [-1, 'SQRT'],
			'log of zero'               => [0, 'LOG'],
			'log of a negative'         => [-1, 'LOG'],
		];
	}

	/**
	 * @dataProvider undefinedResultProvider
	 *
	 * @param float|int $operand
	 * @param string    $operator
	 *
	 * @return void
	 */
	public function testUndefinedResultsFlagErrorInsteadOfPushingNanOrInf($operand, $operator): void {
		$this->assertSame([], $this->evaluate([$operand], $operator));
		$this->assertTrue($GLOBALS['rpn_error']);
	}

	/**
	 * @return void
	 */
	public function testAtan2ComputesAgainstBothOperands(): void {
		$stack = $this->evaluate([1, 1], 'ATAN2');

		$this->assertEqualsWithDelta(M_PI / 4, $stack[0], 1.0e-9);
	}

	/**
	 * ADDNAN exists so a missing sample contributes zero instead of poisoning
	 * the sum.
	 *
	 * @return array<string, array{0: array<int, mixed>, 1: float|int}>
	 */
	public static function addNanProvider() {
		return [
			'both known'      => [[3, 4], 7],
			'right unknown'   => [[3, 'U'], 3],
			'left unknown'    => [['U', 4], 4],
			'right NaN'       => [[3, 'NAN'], 3],
			'both unknown'    => [['U', 'NAN'], 0],
		];
	}

	/**
	 * @dataProvider addNanProvider
	 *
	 * @param array<int, mixed> $stack
	 * @param float|int         $expected
	 *
	 * @return void
	 */
	public function testAddNanTreatsUnknownOperandsAsZero(array $stack, $expected): void {
		$this->assertSame([$expected], $this->evaluate($stack, 'ADDNAN'));
	}

	/**
	 * @return void
	 */
	public function testUnknownOperatorLeavesStackUntouched(): void {
		$this->assertSame([1, 2], $this->evaluate([1, 2], 'NOSUCHOP'));
	}

	/**
	 * @return void
	 */
	public function testUnderflowFlagsErrorRatherThanPoppingAnEmptyStack(): void {
		$this->evaluate([], '+');

		$this->assertTrue($GLOBALS['rpn_error']);
	}
}
