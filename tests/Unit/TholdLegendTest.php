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
 * Rendering of the three status legends: thold_legend(), host_legend() and
 * log_legend().
 *
 * Each renderer walks its state-definition global and prints one
 * .tholdLegendItem chip per state, carrying the state's own CSS class and its
 * display label. The state arrays live in includes/arrays.php; html_start_box()
 * and html_end_box() are no-op stubs from the bootstrap, so only the chip
 * markup reaches the output buffer.
 */
final class TholdLegendTest extends TestCase {
	/**
	 * @return void
	 */
	public static function setUpBeforeClass(): void {
		self::loadPluginSource('thold_functions.php');

		// Publishes $thold_states, $thold_host_states and $thold_log_states.
		self::loadPluginSourceAlways('includes/arrays.php');
	}

	/**
	 * @return void
	 */
	public function test_thold_legend_renders_one_chip_per_threshold_state(): void {
		ob_start();
		thold_legend();
		$output = ob_get_clean();

		$states = $GLOBALS['thold_states'];

		$this->assertStringContainsString('<div class="tholdLegend">', $output);
		$this->assertSame(count($states), substr_count($output, 'tholdLegendItem'));

		foreach ($states as $state) {
			$this->assertStringContainsString('<div class="tholdLegendItem ' . $state['class'] . '">' . $state['display'] . '</div>', $output);
		}
	}

	/**
	 * @return void
	 */
	public function test_host_legend_renders_one_chip_per_device_state(): void {
		ob_start();
		host_legend();
		$output = ob_get_clean();

		$states = $GLOBALS['thold_host_states'];

		$this->assertStringContainsString('<div class="tholdLegend">', $output);
		$this->assertSame(count($states), substr_count($output, 'tholdLegendItem'));

		foreach ($states as $state) {
			$this->assertStringContainsString('<div class="tholdLegendItem ' . $state['class'] . '">' . $state['display'] . '</div>', $output);
		}
	}

	/**
	 * @return void
	 */
	public function test_log_legend_renders_one_chip_per_log_state_using_short_labels(): void {
		ob_start();
		log_legend();
		$output = ob_get_clean();

		$states = $GLOBALS['thold_log_states'];

		$this->assertStringContainsString('<div class="tholdLegend">', $output);
		$this->assertSame(count($states), substr_count($output, 'tholdLegendItem'));

		foreach ($states as $state) {
			$this->assertStringContainsString('<div class="tholdLegendItem ' . $state['class'] . '">' . $state['display_short'] . '</div>', $output);
		}
	}
}
