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
 * thold_calculate_percent() expresses the current reading as a percentage of
 * a second data source on the same RRD.
 *
 * An empty string is the engine's "no usable value" sentinel; returning 0
 * instead would be compared against the bounds as a real reading.
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
		return array('percent_ds' => 'total', 'local_data_id' => 4);
	}

	/**
	 * @return void
	 */
	public function testReadingIsExpressedAsAPercentageOfTheReferenceDataSource(): void {
		$rrd = array(4 => array('total' => 200));

		$this->assertSame(25.0, thold_calculate_percent($this->threshold(), 50, $rrd));
	}

	/**
	 * @return void
	 */
	public function testNonNumericReadingYieldsTheNoValueSentinel(): void {
		$rrd = array(4 => array('total' => 200));

		$this->assertSame('', thold_calculate_percent($this->threshold(), 'U', $rrd));
	}

	/**
	 * @return void
	 */
	public function testMissingReferenceDataSourceYieldsTheNoValueSentinel(): void {
		$rrd = array(4 => array('other' => 200));

		$this->assertSame('', thold_calculate_percent($this->threshold(), 50, $rrd));
	}

	/**
	 * @return void
	 */
	public function testZeroReferenceYieldsZeroRatherThanDividingByZero(): void {
		$rrd = array(4 => array('total' => 0));

		$this->assertSame(0, thold_calculate_percent($this->threshold(), 50, $rrd));
	}

	/**
	 * @return void
	 */
	public function testNegativeReferenceYieldsZero(): void {
		$rrd = array(4 => array('total' => -5));

		$this->assertSame(0, thold_calculate_percent($this->threshold(), 50, $rrd));
	}
}
