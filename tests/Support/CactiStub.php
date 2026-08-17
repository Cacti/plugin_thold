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
 * Recording, programmable stand-in for the Cacti framework functions that
 * thold calls.
 *
 * The plugin is procedural and reaches the database through global db_*
 * helpers, so there is no seam to inject a double into. This class is that
 * seam: tests/bootstrap.php defines each Cacti function as a thin shim over
 * the static state kept here.
 *
 * A test programs the responses it needs, exercises plugin code, then
 * asserts over the recorded call log. Every test case must call reset() first
 * (tests/TestCase.php does it in setUp()) because the state is static.
 */
final class CactiStub {
	/**
	 * Every Cacti function call the plugin made, in order.
	 *
	 * @var array<int, array{fn: string, sql: string, params: array<int, mixed>}>
	 */
	public static $calls = [];

	/**
	 * Queued return values, keyed by function name. Each call shifts one value
	 * off the front; an exhausted queue falls back to the type default.
	 *
	 * @var array<string, array<int, mixed>>
	 */
	public static $returns = [];

	/**
	 * Values handed back by the get_*_request_var() family, keyed by var name.
	 *
	 * @var array<string, mixed>
	 */
	public static $requestVars = [];

	/**
	 * Values handed back by read_config_option(), keyed by option name.
	 *
	 * @var array<string, mixed>
	 */
	public static $configOptions = [];

	/**
	 * Messages passed to cacti_log(), in order.
	 *
	 * @var array<int, string>
	 */
	public static $log = [];

	/**
	 * Clear all recorded and programmed state.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$calls         = [];
		self::$returns       = [];
		self::$requestVars   = [];
		self::$configOptions = [];
		self::$log           = [];
	}

	/**
	 * Record one Cacti function call.
	 *
	 * @param string            $fn     Cacti function name.
	 * @param string            $sql    SQL text, or '' for non-query calls.
	 * @param array<int, mixed> $params Bound parameters, if any.
	 *
	 * @return void
	 */
	public static function record($fn, $sql = '', array $params = []) {
		self::$calls[] = ['fn' => $fn, 'sql' => $sql, 'params' => $params];
	}

	/**
	 * Queue one return value for the next call to $fn.
	 *
	 * @param string $fn    Cacti function name.
	 * @param mixed  $value Value to hand back.
	 *
	 * @return void
	 */
	public static function willReturn($fn, $value) {
		self::$returns[$fn][] = $value;
	}

	/**
	 * Take the next queued return value for $fn, or $default when none is left.
	 *
	 * @param string $fn      Cacti function name.
	 * @param mixed  $default Fallback when the queue is empty.
	 *
	 * @return mixed
	 */
	public static function nextReturn($fn, $default) {
		if (!empty(self::$returns[$fn])) {
			return array_shift(self::$returns[$fn]);
		}

		return $default;
	}

	/**
	 * All recorded calls to $fn.
	 *
	 * @param string $fn Cacti function name.
	 *
	 * @return array<int, array{fn: string, sql: string, params: array<int, mixed>}>
	 */
	public static function callsTo($fn) {
		return array_values(array_filter(self::$calls, function ($call) use ($fn) {
			return $call['fn'] === $fn;
		}));
	}

	/**
	 * The recorded call log reduced to function names, in order. Useful for
	 * asserting transaction sequencing.
	 *
	 * @return array<int, string>
	 */
	public static function callSequence() {
		return array_column(self::$calls, 'fn');
	}
}
