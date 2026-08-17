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
 * Base class for thold tests.
 *
 * CactiStubs keeps its state in statics because the plugin reaches the
 * framework through global functions, so every test has to start from a clean
 * slate. $rpn_error is reset for the same reason: the RPN evaluators latch it
 * globally and a stale true would suppress evaluation in the next test.
 */
abstract class TestCase extends PHPUnit\Framework\TestCase {
	/**
	 * @return void
	 */
	protected function setUp(): void {
		parent::setUp();

		CactiStubs::reset();
		$GLOBALS['rpn_error'] = false;
	}

	/**
	 * Load a plugin source file once per process.
	 *
	 * Requiring it repeatedly would redeclare its functions, so the include
	 * guard has to live here rather than in each test.
	 *
	 * @param string $file File name relative to the plugin root.
	 *
	 * @return void
	 */
	protected static function loadPluginSource($file) {
		thold_test_load(dirname(__DIR__) . '/' . $file);
	}

	/**
	 * Define the plugin's own constants by running the function that owns them.
	 *
	 * ST_*, STAT_* and THOLD_SEVERITY_* are defined inside
	 * thold_config_insert() rather than at file scope, so loading
	 * includes/settings.php is not enough. Calling the real function keeps the
	 * values from drifting out of sync with a copy.
	 *
	 * @return void
	 */
	protected static function loadPluginConstants() {
		self::loadPluginSource('includes/settings.php');

		thold_config_insert();
	}
}
