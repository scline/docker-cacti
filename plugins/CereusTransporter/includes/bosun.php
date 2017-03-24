<?php
	/*******************************************************************************
	 *
	 * File:         $Id$
	 * Modified_On:  $Date$
	 * Modified_By:  $Author$
	 * License:      Commercial
	 * Copyright:    Copyright 2009-2016 by Urban-Software.de / Thomas Urban
	 *******************************************************************************/


	function CereusTransporter_bosun_send_data( $data_array )
	{
		$db_url  = read_config_option( 'cereus_transporter_db_fullurl' );
		$db_type = read_config_option( 'cereus_transporter_dbtype' );

		// curl for bosun and opentsdb
		$curl = NULL;

		// init request
		$curl   = curl_init();
		$db_url = rtrim( $db_url, '/' );
		$page   = '/api/put?details';
		if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_DEBUG ) {
			$page .= '?details';
		}
		curl_setopt( $curl, CURLOPT_URL, $db_url . $page );
		curl_setopt( $curl, CURLOPT_RETURNTRANSFER, TRUE );
		curl_setopt( $curl, CURLOPT_BINARYTRANSFER, TRUE );
		curl_setopt( $curl, CURLOPT_POST, TRUE );

		$metrics = array();

		// preparing points
		$points = array();
		foreach ( $data_array as $point ) {
			if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_DEBUG ) {
				cacti_log( "DEBUG: Appending the following data to request: [" . $db_type . "] [" . $point[ 'timestamp' ] . "] [" . $point[ 'tags' ][ 'hostname' ] . "] [" . json_encode( $point[ 'tags' ] ) . "] [" . $point[ 'tags' ][ 'type' ] . "] [" . $point[ 'value' ] . "]", TRUE, "CereusTransporter" );
			}
			if ( strlen( $point[ 'metric' ] ) > 0 ) {
				// prepare metrics info for bosun
				if ( $db_type == 'bosun' && is_null( $metrics[ $point[ 'metric' ] ] ) ) {
					$metrics_temp       = array();
					$metric             = array();
					$metric[ 'Metric' ] = $point[ 'metric' ];
					$point[ 'value' ]   = (int)$point[ 'value' ]; // make value integer
					$rate               = $point[ 'tags' ][ 'rate' ];
					$desc               = $point[ 'tags' ][ 'metric_text' ];
					$unit               = $point[ 'tags' ][ 'units' ];
					$tags               = $point[ 'tags' ];
					$metrics_host       = array();
					$metrics_host_param = array();
					$metrics_host_tags  = array();

					$metrics_host_tags[ 'host' ]           = $point[ 'tags' ][ 'host' ];
					$metrics_host_param[ 'tags' ]          = $metrics_host_tags;
					$metrics_host_param[ 'Name' ]          = 'hostname';
					$metrics_host_param[ 'Value' ]         = $tags[ 'hostname' ];
					$metrics_host[]                        = $metrics_host_param;
					$metrics_host_param[ 'Name' ]          = 'polling_time';
					$metrics_host_param[ 'Value' ]         = $tags[ 'polling_time' ];
					$metrics_host[]                        = $metrics_host_param;
					$metrics_host_param[ 'Name' ]          = 'host_type';
					$metrics_host_param[ 'Value' ]         = $tags[ 'host_type' ];
					$metrics_host[]                        = $metrics_host_param;
					$metrics[ $point[ 'tags' ][ 'host' ] ] = $metrics_host;

					unset( $tags[ 'metric_text' ] ); // delete unwanted data

					unset( $tags[ 'rate' ] ); // delete unwanted data
					unset( $tags[ 'namecache' ] ); // delete unwanted data
					unset( $tags[ 'type' ] ); // delete unwanted data
					unset( $tags[ 'units' ] ); // delete unwanted data
					unset( $tags[ 'host_type' ] ); // delete unwanted data
					unset( $tags[ 'hostname' ] ); // delete unwanted data
					unset( $tags[ 'polling_time' ] ); // delete unwanted data
					$metric[ 'tags' ] = $tags;
					if ( isset( $desc ) && strlen( $desc ) > 0 ) {
						$metric[ 'Name' ]  = 'desc';
						$metric[ 'Value' ] = $desc;
						$metrics_temp[]    = $metric;
					}
					if ( isset( $rate ) ) {
						$metric[ 'Name' ]  = 'rate';
						$metric[ 'Value' ] = $rate;
						$metrics_temp[]    = $metric;
					}
					// Unit name is taken from Vertical Label of Graph Template for datasource
					// If there are multiple Graph Templates, only one value will be taken
					// Vertical Label text should be from this list, or bosun will skip it:
					// https://godoc.org/bosun.org/metadata#Unit
					if ( isset( $unit ) ) {
						$metric[ 'Name' ]  = 'unit';
						$metric[ 'Value' ] = $unit;
						$metrics_temp[]    = $metric;
					}
					$metrics[ $point[ 'metric' ] ] = $metrics_temp;
				}
				if ( isset( $point[ 'tags' ][ 'index_type' ] ) ) {
					$point[ 'tags' ][ $point[ 'tags' ][ 'index_type' ] ] = $point[ 'tags' ][ 'index_value' ];
					unset( $point[ 'tags' ][ 'index_type' ] ); // delete unwanted data
					unset( $point[ 'tags' ][ 'index_value' ] ); // delete unwanted data
				}
				unset( $point[ 'tags' ][ 'metric_text' ] ); // delete unwanted data
				unset( $point[ 'tags' ][ 'units' ] ); // delete unwanted data
				$points[] = $point;
			}
		}

		if ( sizeof( $points ) > 0 ) {
			if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_DEBUG ) {
				cacti_log( "DEBUG: Adding [" . sizeof( $points ) . "] of data points for [" . $point[ 'tags' ][ 'hostname' ] . "]", TRUE, "CereusTransporter" );
			}

			$db_url = rtrim( read_config_option( 'cereus_transporter_db_fullurl' ), '/' );
			if ( $db_type == 'bosun' && sizeof( $metrics ) > 0 ) {
				$metrics_post = array();
				foreach ( $metrics as $metrics_outer ) {
					foreach ( $metrics_outer as $metric ) {
						$metrics_post[] = $metric;
					}
				}
				$json = json_encode( $metrics_post );
				if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_DEBUG ) {
					cacti_log( "DEBUG: Bosun Metrics for hostname [" . $point[ 'tags' ][ 'hostname' ] . "]: " . $json, TRUE, "CereusTransporter" );
				}
				$metacurl = curl_init();
				curl_setopt( $metacurl, CURLOPT_URL, $db_url . '/api/metadata/put' );
				curl_setopt( $metacurl, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json; charset=UTF-8',
					"Accept:application/json, text/javascript, */*; q=0.01",
					'Content-Length: ' . strlen( $json ) ) );
				curl_setopt( $metacurl, CURLOPT_RETURNTRANSFER, TRUE );
				curl_setopt( $metacurl, CURLOPT_BINARYTRANSFER, TRUE );
				curl_setopt( $metacurl, CURLOPT_POST, TRUE );
				curl_setopt( $metacurl, CURLOPT_POSTFIELDS, $json );
				$curl_result = curl_exec( $metacurl );
				if ( $curl_result === FALSE ) {
					cacti_log( "ERROR: Metadata Curl to [" . $db_url . "] was not succesful: [" . curl_error( $metacurl ) . "]", TRUE, "CereusTransport" );
				}
				curl_close( $metacurl );
			}

			$json = json_encode( $points );
			if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_DEBUG ) {
				cacti_log( "DEBUG: Adding [" . sizeof( $points ) . "] of data points for [" . $point[ 'tags' ][ 'hostname' ] . "] TSDB JSON: " . $json, TRUE, "CereusTransporter" );
			}
			curl_setopt( $curl, CURLOPT_HTTPHEADER, array( 'Content-Type: application/json; charset=UTF-8',
				"Accept:application/json, text/javascript, */*; q=0.01",
				'Content-Length: ' . strlen( $json ) ) );
			curl_setopt( $curl, CURLOPT_POSTFIELDS, $json );
			$curl_result = curl_exec( $curl );
			if ( $curl_result === FALSE ) {
				cacti_log( "ERROR: Data Curl to [" . $db_url . "] for [" . $point[ 'tags' ][ 'hostname' ] . "] was not succesful: [" . curl_error( $curl ) . "]", TRUE, "CereusTransport" );
			}
			curl_close( $curl );

			if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_HIGH ) {
				cacti_log( "Finished adding [" . sizeof( $points ) . "] of data points for [" . $point[ 'tags' ][ 'hostname' ] . "]", TRUE, "CereusTransporter" );
			}
		}
	}