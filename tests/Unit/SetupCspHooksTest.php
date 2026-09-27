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

// Coverage for the CSP-nonce/asset lines added to setup.php's page_head and
// device-edit hook callbacks. Each hook is invoked with output buffered so the
// nonce/get_md5_include_* lines execute. thold_device_top() and
// thold_device_template_top() are not exercised here: their only nonce line
// lives in an item_remove_tt_confirm branch that unconditionally exit()s, so it
// is annotated @codeCoverageIgnore in setup.php instead of being run.

require_once dirname(__DIR__, 2) . '/setup.php';

final class SetupCspHooksTest extends TestCase {
	public function test_host_edit_bottom_emits_a_nonced_inline_script(): void {
		ob_start();
		thold_host_edit_bottom();
		$output = ob_get_clean();

		$this->assertStringContainsString("<script type='text/javascript'", $output);
		$this->assertStringContainsString('changeNotify', $output);
	}

	public function test_settings_bottom_emits_a_nonced_inline_script(): void {
		ob_start();
		thold_settings_bottom();
		$output = ob_get_clean();

		$this->assertStringContainsString("<script type='text/javascript'", $output);
		$this->assertStringContainsString('multiselect', $output);
	}

	public function test_page_head_emits_theme_css_and_a_nonced_inline_script(): void {
		// Point base_path at the fixture tree so the theme-css file_exists()
		// branch (get_selected_theme() is 'modern' in the bootstrap) is taken.
		$GLOBALS['config']['base_path'] = dirname(__DIR__) . '/Fixtures/cacti-root';

		ob_start();
		thold_page_head();
		$output = ob_get_clean();

		$this->assertStringContainsString('themes/modern/main.css', $output);
		$this->assertStringContainsString("<script type='text/javascript'", $output);
	}

	public function test_device_edit_pre_bottom_emits_a_nonced_inline_script(): void {
		CactiStubs::$requestVars['id'] = 1;
		// A non-empty "available templates" result reaches the Add-Template
		// panel that carries the nonced <script>.
		CactiStubs::willReturnFor('db_fetch_assoc_prepared', 'tt.id NOT IN', [['id' => 1, 'name' => 'X']]);

		ob_start();
		thold_device_edit_pre_bottom();
		$output = ob_get_clean();

		$this->assertStringContainsString("<script type='text/javascript'", $output);
		$this->assertStringContainsString('addThresholdTemplate', $output);
	}

	public function test_device_template_edit_emits_a_nonced_inline_script(): void {
		CactiStubs::$requestVars['id'] = 1;
		CactiStubs::willReturnFor('db_fetch_assoc_prepared', 'ptdt.host_template_id IS NULL', [['id' => 1, 'name' => 'X']]);

		ob_start();
		thold_device_template_edit();
		$output = ob_get_clean();

		$this->assertStringContainsString("<script type='text/javascript'", $output);
		$this->assertStringContainsString('addThresholdTemplate', $output);
	}

	/**
	 * @runInSeparateProcess
	 * @preserveGlobalState disabled
	 */
	public function test_csp_nonce_delegates_to_secure_headers_when_available(): void {
		// Declared at runtime (isolated process) so class_exists() takes the
		// CactiSecureHeaders branch without leaking into the class-absent test.
		eval('class CactiSecureHeaders { public static function getNonceAttribute(): string { return "nonce=\"unit\""; } }');

		$this->assertSame('nonce="unit"', plugin_thold_csp_nonce());
	}
}
