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

beforeAll(function() {
	thold_test_load(dirname(__DIR__, 2) . '/thold_functions.php');
});

beforeEach(function() {
	CactiStubs::reset();
	$GLOBALS['rpn_error'] = false;
});

test('binary operators use the safe dispatcher', function($operator, $left, $right, $expected) {
	expect(thold_rpn_math_binary($operator, $left, $right))->toEqual($expected)
		->and($GLOBALS['rpn_error'])->toBeFalse();
})->with([
	'addition'       => ['+', 8, 2, 10],
	'subtraction'    => ['-', 8, 2, 6],
	'multiplication' => ['*', 8, 2, 16],
	'division'       => ['/', 8, 2, 4],
	'modulo'         => ['%', 8, 3, 2],
	'power'          => ['^', 2, 3, 8],
]);

test('unknown binary operators fail closed', function() {
	expect(thold_rpn_math_binary('**', 2, 3))->toBe(0)
		->and($GLOBALS['rpn_error'])->toBeTrue()
		->and(CactiStubs::$log)->not->toBeEmpty();
});

test('the expression evaluator routes binary math through the dispatcher', function() {
	$stack = [8, 2];

	thold_expression_math_rpn('-', $stack);

	expect($stack)->toBe([6])
		->and($GLOBALS['rpn_error'])->toBeFalse();
});
