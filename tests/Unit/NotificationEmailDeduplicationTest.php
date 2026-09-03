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
	CactiStubs::$configOptions['alert_deadnotify_one_mail'] = 'on';
});

test('combined device notifications use the sha256 deduplication path', function() {
	$event = [
		'from'        => ['sender@example.com'],
		'to'          => 'operator@example.com',
		'cc'          => '',
		'bcc'         => '',
		'replyto'     => '',
		'subject'     => 'Device is down',
		'body'        => '<body>Device is down</body>',
		'body_text'   => 'Device is down',
		'attachments' => [],
		'headers'     => [],
		'html'        => true,
	];

	CactiStubs::willReturn('db_fetch_assoc', [[
		'id'         => 42,
		'topic'      => 'thold_dhost_mail',
		'event_data' => json_encode($event),
	]]);

	process_device_notifications(0, 'all', 0);

	$source = file_get_contents(dirname(__DIR__, 2) . '/thold_functions.php');

	expect(CactiStubs::$mail)->toHaveCount(1)
		->and($source)->toContain("hash('sha256', json_encode(")
		->not->toContain('md5(json_encode(');
});
