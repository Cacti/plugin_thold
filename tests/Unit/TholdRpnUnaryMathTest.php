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

test('unary operators use the safe dispatcher', function($operator, $value, $expected) {
	$result = thold_rpn_math_unary($operator, $value);

	expect(abs($result - $expected))->toBeLessThan(1.0e-12)
		->and($GLOBALS['rpn_error'])->toBeFalse();
})->with([
	'sine'               => ['SIN', 0.5, sin(0.5)],
	'cosine'             => ['COS', 0.5, cos(0.5)],
	'tangent'            => ['TAN', 0.5, tan(0.5)],
	'arc tangent'        => ['ATAN', 0.5, atan(0.5)],
	'square root'        => ['SQRT', 9, 3],
	'floor'              => ['FLOOR', 2.75, 2],
	'ceiling'            => ['CEIL', 2.25, 3],
	'degrees to radians' => ['DEG2RAD', 180, M_PI],
	'radians to degrees' => ['RAD2DEG', M_PI, 180],
	'absolute value'     => ['ABS', -4, 4],
	'exponential'        => ['EXP', 1, M_E],
	'natural logarithm'  => ['LOG', M_E, 1],
]);

test('unknown unary operators fail closed', function() {
	expect(thold_rpn_math_unary('SYSTEM', 1))->toBe(0)
		->and($GLOBALS['rpn_error'])->toBeTrue()
		->and(CactiStubs::$log)->not->toBeEmpty();
});

test('the expression evaluator routes unary math through the dispatcher', function() {
	$stack = [-3];

	thold_expression_math_rpn('ABS', $stack);

	expect($stack)->toBe([3])
		->and($GLOBALS['rpn_error'])->toBeFalse();
});
