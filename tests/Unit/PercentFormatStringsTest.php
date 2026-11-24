<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression coverage for percent format specifiers (%% instead of %%%) in
 * includes/functions.php, includes/arrays.php, and setup.php. Under PHP 8,
 * triple percent signs inside __() / __esc() format strings either throw
 * ValueError or consume the trailing text domain argument.
 */

beforeAll(function () {
	require_once __DIR__ . '/../../setup.php';
	require_once __DIR__ . '/../../includes/functions.php';
});

it('formats baseline AOT deviation label with valid percent escaping', function () {
	expect(get_bl_type('5', 'MAX'))->toBe('% Dev [AOT:MAX]');
	expect(get_bl_type('1', 'MAX'))->toBe('% Dev [TIP:MAX]');
});

it('builds percentage notification pause labels with valid percent escaping', function () {
	global $config;

	CactiStubs::$configOptions['poller_interval'] = 300;

	require __DIR__ . '/../../includes/arrays.php';

	expect($notification_pause_values['50|peg'])->toBe('> 50 % of Devices Globally');
	expect($notification_pause_values['50|pes'])->toBe('> 50 % of Devices in a Site');
});

it('renders percentage HRULE graph legends for hi/low and time-based thresholds', function () {
	$baseRow = [
		'id'                  => 1,
		'local_graph_id'      => 10,
		'name_cache'          => 'CPU Usage',
		'data_type'           => '2',
		'percent_ds'          => 'cpu',
		'cdef'                => 0,
		'show_units'          => 0,
		'skipscale'           => 'off',
		'thold_hrule_alert'   => 1,
		'thold_hrule_warning' => 2,
	];

	$hiLow = array_merge($baseRow, [
		'thold_type'       => '0',
		'thold_hi'         => '90',
		'thold_low'        => '10',
		'thold_warning_hi' => '80',
		'thold_warning_low' => '20',
	]);

	$timeBased = array_merge($baseRow, [
		'id'               => 2,
		'thold_type'       => '2',
		'time_hi'          => '95',
		'time_low'         => '5',
		'time_warning_hi'  => '85',
		'time_warning_low' => '15',
	]);

	CactiStubs::$matchedReturns['db_fetch_assoc_prepared'] = [
		['FROM thold_data', [$hiLow, $timeBased]],
	];
	CactiStubs::$matchedReturns['db_fetch_cell_prepared'] = [
		['FROM graph_templates_graph', 1000],
		['FROM colors', 'FF0000'],
	];

	$graph = [
		'graph_id'        => 10,
		'start'           => -3600,
		'end'             => -1,
		'graph_defs'      => "DEF:a='/var/www/rra/host_cpu_10.rrd':'cpu':AVERAGE \\\n",
		'txt_graph_items' => '',
	];

	$result = thold_rrd_graph_graph_options($graph);

	expect($result['txt_graph_items'])
		->toContain('Alert Hi for CPU Usage (90 %)')
		->toContain('Alert Low for CPU Usage (10 %)')
		->toContain('Warning Hi for CPU Usage (80 %)')
		->toContain('Warning Low for CPU Usage (20 %)')
		->toContain('Alert Hi for CPU Usage (95 %)')
		->toContain('Alert Low for CPU Usage (5 %)')
		->toContain('Warning Hi for CPU Usage (85 %)')
		->toContain('Warning Low for CPU Usage (15 %)');
});
