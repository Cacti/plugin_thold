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
 * thold_calculate_percent() expresses a reading as a percentage of a second
 * data source on the same RRD.
 *
 * An empty string is the engine's "no usable value" sentinel; zero is a real
 * percentage and is compared against the bounds as one.
 */
final class TholdCalculatePercentTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @return array<string, mixed>
	 */
	private function threshold() {
		return ['percent_ds' => 'total', 'local_data_id' => 4];
	}

	/**
	 * @param float|int|string $denominator
	 * @param float|int|string $reading
	 *
	 * @return mixed
	 */
	private function percent($denominator, $reading = 50) {
		return thold_calculate_percent($this->threshold(), $reading, [4 => ['total' => $denominator]]);
	}

	/**
	 * @return void
	 */
	public function testReadingIsExpressedAsAPercentageOfTheReference(): void {
		$this->assertEqualsWithDelta(25, $this->percent(200), 1.0e-9);
	}

	/**
	 * A denominator below one used to truncate to zero, forcing the result to
	 * zero and keeping any configured low threshold in permanent breach.
	 *
	 * @return void
	 */
	public function testFractionalDenominatorIsNotTruncated(): void {
		$this->assertEqualsWithDelta(1000, $this->percent(0.5, 5), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testNegativeDenominatorGivesANegativePercentage(): void {
		$this->assertEqualsWithDelta(-25, $this->percent(-200), 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testZeroDenominatorGivesZeroRatherThanDividingByZero(): void {
		$this->assertSame(0, $this->percent(0));
	}

	/**
	 * @return void
	 */
	public function testNonNumericDenominatorGivesZero(): void {
		$this->assertSame(0, $this->percent('U'));
	}

	/**
	 * @return void
	 */
	public function testNonNumericReadingYieldsTheNoValueSentinel(): void {
		$this->assertSame('', $this->percent(200, 'U'));
	}

	/**
	 * @return void
	 */
	public function testMissingReferenceDataSourceYieldsTheNoValueSentinel(): void {
		$this->assertSame('', thold_calculate_percent($this->threshold(), 50, [4 => ['other' => 200]]));
	}
}
