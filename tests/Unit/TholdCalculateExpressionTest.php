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
 * thold_calculate_expression() evaluates a whole RPN expression and returns
 * the single value the threshold is compared against.
 */
final class TholdCalculateExpressionTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @param string $expression
	 *
	 * @return mixed
	 */
	private function evaluate($expression) {
		$thold = [
			'id'             => 1,
			'name'           => 'CPU',
			'local_data_id'  => 4,
			'local_graph_id' => 7,
			'expression'     => $expression,
		];

		$reindexed      = [];
		$time_reindexed = [];

		return thold_calculate_expression($thold, 0, $reindexed, $time_reindexed);
	}

	/**
	 * @return array<string, array{0: string, 1: float|int}>
	 */
	public static function expressionProvider() {
		return [
			'addition'       => ['2,3,+', 5],
			'subtraction'    => ['8,2,-', 6],
			'multiplication' => ['4,3,*', 12],
			'nested'         => ['2,3,+,4,*', 20],
			'single value'   => ['7', 7],
		];
	}

	/**
	 * @dataProvider expressionProvider
	 *
	 * @param string    $expression
	 * @param float|int $expected
	 *
	 * @return void
	 */
	public function testExpressionsReduceToTheTopOfTheStack($expression, $expected): void {
		$this->assertEqualsWithDelta($expected, $this->evaluate($expression), 1.0e-9);
	}

	/**
	 * An expression that leaves more than one value is an authoring error. It
	 * used to return the first operand pushed, which looks like a plausible
	 * reading and is compared against the bounds as though it were one.
	 *
	 * @return void
	 */
	public function testUnbalancedExpressionIsRejectedRatherThanReturningAnOperand(): void {
		// '' (not numeric 0) is this function's unavailable-sample sentinel:
		// polling.php's fail-closed guard only catches non-numeric values, so
		// a manufactured 0 here would be persisted/alerted on as a real reading.
		$this->assertSame('', $this->evaluate('1,2,3,+'));
		$this->assertTrue($GLOBALS['rpn_error']);
	}

	/**
	 * @return void
	 */
	public function testUnsupportedTokenFailsTheExpression(): void {
		$this->assertSame('', $this->evaluate('2,NOSUCHOP'));
		$this->assertTrue($GLOBALS['rpn_error']);
	}

	/**
	 * Division/modulo by zero (with a non-zero numerator) is flagged by
	 * thold_expression_math_rpn() as an rpn_error. The whole-expression
	 * evaluator must fail closed with '' rather than a manufactured 0.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function zeroDivisorProvider() {
		return [
			'division by zero' => ['5,0,/'],
			'modulo by zero'   => ['5,0,%'],
		];
	}

	/**
	 * @dataProvider zeroDivisorProvider
	 *
	 * @param string $expression
	 *
	 * @return void
	 */
	public function testZeroDivisorFailsClosedRatherThanReturningZero($expression): void {
		$this->assertSame('', $this->evaluate($expression));
		$this->assertTrue($GLOBALS['rpn_error']);
	}

	/**
	 * A |pipe| token thold_expand_string() cannot resolve returns ''.
	 * Converting that to '0' would let a manufactured zero flow through the
	 * RPN stack as a valid operand instead of failing the expression closed.
	 *
	 * @return void
	 */
	public function testUnresolvedPipeTokenFailsClosedRatherThanReturningZero(): void {
		$graph = ['id' => 7, 'host_id' => 2, 'snmp_query_id' => '0', 'snmp_index' => ''];

		// thold_calculate_expression() and thold_expand_string() each look up
		// graph_local independently.
		CactiStubs::willReturn('db_fetch_row_prepared', $graph);
		CactiStubs::willReturn('db_fetch_row_prepared', $graph);
		CactiStubs::willReturn('expand_title', '');

		$this->assertSame('', $this->evaluate('|query_ifName|'));
		$this->assertTrue($GLOBALS['rpn_error']);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function specialTokenProvider() {
		return [
			'graph maximum' => ['CURRENT_GRAPH_MAXIMUM_VALUE'],
			'graph minimum' => ['CURRENT_GRAPH_MINIMUM_VALUE'],
		];
	}

	/**
	 * CURRENT_GRAPH_MAXIMUM_VALUE was missing from the dispatch list even
	 * though the evaluator handles it, so every expression using it failed as
	 * an unsupported field and returned zero.
	 *
	 * Reaching the handler is the whole assertion: what it then reads out of
	 * the RRD needs a live rrdtool and is not this test's concern.
	 *
	 * @dataProvider specialTokenProvider
	 *
	 * @param string $token
	 *
	 * @return void
	 */
	public function testSpecialGraphTokensReachTheirHandler($token): void {
		// The handler reads the step off the data source before asking rrdtool.
		CactiStubs::willReturnFor('db_fetch_row_prepared', 'FROM data_template_data', ['rrd_step' => 300]);

		try {
			$this->evaluate($token);
		} catch (Throwable $reached_the_rrd_layer) {
			// Expected: the handler runs and asks rrdtool for a value.
		}

		$unsupported = array_filter(CactiStubs::$log, static function ($message) {
			return strpos($message, 'Unsupported Field') !== false;
		});

		$this->assertSame([], array_values($unsupported));
	}
}
