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
 * What one poll of thold_check_threshold() emitted.
 *
 * Reads the recorded calls rather than the SQL text wherever it can, so a
 * reworded query does not break a test that is about behaviour.
 */
final class ThresholdOutcome {
	/**
	 * The threshold row as the function left it.
	 *
	 * @var array<string, mixed>
	 */
	public $thold;

	/**
	 * @param array<string, mixed> $thold
	 */
	public function __construct(array $thold) {
		$this->thold = $thold;
	}

	/**
	 * Subject lines of the mail that was sent, in order.
	 *
	 * @return array<int, string>
	 */
	public function subjects() {
		return array_column(CactiStub::$mail, 'subject');
	}

	/**
	 * Recipients of the mail that was sent, in order.
	 *
	 * @return array<int, string>
	 */
	public function recipients() {
		return array_column(CactiStub::$mail, 'to');
	}

	/**
	 * @return int
	 */
	public function mailCount() {
		return count(CactiStub::$mail);
	}

	/**
	 * Status codes written to plugin_thold_log, in order.
	 *
	 * The log row goes through sql_save(), so the status is available as data
	 * rather than having to be parsed back out of a query.
	 *
	 * @return array<int, int>
	 */
	public function logStatuses() {
		$statuses = [];

		foreach (CactiStub::callsTo('sql_save') as $call) {
			if ($call['sql'] === 'plugin_thold_log' && isset($call['params']['status'])) {
				$statuses[] = (int) $call['params']['status'];
			}
		}

		return $statuses;
	}

	/**
	 * @return int
	 */
	public function trapCount() {
		return count(CactiStub::callsTo('cacti_snmp_send'));
	}

	/**
	 * Whether the run marked the threshold as having changed state.
	 *
	 * @return bool
	 */
	public function touchedLastChanged() {
		foreach (CactiStub::callsTo('db_execute_prepared') as $call) {
			if (strpos($call['sql'], 'lastchanged = NOW()') !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Whether the run set the acknowledgment flag.
	 *
	 * @return bool
	 */
	public function acknowledged() {
		foreach (CactiStub::callsTo('db_execute_prepared') as $call) {
			if (strpos($call['sql'], 'acknowledgment = "on"') !== false) {
				return true;
			}
		}

		return false;
	}

	/**
	 * Columns the run wrote to thold_data, resolved to their values.
	 *
	 * The statements mix placeholders and literals in the same SET clause, so
	 * the clause is parsed and each "?" resolved against the bound parameters
	 * in order. Returns the merge of every such statement, later writes last.
	 *
	 * @return array<string, string>
	 */
	public function persistedColumns() {
		$columns = [];

		foreach (CactiStub::callsTo('db_execute_prepared') as $call) {
			if (strpos($call['sql'], 'UPDATE thold_data') === false) {
				continue;
			}

			if (!preg_match('/SET\s+(.*?)\s+WHERE/s', $call['sql'], $clause)) {
				continue;
			}

			$position = 0;

			foreach (explode(',', $clause[1]) as $assignment) {
				$parts = explode('=', $assignment, 2);

				if (count($parts) !== 2) {
					continue;
				}

				$name  = trim($parts[0]);
				$value = trim($parts[1]);

				if ($value === '?') {
					$value = isset($call['params'][$position]) ? (string) $call['params'][$position] : '';
					$position++;
				}

				$columns[$name] = trim($value, '"\'');
			}
		}

		return $columns;
	}

	/**
	 * The alert state the run persisted, or null when it wrote none.
	 *
	 * @return int|null
	 */
	public function persistedAlertState() {
		$columns = $this->persistedColumns();

		return isset($columns['thold_alert']) ? (int) $columns['thold_alert'] : null;
	}

	/**
	 * The fail counts the run persisted, or null when it wrote neither.
	 *
	 * @return array{alert: int|null, warning: int|null}|null
	 */
	public function persistedFailCounts() {
		$columns = $this->persistedColumns();

		if (!isset($columns['thold_fail_count']) && !isset($columns['thold_warning_fail_count'])) {
			return null;
		}

		return [
			'alert'   => isset($columns['thold_fail_count']) ? (int) $columns['thold_fail_count'] : null,
			'warning' => isset($columns['thold_warning_fail_count']) ? (int) $columns['thold_warning_fail_count'] : null,
		];
	}

	/**
	 * Whether the run did nothing at all beyond reading.
	 *
	 * @return bool
	 */
	public function isSilent() {
		return $this->mailCount()                         === 0
			&& $this->logStatuses()                          === []
			&& $this->trapCount()                            === 0
			&& CactiStub::callsTo('thold_command_execution') === [];
	}
}
