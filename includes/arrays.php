<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2016 The Cacti Group                                 |
 |                                                                         |
 | This program is free software; you can redistribute it and/or           |
 | modify it under the terms of the GNU General Public License             |
 | as published by the Free Software Foundation; either version 2          |
 | of the License, or (at your option) any later version.                  |
 |                                                                         |
 | This program is distributed in the hope that it will be useful,         |
 | but WITHOUT ANY WARRANTY; without even the implied warranty of          |
 | MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the           |
 | GNU General Public License for more details.                            |
 +-------------------------------------------------------------------------+
 | Cacti: The Complete RRDTool-based Graphing Solution                     |
 +-------------------------------------------------------------------------+
 | This code is designed, written, and maintained by the Cacti Group. See  |
 | about.php and/or the AUTHORS file for specific developer information.   |
 +-------------------------------------------------------------------------+
 | http://www.cacti.net/                                                   |
 +-------------------------------------------------------------------------+
*/

$thold_host_states = array(
	HOST_DOWN       => array('display' => 'Down',          'class' => 'deviceDownFull'),
	HOST_ERROR      => array('display' => 'Error',         'class' => 'deviceErrorFull'),
	HOST_RECOVERING => array('display' => 'Recovering',    'class' => 'deviceRecoveringFull'),
	HOST_UP         => array('display' => 'Up',            'class' => 'deviceUpFull'),
	HOST_UNKNOWN    => array('display' => 'Unknown',       'class' => 'deviceUnknownFull'),
	'disabled'      => array('display' => 'Disabled',      'class' => 'deviceDisabledFull'),
	'notmon'        => array('display' => 'Not Monitored', 'class' => 'deviceNotMonFull')
);

$thold_log_states = array(
	'4' => array('index' => 'alarm',     'display' => 'Alert Notify',    'class' => 'tholdAlertNotify'),
	'3' => array('index' => 'warning',   'display' => 'Warning Notify',  'class' => 'tholdWarningNotify'),
	'2' => array('index' => 'retrigger', 'display' => 'Re-Trigger Event', 'class' => 'tholdReTriggerEvent'),
	'1' => array('index' => 'trigger',   'display' => 'Trigger Event',   'class' => 'tholdTriggerEvent'),
	'5' => array('index' => 'restoral',  'display' => 'Restoral Notify', 'class' => 'tholdRestoralNotify'),
	'0' => array('index' => 'restore',   'display' => 'Restoral Event',  'class' => 'tholdRestoralEvent')
);

$thold_status_list = array(
	'0' => array('index' => 'restore',   'display' => 'Restore',       'class' => 'tholdRestore'),
	'1' => array('index' => 'trigger',   'display' => 'Alert Trigger', 'class' => 'tholdAlertTrigger'),
	'2' => array('index' => 'retrigger', 'display' => 'Re-Trigger',    'class' => 'tholdReTrigger'),
	'3' => array('index' => 'warning',   'display' => 'Warning',       'class' => 'tholdWarning'),
	'4' => array('index' => 'alarm',     'display' => 'Alert',         'class' => 'tholdAlert'),
	'5' => array('index' => 'restoral',  'display' => 'Restoral',      'class' => 'tholdRestoral'),
	'6' => array('index' => 'wtrigger',  'display' => 'Warn Trigger',  'class' => 'tholdWarnTrigger'),
	'7' => array('index' => 'alarmwarn', 'display' => 'Alert-Warn',    'class' => 'tholdAlert2Warn')
);

$thold_states = array(
	'red'     => array('class' => 'tholdAlert',     'display' => 'Alert'),
	'orange'  => array('class' => 'tholdBaseAlert', 'display' => 'Baseline Alert'),
	'warning' => array('class' => 'tholdWarning',   'display' => 'Warning'),
	'yellow'  => array('class' => 'tholdNotice',    'display' => 'Notice'),
	'green'   => array('class' => 'tholdOk',        'display' => 'Ok'),
	'grey'    => array('class' => 'tholdDisabled',  'display' => 'Disabled')
);

if (!isset($step)) {
	$step = read_config_option('poller_interval');
}

if ($step == 60) {
	$repeatarray = array(
		0     => 'Never', 
		1     => 'Every Minute', 
		2     => 'Every 2 Minutes', 
		3     => 'Every 3 Minutes', 
		4     => 'Every 4 Minutes', 
		5     => 'Every 5 Minutes', 
		10    => 'Every 10 Minutes',
		15    => 'Every 15 Minutes', 
		20    => 'Every 20 Minutes', 
		30    => 'Every 30 Minutes', 
		45    => 'Every 45 Minutes', 
		60    => 'Every Hour', 
		120   => 'Every 2 Hours', 
		180   => 'Every 3 Hours', 
		240   => 'Every 4 Hours', 
		360   => 'Every 6 Hours', 
		480   => 'Every 8 Hours', 
		720   => 'Every 12 Hours', 
		1440  => 'Every Day', 
		2880  => 'Every 2 Days', 
		10080 => 'Every Week', 
		20160 => 'Every 2 Weeks', 
		43200 => 'Every Month'
	);

	$alertarray  = array(
		0     => 'Never', 
		1     => '1 Minute', 
		2     => '2 Minutes', 
		3     => '3 Minutes', 
		4     => '4 Minutes', 
		5     => '5 Minutes', 
		10    => '10 Minutes', 
		15    => '15 Minutes', 
		20    => '20 Minutes', 
		30    => '30 Minutes', 
		45    => '45 Minutes', 
		60    => '1 Hour', 
		120   => '2 Hours', 
		180   => '3 Hours', 
		240   => '4 Hours', 
		360   => '6 Hours', 
		480   => '8 Hours', 
		720   => '12 Hours', 
		1440  => '1 Day', 
		2880  => '2 Days', 
		10080 => '1 Week', 
		20160 => '2 Weeks', 
		43200 => '1 Month'
	);

	$timearray   = array(
		1     => '1 Minute', 
		2     => '2 Minutes', 
		3     => '3 Minutes', 
		4     => '4 Minutes', 
		5     => '5 Minutes', 
		6     => '6 Minutes', 
		7     => '7 Minutes', 
		8     => '8 Minutes', 
		9     => '9 Minutes', 
		10    => '10 Minutes', 
		12    => '12 Minutes', 
		15    => '15 Minutes', 
		20    => '20 Minutes', 
		24    => '24 Minutes', 
		30    => '30 Minutes', 
		45    => '45 Minutes', 
		60    => '1 Hour', 
		120   => '2 Hours', 
		180   => '3 Hours', 
		240   => '4 Hours', 
		288   => '4.8 Hours', 
		360   => '6 Hours', 
		480   => '8 Hours', 
		720   => '12 Hours', 
		1440  => '1 Day', 
		2880  => '2 Days', 
		10080 => '1 Week', 
		20160 => '2 Weeks', 
		43200 => '1 Month'
	);
} else if ($step == 300) {
	$repeatarray = array(
		0    => 'Never', 
		1    => 'Every 5 Minutes', 
		2    => 'Every 10 Minutes', 
		3    => 'Every 15 Minutes', 
		4    => 'Every 20 Minutes', 
		6    => 'Every 30 Minutes', 
		8    => 'Every 45 Minutes', 
		12   => 'Every Hour', 
		24   => 'Every 2 Hours', 
		36   => 'Every 3 Hours', 
		48   => 'Every 4 Hours', 
		72   => 'Every 6 Hours', 
		96   => 'Every 8 Hours', 
		144  => 'Every 12 Hours', 
		288  => 'Every Day', 
		576  => 'Every 2 Days', 
		2016 => 'Every Week', 
		4032 => 'Every 2 Weeks', 
		8640 => 'Every Month'
	);

	$alertarray  = array(
		0    => 'Never', 
		1    => '5 Minutes', 
		2    => '10 Minutes', 
		3    => '15 Minutes', 
		4    => '20 Minutes', 
		6    => '30 Minutes', 
		8    => '45 Minutes', 
		12   => '1 Hour', 
		24   => '2 Hours', 
		36   => '3 Hours', 
		48   => '4 Hours', 
		72   => '6 Hours', 
		96   => '8 Hours', 
		144  => '12 Hours', 
		288  => '1 Day', 
		576  => '2 Days', 
		2016 => '1 Week', 
		4032 => '2 Weeks', 
		8640 => '1 Month'
	);

	$timearray   = array(
		1   => '5 Minutes', 
		2   => '10 Minutes', 
		3   => '15 Minutes', 
		4   => '20 Minutes', 
		6   => '30 Minutes', 
		8   => '45 Minutes', 
		12   => '1 Hour', 
		24   => '2 Hours', 
		36   => '3 Hours', 
		48   => '4 Hours', 
		72   => '6 Hours', 
		96   => '8 Hours', 
		144  => '12 Hours', 
		288  => '1 Day', 
		576  => '2 Days', 
		2016 => '1 Week', 
		4032 => '2 Weeks', 
		8640 => '1 Month'
	);
} else {
	$repeatarray = array(
		0    => 'Never', 
		1    => 'Every Polling', 
		2    => 'Every 2 Pollings', 
		3    => 'Every 3 Pollings', 
		4    => 'Every 4 Pollings', 
		6    => 'Every 6 Pollings', 
		8    => 'Every 8 Pollings', 
		12   => 'Every 12 Pollings', 
		24   => 'Every 24 Pollings', 
		36   => 'Every 36 Pollings', 
		48   => 'Every 48 Pollings', 
		72   => 'Every 72 Pollings', 
		96   => 'Every 96 Pollings', 
		144  => 'Every 144 Pollings', 
		288  => 'Every 288 Pollings', 
		576  => 'Every 576 Pollings', 
		2016 => 'Every 2016 Pollings'
	);

	$alertarray  = array(
		0    => 'Never', 
		1    => '1 Polling', 
		2    => '2 Pollings', 
		3    => '3 Pollings', 
		4    => '4 Pollings', 
		6    => '6 Pollings', 
		8    => '8 Pollings', 
		12   => '12 Pollings', 
		24   => '24 Pollings', 
		36   => '36 Pollings', 
		48   => '48 Pollings', 
		72   => '72 Pollings', 
		96   => '96 Pollings', 
		144  => '144 Pollings', 
		288  => '288 Pollings', 
		576  => '576 Pollings', 
		2016 => '2016 Pollings'
	);

	$timearray   = array(
		1    => '1 Polling', 
		2    => '2 Pollings', 
		3    => '3 Pollings', 
		4    => '4 Pollings', 
		6    => '6 Pollings', 
		8    => '8 Pollings', 
		12   => '12 Pollings', 
		24   => '24 Pollings', 
		36   => '36 Pollings', 
		48   => '48 Pollings', 
		72   => '72 Pollings', 
		96   => '96 Pollings', 
		144  => '144 Pollings', 
		288  => '288 Pollings', 
		576  => '576 Pollings', 
		2016 => '2016 Pollings'
	);
}

$thold_types = array (
	0 => 'High / Low Values',
	1 => 'Baseline Deviation',
	2 => 'Time Based',
);

$data_types = array (
	0 => 'Exact Value',
	1 => 'CDEF',
	2 => 'Percentage',
	3 => 'RPN Expression'
);

