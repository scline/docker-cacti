# syslog

The syslog plugin is a Cacti plugin that has been around for more than a decade.  It was inspired by the 'aloe' and 'h.aloe' plugins originally developed by the Cacti users sidewinder and Harlequin in the early 2000's.  As you will be able to see from the ChangeLog, it has undergone several changes throughout the years, and remains, even today when you have enterprise offering from both Elastic and Splunk, remains a relevant plugin for small to medium sized companies.

It provides a simple Syslog event search an Alert generation and notification interface that can generate both HTML and SMS messages for operations personnel who wish to receive notifications inside of a data or network operations center.

When combined by the Linux SNMPTT package, it can be converted into an SNMP Trap and Inform receiver and notification engine as the SNMPTT tool will receive SNMP Traps and Informs and convert them into Syslog messages on your log server.  These syslog messages can then be consumed by the syslog plugin.  So, this tool is quite handy.

For log events that continue to be generated frequently on a device, such as smartd's feature to notify every 15 minutes of an impending drive failure, can be quieted using syslog's 'Re-Alert' setting.

## Features

* Message filter
* Message search
* Output to screen or file
* Date time picker
* Event Alerter
* Event Removal (for Events you don't want to see)
* Filter events by Cacti Graph window from Cacti's Graph View pages
* Use of native MySQL and MariaDB database partitioning for larger installs
* Remote Log Server connection capabilities
* Custom column mappings between Remote Log Server and required Syslog columns

## Installation

To install the syslog plugin, simply copy the plugin_sylog directory to Cacti's plugins directory and rename it to simply 'syslog'. Once you have done this, goto Cacti's Plugin Management page, and Install and Enable the plugin. Once this is complete, you can grant users permission to view syslog messages, as well as create Alert, Removal and Report Rules.

If you are upgrading to 2.0 from a prior install, you must first uninstall syslog and insure both the syslog, syslog_removal, and syslog_incoming tables are removed, and recreated at install time.

In addtion, the rsyslog configuration has changed in 2.0.  So, for example, to configure modern rsyslog for Cacti, you must create a file called cacti.conf in the /etc/rsyslog.d/ directory that includes the following:

	--------------------- start /etc/rsyslog.d/cacti.conf ---------------------

	$ModLoad imudp
	$UDPServerRun 514
	$ModLoad ommysql

	$template cacti_syslog,"INSERT INTO syslog_incoming(facility_id, priority_id, program, date, time, host, message) \ 
    values (%syslogfacility%, %syslogpriority%, '%programname%', '%timereported:::date-mysql%', '%timereported:::date-mysql%', '%HOSTNAME%', TRIM('%msg%'))", SQL

	*.* >localhost,my_database,my_user,my_password;cacti_syslog

	--------------------- end /etc/rsyslog.d/cacti.conf ---------------------

Ensure you restart rsyslog after these changes are completed.  Other logging servers such as Syslog-NG are also supported with this plugin.

We are using the pure integer values that rsyslog provides to both the priority and facility in this version syslog, which makes the data collection must less costly for the database.  We have also started including the 'program' syslog column for searching and storage and alert generation.

To setup log forwarding from your network switches and routers, and from your various Linux, UNIX, and other operating system devices, please see their respective documentation.

## Possible Bugs and Feature Enhancements

Bug and feature enhancements for the syslog plugin are handled in GitHub. If you find a first search the Cacti forums for a solution before creating an issue in GitHub.

## Authors

The sylog plugin has been in development for well over a decade with increasing functionality and stibility over that time. There have been several contributors to thold over the years. Chief amonst them are Jimmy Conner, Larry Adams, SideWinder, and Harlequin. We hope that version 2.0 and beyond are the most stable and robust versions of syslog ever published. We are always looking for new ideas. So, this won't be the last release of syslog, you can rest assured of that.

## ChangeLog

--- 2.1  ---
* bug#18: Issues with syslog statistics display
* bug#17: Compatibility with remote database
* bug#19: Removal rules issues
* bug#20: Issues viewing removed records
* bug: SQL for matching Cacti host incorrect
* bug: Syslog Reports were not functional

--- 2.0  ---
* feature: Compatibility with Cacti 1.0

--- 1.30 ---
* feature: Allow Statistics to be disabled
* feature: Allow Processing of Removal Rules on Main Syslog Table
* feature: Cleanup UI irregularities
* feature: Allow purging of old host entries
* bug: Remove syslog 'message' from Log message to prvent deadlock on cacti log syslog processing

--- 1.22  ---
* bug: Upgrade script does not properly handle all conditions
* bug: Strip domain does not always work as expected
* bug: Resizing a page on IE6 caused a loop on the syslog page
* bug: Correct issue where 'warning' is used instead of 'warn' on log insert
* bug: Issue with Plugin Realm naming

--- 1.21 ---
* bug: Fix timespan selector
* bug: Reintroduce Filter time range view
* bug: Syslog Statistics Row Counter Invalid
* feature: Provide option to tag invalid hosts

--- 1.20 ---
* feature: Provide host based statistics tab
* feature: Support generic help desk integration.  Requires customer script
* feature: Support re-alert cycles for all alert type
* feature: Limit re-alert cycles to the max log retention
* feature: Make the default timespan 30 minutes for performance reasons
* bug: sort fields interfering with one another between syslog and alarm tabs
* bug: Message column was date column

--- 1.10 ---
* feature: Allow Syslog to Strip Domains Suffix's.
* feature: Make compatible with earlier versions of Cacti.
* feature: Allow Plugins to extend filtering
* bug: Minor issue with wrong db function being called.
* bug: Legend had Critical and Alert reversed.
* bug: Syslog filter can cause SQL errors
* bug: Wrong page redirect links.
* bug: Partitioning was writing always to the dMaxValue partition
* bug: Emergency Logs were not being highlighted correctly
* bug: Can not add disabled alarm/removal/report rule

--- 1.07 ---
* bug: Rearchitect to improve support mutliple databases
* bug: Don't process a report if it's not enabled.
* bug: Don't process an alert if it's not enabled.
* bug: Don't process a removal rule if it's not enabled.

--- 1.06 ---
* bug#0001854: Error found in Cacti Log
* bug#0001871: Priority dropdown labels in syslog.php for "All Priorities" set to incorrect priority id 
* bug#0001872: Priorities drop drown to show specific value
* bug: Only show one facility in the dropdown
* bug: Hex Errors Upon Install

--- 1.05 ---
* bug: Remove poorly defined security settings
* bug: Don't show actions if you don't have permissions
* bug: Fix page refresh dropdown bug
* feature: Re-add refresh settings to syslog

--- 1.04 ---
* bug#0001824: Syslog icon is not shown in graph view 
* bug: Link on Alarm Log does not properly redirect to 'current' tab
* bug: Unselecting all hosts results in SQL error
* bug: Exporting to CSV not working properly
* compat: Remove deprecated split() command

--- 1.03 ---
* feature: Add alarm host and counts to sms messages
* bug: Fix issue with individual syslog html messages
* bug: Fix creating alarms and removals from the syslog tab
* bug: Fix syslog removal UI with respect to rule type's

--- 1.02 ---
* feature: Add syslog database functions to mitigate issues with same system installs

--- 1.01 ---
* feature: Add alert commands by popular demand
* bug#0001788: missing closing quote in syslog_alerts.php
* bug#0001785: revision 1086 can not save reports when using seperate syslog mysql database

--- 1.0 ---
* feature: Support SMS e-mail messages
* feature: Support MySQL partitioning for MySQL 5.1 and above for performance reasons
* feature: Normalize the syslog table for performance reasons
* feature: Allow editing of Alerts, Removal Rules and Reports
* feature: Priorities are now >= behavior from syslog interface
* feature: Move Altering and Removal menu's to the Console
* feature: Allow specification of foreground/background colors from UI
* feature: Add Walter Zorn's tooltip to syslog messages (www.walterzorn.com)
* feature: Allow the syslog page to be sorted
* feature: Add Removal Rules to simply move log messages to a lower priority table
* feature: Use more Javascript on the Syslog page
* feature: Add HTML e-Mail capability with CSS
* feature: Display Alert Log history from the UI
* feature: Allow Removal Rules to be filtered from the UI
* feature: Add Reporting capability
* feature: Add Threshold Alarms
* feature: Add Alert Severity to Alarms
* feature: Turn images to buttons

--- 0.5.2 ---
* bug: Fixes to make syslog work properly when using the Superlinks plugin
* bug: Fix a few image errors

* --- 0.5.1 ---
* bug: More 0.8.7 Compatibility fixes

--- 0.5 ---
* feature: Modified Message retrieval function to better make use of indexes, which greatly speeds it up
* feature: When adding a removal rule, only that rule will execute immediately, instead of rerunning all rules
* feature: Alert email now uses the Alert Name in the subject
* feature: Add ability to create Reports
* feature: Allow access for the guest account
* feature: Change name to syslog, from haloe
* feature: Use mailer options from the Settings Plugin
* feature: Add option for From Email address and From Display Name
* feature: Use new "api_user_realm_auth" from Plugin Architecture
* bug#0000046 - Event text colors (black) when setup a event color in black
* bug#0000047 - Change the Priority and Levels to be in Ascending order
* bug: Fixes for errors when using removal rules
* bug: Minor fix for error that would sometimes cause Syslog to not be processed
* bug: Update SQL to include indexes
* bug: Fix pagination of Alerts and Removal Rules
* bug: Lots of code / html cleanup for faster pages loads (use a little CSS also)
* bug: Fix for improper display of html entities in the syslog message (thanks dagonet)
* bug: Fix Cacti 0.8.7 compatibility

--- 0.4 ---
* bug#0000034 - Fix for shadow.gif file error in httpd logs.
* bug#0000036 - Syslog plugin causes duplicates if multiple log processors are running at once
* bug#0000037 - Option for max time to save syslog events
* bug: Removed some debugging code

--- 0.3 ---
* feature: Move Processing code to its own file
* feature: Add Debugging to the Processing Code (/debug)
* bug: Fixed an issue with "message" being hard coded
* bug: Fixed a typo in the removal code

--- 0.2 ---
* bug#0000010 Remove use of CURRENT_TIMESTAMP so that Mysql 3.x works again
* bug#0000013 - Fix issues with database names with uncommon characters by enclosing in back-ticks
* bug: Fixed a minor error that caused the graphs page to not refresh
* bug: Modified SQL query in syslog processor to speed things up greatly

--- 0.1 ---
* Initial release
