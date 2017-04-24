<?php
/*
 +-------------------------------------------------------------------------+
 | Copyright (C) 2007-2017 The Cacti Group                                 |
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

/* syslog_db_connect_real - makes a connection to the database server
   @arg $host - the hostname of the database server, 'localhost' if the database server is running
      on this machine
   @arg $user - the username to connect to the database server as
   @arg $pass - the password to connect to the database server with
   @arg $db_name - the name of the database to connect to
   @arg $db_type - the type of database server to connect to, only 'mysql' is currently supported
   @arg $retries - the number a time the server should attempt to connect before failing
   @returns - (object) connection_id for success, (bool) '0' for error */
function syslog_db_connect_real($host, $user, $pass, $db_name, $db_type, $port = '3306', $retries = 20) {
	return db_connect_real($host, $user, $pass, $db_name, $db_type, $port, $retries);
}

/* syslog_db_close - closes the open connection
   @arg $syslog_cnn - the connection object to connect to
   @returns - the result of the close command */
function syslog_db_close($syslog_cnn) {
	return db_close($syslog_cnn);
}

/* syslog_db_execute - run an sql query and do not return any output
   @arg $syslog_cnn - the connection object to connect to
   @arg $sql - the sql query to execute
   @arg $log - whether to log error messages, defaults to true
   @returns - '1' for success, '0' for error */
function syslog_db_execute($sql, $log = TRUE) {
	global $syslog_cnn;
	return db_execute($sql, $log, $syslog_cnn);
}

/* syslog_db_execute_prepared - run an sql query and do not return any output
   @arg $sql - the sql query to execute
   @arg $log - whether to log error messages, defaults to true
   @returns - '1' for success, '0' for error */
function syslog_db_execute_prepared($sql, $parms = array(), $log = TRUE) {
	global $syslog_cnn;
	return db_execute_prepared($sql, $parms, $log, $syslog_cnn);
}

/* syslog_db_fetch_cell - run a 'select' sql query and return the first column of the
     first row found
   @arg $sql - the sql query to execute
   @arg $log - whether to log error messages, defaults to true
   @arg $col_name - use this column name instead of the first one
   @returns - (bool) the output of the sql query as a single variable */
function syslog_db_fetch_cell($sql, $col_name = '', $log = TRUE) {
	global $syslog_cnn;
	return db_fetch_cell($sql, $col_name, $log, $syslog_cnn);
}

/* syslog_db_fetch_cell_prepared - run a 'select' sql query and return the first column of the
     first row found
   @param $sql - the sql query to execute
   @param $col_name - use this column name instead of the first one
   @param $log - whether to log error messages, defaults to true
   @returns - (bool) the output of the sql query as a single variable */
function syslog_db_fetch_cell_prepared($sql, $params = array(), $col_name = '', $log = TRUE) {
	global $syslog_cnn;
	return db_fetch_cell_prepared($sql, $params, $col_name, $log, $syslog_cnn);
}

/* syslog_db_fetch_row - run a 'select' sql query and return the first row found
   @arg $sql - the sql query to execute
   @arg $log - whether to log error messages, defaults to true
   @returns - the first row of the result as a hash */
function syslog_db_fetch_row($sql, $log = TRUE) {
	global $syslog_cnn;
	return db_fetch_row($sql, $log, $syslog_cnn);
}

/* syslog_db_fetch_assoc - run a 'select' sql query and return all rows found
   @arg $sql - the sql query to execute
   @arg $log - whether to log error messages, defaults to true
   @returns - the entire result set as a multi-dimensional hash */
function syslog_db_fetch_assoc($sql, $log = TRUE) {
	global $syslog_cnn;
	return db_fetch_assoc($sql, $log, $syslog_cnn);
}

/* syslog_db_fetch_insert_id - get the last insert_id or auto incriment
   @arg $syslog_cnn - the connection object to connect to
   @returns - the id of the last auto incriment row that was created */
function syslog_db_fetch_insert_id($syslog_cnn) {
	return  db_fetch_insert_id($syslog_cnn);
}

/* syslog_db_replace - replaces the data contained in a particular row
   @arg $table_name - the name of the table to make the replacement in
   @arg $array_items - an array containing each column -> value mapping in the row
   @arg $keyCols - the name of the column containing the primary key
   @arg $autoQuote - whether to use intelligent quoting or not
   @returns - the auto incriment id column (if applicable) */
function syslog_db_replace($table_name, $array_items, $keyCols) {
	global $syslog_cnn;
	return db_replace($table_name, $array_items, $keyCols, $syslog_cnn);
}

/* syslog_sql_save - saves data to an sql table
   @arg $array_items - an array containing each column -> value mapping in the row
   @arg $table_name - the name of the table to make the replacement in
   @arg $key_cols - the primary key(s)
   @returns - the auto incriment id column (if applicable) */
function syslog_sql_save($array_items, $table_name, $key_cols = 'id', $autoinc = true) {
	global $syslog_cnn;
	return sql_save($array_items, $table_name, $key_cols, $autoinc, $syslog_cnn);
}

