<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2004-2026 The Cacti Group                                 |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDtool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
*/

/*
 * Regression tests for XSS via unescaped request variables in HTML output.
 *
 * Pre-fix: get_request_var('page') was printed raw into hidden input value
 * attributes, allowing an attacker to inject HTML/JS via a crafted page= param.
 *
 * Fix: html_escape() applied at every output boundary.
 */

it('thold_graph.php hidden page input uses html_escape', function () {
	$src = file_get_contents(realpath(__DIR__ . '/../../thold_graph.php'));
	expect($src)->not->toContain("value='<?php print get_request_var('page'); ?>'");
	expect($src)->toContain("html_escape(get_request_var('page'))");
});

it('html_escape converts angle brackets to entities', function () {
	expect(html_escape('<script>alert(1)</script>'))->toBe('&lt;script&gt;alert(1)&lt;/script&gt;');
});

it('html_escape converts double quotes to entities', function () {
	expect(html_escape('"quoted"'))->toBe('&quot;quoted&quot;');
});

it('html_escape converts single quotes to entities', function () {
	// ENT_QUOTES|ENT_HTML5 encodes single quotes as &apos; (HTML5 named entity)
	expect(html_escape("O'Brien"))->toBe('O&apos;Brien');
});
