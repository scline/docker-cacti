var url

function scan_device(device_id) {
	url=urlPath+'plugins/mactrack/mactrack_ajax_admin.php?action=rescan&device_id='+device_id
	$('#r_'+device_id).attr('src', 'images/view_busy.gif');
	$.get(url, function(data) {
		reply     = data.split('!!!!')
		type      = reply[0]
		device_id = reply[1]
		content   = reply[2]
		$('#r_'+device_id).attr('src', 'images/rescan_site.gif');
		$('#response').html(content);
	});
}

function site_scan(site_id) {
	url=urlPath+'plugins/mactrack/mactrack_ajax_admin.php?action=site_scan&site_id='+site_id;
	$('#r_'+site_id).attr('src', urlPath+'plugins/mactrack/images/view_busy.gif');
	$.get(url, function(data) {
		reply     = data.split('!!!!')
		type      = reply[0]
		site_id   = reply[1]
		content   = reply[2]
		$('#r_'+site_id).attr('src', 'images/rescan_site.gif');
		$('#response').html(content);
	});
}

function scan_device_interface(device_id, ifName) {
	url=urlPath+'plugins/mactrack/mactrack_ajax_admin.php?action=rescan&device_id='+device_id+'&ifName='+ifName;
	$('#r_'+device_id+'_'+ifName).attr('src', urlPath+'plugins/mactrack/images/view_busy.gif');
	$.get(url, function(data) {
		reply     = data.split('!!!!')
		type      = reply[0]
		device_id = reply[1]
		ifName    = reply[2]
		content   = reply[3]
		$('#r_'+device_id+'_'+ifName).attr('src', 'images/rescan_device.gif');
		$('#response').html(content);
	});
}

function clearScanResults() {
	$('#response').html('');
}

function disable_device(device_id) {
	url=urlPath+'plugins/mactrack/mactrack_ajax_admin.php?action=disable&device_id='+device_id;
	$.get(url, function(data) {
		reply     = data.split('!!!!')
		type      = reply[0]
		device_id = reply[1]
		content   = reply[2]
		$('#row_'+device_id).html(content);
	});
}

function enable_device(device_id) {
	url=urlPath+'plugins/mactrack/mactrack_ajax_admin.php?action=enable&device_id='+device_id;
	$.get(url, function(data) {
		reply     = data.split('!!!!')
		type      = reply[0]
		device_id = reply[1]
		content   = reply[2]
		$('#row_'+device_id).html(content);
	});
}
