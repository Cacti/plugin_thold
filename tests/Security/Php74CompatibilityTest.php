<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Verify plugin source files do not use PHP 8.0+ syntax.
 * Cacti 1.2.x plugins must remain compatible with PHP 7.4.
 */

function thold_security_compatibility_files() {
	return array(
		'includes/database.php',
		'includes/polling.php',
		'includes/settings.php',
		'notify_lists.php',
		'notify_queue.php',
		'poller_thold.php',
		'setup.php',
		'thold.php',
		'thold_graph.php',
	);
}

function thold_security_read_file($relativeFile) {
	$path = realpath(__DIR__ . '/../../' . $relativeFile);
	expect($path)->not->toBeFalse("Failed to resolve target file path: {$relativeFile}");

	$contents = file_get_contents($path);
	expect($contents)->not->toBeFalse("Failed to read target file: {$relativeFile}");

	return $contents;
}

it('does not use str_contains (PHP 8.0)', function () {
	foreach (thold_security_compatibility_files() as $relativeFile) {
		$contents = thold_security_read_file($relativeFile);

		expect(preg_match('/\bstr_contains\s*\(/', $contents))->toBe(0,
			"{$relativeFile} uses str_contains() which requires PHP 8.0"
		);
	}
});

it('does not use str_starts_with (PHP 8.0)', function () {
	foreach (thold_security_compatibility_files() as $relativeFile) {
		$contents = thold_security_read_file($relativeFile);

		expect(preg_match('/\bstr_starts_with\s*\(/', $contents))->toBe(0,
			"{$relativeFile} uses str_starts_with() which requires PHP 8.0"
		);
	}
});

it('does not use str_ends_with (PHP 8.0)', function () {
	foreach (thold_security_compatibility_files() as $relativeFile) {
		$contents = thold_security_read_file($relativeFile);

		expect(preg_match('/\bstr_ends_with\s*\(/', $contents))->toBe(0,
			"{$relativeFile} uses str_ends_with() which requires PHP 8.0"
		);
	}
});

it('does not use nullsafe operator (PHP 8.0)', function () {
	foreach (thold_security_compatibility_files() as $relativeFile) {
		$contents = thold_security_read_file($relativeFile);

		expect(preg_match('/\?->/', $contents))->toBe(0,
			"{$relativeFile} uses nullsafe operator which requires PHP 8.0"
		);
	}
});
