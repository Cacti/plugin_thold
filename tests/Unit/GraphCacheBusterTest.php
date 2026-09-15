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
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

/**
 * The graph image cache-buster on the threshold edit page must never make
 * editing fatal. random_int() throws Random\RandomException on CSPRNG
 * failure; mt_rand() does not throw and is the correct tool for a
 * non-security cache-buster.
 */
final class GraphCacheBusterTest extends TestCase {
	/**
	 * @return void
	 */
	public function testMtRandDoesNotThrowAndProducesInteger(): void {
		// mt_rand() must not throw under any circumstance
		$value = mt_rand();

		$this->assertIsInt($value);
		$this->assertGreaterThan(0, $value);
	}

	/**
	 * @return void
	 */
	public function testMtRandProducesVaryingValuesAcrossCalls(): void {
		$values = [];

		for ($i = 0; $i < 100; $i++) {
			$values[] = mt_rand();
		}

		// At least two distinct values in 100 calls — cache-busting requires variation
		$this->assertGreaterThan(1, count(array_unique($values)));
	}

	/**
	 * The cache-buster is embedded in an HTML img src attribute via
	 * html_escape(). Confirm the value round-trips safely.
	 *
	 * @return void
	 */
	public function testCacheBusterValueIsHtmlSafe(): void {
		$value = mt_rand();

		$escaped = html_escape((string) $value);

		$this->assertSame((string) $value, $escaped);
	}
}