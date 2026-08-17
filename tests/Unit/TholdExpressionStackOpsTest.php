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
 * Stack and set operators, checked against rrdtool's RPN semantics.
 *
 * Stacks are written bottom element first, so the last entry is the top.
 */
final class TholdExpressionStackOpsTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @param array<int, mixed> $stack
	 * @param string            $operator
	 *
	 * @return array<int, mixed>
	 */
	private function stackOp(array $stack, $operator) {
		thold_expression_stackops_rpn($operator, $stack);

		return $stack;
	}

	/**
	 * @param array<int, mixed> $stack
	 * @param string            $operator
	 *
	 * @return array<int, mixed>
	 */
	private function setOp(array $stack, $operator) {
		thold_expression_setops_rpn($operator, $stack);

		return $stack;
	}

	/**
	 * @return void
	 */
	public function testDupCopiesTheTopOfTheStack(): void {
		$this->assertSame([1, 7, 7], $this->stackOp([1, 7], 'DUP'));
	}

	/**
	 * @return void
	 */
	public function testPopDiscardsTheTopOfTheStack(): void {
		$this->assertSame([1], $this->stackOp([1, 7], 'POP'));
	}

	/**
	 * @return void
	 */
	public function testExcExchangesTheTopTwoElements(): void {
		$this->assertSame([2, 1], $this->stackOp([1, 2], 'EXC'));
	}

	/**
	 * @return void
	 */
	public function testExcLeavesDeeperElementsAlone(): void {
		$this->assertSame([9, 8, 2, 1], $this->stackOp([9, 8, 1, 2], 'EXC'));
	}

	/**
	 * @return void
	 */
	public function testExcOnAShortStackFlagsAnError(): void {
		$this->stackOp([1], 'EXC');

		$this->assertTrue($GLOBALS['rpn_error']);
	}

	/**
	 * @return void
	 */
	public function testDupOnAnEmptyStackFlagsAnError(): void {
		$this->assertSame([], $this->stackOp([], 'DUP'));
		$this->assertTrue($GLOBALS['rpn_error']);
	}

	/**
	 * @return void
	 */
	public function testUnrecognisedSetOperatorLeavesTheStackUntouched(): void {
		$this->assertSame([1, 2], $this->setOp([1, 2], 'NOSUCHOP'));
		$this->assertFalse($GLOBALS['rpn_error']);
	}

	/**
	 * @return void
	 */
	public function testRevReversesTheRequestedNumberOfElements(): void {
		$this->assertSame([3, 2, 1], $this->setOp([1, 2, 3, 3], 'REV'));
	}

	/**
	 * @return void
	 */
	public function testRevLeavesDeeperElementsAlone(): void {
		$this->assertSame([9, 3, 2, 1], $this->setOp([9, 1, 2, 3, 3], 'REV'));
	}

	/**
	 * @return void
	 */
	public function testRevOfZeroElementsLeavesTheStackUnchanged(): void {
		$this->assertSame([1, 2], $this->setOp([1, 2, 0], 'REV'));
	}

	/**
	 * @return void
	 */
	public function testSortOrdersTheRequestedElementsAscending(): void {
		$this->assertSame([1, 2, 3], $this->setOp([3, 1, 2, 3], 'SORT'));
	}

	/**
	 * @return void
	 */
	public function testSortLeavesDeeperElementsAlone(): void {
		$this->assertSame([9, 1, 2, 3], $this->setOp([9, 3, 1, 2, 3], 'SORT'));
	}

	/**
	 * @return void
	 */
	public function testSortAndRevAreInversesOverTheSameSpan(): void {
		$sorted   = $this->setOp([3, 1, 2, 3], 'SORT');
		$sorted[] = 3;

		$this->assertSame([3, 2, 1], $this->setOp($sorted, 'REV'));
	}

	/**
	 * @return void
	 */
	public function testAvgDividesTheSumByTheCount(): void {
		$this->assertEqualsWithDelta([3], $this->setOp([2, 4, 2], 'AVG'), 1.0e-9);
	}

	/**
	 * rrdtool's AVG ignores unknown samples and averages the rest, rather than
	 * failing the whole expression.
	 *
	 * @return void
	 */
	public function testAvgSkipsUnknownSamplesInsteadOfThrowing(): void {
		$this->assertEqualsWithDelta([3], $this->setOp([2, 4, 'U', 3], 'AVG'), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testAvgSkipsNanSamples(): void {
		$this->assertEqualsWithDelta([3], $this->setOp([2, 4, 'NAN', 3], 'AVG'), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testAvgOfOnlyUnknownSamplesIsUnknown(): void {
		$this->assertSame(['U'], $this->setOp(['U', 'NAN', 2], 'AVG'));
	}

	/**
	 * @return void
	 */
	public function testAvgPropagatesInfinity(): void {
		$this->assertSame(['INF'], $this->setOp([2, 'INF', 2], 'AVG'));
		$this->assertSame(['NEGINF'], $this->setOp([2, 'NEGINF', 2], 'AVG'));
	}

	/**
	 * Popping more elements than the stack holds leaves the operator with no
	 * usable operands, so it must not push a result.
	 *
	 * @return array<string, array{0: string}>
	 */
	public static function setOperatorProvider() {
		return [
			'SORT' => ['SORT'],
			'REV'  => ['REV'],
			'AVG'  => ['AVG'],
		];
	}

	/**
	 * @dataProvider setOperatorProvider
	 *
	 * @param string $operator
	 *
	 * @return void
	 */
	public function testUnderflowFlagsAnErrorAndPushesNothing($operator): void {
		$this->assertSame([], $this->setOp([1, 5], $operator));
		$this->assertTrue($GLOBALS['rpn_error']);
	}
}
