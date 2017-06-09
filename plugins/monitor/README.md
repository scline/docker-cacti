# monitor

This plugin allows you to view at a glance all your critical Cacti hosts, and will alert you audibly and via Email when a Device or Devices go down.

Some audio clips have been added to this plugin courtesy of SoundBible.com and are licensed under Creative Commons and other Free to use Licenses.  If you create an MP3 file called 'First Order Chorus.mp3' and place it in the sound folder, you may get a surprise if you choose to use it.

The Monitor plugin has been around for well over a decade.  This very old plugin has recently gone through major recent enhancements through it's lifetime.  The version of Monitor included with Cacti 1.0 is almost unrecognizable from earlier versions of the plugin.  It's is essentially a finished project, though if there are any feature requests, we won't push them away.

## Features

* Data Center Dashboard
* Audible and Visual Alerting
* Respects Cacti's user permissions
* Monitoring can be enabled or disabled at the Device level
* Supports Monitoring Devices by Criticality

## Installation

To install the Monitor plugin, simply copy the plugin_monitor directory to Cacti's plugins directory and rename it to simply 'monitor'. Once you have done this, goto Cacti's Plugin Management page, Install and Enable the webseer. Once this is complete, you can grant users permission to view the Monitor tab.

It would be advisable to view Monitors email notification settings under Settings in Cacti.  Monitor is configured from the Monitor tab in Settings, and Devices can have their Criticality in the Cacti Device Management page.  Monitor includes a Device filter to show Devices of differing criticalities.

## Bugs and Feature Enhancements

Bug and feature enhancements for the webseer plugin are handled in GitHub. If you find a first search the Cacti forums for a solution before creating an issue in GitHub.

## Changelog

--- 2.1 ---
* feature: Convert Monitor to use CSS for skin developers

--- 2.0 ---
* feature: Support for Cacti 1.0
* feature: Complete redesign using font awesome
* feature: Allow specification of device criticality
* feature: Allow specification of warning and alert ping round trip latency number
* feature: All GUI interactions using ajax
* feature: Specification of settings using filter
* feature: Save user based settings
* feature: Using new Cacti permissions system
* feature: Generalized code cleanup
* feature: Integrate better with Thold and Syslog

--- 1.3 ---
* compat: Fix general header

--- 1.2 ---
* bug#0001654: a little update for monitor plugin 
* bug: Correct some undefined offset errors

--- 1.1 ---
* compat: Allow proper navigation text generation
* bug: User would see console as a pick even if they were guest
* bug: Mute button does not work properly
* bug: Text fields in  MySQL can not include a default

--- 1.0 ---
* feature: Add Grouping by Host Template
* feature: Adding 0.8.7f features

--- 0.9 ---
* compat: Monitor is now only PA 2.0 compatible
* bug: Fix for mass enabling / disabling monitoring of hosts

--- 0.8.2 ---
* feature: Change from JS Status popup to pure CSS
* feature: Remove "Details" view

--- 0.8.1 ---
* feature: Fix compatibility issue between monitor and thold v0.3.6

--- 0.8 ---
* feature: Add a muted icon to show what hosts are currently muted
* feature: Only show hosts that have had at least 2 pollings.  This stops it from alerting on new hosts that haven't been properly polled yet.
* feature: All the selecting of "None" as a sound to not have it play an alert
* feature: Move from using cookies to using session variables
* feature: Allow the display of a Host Down Message
* bug: Add fix for not showing disabled thresholds
* bug: Lots of code cleanup
* feature: Add a patch by fri that allows grouping by tree / header and does user auth checking
* feature: Use new "api_user_realm_auth" from Plugin Architecture
* feature: Display host down time under the hostname
* bug: Fix Cacti 0.8.7 compatibility

--- 0.7 ---
* bug#0000044 - Modify device image to link to device's graphs
* bug#0000052 - If the Threshold plugin is running, change the host color to Orange if a threshold is breached
* feature: Add option to select an different alarm sound from the available wav and mp3 files in the sounds directory
* feature: Update tab image to better resemble the original cacti images
* feature: Add option to add an icon legend to the Monitor display
* feature: Moved sounds to their own folder
* bug: Fixes to the fast poller

--- 0.6 ---
* feature: Allow guest access to the Monitor Tab
* bug#0000013 - Fix issues with database names with uncommon characters by enclosing in back-ticks

--- 0.5 ---
* bug: Fixed an issue with the mute button action url (thanks Tut'!)
* bug: Fixed an issue with the monitor page and includes
* bug: Fixed an issue with the Fast Poller paths

--- 0.4 ---
* feature: Added Javscript Mouseover Tooltips.  This replaces the title attribute which I used before (Newlines didn't work in Firefox)

--- 0.3 ---
* feature: Added Settings for Refresh Rate and Width of Hosts See Settings >> Misc
* feature: Added Faster Poller so that you can know immediately if something is down
           
--- 0.2 ---
* bug: Fix for navigational line

--- 0.1 ---
* Initial release
