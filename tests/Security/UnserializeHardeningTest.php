<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression tests for PHP object injection via unsafe unserialize on POST data.
 *
 * selected_graphs_array is a nested wizard structure (cg/sg keys), not a flat
 * ID list. sanitize_unserialize_selected_items() rejects nested arrays and must
 * not be used for this payload.
 */

it('thold_webapi.php does not use flat sanitize_unserialize_selected_items for wizard graphs', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../thold_webapi.php'));
	expect($src)->not->toContain("sanitize_unserialize_selected_items(get_nfilter_request_var('selected_graphs_array')");
});

it('thold_webapi.php deserializes selected_graphs_array without object injection', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../thold_webapi.php'));
	// Prefer the graphs-specific sanitizer, or allowed_classes => false unserialize.
	$uses_graphs_helper = str_contains($src, 'sanitize_unserialize_selected_graphs');
	$uses_safe_unserialize = str_contains($src, "allowed_classes' => false")
		|| str_contains($src, 'cacti_unserialize');
	expect($uses_graphs_helper || $uses_safe_unserialize)->toBeTrue();
});

it('thold_webapi.php rejects non-array selected_graphs_array before foreach', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../thold_webapi.php'));
	expect($src)->toContain('if (!is_array($selected_graphs_array))');
});

it('nested wizard payload shape is accepted by the graphs sanitizer when present', function () {
	$payload = serialize([
		'cg' => [1 => [1 => true]],
		'sg' => [2 => [3 => [4 => true]]],
	]);

	if (function_exists('sanitize_unserialize_selected_graphs')) {
		$result = sanitize_unserialize_selected_graphs($payload);
		expect($result)->toBeArray();
		expect($result)->toHaveKey('cg');
		expect($result)->toHaveKey('sg');
	} else {
		// Fallback path used on older Cacti without the graphs helper.
		$unstripped = stripslashes($payload);
		$result = unserialize($unstripped, ['allowed_classes' => false]);
		expect($result)->toBeArray();
		expect($result)->toHaveKey('cg');
	}
});

it('object payloads are rejected by selected_items sanitizer', function () {
	$payload = serialize(new stdClass());
	expect(sanitize_unserialize_selected_items($payload))->toBeFalse();
});

it('sanitize_unserialize_selected_items accepts array of integers', function () {
	$payload = serialize([1, 2, 3]);
	$result  = sanitize_unserialize_selected_items($payload);
	expect($result)->toBeArray();
	expect($result)->toEqual([1, 2, 3]);
});

it('sanitize_unserialize_selected_items rejects arrays containing non-numeric values', function () {
	$payload = serialize(['id' => '1; DROP TABLE thold_data']);
	expect(sanitize_unserialize_selected_items($payload))->toBeFalse();
});
