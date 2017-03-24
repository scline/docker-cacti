<?php
	/*******************************************************************************
	 *
	 * File:         $Id$
	 * Modified_On:  $Date$
	 * Modified_By:  $Author$
	 * License:      Commercial
	 * Copyright:    Copyright 2009-2016 by Urban-Software.de / Thomas Urban
	 *******************************************************************************/
	function CereusTransporter_mssql_send_data( $data_array ) {

		$db_type = read_config_option( 'cereus_transporter_dbtype' );
		return;
		$metrics = array();

		// preparing points
		$points = array();
		foreach ( $data_array as $point ) {
			if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_DEBUG ) {
				cacti_log( "DEBUG: Appending the following data to request: [" . $db_type . "] [" . $point[ 'timestamp' ] . "] [" . $point[ 'tags' ][ 'hostname' ] . "] [" . json_encode( $point[ 'tags' ] ) . "] [" . $point[ 'tags' ][ 'type' ] . "] [" . $point[ 'value' ] . "]", TRUE, "CereusTransporter" );
			}
			if ( strlen( $point[ 'metric' ] ) > 0 ) {
				try {
					if ( array_key_exists( 'metric_text', $point[ 'tags' ] ) ) {
						$point[ 'tags' ][ 'metric_text' ] = CereusTransporter_cleanTag( $db_type, $point[ 'tags' ][ 'metric_text' ] );
					}
					if ( array_key_exists( 'units', $point[ 'tags' ] ) ) {
						$point[ 'tags' ][ 'units' ] = CereusTransporter_cleanTag( $db_type, $point[ 'tags' ][ 'units' ] );
					}
					$points[] = new InfluxDB\Point(
						$point[ 'metric' ],
						$point[ 'value' ],
						$point[ 'tags' ],
						array( 'value' => $point[ 'value' ] ),
						$point[ 'timestamp' ] );
				}
				catch ( Exception $e ) {
					cacti_log( "ERROR: " . $e->getMessage(), TRUE, "CereusTransporter" );
				}
			}
		}

		if ( sizeof( $points ) > 0 ) {
			if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_DEBUG ) {
				cacti_log( "DEBUG: Adding [" . sizeof( $points ) . "] of data points for [" . $point[ 'tags' ][ 'hostname' ] . "]", TRUE, "CereusTransporter" );
			}


			// we are writing unix timestamps, which have a seconds precision
			$newPoints = '';
			try {
				$data      = print_r( $points, TRUE );
				$newPoints = $database->writePoints( $points, InfluxDB\Database::PRECISION_SECONDS );
			}
			catch ( Exception $e ) {
				cacti_log( "ERROR: " . $e->getMessage() . ' ' . $newPoints, TRUE, "CereusTransporter" );
			}

			if ( read_config_option( 'log_verbosity' ) >= POLLER_VERBOSITY_HIGH ) {
				cacti_log( "Finished adding [" . sizeof( $points ) . "] of data points for [" . $point[ 'tags' ][ 'hostname' ] . "]", TRUE, "CereusTransporter" );
			}
		}
	}
