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
 * The pair that moves a threshold bound between what an operator types and
 * what is stored.
 *
 * They have to be inverses. A threshold is read out of the database, rendered
 * into the form, and written back on every save, so any disagreement between
 * them compounds each time the form is opened.
 */
final class TholdUnitSuffixTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @return array<string, array{0: string, 1: float}>
	 */
	public static function suffixProvider() {
		return [
			'yocto' => ['5y', 5.0e-24],
			'zepto' => ['5z', 5.0e-21],
			'atto'  => ['5a', 5.0e-18],
			'femto' => ['5f', 5.0e-15],
			'pico'  => ['5p', 5.0e-12],
			'nano'  => ['5n', 5.0e-9],
			'micro' => ['5u', 5.0e-6],
			'milli' => ['5m', 5.0e-3],
			'kilo'  => ['5K', 5.0e3],
			'mega'  => ['5M', 5.0e6],
			'giga'  => ['5G', 5.0e9],
			'tera'  => ['5T', 5.0e12],
			'peta'  => ['5P', 5.0e15],
			'exa'   => ['5E', 5.0e18],
			'zetta' => ['5Z', 5.0e21],
			'yotta' => ['5Y', 5.0e24],
		];
	}

	/**
	 * @dataProvider suffixProvider
	 *
	 * @param string $typed
	 * @param float  $stored
	 *
	 * @return void
	 */
	public function testEachSuffixScalesByItsSiFactor($typed, $stored): void {
		$this->assertEqualsWithDelta($stored, thold_display_to_raw($typed, 'thold_hi'), abs($stored) * 1.0e-9);
	}

	/**
	 * @dataProvider suffixProvider
	 *
	 * @param string $typed
	 * @param float  $stored
	 *
	 * @return void
	 */
	public function testEachStoredValueRendersWithItsSiSuffix($typed, $stored): void {
		$this->assertSame($typed, thold_raw_to_display($stored));
	}

	/**
	 * Opening a threshold and saving it again must not change it. This is the
	 * failure that mattered: a value stored at 1e-12 rendered as 5f, which
	 * parsed back as 1e-15, so every visit to the form divided it by a
	 * thousand.
	 *
	 * @dataProvider suffixProvider
	 *
	 * @param string $typed
	 * @param float  $stored
	 *
	 * @return void
	 */
	public function testAValueSurvivesBeingDisplayedAndReEntered($typed, $stored): void {
		$round_tripped = thold_display_to_raw(thold_raw_to_display($stored), 'thold_hi');

		$this->assertEqualsWithDelta($stored, $round_tripped, abs($stored) * 1.0e-9);
	}

	/**
	 * @return void
	 */
	public function testAPlainNumberIsLeftAlone(): void {
		$this->assertSame('42', thold_display_to_raw('42', 'thold_hi'));
		$this->assertSame('42', thold_raw_to_display(42));
	}

	/**
	 * @return void
	 */
	public function testZeroIsLeftAlone(): void {
		$this->assertSame('0', thold_raw_to_display(0));
	}

	/**
	 * @return void
	 */
	public function testNegativeValuesKeepTheirSign(): void {
		$this->assertSame('-5K', thold_raw_to_display(-5000));
		$this->assertEqualsWithDelta(-5000, thold_display_to_raw('-5K', 'thold_hi'), 1.0e-6);
	}

	/**
	 * @return array<string, array{0: string}>
	 */
	public static function rejectedInputProvider() {
		return [
			'unknown suffix' => ['5x'],
			'letters only'   => ['abc'],
			'empty'          => [''],
		];
	}

	/**
	 * @dataProvider rejectedInputProvider
	 *
	 * @param string $typed
	 *
	 * @return void
	 */
	public function testUnusableInputIsRejectedAndFlagged($typed): void {
		$this->assertFalse(thold_display_to_raw($typed, 'thold_hi'));
		$this->assertArrayHasKey('thold_hi', $_SESSION['sess_error_fields']);
	}

	/**
	 * @return void
	 */
	public function testNonNumericInputHasNoDisplayForm(): void {
		$this->assertFalse(thold_raw_to_display('abc'));
	}

	/**
	 * Beyond the largest and smallest suffix there is nothing left to index,
	 * and the old code read past the end of the pattern and dropped the
	 * magnitude entirely.
	 *
	 * @return void
	 */
	public function testMagnitudesBeyondTheLargestSuffixKeepTheirScale(): void {
		$rendered = thold_raw_to_display(5.0e27);

		$this->assertNotSame('5', $rendered);
		$this->assertEqualsWithDelta(5.0e27, (float) thold_display_to_raw($rendered, 'thold_hi'), 5.0e18);
	}

	/**
	 * @return void
	 */
	public function testMagnitudesBelowTheSmallestSuffixKeepTheirScale(): void {
		$rendered = thold_raw_to_display(5.0e-18);

		$this->assertNotSame('5', $rendered);
		$this->assertEqualsWithDelta(5.0e-18, (float) thold_display_to_raw($rendered, 'thold_hi'), 5.0e-27);
	}
}
