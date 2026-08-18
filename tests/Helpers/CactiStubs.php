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
final class CactiStubs {
	/**
	 * Every Cacti function call the plugin made, in order.
	 *
	 * @var array<int, array{fn: string, sql: string, params: array<int|string, mixed>}>
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
	 * Return values chosen by a fragment of the SQL, keyed by function name.
	 * Each entry is [fragment, value]. Consulted before $returns.
	 *
	 * @var array<string, array<int, array{0: string, 1: mixed}>>
	 */
	public static $matchedReturns = [];

	/**
	 * Values handed back on every call, keyed by function name. Consulted last.
	 *
	 * @var array<string, mixed>
	 */
	public static $stickyReturns = [];

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
	 * Mail handed to Cacti's mailer(), in order.
	 *
	 * @var array<int, array{to: string, bcc: string, subject: string}>
	 */
	public static $mail = [];

	/**
	 * Clear all recorded and programmed state.
	 *
	 * @return void
	 */
	public static function reset() {
		self::$calls          = [];
		self::$returns        = [];
		self::$matchedReturns = [];
		self::$stickyReturns  = [];
		self::$requestVars    = [];
		self::$configOptions  = [];
		self::$log            = [];
		self::$mail           = [];
	}

	/**
	 * Record one Cacti function call.
	 *
	 * @param string                   $fn     Cacti function name.
	 * @param string                   $sql    SQL text, or '' for non-query calls.
	 * @param array<int|string, mixed> $params Bound parameters, if any.
	 *
	 * @return void
	 */
	public static function record($fn, $sql = '', array $params = []) {
		self::$calls[] = ['fn' => $fn, 'sql' => $sql, 'params' => $params];
	}

	/**
	 * Hand back $value for every call to $fn.
	 *
	 * @param string $fn    Cacti function name.
	 * @param mixed  $value Value to hand back.
	 *
	 * @return void
	 */
	public static function willAlwaysReturn($fn, $value) {
		self::$stickyReturns[$fn] = $value;
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
	 * Answer any call to $fn whose SQL contains $fragment with $value.
	 *
	 * A function such as db_fetch_cell_prepared is called many times with
	 * different queries in one run, so a positional queue would break as soon
	 * as the code under test reordered a lookup. Matching on the query keeps
	 * the fixture readable and stable.
	 *
	 * @param string $fn       Cacti function name.
	 * @param string $fragment Distinctive substring of the SQL.
	 * @param mixed  $value    Value to hand back, or a callable taking the SQL
	 *                         and returning it.
	 *
	 * @return void
	 */
	public static function willReturnFor($fn, $fragment, $value) {
		self::$matchedReturns[$fn][] = [$fragment, $value];
	}

	/**
	 * Take the return value for a call: a SQL match first, then the queue, then
	 * the type default.
	 *
	 * @param string $fn      Cacti function name.
	 * @param mixed  $default Fallback when nothing matches.
	 * @param string $sql     SQL the caller passed, for matching.
	 *
	 * @return mixed
	 */
	public static function nextReturn($fn, $default, $sql = '') {
		if ($sql !== '' && !empty(self::$matchedReturns[$fn])) {
			$flat = preg_replace('/\s+/', ' ', $sql);

			foreach (self::$matchedReturns[$fn] as $entry) {
				if (strpos($flat, preg_replace('/\s+/', ' ', $entry[0])) !== false) {
					// A callable answers per query, for a lookup whose result
					// depends on the values the caller asked about.
					return is_callable($entry[1]) ? ($entry[1])($sql) : $entry[1];
				}
			}
		}

		if (!empty(self::$returns[$fn])) {
			return array_shift(self::$returns[$fn]);
		}

		if (array_key_exists($fn, self::$stickyReturns)) {
			return self::$stickyReturns[$fn];
		}

		return $default;
	}

	/**
	 * All recorded calls to $fn.
	 *
	 * @param string $fn Cacti function name.
	 *
	 * @return array<int, array{fn: string, sql: string, params: array<int|string, mixed>}>
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
