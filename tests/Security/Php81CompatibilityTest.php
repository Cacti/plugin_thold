<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Verify selected plugin files stay compatible with the repo's PHP 8.1
 * baseline by avoiding newer PHP 8.2+ and 8.3+ syntax/features.
 */

describe('PHP 8.1 compatibility in thold', function () {
	$files = array(
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

	it('does not use readonly classes (PHP 8.2)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);
			expect($path)->not->toBeFalse("Failed to resolve target file path: {$relativeFile}");

			$contents = file_get_contents($path);
			expect($contents)->not->toBeFalse("Failed to read target file: {$relativeFile}");

			expect(preg_match('/\breadonly\s+class\b/', $contents))->toBe(0,
				"{$relativeFile} uses readonly class syntax which requires PHP 8.2"
			);
		}
	});

	it('does not use json_validate (PHP 8.3)', function () use ($files) {
		foreach ($files as $relativeFile) {
			$path = realpath(__DIR__ . '/../../' . $relativeFile);
			expect($path)->not->toBeFalse("Failed to resolve target file path: {$relativeFile}");

			$contents = file_get_contents($path);
			expect($contents)->not->toBeFalse("Failed to read target file: {$relativeFile}");

			expect(preg_match('/\bjson_validate\s*\(/', $contents))->toBe(0,
				"{$relativeFile} uses json_validate() which requires PHP 8.3"
			);
		}
	});
});
