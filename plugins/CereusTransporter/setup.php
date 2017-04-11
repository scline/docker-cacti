<?php
	/*******************************************************************************
	 *
	 * File:         $Id: setup.php,v 5960bc2c8471 2016/01/21 11:08:40 ThomasUrban $
	 * Modified_On:  $Date: 2016/01/21 11:08:40 $
	 * Modified_By:  $Author: ThomasUrban $
	 * Copyright:    Copyright 2009/2016 by Urban-Software.de / Thomas Urban
	 *******************************************************************************/

	// autoload dependencies
	require __DIR__ . '/vendor/autoload.php';

	// Include the target databases/storages
	include_once ( 'includes/bosun.php');
	include_once ( 'includes/opentsdb.php');
	include_once ( 'includes/influxdb.php');
	if ( function_exists( 'mssql_connect' ) ) {
		include_once ( 'includes/mssql.php');
	}
	/**
	 *
	 */
	function plugin_CereusTransporter_install()
	{
		api_plugin_register_hook( 'CereusTransporter', 'poller_top', 'CereusTransporter_poller_top', 'setup.php' );
		api_plugin_register_hook( 'CereusTransporter', 'poller_bottom', 'CereusTransporter_poller_bottom', 'setup.php' );
		api_plugin_register_hook( 'CereusTransporter', 'poller_output', 'CereusTransporter_poller_output', 'setup.php' );
		api_plugin_register_hook( 'CereusTransporter', 'console_after', 'CereusTransporter_console_after', 'setup.php' );
		api_plugin_register_hook( 'CereusTransporter', 'config_settings', 'CereusTransporter_config_settings', 'setup.php' );
		api_plugin_register_hook( 'CereusTransporter', 'poller_on_demand', 'CereusTransporter_poller_on_demand', 'setup.php' );
		CereusTransporter_setup_table_new();
	}

	function CereusTransporter_poller_on_demand( $results ) {
		if ( read_config_option( 'cereus_transporter_disable_poller' ) == TRUE ) {
			return false;
		}
		return true;
	}

	function CereusTransporter_poller_top()
	{
		if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_DEBUG ) {
			cacti_log( "DEBUG: Preparing for the next pass, deleting all data from plugin_CereusTransporter_data (if there was any)", TRUE, "CereusTransporter" );
		}
		db_execute( "DELETE FROM `plugin_CereusTransporter_data`" );
	}

	/**
	 * @param $rrd_update_array
	 *
	 * @return mixed
	 */
	function CereusTransporter_poller_output( $rrd_update_array )
	{
		foreach ( $rrd_update_array as $item ) {
			if ( is_array( $item ) ) {
				if ( array_key_exists( 'times', $item ) ) {
					if ( array_key_exists( key( $item[ 'times' ] ), $item[ 'times' ] ) ) {
						$array = $item[ 'times' ][ key( $item[ 'times' ] ) ];
						while ( list ( $key, $val ) = each( $array ) ) {
							if ( strlen( $key ) > 0 ) {
								db_execute( "INSERT INTO `plugin_CereusTransporter_data` (`timestamp`, `local_data_id`, `key`, `value`) VALUES ('" . key( $item[ 'times' ] ) . "'," . $item[ 'local_data_id' ] . ",'" . $key . "','" . $val . "') " );
							}
						}
					}
				}
			}
		}
		return $rrd_update_array;
	}

	function CereusTransporter_send_data( $data_array )
	{
		$db_type = read_config_option( 'cereus_transporter_dbtype' );

		// curl for bosun and opentsdb
		$curl = NULL;

		// init request
		switch ( $db_type ) {
			case 'influxdb':
				CereusTransporter_influxdb_send_data( $data_array );
				break;
			case 'opentsdb':
				CereusTransporter_opentsdb_send_data( $data_array);
				break;
			case 'bosun':
				CereusTransporter_bosun_send_data( $data_array );
				break;
			case 'bmssql':
				CereusTransporter_mssql_send_data( $data_array );
				break;
		}
		return;
	}

	function CereusTransporter_poller_bottom()
	{
		$db_type = read_config_option( 'cereus_transporter_dbtype' );
		if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_LOW ) {
			cacti_log( "INFO: Adding data to $db_type", TRUE, "CereusTransporter" );
		}

		/* take time and log performance data */
		$transport_stats           = array();
		$transport_stats[ 'Time' ] = ''; // init this parameter early, making it first in stats
		$start                     = CereusTransporter_currentTime();

		// populate field index type info
		$field_lookup_query = <<<EOT
    SELECT
     ds.id
     ,MAX(CASE WHEN type_code='index_type' THEN input.value END) index_type
     ,MAX(CASE WHEN type_code='index_value' THEN input.value END) index_value
    FROM data_template_data data
    INNER JOIN data_local ds ON ds.id=data.local_data_id
    INNER JOIN data_input_fields field ON field.data_input_id=data.data_input_id AND field.input_output='in' AND field.type_code IN ('index_type', 'index_value')
    INNER JOIN data_input_data input ON input.data_template_data_id=data.id AND input.data_input_field_id=field.id 
    WHERE 
     data.local_data_template_data_id <> 0
EOT;
		$field_lookup       = db_fetch_assoc( $field_lookup_query );
		$field_info         = array();
		foreach ( $field_lookup as $dsfield ) {
			if ( isset( $dsfield[ 'index_type' ] ) ) {
				$field_info[ $dsfield[ 'id' ] ][ 'index_type' ]  = CereusTransporter_cleanTag( $db_type, $dsfield[ 'index_type' ] );
				$field_info[ $dsfield[ 'id' ] ][ 'index_value' ] = CereusTransporter_cleanTag( $db_type, $dsfield[ 'index_value' ] );
			}
		}

		// populate lookup data - hostname, index and various info for each ds
		// if host template is empty, replacing it with the first found host template by id in db

		$pollingTime_sql_statement = '';
		if ( read_config_option( "cereus_transporter_use_modified_spine" ) == 'true' ) {
			$pollingTime_sql_statement = ',host.polling_time as polling_time';
		}
		$ds_lookup_query = <<<EOT
    SELECT
     ds.id
     ,host.hostname
     ,host.description
     ,data.name_cache
     ,(CASE WHEN rrd.data_source_type_id=1 THEN 'gauge' WHEN rrd.data_source_type_id=2 THEN 'counter' WHEN rrd.data_source_type_id=3 THEN 'counter' WHEN rrd.data_source_type_id=4 THEN 'counter' END) AS rate
     ,data_template.name AS metric
     ,host_template.name AS host_type
     $pollingTime_sql_statement
    FROM data_template_data data
    INNER JOIN data_local ds ON ds.id=data.local_data_id
    INNER JOIN host ON host.id=ds.host_id
    INNER JOIN host_template ON host_template.id=(CASE host.host_template_id WHEN 0 THEN (SELECT id FROM host_template ORDER BY id LIMIT 1) ELSE host.host_template_id END)
    INNER JOIN data_template ON data_template.id=data.data_template_id 
    INNER JOIN data_template_rrd rrd ON rrd.local_data_id=data.local_data_id
    WHERE 
     data.local_data_template_data_id <> 0
     AND host.disabled <> 'on'
    GROUP BY ds.id
EOT;
		$ds_lookup       = db_fetch_assoc( $ds_lookup_query );
		$ds_info         = array();
		foreach ( $ds_lookup as $ds ) {
			$ds_id                                = $ds[ 'id' ];
			$ds_info[ $ds_id ][ 'cacti_data_id' ] = $ds[ 'id' ];
			$ds_info[ $ds_id ][ 'collector' ]     = 'cacti';
			$ds_info[ $ds_id ][ 'hostname' ]      = $ds[ 'hostname' ];
			$ds_info[ $ds_id ][ 'description' ]   = CereusTransporter_cleanTag( $db_type, $ds[ 'description' ] );
			$host                                 = CereusTransporter_cleanTag( $db_type, CereusTransporter_host( $ds[ "hostname" ], $ds[ "description" ] ) );
			$ds_info[ $ds_id ][ 'host' ]          = strlen( $host ) > 0 ? $host : $ds[ 'hostname' ];
			$ds_info[ $ds_id ][ 'host_type' ]     = CereusTransporter_cleanTag( $db_type, $ds[ 'host_type' ] );
			$ds_info[ $ds_id ][ 'metric' ]        = CereusTransporter_templateToMetric( $ds[ 'metric' ] );
			$ds_info[ $ds_id ][ 'metric_text' ]   = $ds[ 'metric' ];
			$ds_info[ $ds_id ][ 'rate' ]          = $ds[ 'rate' ];
			$ds_info[ $ds_id ][ 'namecache' ]     = CereusTransporter_cleanTag( $db_type, $ds[ 'name_cache' ] );
			// custom variable with host polling time info, in modified spine only
			if ( isset( $ds[ 'polling_time' ] ) ) {
				$ds_info[ $ds_id ][ 'polling_time' ] = $ds[ 'polling_time' ];
			}
			if ( array_key_exists( $ds_id, $field_info ) && array_key_exists( 'index_type', $field_info[ $ds_id ] ) && is_null( $ds_info[ $ds_id ][ 'index_type' ] ) ) {
				$ds_info[ $ds_id ][ 'index_type' ]  = $field_info[ $ds_id ][ 'index_type' ];
				$ds_info[ $ds_id ][ 'index_value' ] = $field_info[ $ds_id ][ 'index_value' ];
			}
			// clean empty entries
			$ds_info[ $ds_id ] = array_filter( $ds_info[ $ds_id ] );
		}
		$transport_stats[ 'LookupTableSize' ] = sizeof( $ds_info );
		if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_HIGH ) {
			cacti_log( "DEBUG: " . $db_type . " Lookup table size: [" . sizeof( $ds_info ) . "]", TRUE, "CereusTransporter" );
		}

		// graph requests are incredibly slow, caching units information too
		$units_lookup_query = <<<EOT
    SELECT DISTINCT
     rrd.local_data_id
     ,rrd.data_source_name
     ,gtg.vertical_label
    FROM graph_templates_item gti
    INNER JOIN data_template_rrd rrd ON rrd.id=gti.task_item_id
    INNER JOIN graph_templates_graph gtg ON gtg.local_graph_id=gti.local_graph_id
    WHERE 
     gti.local_graph_template_item_id <> 0
     AND gtg.vertical_label <> ''
EOT;
		$units_lookup       = db_fetch_assoc( $units_lookup_query );
		$units              = array();
		foreach ( $units_lookup as $unit ) {
			if (
				( array_key_exists(  $unit[ 'local_data_id' ], $units) == false ) ||
				( is_null( $units[ $unit[ 'local_data_id' ] ] ) )
			) {
				$units[ $unit[ 'local_data_id' ] ] = array();
			}
			$units[ $unit[ 'local_data_id' ] ][ $unit[ 'data_source_name' ] ] = $unit[ 'vertical_label' ];
		}
		$transport_stats[ 'LookupUnitsTableSize' ] = sizeof( $units );
		if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_HIGH ) {
			cacti_log( "DEBUG: " . $db_type . " Units lookup table size: [" . sizeof( $units ) . "]", TRUE, "CereusTransporter" );
		}

		// Retrieve data from CereusTransporter
		$transport_stats[ 'MetricsCount' ] = db_fetch_cell( "SELECT COUNT(local_data_id) FROM plugin_CereusTransporter_data" );
		$polling_data                      = db_fetch_assoc( "SELECT `timestamp`, `local_data_id`, `key`, `value` FROM plugin_CereusTransporter_data ORDER BY `timestamp`,`local_data_id`,`key`" );
		// Clean
		db_execute( "DELETE FROM `plugin_CereusTransporter_data`" );

		// Initialize data 
		$old_hostname = '';
		$data_array   = array();
		$timestamp    = '';
		$host_name    = '';
		$host_times   = array();

		$host_start_time = CereusTransporter_currentTime();

		foreach ( $polling_data as $item ) {
			if ( $item[ 'timestamp' ] > 0 ) {
				$key       = $item[ 'key' ];
				$val       = $item[ 'value' ];
				$timestamp = $item[ 'timestamp' ];
				$id        = $item[ 'local_data_id' ];
				if ( is_numeric( $val ) ) {
					$host_name = $ds_info[ $id ][ 'hostname' ];
					if ( $old_hostname <> $host_name ) {
						if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_DEBUG ) {
							cacti_log( "DEBUG: Adding the following data to $db_type: [" . $timestamp . "] [" . $old_hostname . "]", TRUE, "CereusTransporter" );
						}

						if ( strlen( $host_name ) < 1 ) {
							cacti_log( "Empty hostname for datasource #[" . $id . "]", TRUE, "CereusTransporter" );
						}

						if ( strlen( $old_hostname ) > 0 ) {
							// Send data for old host:
							CereusTransporter_send_data( $data_array );
						}

						// Reset/Initialize variables
						$host_times[ $old_hostname ] = round( CereusTransporter_currentTime() - $host_start_time, 4 );
						if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_DEBUG ) {
							cacti_log( "DEBUG: Finished adding the data to $db_type: [" . $old_hostname . "] [Time: " . $host_times[ $old_hostname ] . "]", TRUE, "CereusTransporter" );
						}

						$host_start_time = CereusTransporter_currentTime();
						$old_hostname    = $host_name;
						$data_array      = array();
					}
					$point[ 'metric' ]         = $ds_info[ $id ][ 'metric' ];
					$point[ 'timestamp' ]      = (int)$timestamp;
					$point[ 'value' ]          = $val;  // Removed (int) before $val. InfluxDB can handle floats as well.
					$point[ 'tags' ]           = $ds_info[ $id ];
					$point[ 'tags' ][ 'type' ] = $key;
					$unit                      = NULL;
					// add units info
					if ( isset( $units[ $id ] ) && isset( $units[ $id ][ $key ] ) ) {
						$point[ 'tags' ][ 'units' ] = $units[ $id ][ $key ];
					}

					unset( $point[ 'tags' ][ 'metric' ] ); // delete unwanted data
					unset( $point[ 'tags' ][ 'id' ] ); // delete unwanted data

					// push point to array unconditionally, to avoid skipping data
					$data_array[] = $point;
				}
			}
		}

		// Send last data item:
		if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_DEBUG ) {
			cacti_log( "DEBUG: Adding the following data to " . $db_type . ": [" . $timestamp . "] [" . $host_name . "]", TRUE, "CereusTransporter" );
		}
		// Send data to database:
		CereusTransporter_send_data( $data_array );
		$host_times[ $old_hostname ] = round( CereusTransporter_currentTime() - $host_start_time, 2 );

		// log performance data
		$transport_stats[ 'Time' ]            = sprintf( "%01.2f", CereusTransporter_currentTime() - $start );
		$transport_stats[ 'AverageHostTime' ] = sprintf( "%01.2f", array_sum( $host_times ) / count( $host_times ) );
		asort( $host_times );
		$slowest_hosts = array_slice( $host_times, -4 );
		arsort( $slowest_hosts );
		$transport_stats[ 'SlowestHostnames' ] = '[';
		foreach ( $slowest_hosts as $hostname => $time ) {
			$transport_stats[ 'SlowestHostnames' ] .= $hostname . ': ' . sprintf( "%01.3f", $time ) . 's, ';
		}
		$transport_stats[ 'SlowestHostnames' ] = rtrim( $transport_stats[ 'SlowestHostnames' ], ', ' ) . ']';
		$transport_stats_text                  = '';
		foreach ( $transport_stats as $data => $value ) {
			$transport_stats_text .= $data . ':' . $value . ' ';
		}
		$transport_stats_text = rtrim( $transport_stats_text, '; ' );
		if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_LOW ) {
			cacti_log( "STATS: " . $transport_stats_text, TRUE, "CereusTransporter" );
		}
	}


	/**
	 * Returns strings with space, comma, or equals sign characters backslashed per Influx write protocol syntax
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	function CereusTransporter_addShalshes( $value )
	{
		$value = str_replace( ' ', '\ ', $value );
		$value = str_replace( ',', '\,', $value );
		$value = str_replace( '=', '\=', $value );
		return $value;
	}

	/**
	 * Cleans white space and unprintable characters from string
	 *
	 * @param string $db_type
	 * @param string $value
	 *
	 * @return string
	 */
	function CereusTransporter_cleanTag( $db_type, $value )
	{
		if ( $db_type == 'influxdb' ) {
			return CereusTransporter_addShalshes( $value );
		}
		$value = trim( $value );
		$value = preg_replace( "/[^A-Za-z0-9_\/\-]+/", '_', $value );
		$value = trim( $value, '_' );
		return $value;
	}

	/**
	 * Finds host name by hostname and host description ('10.10.10.10 Hostname' and 'Hostname 10.10.10.10' formats)
	 *
	 * @param string $hostname
	 * @param string $description
	 *
	 * @return string
	 */
	function CereusTransporter_host( $hostname, $description )
	{
		if ( filter_var( $hostname, FILTER_VALIDATE_IP ) && strpos( $description, $hostname ) !== FALSE ) {
			return preg_replace( '/\s*' . preg_quote( $hostname, '/' ) . '\s*/', '', $description );
		}
		return $hostname;
	}

	/**
	 * Replaces Data Template name with like.this.notation
	 *
	 * @param string $value
	 *
	 * @return string
	 */
	function CereusTransporter_templateToMetric( $value )
	{
		$value = trim( $value );
		$value = preg_replace( "/[^A-Za-z0-9_\.]+/", '.', $value );
		$value = rtrim( $value, '.' );
		$value = strtolower( $value );
		return $value;
	}

	function CereusTransporter_currentTime()
	{
		list( $micro, $seconds ) = explode( " ", microtime() );
		return $seconds + $micro;
	}

	/**
	 *
	 */
	function plugin_CereusTransporter_uninstall()
	{
		// Do any extra Uninstall stuff here
		return;
	}


	/**
	 * @return bool
	 */
	function plugin_CereusTransporter_check_config()
	{
		// Here we will check to ensure everything is configured
		CereusTransporter_check_upgrade();

		return TRUE;
	}

	/**
	 * @return bool
	 */
	function plugin_CereusTransporter_upgrade()
	{
		// Here we will upgrade to the newest version
		CereusTransporter_check_upgrade();
		return FALSE;
	}


	/**
	 *
	 */
	function CereusTransporter_check_upgrade()
	{
		global $config;

		$files = array( 'index.php', 'plugins.php' );
		if ( isset( $_SERVER[ 'PHP_SELF' ] ) && !in_array( basename( $_SERVER[ 'PHP_SELF' ] ), $files ) ) {
			return;
		}

		$current = plugin_CereusTransporter_version();
		$current = $current[ 'version' ];
		$old     = db_fetch_row( "SELECT * FROM plugin_config WHERE directory='CereusTransporter'" );
		if ( sizeof( $old ) && $current != $old[ "version" ] ) {
			/* if the plugin is installed and/or active */
			if ( $old[ "status" ] == 1 || $old[ "status" ] == 4 ) {
				/* re-register the hooks */
				plugin_CereusTransporter_install();

				/* perform a database upgrade */
				if ( $old[ 'version' ] < 0.45 ) {
					db_execute( "DROP TABLE `plugin_CereusTransporter_data`" );
					CereusTransporter_setup_table_new();
				}
			}

			/* update the plugin information */
			$info = plugin_CereusTransporter_version();
			$id   = db_fetch_cell( "SELECT id FROM plugin_config WHERE directory='CereusTransporter'" );
			db_execute( "UPDATE plugin_config
				SET name='" . $info[ "longname" ] . "',
				author='" . $info[ "author" ] . "',
				webpage='" . $info[ "homepage" ] . "',
				version='" . $info[ "version" ] . "'
				WHERE id='$id'" );
		}

		return;
	}

	/**
	 * @return bool
	 */
	function CereusTransporter_check_dependencies()
	{
		return TRUE;
	}

	function CereusTransporter_config_settings()
	{
		global $tabs, $settings;

		$tabs[ "misc" ] = "Misc";

		$temp = array(
			"cereus_transporter_header"             => array(
				"friendly_name" => "CereusTransporter Settings",
				"method"        => "spacer",
			),
			"cereus_transporter_dbtype"             => array(
				"friendly_name" => "Database target type",
				"description"   => "The type of database being used for exporting the data to.",
				"method"        => "drop_array",
				"default"       => "influxdb",
				"array"         => array(
					"influxdb" => "InfluxDB",
					"opentsdb" => "OpenTSDB",
					"bosun"    => "Bosun"
					// "mssql"    => "Microsoft SQL Server (BETA)"
				)
			),
			"cereus_transporter_db_fullurl"         => array(
				"friendly_name" => "Target URL/System",
				"description"   => "Full Database URL. Example: influxdb://user:password@localhost:8086/dbname for InfluxDB, https://user:password@localhost:8070/ for Bosun",
				"method"        => "textbox",
				"default"       => "",
				"max_length"    => 1024,
			),
			"cereus_transporter_use_modified_spine" => array(
				"friendly_name" => "Use modified spine",
				"description"   => "Use the modified spine extra fields (host.polling_time).",
				"method"        => "drop_array",
				"default"       => "false",
				"array"         => array(
					"false" => "False",
					"true"  => "True"
				)
			),
			"cereus_transporter_disable_poller" => array(
				"friendly_name" => "Disable the RRD Updates",
				"description"   => "Disables the rrd updates, but keeps the poller running.",
				"method"        => "drop_array",
				"default"       => "false",
				"array"         => array(
					FALSE => "False",
					TRUE  => "True"
				)
	       )
		);

		if ( function_exists( 'mssql_connect' ) ) {
			if ( read_config_option('cereus_transporter_dbtype') == 'mssql' ) {
				$temp_mssql = array(
					"cereus_transporter_db_mssql_server" => array(
						"friendly_name" => "MS SQL Server IP/Hostname",
						"description"   => "MS SQL Server IP/Hostname",
						"method"        => "textbox",
						"default"       => "",
						"max_length"    => 1024,
					),
					"cereus_transporter_db_mssql_port" => array(
						"friendly_name" => "MS SQL Server Port",
						"description"   => "MS SQL Server Port to connect to",
						"method"        => "textbox",
						"default"       => "",
						"max_length"    => 1024,
					),
					"cereus_transporter_db_mssql_instance" => array(
						"friendly_name" => "MS SQL Server Instance Name",
						"description"   => "The MS SQL Server Instance name to connect",
						"method"        => "textbox",
						"default"       => "",
						"max_length"    => 1024,
					),
					"cereus_transporter_db_mssql_database" => array(
						"friendly_name" => "MS SQL Server Database to be used",
						"description"   => "The database containing the target table",
						"method"        => "textbox",
						"default"       => "",
						"max_length"    => 1024,
					),
					"cereus_transporter_db_mssql_table" => array(
						"friendly_name" => "MS SQL Server Table to be used",
						"description"   => "The table being used to store the data into",
						"method"        => "textbox",
						"default"       => "",
						"max_length"    => 1024,
					),
					"cereus_transporter_db_mssql_username" => array(
						"friendly_name" => "MS SQL Server User",
						"description"   => "The username being used to connect to the MS SQL Server",
						"method"        => "textbox",
						"default"       => "",
						"max_length"    => 1024,
					),
					"cereus_transporter_db_mssql_password" => array(
						"friendly_name" => "MS SQL Server Password",
						"description"   => "The password being used to connect to the MS SQL Server",
						"method"        => "textbox_password",
						"default"       => "",
						"max_length"    => 1024,
					)
				);
				unset ($temp[ "cereus_transporter_db_fullurl" ]);
				$temp = array_merge( $temp, $temp_mssql);
			}
		} else {
			$temp[ "cereus_transporter_dbtype" ] = array(
				"friendly_name" => "Database target type",
				"description"   => "The type of database being used for exporting the data to.",
				"method"        => "drop_array",
				"default"       => "influxdb",
				"array"         => array(
					"influxdb" => "InfluxDB",
					"opentsdb" => "OpenTSDB",
					"bosun"    => "Bosun"
				)
			);
		}

		if ( isset( $settings[ "misc" ] ) ) {
			$settings[ "misc" ] = array_merge( $settings[ "misc" ], $temp );
		}
		else {
			$settings[ "misc" ] = $temp;
		}
	}



	function plugin_CereusTransporter_version() {
		global $config;
		$info = parse_ini_file($config['base_path'] . '/plugins/CereusTransporter/INFO', true);
		return $info['info'];
	}

	/**
	 *
	 */
	function CereusTransporter_console_after()
	{
		// Here we will upgrade to the newest version
		CereusTransporter_check_upgrade();

		return;
	}


	/**
	 *
	 */
	function CereusTransporter_setup_table()
	{
		return;
	}

	function CereusTransporter_setup_table_new()
	{
		global $config, $database_default;
		include_once( $config[ "library_path" ] . "/database.php" );

		$data                = array();
		$data[ 'columns' ][] = array( 'name'    => 'timestamp', 'type' => 'varchar(1024)', 'NULL' => FALSE,
		                              'default' => '0' );
		$data[ 'columns' ][] = array( 'name'    => 'local_data_id', 'type' => 'int(11)', 'NULL' => FALSE,
		                              'default' => '0' );
		$data[ 'columns' ][] = array( 'name' => 'key', 'type' => 'varchar(1024)', 'NULL' => FALSE, 'default' => '0' );
		$data[ 'columns' ][] = array( 'name' => 'value', 'type' => 'varchar(1024)', 'NULL' => FALSE, 'default' => '0' );
		$data[ 'keys' ][]    = array( 'name' => 'local_data_id', 'columns' => 'local_data_id' );
		$data[ 'keys' ][]    = array( 'name' => 'key', 'columns' => 'key' );
		$data[ 'type' ]      = 'Memory';
		$data[ 'comment' ]   = 'NMID CereusTransporter Data';
		api_plugin_db_table_create( 'CereusTransporter', 'plugin_CereusTransporter_data', $data );
	}

