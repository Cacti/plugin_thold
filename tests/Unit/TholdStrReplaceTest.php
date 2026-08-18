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
 * thold_str_replace() performs every tag substitution in notification bodies,
 * subjects and trigger commands.
 *
 * It exists to turn an absent value into an empty string rather than printing
 * the word "null". Zero is a real reading and has to survive.
 */
final class TholdStrReplaceTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');
	}

	/**
	 * @return array<string, array{0: mixed, 1: string}>
	 */
	public static function preservedValueProvider() {
		return [
			'integer zero'        => [0, 'v=0'],
			'string zero'         => ['0', 'v=0'],
			'float zero'          => [0.0, 'v=0'],
			'negative'            => [-5, 'v=-5'],
			'positive integer'    => [5, 'v=5'],
			'float'               => [2.5, 'v=2.5'],
			'string zero decimal' => ['0.0', 'v=0.0'],
		];
	}

	/**
	 * @dataProvider preservedValueProvider
	 *
	 * @param mixed  $replace
	 * @param string $expected
	 *
	 * @return void
	 */
	public function testNumericValuesSurviveSubstitution($replace, $expected): void {
		$this->assertSame($expected, thold_str_replace('<X>', $replace, 'v=<X>'));
	}

	/**
	 * @return array<string, array{0: mixed}>
	 */
	public static function absentValueProvider() {
		return [
			'null'         => [null],
			'false'        => [false],
			'empty string' => [''],
		];
	}

	/**
	 * @dataProvider absentValueProvider
	 *
	 * @param mixed $replace
	 *
	 * @return void
	 */
	public function testAbsentValuesBecomeEmpty($replace): void {
		$this->assertSame('v=', thold_str_replace('<X>', $replace, 'v=<X>'));
	}

	/**
	 * @return void
	 */
	public function testEveryOccurrenceIsReplaced(): void {
		$this->assertSame('0 and 0', thold_str_replace('<X>', 0, '<X> and <X>'));
	}

	/**
	 * @return void
	 */
	public function testSubjectWithoutTheTagIsUnchanged(): void {
		$this->assertSame('no tags here', thold_str_replace('<X>', 5, 'no tags here'));
	}

	/**
	 * @return void
	 */
	public function testNullableInputsAreNormalizedAtTheBoundary(): void {
		$this->assertSame('', thold_str_replace('<X>', 'value', null));
		$this->assertSame('subject', thold_str_replace(null, 'value', 'subject'));
		$this->assertSame('', thold_str_replace(null, null, null));
	}
}
