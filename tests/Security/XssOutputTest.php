<?php
declare(strict_types=1);

require_once __DIR__ . '/../Helpers/CactiStubs.php';

describe('XSS output escaping', function (): void {
	it('encodes HTML special characters before output', function (): void {
		$payload = '<script>alert(document.cookie)</script>';
		$escaped = html_escape($payload);
		expect($escaped)
			->not->toContain('<script>')
			->toContain('&lt;script&gt;');
	});

	it('encodes double quotes in attribute context', function (): void {
		$payload = '" onmouseover="alert(1)';
		$escaped = html_escape($payload);
		expect($escaped)->not->toContain('"');
	});

	it('encodes single quotes', function (): void {
		$payload = "' onmouseover='alert(1)";
		$escaped = html_escape($payload);
		expect($escaped)->not->toContain("'");
	});

	it('encodes ampersands', function (): void {
		$payload = '<img src=x onerror=alert(1)>&other';
		$escaped = html_escape($payload);
		expect($escaped)
			->not->toContain('<img')
			->toContain('&amp;');
	});
});

describe('SQL column name allowlist (setup.php thold_ / time_ interpolation)', function (): void {
	/*
	 * setup.php lines 345/350/386/391 interpolate $matches[1] directly into
	 * a column name: 'SELECT thold_' . $matches[1] . ' FROM thold_data ...'
	 * The regex /\|thold\:(hi|low)\:.../ and /\|thold\:(warning_hi|warning_low)\:/
	 * constrains the capture to a fixed set. Tests verify the allowlist logic
	 * that should be added to harden the call sites.
	 */

	it('accepts known thold column suffixes', function (): void {
		$allowed = ['hi', 'low', 'warning_hi', 'warning_low'];
		foreach ($allowed as $suffix) {
			expect(in_array($suffix, $allowed, true))->toBeTrue();
		}
	});

	it('rejects arbitrary strings that would escape the column name', function (): void {
		$malicious = "hi FROM dual; DROP TABLE thold_data; --";
		$allowed   = ['hi', 'low', 'warning_hi', 'warning_low'];
		expect(in_array($malicious, $allowed, true))->toBeFalse();
	});

	it('rejects empty string as column suffix', function (): void {
		$allowed = ['hi', 'low', 'warning_hi', 'warning_low'];
		expect(in_array('', $allowed, true))->toBeFalse();
	});
})->todo('Seam required: extract column-name resolution to a pure function in src/ so it can be called without the Cacti DB layer');

describe('notify_lists.php DELETE without prepared statement (line 484)', function (): void {
	/*
	 * db_execute('DELETE FROM plugin_thold_threshold_contact WHERE thold_id=' . $selected_items[$i])
	 * $selected_items comes from sanitize_unserialize_selected_items(), which validates integers,
	 * so exploitability is low. Test documents the safe pattern expectation.
	 */

	it('rejects non-integer thold_id values', function (): void {
		$raw = '1; DROP TABLE plugin_thold_threshold_contact; --';
		expect(is_numeric($raw))->toBeFalse();
	});

	it('accepts integer thold_id values', function (): void {
		expect(is_numeric('42'))->toBeTrue();
		expect(is_numeric(42))->toBeTrue();
	});
})->todo('Seam required: extract delete action to src/ function accepting int $tholdId to enable full unit coverage');
