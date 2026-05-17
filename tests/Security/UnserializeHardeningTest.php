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
 * Pre-fix: thold_webapi.php called cacti_unserialize(stripslashes(...)) on
 * selected_graphs_array, which accepted arbitrary serialized objects.
 *
 * Fix: sanitize_unserialize_selected_items() validates the deserialized data
 * is an array of integers only.
 */

it('thold_webapi.php does not call stripslashes on selected_graphs_array before unserialize', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../thold_webapi.php'));
	expect($src)->not->toContain("stripslashes(get_nfilter_request_var('selected_graphs_array')");
});

it('thold_webapi.php uses sanitize_unserialize_selected_items for selected_graphs_array', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../thold_webapi.php'));
	expect($src)->toContain("sanitize_unserialize_selected_items(get_nfilter_request_var('selected_graphs_array')");
});

it('sanitize_unserialize_selected_items rejects serialized objects', function () {
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
