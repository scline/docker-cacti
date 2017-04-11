<?php
/*
 ex: set tabstop=4 shiftwidth=4 autoindent:
 +-------------------------------------------------------------------------+
 | Copyright (C) 2006-2017 The Cacti Group                                 |
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

$thold_log_retention = array(
	'-1'  => __('Indefinitely'), 
	'31'  => __('%d Month', 1), 
	'62'  => __('%d Months', 2), 
	'93'  => __('%d Months', 3),
	'124' => __('%d Months', 4), 
	'186' => __('%d Months', 6), 
	'365' => __('%d Year', 1)
);

$thold_host_states = array(
	HOST_DOWN       => array('display' => __('Down'),          'class' => 'deviceDownFull'),
	HOST_ERROR      => array('display' => __('Error'),         'class' => 'deviceErrorFull'),
	HOST_RECOVERING => array('display' => __('Recovering'),    'class' => 'deviceRecoveringFull'),
	HOST_UP         => array('display' => __('Up'),            'class' => 'deviceUpFull'),
	HOST_UNKNOWN    => array('display' => __('Unknown'),       'class' => 'deviceUnknownFull'),
	'disabled'      => array('display' => __('Disabled'),      'class' => 'deviceDisabledFull'),
	'notmon'        => array('display' => __('Not Monitored'), 'class' => 'deviceNotMonFull')
);

$thold_log_states = array(
	'4' => array('index' => 'alarm',     'display' => __('Notify - Alert'),          'display_short' => 'Alert', 'class' => 'tholdAlertNotify'),
	'7' => array('index' => 'alarm',     'display' => __('Notify - Alert2Warning'),  'display_short' => 'Alert2Warn', 'class' => 'tholdAlert2Warn'),
	'3' => array('index' => 'warning',   'display' => __('Notify - Warning'),        'display_short' => 'Warning', 'class' => 'tholdWarningNotify'),
	'2' => array('index' => 'retrigger', 'display' => __('Notify - Re-Trigger'),     'display_short' => 'Re-Trigger', 'class' => 'tholdReTriggerEvent'),
	'5' => array('index' => 'restoral',  'display' => __('Notify - Restoral'),       'display_short' => 'Retoral', 'class' => 'tholdRestoralNotify'),
	'1' => array('index' => 'trigger',   'display' => __('Event - Alert Trigger'),   'display_short' => 'Alert Event', 'class' => 'tholdTriggerEvent'),
	'6' => array('index' => 'restoral',  'display' => __('Event - Warning Trigger'), 'display_short' => 'Warning Event', 'class' => 'tholdWarnTrigger'),
	'0' => array('index' => 'restore',   'display' => __('Event - Restoral'),        'display_short' => 'Restoral Event', 'class' => 'tholdRestoralEvent')
);

$thold_status_list = array(
	'0' => array('index' => 'restore',   'display' => __('Restore'),       'class' => 'tholdRestore'),
	'1' => array('index' => 'trigger',   'display' => __('Alert Trigger'), 'class' => 'tholdAlertTrigger'),
	'2' => array('index' => 'retrigger', 'display' => __('Re-Trigger'),    'class' => 'tholdReTrigger'),
	'3' => array('index' => 'warning',   'display' => __('Warning'),       'class' => 'tholdWarning'),
	'4' => array('index' => 'alarm',     'display' => __('Alert'),         'class' => 'tholdAlert'),
	'5' => array('index' => 'restoral',  'display' => __('Restoral'),      'class' => 'tholdRestoral'),
	'6' => array('index' => 'wtrigger',  'display' => __('Warn Trigger'),  'class' => 'tholdWarnTrigger'),
	'7' => array('index' => 'alarmwarn', 'display' => __('Alert-Warn'),    'class' => 'tholdAlert2Warn')
);

$thold_states = array(
	'red'     => array('class' => 'tholdAlert',     'display' => __('Alert')),
	'orange'  => array('class' => 'tholdBaseAlert', 'display' => __('Baseline Alert')),
	'warning' => array('class' => 'tholdWarning',   'display' => __('Warning')),
	'yellow'  => array('class' => 'tholdNotice',    'display' => __('Notice')),
	'green'   => array('class' => 'tholdOk',        'display' => __('Ok')),
	'grey'    => array('class' => 'tholdDisabled',  'display' => __('Disabled'))
);

if (!isset($step)) {
	$step = read_config_option('poller_interval');
}

if ($step == 60) {
	$repeatarray = array(
		0     => __('Never'), 
		1     => __('Every Minute'), 
		2     => __('Every %d Minutes', 2), 
		3     => __('Every %d Minutes', 3), 
		4     => __('Every %d Minutes', 4), 
		5     => __('Every %d Minutes', 5), 
		10    => __('Every %d Minutes', 10),
		15    => __('Every %d Minutes', 15), 
		20    => __('Every %d Minutes', 20), 
		30    => __('Every %d Minutes', 30), 
		45    => __('Every %d Minutes', 45), 
		60    => __('Every Hour'), 
		120   => __('Every %d Hours', 2), 
		180   => __('Every %d Hours', 3), 
		240   => __('Every %d Hours', 4), 
		360   => __('Every %d Hours', 6), 
		480   => __('Every %d Hours', 8), 
		720   => __('Every %d Hours', 12), 
		1440  => __('Every Day'), 
		2880  => __('Every %d Days', 2), 
		10080 => __('Every Week'), 
		20160 => __('Every %d Weeks', 2), 
		43200 => __('Every Month')
	);

	$alertarray  = array(
		0     => __('Never'), 
		1     => __('%d Minute', 1), 
		2     => __('%d Minutes', 2), 
		3     => __('%d Minutes', 3), 
		4     => __('%d Minutes', 4), 
		5     => __('%d Minutes', 5), 
		10    => __('%d Minutes', 10), 
		15    => __('%d Minutes', 15), 
		20    => __('%d Minutes', 20), 
		30    => __('%d Minutes', 30), 
		45    => __('%d Minutes', 45), 
		60    => __('%d Hour', 1), 
		120   => __('%d Hours', 2), 
		180   => __('%d Hours', 3), 
		240   => __('%d Hours', 4), 
		360   => __('%d Hours', 6), 
		480   => __('%d Hours', 8), 
		720   => __('%d Hours', 12), 
		1440  => __('%d Day', 1), 
		2880  => __('%d Days', 2), 
		10080 => __('%d Week', 1), 
		20160 => __('%d Weeks', 2), 
		43200 => __('%d Month', 1)
	);

	$timearray   = array(
		1     => __('%d Minute', 1), 
		2     => __('%d Minutes', 2), 
		3     => __('%d Minutes', 3), 
		4     => __('%d Minutes', 4), 
		5     => __('%d Minutes', 5), 
		6     => __('%d Minutes', 6), 
		7     => __('%d Minutes', 7), 
		8     => __('%d Minutes', 8), 
		9     => __('%d Minutes', 9), 
		10    => __('%d Minutes', 10), 
		12    => __('%d Minutes', 12), 
		15    => __('%d Minutes', 15), 
		20    => __('%d Minutes', 20), 
		24    => __('%d Minutes', 24), 
		30    => __('%d Minutes', 30), 
		45    => __('%d Minutes', 45), 
		60    => __('%d Hour', 1), 
		120   => __('%d Hours', 2), 
		180   => __('%d Hours', 3), 
		240   => __('%d Hours', 4), 
		288   => __('%0.1f Hours', 4.8), 
		360   => __('%d Hours', 6), 
		480   => __('%d Hours', 8), 
		720   => __('%d Hours', 12), 
		1440  => __('%d Day', 1), 
		2880  => __('%d Days', 2), 
		10080 => __('%d Week', 1), 
		20160 => __('%d Weeks', 2), 
		43200 => __('%d Month', 1)
	);
} else if ($step == 300) {
	$repeatarray = array(
		0    => __('Never'), 
		1    => __('Every %d Minutes', 5), 
		2    => __('Every %d Minutes', 10), 
		3    => __('Every %d Minutes', 15), 
		4    => __('Every %d Minutes', 20), 
		6    => __('Every %d Minutes', 30), 
		8    => __('Every %d Minutes', 45), 
		12   => __('Every Hour'), 
		24   => __('Every %d Hours', 2), 
		36   => __('Every %d Hours', 3), 
		48   => __('Every %d Hours', 4), 
		72   => __('Every %d Hours', 6), 
		96   => __('Every %d Hours', 8), 
		144  => __('Every %d Hours', 12), 
		288  => __('Every Day'), 
		576  => __('Every %d Days', 2), 
		2016 => __('Every Week'), 
		4032 => __('Every %d Weeks', 2), 
		8640 => __('Every Month')
	);

	$alertarray  = array(
		0    => __('Never'), 
		1    => __('%d Minutes', 5), 
		2    => __('%d Minutes', 10), 
		3    => __('%d Minutes', 15), 
		4    => __('%d Minutes', 20), 
		6    => __('%d Minutes', 30), 
		8    => __('%d Minutes', 45), 
		12   => __('%d Hour', 1), 
		24   => __('%d Hours', 2), 
		36   => __('%d Hours', 3), 
		48   => __('%d Hours', 4), 
		72   => __('%d Hours', 6), 
		96   => __('%d Hours', 8), 
		144  => __('%d Hours', 12), 
		288  => __('%d Day', 1), 
		576  => __('%d Days', 2), 
		2016 => __('%d Week', 1), 
		4032 => __('%d Weeks', 2), 
		8640 => __('%d Month', 1)
	);

	$timearray   = array(
		1   => __('%d Minutes', 5), 
		2   => __('%d Minutes', 10), 
		3   => __('%d Minutes', 15), 
		4   => __('%d Minutes', 20), 
		6   => __('%d Minutes', 30), 
		8   => __('%d Minutes', 45), 
		12   => __('%d Hour', 1), 
		24   => __('%d Hours', 2), 
		36   => __('%d Hours', 3), 
		48   => __('%d Hours', 4), 
		72   => __('%d Hours', 6), 
		96   => __('%d Hours', 8), 
		144  => __('%d Hours', 12), 
		288  => __('%d Day', 1), 
		576  => __('%d Days', 2), 
		2016 => __('%d Week', 1), 
		4032 => __('%d Weeks', 2), 
		8640 => __('%d Month', 1)
	);
} else {
	$repeatarray = array(
		0    => __('Never'), 
		1    => __('Every Polling'), 
		2    => __('Every %d Pollings', 1), 
		3    => __('Every %d Pollings', 3), 
		4    => __('Every %d Pollings', 4), 
		6    => __('Every %d Pollings', 6), 
		8    => __('Every %d Pollings', 8), 
		12   => __('Every %d Pollings', 12), 
		24   => __('Every %d Pollings', 24), 
		36   => __('Every %d Pollings', 36), 
		48   => __('Every %d Pollings', 48), 
		72   => __('Every %d Pollings', 72), 
		96   => __('Every %d Pollings', 96), 
		144  => __('Every %d Pollings', 144), 
		288  => __('Every %d Pollings', 288), 
		576  => __('Every %d Pollings', 576), 
		2016 => __('Every %d Pollings', 2016)
	);

	$alertarray  = array(
		0    => __('Never'), 
		1    => __('%d Polling', 1),
		2    => __('%d Pollings', 2), 
		3    => __('%d Pollings', 3), 
		4    => __('%d Pollings', 4), 
		6    => __('%d Pollings', 6), 
		8    => __('%d Pollings', 8), 
		12   => __('%d Pollings', 12), 
		24   => __('%d Pollings', 24), 
		36   => __('%d Pollings', 36), 
		48   => __('%d Pollings', 45), 
		72   => __('%d Pollings', 72), 
		96   => __('%d Pollings', 96), 
		144  => __('%d Pollings', 144), 
		288  => __('%d Pollings', 288), 
		576  => __('%d Pollings', 576), 
		2016 => __('%d Pollings', 2016)
	);

	$timearray   = array(
		1    => __('%d Polling', 1), 
		2    => __('%d Pollings', 2), 
		3    => __('%d Pollings', 3), 
		4    => __('%d Pollings', 4), 
		6    => __('%d Pollings', 6), 
		8    => __('%d Pollings', 8), 
		12   => __('%d Pollings', 12), 
		24   => __('%d Pollings', 24), 
		36   => __('%d Pollings', 36), 
		48   => __('%d Pollings', 48), 
		72   => __('%d Pollings', 72), 
		96   => __('%d Pollings', 96), 
		144  => __('%d Pollings', 144), 
		288  => __('%d Pollings', 288), 
		576  => __('%d Pollings', 576), 
		2016 => __('%d Pollings', 2016)
	);
}

$thold_types = array (
	0 => __('High / Low'),
	1 => __('Baseline Deviation'),
	2 => __('Time Based')
);

$data_types = array (
	0 => __('Exact Value'),
	1 => __('CDEF'),
	2 => __('Percentage'),
	3 => __('RPN Expression')
);

/* perform database upgrade */
include_once($config['base_path'] . '/plugins/thold/setup.php');
thold_check_upgrade();
