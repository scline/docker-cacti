$(function() {
	$('#snmp_version').change(function() {
		setSNMP();
	}).change();
});

function setSNMP() {
	snmp_version = $('#snmp_version').val();
	switch(snmp_version) {
	case '0': // No SNMP
		$('#row_snmp_username').hide();
		$('#row_snmp_password').hide();
		$('#row_snmp_readstring').hide();
		$('#row_snmp_readstrings').hide();
		$('#row_snmp_auth_protocol').hide();
		$('#row_snmp_priv_passphrase').hide();
		$('#row_snmp_priv_protocol').hide();
		$('#row_snmp_context').hide();
		$('#row_snmp_engine_id').hide();
		$('#row_snmp_port').hide();
		$('#row_snmp_timeout').hide();
		$('#row_snmp_retries').hide();
		$('#row_max_oids').hide();
		break;
	case '1': // SNMP v1
	case '2': // SNMP v2c
		$('#row_snmp_username').hide();
		$('#row_snmp_password').hide();
		$('#row_snmp_readstring').show();
		$('#row_snmp_readstrings').show();
		$('#row_snmp_auth_protocol').hide();
		$('#row_snmp_priv_passphrase').hide();
		$('#row_snmp_priv_protocol').hide();
		$('#row_snmp_context').hide();
		$('#row_snmp_engine_id').hide();
		$('#row_snmp_port').show();
		$('#row_snmp_timeout').show();
		$('#row_snmp_retries').show();
		$('#row_max_oids').show();
		break;
	case '3': // SNMP v3
		$('#row_snmp_username').show();
		$('#row_snmp_password').show();
		$('#row_snmp_readstring').hide();
		$('#row_snmp_readstrings').hide();
		$('#row_snmp_auth_protocol').show();
		$('#row_snmp_priv_passphrase').show();
		$('#row_snmp_priv_protocol').show();
		$('#row_snmp_context').show();
		$('#row_snmp_engine_id').show();
		$('#row_snmp_port').show();
		$('#row_snmp_timeout').show();
		$('#row_snmp_retries').show();
		$('#row_max_oids').show();
		break;
	}
}
