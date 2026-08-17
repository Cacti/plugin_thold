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
		return array(
			'addition'                 => array(array(8, 2), '+', 10),
			'subtraction keeps order'  => array(array(8, 2), '-', 6),
			'multiplication'           => array(array(8, 2), '*', 16),
			'division keeps order'     => array(array(8, 2), '/', 4),
			'modulo'                   => array(array(8, 3), '%', 2),
			'float addition'           => array(array(1.5, 2.25), '+', 3.75),
			'numeric string operands'  => array(array('8', '2'), '-', 6),
			'negative operands'        => array(array(-8, 2), '/', -4),
		);
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
		$this->assertSame(array($expected), $this->evaluate($stack, $operator));
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
		$this->assertSame(array(6), $this->evaluate(array(5, 3), '^'));
		$this->assertSame(array(1), $this->evaluate(array(2, 3), '^'));
	}

	/**
	 * @return void
	 */
	public function testCaretOperatorTruncatesFloatOperandsToIntegers(): void {
		$this->assertSame(array(6), $this->evaluate(array(5.9, 3.9), '^'));
	}

	/**
	 * @return void
	 */
	public function testModuloTruncatesFloatOperandsToIntegers(): void {
		$this->assertSame(array(1), $this->evaluate(array(7.9, 3.2), '%'));
	}

	/**
	 * Dividing zero by zero is defined as zero rather than an error so a
	 * counter that has not moved does not flag the whole threshold.
	 *
	 * @return void
	 */
	public function testZeroDividedByZeroYieldsZeroWithoutError(): void {
		$this->assertSame(array(0), $this->evaluate(array(0, 0), '/'));
		$this->assertFalse($GLOBALS['rpn_error']);
	}

	/**
	 * @return void
	 */
	public function testDivisionByZeroFlagsErrorAndPushesNothing(): void {
		$this->assertSame(array(), $this->evaluate(array(8, 0), '/'));
		$this->assertTrue($GLOBALS['rpn_error']);
	}

	/**
	 * @return void
	 */
	public function testModuloByZeroFlagsErrorInsteadOfThrowing(): void {
		$this->assertSame(array(), $this->evaluate(array(8, 0), '%'));
		$this->assertTrue($GLOBALS['rpn_error']);
	}

	/**
	 * @return array<string, array{0: array<int, mixed>, 1: string}>
	 */
	public static function nonNumericOperandProvider() {
		return array(
			'unknown right operand' => array(array(8, 'U'), '+'),
			'unknown left operand'  => array(array('U', 8), '+'),
			'NaN right operand'     => array(array(8, 'NAN'), '*'),
			'text operand'          => array(array(8, 'abc'), '-'),
		);
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
		$this->assertSame(array(), $this->evaluate($stack, $operator));
		$this->assertTrue($GLOBALS['rpn_error']);
		$this->assertNotEmpty(CactiStub::$log);
	}

	/**
	 * @return array<string, array{0: float|int, 1: string, 2: float|int}>
	 */
	public static function unaryFunctionProvider() {
		return array(
			'SIN'     => array(0, 'SIN', 0.0),
			'COS'     => array(0, 'COS', 1.0),
			'TAN'     => array(0, 'TAN', 0.0),
			'ATAN'    => array(0, 'ATAN', 0.0),
			'SQRT'    => array(9, 'SQRT', 3.0),
			'FLOOR'   => array(2.7, 'FLOOR', 2.0),
			'CEIL'    => array(2.1, 'CEIL', 3.0),
			'DEG2RAD' => array(180, 'DEG2RAD', M_PI),
			'RAD2DEG' => array(M_PI, 'RAD2DEG', 180.0),
			'ABS'     => array(-5, 'ABS', 5),
			'EXP'     => array(0, 'EXP', 1.0),
			'LOG'     => array(M_E, 'LOG', 1.0),
		);
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
		$stack = $this->evaluate(array($operand), $operator);

		$this->assertCount(1, $stack);
		$this->assertEqualsWithDelta($expected, $stack[0], 1.0e-9);
		$this->assertFalse($GLOBALS['rpn_error']);
	}

	/**
	 * @return void
	 */
	public function testUnaryFunctionRejectsNonNumericOperand(): void {
		$this->assertSame(array(), $this->evaluate(array('U'), 'SQRT'));
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
		return array(
			'square root of a negative' => array(-1, 'SQRT'),
			'log of zero'               => array(0, 'LOG'),
			'log of a negative'         => array(-1, 'LOG'),
		);
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
		$this->assertSame(array(), $this->evaluate(array($operand), $operator));
		$this->assertTrue($GLOBALS['rpn_error']);
	}

	/**
	 * @return void
	 */
	public function testAtan2ComputesAgainstBothOperands(): void {
		$stack = $this->evaluate(array(1, 1), 'ATAN2');

		$this->assertEqualsWithDelta(M_PI / 4, $stack[0], 1.0e-9);
	}

	/**
	 * ADDNAN exists so a missing sample contributes zero instead of poisoning
	 * the sum.
	 *
	 * @return array<string, array{0: array<int, mixed>, 1: float|int}>
	 */
	public static function addNanProvider() {
		return array(
			'both known'      => array(array(3, 4), 7),
			'right unknown'   => array(array(3, 'U'), 3),
			'left unknown'    => array(array('U', 4), 4),
			'right NaN'       => array(array(3, 'NAN'), 3),
			'both unknown'    => array(array('U', 'NAN'), 0),
		);
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
		$this->assertSame(array($expected), $this->evaluate($stack, 'ADDNAN'));
	}

	/**
	 * @return void
	 */
	public function testUnknownOperatorLeavesStackUntouched(): void {
		$this->assertSame(array(1, 2), $this->evaluate(array(1, 2), 'NOSUCHOP'));
	}

	/**
	 * @return void
	 */
	public function testUnderflowFlagsErrorRatherThanPoppingAnEmptyStack(): void {
		$this->evaluate(array(), '+');

		$this->assertTrue($GLOBALS['rpn_error']);
	}
}
