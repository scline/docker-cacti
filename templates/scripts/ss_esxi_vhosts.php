<?php
$no_http_headers = true;

/* display No errors */
error_reporting(E_ERROR);
include_once(dirname(__FILE__) . "/../include/config.php");
include_once(dirname(__FILE__) . "/../lib/snmp.php");

if (!isset($called_by_script_server)) {
	array_shift($_SERVER["argv"]);
	print call_user_func_array("ss_esxi_vhosts", $_SERVER["argv"]);
}

function ss_esxi_vhosts($hostname,$snmp_community,$snmp_port,$snmp_timeout) {
	$oids = array(
    	    "vh" 		=> ".1.3.6.1.4.1.6876.2.1.1.2",
            "vh_state"     	=> ".1.3.6.1.4.1.6876.2.1.1.6",
            "vh_tools"     	=> ".1.3.6.1.4.1.6876.2.1.1.4"
	);

	$vh_tools_run = 0;
	$vh_state = 0;
	$vh_tools_ninst = 0;
	$vh_tools_run = 0;
	$vh_tools_nrun = 0;

	$array = cacti_snmp_walk("$hostname", "$snmp_community",$oids["vh_state"], 1, "","", "","", "", "", $snmp_port, $snmp_timeout, 2, 20,SNMP_POLLER);
	$vhosts = count($array);

	$array = cacti_snmp_walk($hostname, $snmp_community,$oids["vh_state"], 1, "","", "","", "", "", $snmp_port, $snmp_timeout, 2, 20,SNMP_POLLER);

	foreach ($array as $key => $value)	{
	    if (strtolower(trim($value["value"])) == "powered on" || strtolower(trim($value["value"])) == "poweredon" )
		$vh_state++;
	}

	$array = cacti_snmp_walk($hostname, $snmp_community,$oids["vh_tools"], 1, "","", "","", "", "", $snmp_port, $snmp_timeout, 2, 20,SNMP_POLLER);
//var_dump($array);
	foreach ($array as $key => $value)	{
	    if (strpos ($value["value"], "not installed") )
		$vh_tools_ninst++;
	    elseif (strpos ($value["value"], "not running") !== false)
	        $vh_tools_nrun++;
	    else
		$vh_tools_run++;
	}

        // cacti_snmp_get(hostname, snmp_community, oid, snmp_version, 
	// snmp_auth_username,snmp_auth_password, snmp_auth_protocol, snmp_priv_passphrase,
	// snmp_priv_protocol, snmp_context, snmp_port, snmp_timeout, ping_retries, max_oids ,SNMP_POLLER);
	// example -  ps_total:56 ps_i:5 ps_d:9\n

	return("vh:$vhosts vh_state:$vh_state tools_run:$vh_tools_run tools_nrun:$vh_tools_nrun tools_ninst:$vh_tools_ninst\n");
}
?>
