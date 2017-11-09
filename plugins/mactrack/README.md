# mactrack

The MacTrack plugin is designed to scan network switches, routers and intellegent hubs for connected devices, and record their location either based upon the portname or alias of the switch or hub.  It also attempts to discover the ip address of the mac address from the routers included in the MacTrack database.  MacTrack can also use arpwatch to gether IP to MAC address associations.

MacTrack has the ability to also notify admins or security personnel when the MAC address of a missing or stolen computer re-appears on the network.  This can be helpful in recovering lost equipment.  Through MacTrack's interface monitoring feature, a Network Administrator can get a very good idea of where utilization is, where there are errors, etc within their network.

## Features

* Scans Devices
* Finds Macs
* Associates Macs with their IP's
* Keeps a Nice Inventory of Port Counts
* Finds Stolen/Lost PC's
* Tells You When Someone is Connected Who Shouldn't be

## Prerequisites

The MacTrack on GitHub requiest Cacti 1.0 as a minumum.

## Installation

Just like any Cacti plugin, untar the package to the Cacti plugins directory, rename the directory to 'mactrack', and then from Cacti's Plugin Management interface, Install and Enable the plugin.

## Bugs and Feature Enhancements

Bug and feature enhancements for the webseer plugin are handled in GitHub. If you find a first search the Cacti forums for a solution before creating an issue in GitHub.

## Special Thanks

* Jimmy Conner (cigamit) - For bringing the plugin architecture to the world of Cacti and provding continual support of my development efforts.
* Reinhard Scheck (gandalf) - The European Cacti strongman and Cacti/RRDtool guru.  Thanks for keeping me honest.
* Cacti Users Everywhere - For just using Cacti.  Thanks for giving me a hobby to live for.

## ChangeLog

--- 4.1 ---
* feature: Updates to facilitate i18n by contributors
* issue#25: Unable to remove Site
* issue#34: Ambiguous messages when running the MacTrack poller

--- 4.0 ---
* feature: Cacti 1.0 compatibility 
* feature: i18n MacTrack
* feature: interface backgrounds in CSS
* feature: use jQuery exclusively for dom manipulation
* issue#22: Mactrack devices, 'Save' option missing.
* issue#17: MacTrack Show Site SQL Syntax
* issue#16: MacTrack Undefined indexes and offsets
* issue: MacTrack view Interfaces showed wrong color for interfaces UP.
* issue: MacTrack view MACs 'portname' filter option missing.
* feature: Removed deprecated ifInNUcastPkts and IfOutNUcastPkts and replaced by Ifin/OutMulticastPkt and Ifin/OutBroadcastPkt.
* feature: Updated spanish translations


--- 3.0 ---
* bug#0001838: support partitioned tables mac_track_ports
* bug#0001858: No device filter on Graphs tab
* bug#0001873: hook 'mactrack', 'page_head'
* bug#0001865: rescan doesn't work 
* bug: don't use htmlspecialchars recursively in forms
* bug: make the device removal a global function
* bug: don't generate replace errors if device has an apostrophe
* bug: reorganize API functions to assist third party integrations
* bug: use qstr instead of addslashes as it's not reliable
* feature: Attempt to kill processes if they have run over their allotted time
* feature: Support new Theme engine

--- 2.9 ---
* bug#0001799: Select all checkbox on Mac Addresses tab not working
* bug#0001723: Can't import devices
* bug#0001751: Multiple ignore ports for Extreme Network switch
* bug#0001777: MacTrack MacAuth Filters not working
* bug#0001779: Site IP Range Report View  
* bug#0001841: Mactrack ARP table unreadable
* bug: Saving an edited site redirects to blank page

--- 2.8 ---
* compat: Allow proper navigation text generation
* bug: Guest user could not access site

--- 2.7 ---
* bug#0001742: PHP error () in lib/mactrack_extreme.php
* bug#0001743: DEBUG: Authorized MAC ID: empty
* bug: Correcting SNMPv3 and Cisco support
* bug: Importing OUIDB broken with redefine function error
* bug: Exporting Devices from the Console did not work
* bug: Lastchange was not displaying correctly

--- 2.6 ---
* feature:#0001718: New get_arp_table function for Extreme Networks devices 
* bug#0001665: New line missing when printing Cisco device stats 
* bug#0001668: Mactrack sometimes shows "No results found" when results are shown 
* bug#0001670: spikekill and mactrack JS functions clash stateChanged and getfromserver 
* bug#0001677: index initialization errors (courtesy toe_cutter)
* bug#0001677: unintended overwrite of non-synced devices (courtesy toe_cutter)
* bug#0001717: Ip addresses range report a false value 
* bug: Undefined index when paging through interfaces
* bug: Graph View still being called for one class of graphs incorrectly
* bug: With Scan Date set to 'All' rows counter was not correct for matches
* bug: When viewing IP's, device filter not operable
 
--- 2.5 ---
* bug#0001677: Undefined indexes
* bug: Undefined index reports in mactrak_view_graphs.php
* bug: Small visual issue with Site Details	
* bug: Interfaces table not being created during upgrade
* feature: Portname search filter courtesy KAA and gthe
    
--- 2.4 ---
* bug: 0001546: mactrack_view_devices does not display the proper page numbers 
* bug: 0001545: mactrack_scanner does not complete successfully for some hosts 
* bug: 0001547: mactrack_view_sites does not show page numbers
* bug: 0001548: mactrack_view_macs various issues 
* bug: IEEE Database import runs out of memory
* bug: Correct uninitialized error in mactrack_hp.php
* bug: Resolved issue where Vendor Mac was lost during resolver process
* bug: fix syntax of cacti_snmp_* calls
* feature: 0001638: Allow Interface Data to Be "Scaned" on Demand from the WebUI Similar to Scanner 
* feature: 0001637: Enable Site Level Scanning Through the UI and Using Ajax 
* feature: Adding support for ArpWatch
* feature: Adding Juniper Support
* feature: Support Foundry Dual Mode Ports
* feature: Implement MacWatch in code.  E-Mailing now.
* feature: Adding significant content to the mac_track_interfaces table
* feature: Implement MacAuth functionality and periodic reports
* feature: Implement Interfaces tab to MacTrack
* feature: Aggregated ports patch from Gthe!
* feature: Linux and DLink Scanners from Gthe!
* feature: add all device SNMP options to mac_track_devices
* feature: add full SNMP V3 support
* feature: deprecate readstrings; maintain "SNMP Option Sets" in favour of them
* feature: some Enterasys scanning functions (N7, C2, C3)
* feature: import cacti devices into mac_track_devices (new action hook for host.php) 
* feature: optionally sync SNMP settings of mac_track_devices and cacti device, governed by a config setting (defaulting to "none") to allow either: mactrack -> host (when scanning) or host -> mactrack updates (when manually updating the host)
* feature: allow for "connecting" existing mactrack devices to cacti devices (via hostname, new action for mactrack_devices.php) 
* feature: copy snmp settings from cacti devices to mactrack devices (via host_id connection, new action for mactrack_devices.php)
* feature: Allow mapping of Cacti graphs to MacTrack
* feature: Add columns for AutoNegotiation - Implementation TBD

--- 1.1 ---
* First Official Release

--- 0.1 ---
* Initial Release!  Oh, so long ago
