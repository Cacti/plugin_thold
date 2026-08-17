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
 * get_current_value() reads one data source's latest value out of the RRD.
 *
 * Its contract on failure is to return 0. Returning some other data source's
 * value instead is worse than returning nothing, because the caller compares
 * whatever it gets against the threshold bounds.
 */
final class GetCurrentValueTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		CactiStub::willReturn('db_fetch_row_prepared', ['rrd_step' => 300]);

		// thold_rrd_last() returns whatever `rrdtool last` printed.
		CactiStub::willReturn('rrdtool_execute', '1700000000');
	}

	/**
	 * @param array<string, mixed> $fetch
	 *
	 * @return void
	 */
	private function rrdReturns(array $fetch) {
		CactiStub::willReturn('rrdtool_function_fetch', $fetch);
	}

	/**
	 * @return void
	 */
	public function testTheRequestedDataSourceValueIsReturned(): void {
		$this->rrdReturns([
			'data_source_names' => ['traffic_in', 'traffic_out'],
			'values'            => [['1700000000' => 10.0], ['1700000000' => 20.0]],
		]);

		$this->assertSame(20.0, get_current_value(4, 'traffic_out'));
	}

	/**
	 * array_search() returns false, not null, so a guard written against null
	 * let the miss through and PHP then read index 0 — the first data source.
	 *
	 * @return void
	 */
	public function testUnknownDataSourceReturnsZeroRatherThanTheFirstOne(): void {
		$this->rrdReturns([
			'data_source_names' => ['traffic_in', 'traffic_out'],
			'values'            => [['1700000000' => 10.0], ['1700000000' => 20.0]],
		]);

		$this->assertSame(0, get_current_value(4, 'upper_limit'));
	}

	/**
	 * @return void
	 */
	public function testFirstDataSourceIsStillReachableByName(): void {
		$this->rrdReturns([
			'data_source_names' => ['traffic_in', 'traffic_out'],
			'values'            => [['1700000000' => 10.0], ['1700000000' => 20.0]],
		]);

		$this->assertSame(10.0, get_current_value(4, 'traffic_in'));
	}

	/**
	 * @return void
	 */
	public function testMissingDataSourceNamesReturnsZero(): void {
		$this->rrdReturns([]);

		$this->assertSame(0, get_current_value(4, 'traffic_in'));
	}

	/**
	 * @return void
	 */
	public function testMissingValuesReturnsZero(): void {
		$this->rrdReturns(['data_source_names' => ['traffic_in']]);

		$this->assertSame(0, get_current_value(4, 'traffic_in'));
	}

	/**
	 * @return void
	 */
	public function testEmptyValueSeriesReturnsZero(): void {
		$this->rrdReturns([
			'data_source_names' => ['traffic_in'],
			'values'            => [[]],
		]);

		$this->assertSame(0, get_current_value(4, 'traffic_in'));
	}

	/**
	 * A missing or unreadable RRD makes `rrdtool last` print nothing, which
	 * used to reach the timestamp arithmetic as an empty string and fatal.
	 *
	 * @return void
	 */
	public function testUnreadableRrdReturnsZeroRatherThanThrowing(): void {
		CactiStub::reset();
		CactiStub::willReturn('db_fetch_row_prepared', ['rrd_step' => 300]);
		CactiStub::willReturn('rrdtool_execute', '');
		$this->rrdReturns([]);

		$this->assertSame(0, get_current_value(4, 'traffic_in'));
	}

	/**
	 * @return void
	 */
	public function testValueIsRoundedToFourDecimals(): void {
		$this->rrdReturns([
			'data_source_names' => ['traffic_in'],
			'values'            => [['1700000000' => 1.23456789]],
		]);

		$this->assertSame(1.2346, get_current_value(4, 'traffic_in'));
	}
}
