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
	'-1'  => __('Indefinitely', 'thold'),
	'31'  => __('%d Month', 1, 'thold'),
	'62'  => __('%d Months', 2, 'thold'),
	'93'  => __('%d Months', 3, 'thold'),
	'124' => __('%d Months', 4, 'thold'),
	'186' => __('%d Months', 6, 'thold'),
	'365' => __('%d Year', 1, 'thold')
);

$thold_host_states = array(
	HOST_DOWN       => array('display' => __('Down', 'thold'),          'class' => 'deviceDownFull'),
	HOST_ERROR      => array('display' => __('Error', 'thold'),         'class' => 'deviceErrorFull'),
	HOST_RECOVERING => array('display' => __('Recovering', 'thold'),    'class' => 'deviceRecoveringFull'),
	HOST_UP         => array('display' => __('Up', 'thold'),            'class' => 'deviceUpFull'),
	HOST_UNKNOWN    => array('display' => __('Unknown', 'thold'),       'class' => 'deviceUnknownFull'),
	'disabled'      => array('display' => __('Disabled', 'thold'),      'class' => 'deviceDisabledFull'),
	'notmon'        => array('display' => __('Not Monitored', 'thold'), 'class' => 'deviceNotMonFull')
);

$thold_log_states = array(
	'4' => array('index' => 'alarm',     'display' => __('Notify - Alert', 'thold'),          'display_short' => 'Alert', 'class' => 'tholdAlertNotify'),
	'7' => array('index' => 'alarm',     'display' => __('Notify - Alert2Warning', 'thold'),  'display_short' => 'Alert2Warn', 'class' => 'tholdAlert2Warn'),
	'3' => array('index' => 'warning',   'display' => __('Notify - Warning', 'thold'),        'display_short' => 'Warning', 'class' => 'tholdWarningNotify'),
	'2' => array('index' => 'retrigger', 'display' => __('Notify - Re-Trigger', 'thold'),     'display_short' => 'Re-Trigger', 'class' => 'tholdReTriggerEvent'),
	'5' => array('index' => 'restoral',  'display' => __('Notify - Restoral', 'thold'),       'display_short' => 'Retoral', 'class' => 'tholdRestoralNotify'),
	'1' => array('index' => 'trigger',   'display' => __('Event - Alert Trigger', 'thold'),   'display_short' => 'Alert Event', 'class' => 'tholdTriggerEvent'),
	'6' => array('index' => 'restoral',  'display' => __('Event - Warning Trigger', 'thold'), 'display_short' => 'Warning Event', 'class' => 'tholdWarnTrigger'),
	'0' => array('index' => 'restore',   'display' => __('Event - Restoral', 'thold'),        'display_short' => 'Restoral Event', 'class' => 'tholdRestoralEvent')
);

$thold_status_list = array(
	'0' => array('index' => 'restore',   'display' => __('Restore', 'thold'),       'class' => 'tholdRestore'),
	'1' => array('index' => 'trigger',   'display' => __('Alert Trigger', 'thold'), 'class' => 'tholdAlertTrigger'),
	'2' => array('index' => 'retrigger', 'display' => __('Re-Trigger', 'thold'),    'class' => 'tholdReTrigger'),
	'3' => array('index' => 'warning',   'display' => __('Warning', 'thold'),       'class' => 'tholdWarning'),
	'4' => array('index' => 'alarm',     'display' => __('Alert', 'thold'),         'class' => 'tholdAlert'),
	'5' => array('index' => 'restoral',  'display' => __('Restoral', 'thold'),      'class' => 'tholdRestoral'),
	'6' => array('index' => 'wtrigger',  'display' => __('Warn Trigger', 'thold'),  'class' => 'tholdWarnTrigger'),
	'7' => array('index' => 'alarmwarn', 'display' => __('Alert-Warn', 'thold'),    'class' => 'tholdAlert2Warn')
);

$thold_states = array(
	'red'     => array('class' => 'tholdAlert',     'display' => __('Alert', 'thold')),
	'orange'  => array('class' => 'tholdBaseAlert', 'display' => __('Baseline Alert', 'thold')),
	'warning' => array('class' => 'tholdWarning',   'display' => __('Warning', 'thold')),
	'yellow'  => array('class' => 'tholdNotice',    'display' => __('Notice', 'thold')),
	'green'   => array('class' => 'tholdOk',        'display' => __('Ok', 'thold')),
	'grey'    => array('class' => 'tholdDisabled',  'display' => __('Disabled', 'thold'))
);

if (!isset($step)) {
	$step = read_config_option('poller_interval');
}

if ($step == 60) {
	$repeatarray = array(
		0     => __('Never', 'thold'),
		1     => __('Every Minute', 'thold'),
		2     => __('Every %d Minutes', 2, 'thold'),
		3     => __('Every %d Minutes', 3, 'thold'),
		4     => __('Every %d Minutes', 4, 'thold'),
		5     => __('Every %d Minutes', 5, 'thold'),
		10    => __('Every %d Minutes', 10, 'thold'),
		15    => __('Every %d Minutes', 15, 'thold'),
		20    => __('Every %d Minutes', 20, 'thold'),
		30    => __('Every %d Minutes', 30, 'thold'),
		45    => __('Every %d Minutes', 45, 'thold'),
		60    => __('Every Hour', 'thold'),
		120   => __('Every %d Hours', 2, 'thold'),
		180   => __('Every %d Hours', 3, 'thold'),
		240   => __('Every %d Hours', 4, 'thold'),
		360   => __('Every %d Hours', 6, 'thold'),
		480   => __('Every %d Hours', 8, 'thold'),
		720   => __('Every %d Hours', 12, 'thold'),
		1440  => __('Every Day', 'thold'),
		2880  => __('Every %d Days', 2, 'thold'),
		10080 => __('Every Week', 'thold'),
		20160 => __('Every %d Weeks', 2, 'thold'),
		43200 => __('Every Month', 'thold')
	);

	$alertarray  = array(
		0     => __('Never', 'thold'),
		1     => __('%d Minute', 1, 'thold'),
		2     => __('%d Minutes', 2, 'thold'),
		3     => __('%d Minutes', 3, 'thold'),
		4     => __('%d Minutes', 4, 'thold'),
		5     => __('%d Minutes', 5, 'thold'),
		10    => __('%d Minutes', 10, 'thold'),
		15    => __('%d Minutes', 15, 'thold'),
		20    => __('%d Minutes', 20, 'thold'),
		30    => __('%d Minutes', 30, 'thold'),
		45    => __('%d Minutes', 45, 'thold'),
		60    => __('%d Hour', 1, 'thold'),
		120   => __('%d Hours', 2, 'thold'),
		180   => __('%d Hours', 3, 'thold'),
		240   => __('%d Hours', 4, 'thold'),
		360   => __('%d Hours', 6, 'thold'),
		480   => __('%d Hours', 8, 'thold'),
		720   => __('%d Hours', 12, 'thold'),
		1440  => __('%d Day', 1, 'thold'),
		2880  => __('%d Days', 2, 'thold'),
		10080 => __('%d Week', 1, 'thold'),
		20160 => __('%d Weeks', 2, 'thold'),
		43200 => __('%d Month', 1, 'thold')
	);

	$timearray   = array(
		1     => __('%d Minute', 1, 'thold'),
		2     => __('%d Minutes', 2, 'thold'),
		3     => __('%d Minutes', 3, 'thold'),
		4     => __('%d Minutes', 4, 'thold'),
		5     => __('%d Minutes', 5, 'thold'),
		6     => __('%d Minutes', 6, 'thold'),
		7     => __('%d Minutes', 7, 'thold'),
		8     => __('%d Minutes', 8, 'thold'),
		9     => __('%d Minutes', 9, 'thold'),
		10    => __('%d Minutes', 10, 'thold'),
		12    => __('%d Minutes', 12, 'thold'),
		15    => __('%d Minutes', 15, 'thold'),
		20    => __('%d Minutes', 20, 'thold'),
		24    => __('%d Minutes', 24, 'thold'),
		30    => __('%d Minutes', 30, 'thold'),
		45    => __('%d Minutes', 45, 'thold'),
		60    => __('%d Hour', 1, 'thold'),
		120   => __('%d Hours', 2, 'thold'),
		180   => __('%d Hours', 3, 'thold'),
		240   => __('%d Hours', 4, 'thold'),
		288   => __('%0.1f Hours', 4.8, 'thold'),
		360   => __('%d Hours', 6, 'thold'),
		480   => __('%d Hours', 8, 'thold'),
		720   => __('%d Hours', 12, 'thold'),
		1440  => __('%d Day', 1, 'thold'),
		2880  => __('%d Days', 2, 'thold'),
		10080 => __('%d Week', 1, 'thold'),
		20160 => __('%d Weeks', 2, 'thold'),
		43200 => __('%d Month', 1, 'thold')
	);
} else if ($step == 300) {
	$repeatarray = array(
		0    => __('Never', 'thold'),
		1    => __('Every %d Minutes', 5, 'thold'),
		2    => __('Every %d Minutes', 10, 'thold'),
		3    => __('Every %d Minutes', 15, 'thold'),
		4    => __('Every %d Minutes', 20, 'thold'),
		6    => __('Every %d Minutes', 30, 'thold'),
		8    => __('Every %d Minutes', 45, 'thold'),
		12   => __('Every Hour', 'thold'),
		24   => __('Every %d Hours', 2, 'thold'),
		36   => __('Every %d Hours', 3, 'thold'),
		48   => __('Every %d Hours', 4, 'thold'),
		72   => __('Every %d Hours', 6, 'thold'),
		96   => __('Every %d Hours', 8, 'thold'),
		144  => __('Every %d Hours', 12, 'thold'),
		288  => __('Every Day', 'thold'),
		576  => __('Every %d Days', 2, 'thold'),
		2016 => __('Every Week', 'thold'),
		4032 => __('Every %d Weeks', 2, 'thold'),
		8640 => __('Every Month', 'thold')
	);

	$alertarray  = array(
		0    => __('Never', 'thold'),
		1    => __('%d Minutes', 5, 'thold'),
		2    => __('%d Minutes', 10, 'thold'),
		3    => __('%d Minutes', 15, 'thold'),
		4    => __('%d Minutes', 20, 'thold'),
		6    => __('%d Minutes', 30, 'thold'),
		8    => __('%d Minutes', 45, 'thold'),
		12   => __('%d Hour', 1, 'thold'),
		24   => __('%d Hours', 2, 'thold'),
		36   => __('%d Hours', 3, 'thold'),
		48   => __('%d Hours', 4, 'thold'),
		72   => __('%d Hours', 6, 'thold'),
		96   => __('%d Hours', 8, 'thold'),
		144  => __('%d Hours', 12, 'thold'),
		288  => __('%d Day', 1, 'thold'),
		576  => __('%d Days', 2, 'thold'),
		2016 => __('%d Week', 1, 'thold'),
		4032 => __('%d Weeks', 2, 'thold'),
		8640 => __('%d Month', 1, 'thold')
	);

	$timearray   = array(
		1   => __('%d Minutes', 5, 'thold'),
		2   => __('%d Minutes', 10, 'thold'),
		3   => __('%d Minutes', 15, 'thold'),
		4   => __('%d Minutes', 20, 'thold'),
		6   => __('%d Minutes', 30, 'thold'),
		8   => __('%d Minutes', 45, 'thold'),
		12   => __('%d Hour', 1, 'thold'),
		24   => __('%d Hours', 2, 'thold'),
		36   => __('%d Hours', 3, 'thold'),
		48   => __('%d Hours', 4, 'thold'),
		72   => __('%d Hours', 6, 'thold'),
		96   => __('%d Hours', 8, 'thold'),
		144  => __('%d Hours', 12, 'thold'),
		288  => __('%d Day', 1, 'thold'),
		576  => __('%d Days', 2, 'thold'),
		2016 => __('%d Week', 1, 'thold'),
		4032 => __('%d Weeks', 2, 'thold'),
		8640 => __('%d Month', 1, 'thold')
	);
} else {
	$repeatarray = array(
		0    => __('Never', 'thold'),
		1    => __('Every Polling', 'thold'),
		2    => __('Every %d Pollings', 1, 'thold'),
		3    => __('Every %d Pollings', 3, 'thold'),
		4    => __('Every %d Pollings', 4, 'thold'),
		6    => __('Every %d Pollings', 6, 'thold'),
		8    => __('Every %d Pollings', 8, 'thold'),
		12   => __('Every %d Pollings', 12, 'thold'),
		24   => __('Every %d Pollings', 24, 'thold'),
		36   => __('Every %d Pollings', 36, 'thold'),
		48   => __('Every %d Pollings', 48, 'thold'),
		72   => __('Every %d Pollings', 72, 'thold'),
		96   => __('Every %d Pollings', 96, 'thold'),
		144  => __('Every %d Pollings', 144, 'thold'),
		288  => __('Every %d Pollings', 288, 'thold'),
		576  => __('Every %d Pollings', 576, 'thold'),
		2016 => __('Every %d Pollings', 2016, 'thold')
	);

	$alertarray  = array(
		0    => __('Never', 'thold'),
		1    => __('%d Polling', 1, 'thold'),
		2    => __('%d Pollings', 2, 'thold'),
		3    => __('%d Pollings', 3, 'thold'),
		4    => __('%d Pollings', 4, 'thold'),
		6    => __('%d Pollings', 6, 'thold'),
		8    => __('%d Pollings', 8, 'thold'),
		12   => __('%d Pollings', 12, 'thold'),
		24   => __('%d Pollings', 24, 'thold'),
		36   => __('%d Pollings', 36, 'thold'),
		48   => __('%d Pollings', 45, 'thold'),
		72   => __('%d Pollings', 72, 'thold'),
		96   => __('%d Pollings', 96, 'thold'),
		144  => __('%d Pollings', 144, 'thold'),
		288  => __('%d Pollings', 288, 'thold'),
		576  => __('%d Pollings', 576, 'thold'),
		2016 => __('%d Pollings', 2016, 'thold')
	);

	$timearray   = array(
		1    => __('%d Polling', 1, 'thold'),
		2    => __('%d Pollings', 2, 'thold'),
		3    => __('%d Pollings', 3, 'thold'),
		4    => __('%d Pollings', 4, 'thold'),
		6    => __('%d Pollings', 6, 'thold'),
		8    => __('%d Pollings', 8, 'thold'),
		12   => __('%d Pollings', 12, 'thold'),
		24   => __('%d Pollings', 24, 'thold'),
		36   => __('%d Pollings', 36, 'thold'),
		48   => __('%d Pollings', 48, 'thold'),
		72   => __('%d Pollings', 72, 'thold'),
		96   => __('%d Pollings', 96, 'thold'),
		144  => __('%d Pollings', 144, 'thold'),
		288  => __('%d Pollings', 288, 'thold'),
		576  => __('%d Pollings', 576, 'thold'),
		2016 => __('%d Pollings', 2016, 'thold')
	);
}

$thold_types = array (
	0 => __('High / Low', 'thold'),
	1 => __('Baseline Deviation', 'thold'),
	2 => __('Time Based', 'thold')
);

$data_types = array (
	0 => __('Exact Value', 'thold'),
	1 => __('CDEF', 'thold'),
	2 => __('Percentage', 'thold'),
	3 => __('RPN Expression', 'thold')
);

/* perform database upgrade */
include_once($config['base_path'] . '/plugins/thold/setup.php');
plugin_thold_upgrade ();
