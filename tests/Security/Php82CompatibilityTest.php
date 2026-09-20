<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Verify plugin source files do not use PHP 8.3+ syntax.
 * Cacti 1.2.x plugins must remain compatible with PHP 8.2.
 */

// Discovered recursively so new production PHP files are covered automatically.
$pluginRoot = realpath(__DIR__ . '/../..');
$files      = array();

$iterator = new RecursiveIteratorIterator(
	new RecursiveDirectoryIterator($pluginRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
	if ($file->getExtension() !== 'php') {
		continue;
	}

	$relativeFile = ltrim(str_replace($pluginRoot, '', $file->getPathname()), DIRECTORY_SEPARATOR);
	$relativeFile = str_replace(DIRECTORY_SEPARATOR, '/', $relativeFile);

	if (strpos($relativeFile, 'tests/') === 0) {
		continue;
	}

	$files[] = $relativeFile;
}

sort($files);

it('does not use typed class constants (PHP 8.3)', function () use ($files) {
	foreach ($files as $relativeFile) {
		$contents = plugin_test_read_source($relativeFile);

		expect(preg_match('/\bconst\s+(?:int|string|float|bool|array|self|static|mixed)\s+[A-Z_][A-Za-z0-9_]*\s*=/', $contents))->toBe(0,
			"{$relativeFile} uses typed class constants which require PHP 8.3"
		);
	}
});

it('does not use json_validate (PHP 8.3)', function () use ($files) {
	foreach ($files as $relativeFile) {
		$contents = plugin_test_read_source($relativeFile);

		expect(preg_match('/\bjson_validate\s*\(/', $contents))->toBe(0,
			"{$relativeFile} uses json_validate() which requires PHP 8.3"
		);
	}
});

it('does not use the #[Override] attribute (PHP 8.3)', function () use ($files) {
	foreach ($files as $relativeFile) {
		$contents = plugin_test_read_source($relativeFile);

		expect(preg_match('/#\[\s*Override\s*\]/i', $contents))->toBe(0,
			"{$relativeFile} uses the #[Override] attribute which requires PHP 8.3"
		);
	}
});

it('does not use array_find, array_any, or array_all (PHP 8.4)', function () use ($files) {
	foreach ($files as $relativeFile) {
		$contents = plugin_test_read_source($relativeFile);

		expect(preg_match('/\barray_(?:find|any|all)\s*\(/', $contents))->toBe(0,
			"{$relativeFile} uses array_find()/array_any()/array_all() which require PHP 8.4"
		);
	}
});

it('does not use asymmetric visibility (PHP 8.4)', function () use ($files) {
	foreach ($files as $relativeFile) {
		$contents = plugin_test_read_source($relativeFile);

		expect(preg_match('/\bpublic\(set\)|\bprotected\(set\)|\bprivate\(set\)/', $contents))->toBe(0,
			"{$relativeFile} uses asymmetric visibility which requires PHP 8.4"
		);
	}
});

it('does not use the #[Deprecated] attribute (PHP 8.4)', function () use ($files) {
	foreach ($files as $relativeFile) {
		$contents = plugin_test_read_source($relativeFile);

		expect(preg_match('/#\[\s*Deprecated\b/i', $contents))->toBe(0,
			"{$relativeFile} uses the #[Deprecated] attribute which requires PHP 8.4"
		);
	}
});
