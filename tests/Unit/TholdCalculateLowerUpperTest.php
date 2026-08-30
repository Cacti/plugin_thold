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
 * thold_calculate_lower_upper() reassembles a 64-bit counter that the device
 * reports as two 32-bit data sources: the threshold's own data source carries
 * the low word and upper_ds carries the high word.
 */
final class TholdCalculateLowerUpperTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @return void
	 */
	public function testHighWordIsShiftedAndCombinedWithTheLowWord(): void {
		$thold = ['upper_ds' => 'octets_hi', 'local_data_id' => 4];
		$rrd   = [4 => ['octets_hi' => 2]];

		$this->assertSame((2 << 32) + 100, thold_calculate_lower_upper($thold, 100, $rrd));
	}

	/**
	 * @return void
	 */
	public function testValuePassesThroughWhenTheHighWordIsAbsent(): void {
		$thold = ['upper_ds' => 'octets_hi', 'local_data_id' => 4];
		$rrd   = [4 => ['octets_lo' => 5]];

		$this->assertSame(100, thold_calculate_lower_upper($thold, 100, $rrd));
	}

	/**
	 * @return void
	 */
	public function testValuePassesThroughWhenTheDataSourceHasNoReadings(): void {
		$thold = ['upper_ds' => 'octets_hi', 'local_data_id' => 4];

		$this->assertSame(100, thold_calculate_lower_upper($thold, 100, []));
	}

	/**
	 * @return void
	 */
	public function testHighWordOfZeroLeavesTheValueUnchanged(): void {
		$thold = ['upper_ds' => 'octets_hi', 'local_data_id' => 4];
		$rrd   = [4 => ['octets_hi' => 0]];

		$this->assertSame(100, thold_calculate_lower_upper($thold, 100, $rrd));
	}
}
