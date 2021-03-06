<?php
/**
 * This file is part of the msc_lib library (https://github.com/microstudi/msc_lib)
 * Copyright: Ivan Vergés 2011 - 2014
 * License: http://www.gnu.org/copyleft/lgpl.html
 *
 * SQL functions
 * This functions uses the class mMySQL defined in the file classes/mysql.php
 *
 * @category MSCLIB
 * @package SQL
 * @author Ivan Vergés
 */

/**
* Setup the database connection
*/
function m_sql_set_database($dbhost='', $dbname='', $dbuser='', $dbpass='', $type='mysql') {
	global $CONFIG;

	//only mysql at this time
	$CONFIG->default_database = 'mysql';
	if($CONFIG->db instanceOf mMySQL) $CONFIG->db->close();

	$CONFIG->db = new mMySQL($dbhost, $dbname, $dbuser, $dbpass);

}

/**
 * Set a cache from the library phpfastcache
 * http://www.phpfastcache.com/
 *
 * valid for select & show queries only
 *
 * Example
 * <code>
 * //enables runtime cache
 * m_sql_set_cache();
 * m_sql_list('table'); //this query goes to the mysql server
 * m_sql_list('table'); //this query is repeated, readed from the runtime cache
 *
 * //enables long-term cache for 5 minutes
 * m_sql_set_cache('files', 300, 'temp_dir');
 * </code>
 *
 * @param  string $type    'runtime' This provides cache for repeated queries while script is executing
 *         				   'auto', "apc", "memcache", "memcached", "wincache" ,"files", "sqlite" and "xcache"
 * @param integer $time     seconds to store the cache
 * @param  string  $path path to dir where to cache in case of files
 */
function m_sql_set_cache($type = 'runtime', $time = 60, $path = '', $options = array()) {
	global $CONFIG;

	//runtime cache enable by default
	$CONFIG->database_run_cache = array();
	$CONFIG->database_run_cache_autoclear = true; //clears runcache after updates or deletes

	if(in_array($type, array('auto', 'apc', 'memcache', 'memcached', 'wincache' ,'files', 'sqlite', 'xcache'))) {
		require_once(dirname(dirname(__FILE__)) . '/classes/phpfastcache/phpfastcache.php');

		$CONFIG->database_cache = phpFastCache($type, array('path' => $path) + $options);
		$CONFIG->database_cache_time = $time;
	}
	$CONFIG->database_cache_enabled = true;
}

/**
 * Wipes the cache
 */
function m_sql_clear_cache() {
	global $CONFIG;

	if($CONFIG->database_cache) {
		$CONFIG->database_cache->clean();
	}
	if(is_array($CONFIG->database_run_cache)) {
		$CONFIG->database_run_cache = array();
	}
}
/**
 * Enable/disable the cache
 * Even if cache is disable, new data will be updated in the cache
 * @param  boolean $enable [description]
 */
function m_sql_cache($enable = true) {
	global $CONFIG;
	if($enable) $CONFIG->database_cache_enabled = true;
	else $CONFIG->database_cache_enabled = false;
}

/**
 * Does not applies the cache in the next (only) query, for next coming queries cache will apply again
 * However, cache will be update with the new data
 */
function m_sql_no_cache() {
	global $CONFIG;
	$CONFIG->database_cache_enabled = false;
	$CONFIG->database_cache_paused = true;
}

/**
 * Opens a database connection, returns the link resource
 */
function m_sql_open( ) {
	global $CONFIG;

	if( !($CONFIG->db instanceOf mMySQL) ) return false;

	return $CONFIG->db->open();
}

/**
 * Closes a database connection
 */
function m_sql_close( ) {
	global $CONFIG;

	if( !($CONFIG->db instanceOf mMySQL) ) return false;

	return $CONFIG->db->close();
}

/**
 * Escapes a string to be used
 */
function m_sql_escape($val) {
	global $CONFIG;
	if($CONFIG->default_database == 'mysql') {
		if($CONFIG->db instanceOf mMySQL && $CONFIG->db->is_open()) return $CONFIG->db->escape($val);
		else return mMySQL::static_escape($val);
	}
	return mSQL::static_escape($val);
}

/**
 * Creates a table
 *
 * @param $keys array("id" => 'int(10) UNSIGNED NOT NULL AUTO_INCREMENT')
 * @param $pk 'id' //PRIMARY KEY
 * @param $pk array('id','id2') //PRIMARY KEY
 * @param $unique 'field1' //UNIQUE INDEX FIELDS
 * @param $unique array('field1','field2') //UNIQUE INDEX FIELDS
 * @param $fulltext 'field1' //FULLTEXT INDEX FIELDS
 * @param $fulltext array('field1','field2') //FULLTEXT INDEX FIELDS
 */
function m_create_table($table, $keys=array(), $pk='', $unique='', $fulltext='', $default_charset='utf8', $engine='') {
	global $CONFIG;

	$sql = "CREATE TABLE `$table` (";
	$fields = array();
	foreach($keys as $k => $v) {
		$fields[] = "`$k` $v";
	}
	$sql .= implode(",\n", $fields);
	if(!empty($pk)) {
		if(is_array($pk)) $sql .= ",\nPRIMARY KEY (`".implode("`,`", $pk)."`)\n";
		else $sql .= ",\nPRIMARY KEY (`$pk`)\n";
	}
	if(!empty($unique)) {
		if(is_array($unique)) $sql .= ",\nUNIQUE KEY (`".implode("`,`", $unique)."`)\n";
		else $sql .= ",\nUNIQUE KEY (`$unique`)\n";
	}
	if(!empty($fulltext)) {
		if(is_array($fulltext)) $sql .= ",\nFULLTEXT KEY (`".implode("`,`", $fulltext)."`)\n";
		else  $sql .= ",\nFULLTEXT KEY (`$fulltext`)\n";
	}
	$sql .= ")";
	if($engine) $sql .= " ENGINE=$engine";
	if($default_charset) $sql .= " DEFAULT CHARSET=$default_charset";

	return m_sql_exec($sql);
}

/**
 * Tries to create a table from a array data => value
 * @param $table name of the table to be created
 * @param $array array of pairs keys => values, keys will be the fields names, values will be used to establish the type of the files
 * */
function m_auto_create_table($table, $array=array()){
	global $CONFIG;
	$keys = array();
	$pk = array();
	$unique = array();
	$fulltext = array();
	foreach($array as $k => $v) {
		$type = '';
		if(is_string($v)) {
			if(strlen($v)>100) $type = 'TEXT';
			else $type = 'CHAR(255)';
		}
		if(is_integer($v)) {
			$type = 'INT';
		}
		$keys[$k] = $type;
	}
	return m_create_table($table, $keys, $pk, $unique, $fulltext);
}

/**
 * Returns a list of objects (array of objects) from a sql
 * @param $sql the SQL query
 */
function m_sql_objects($sql, $class='') {
	global $CONFIG;

	//open connection if not opened
	if(!m_sql_open()) return false;

	$is_select = ( strtolower(rtrim(substr(ltrim($sql),0 ,6))) === 'select' || strtolower(rtrim(substr(ltrim($sql),0 ,4))) === 'show' );
	if($is_select) $CONFIG->database_counter['select']++;

	//cache on select or show only
	$id = 'm_sql-' . $CONFIG->db->token. '-' . md5($sql);
	if($CONFIG->database_cache_enabled) {
		$is_update = ( strtolower(rtrim(substr(ltrim($sql),0 ,6))) === 'update' || strtolower(rtrim(substr(ltrim($sql),0 ,6))) === 'delete' );
		if($is_select) {

			if (is_array($CONFIG->database_run_cache) && array_key_exists($id, $CONFIG->database_run_cache)) {
				// echo "EXISTS CACHE [$id] [$sql]\n";
				//runtime query cached

				$ret = unserialize($CONFIG->database_run_cache[$id]);
				if($ret) {
					$CONFIG->database_counter['runcached']++;
					if($CONFIG->database_log_queries) $CONFIG->database_log['runcached'][] = $sql;

					return $ret;
				}
			}
			if($CONFIG->database_cache) {
				$rows = $CONFIG->database_cache->get($id);
				if($rows !== null) {
					// $CONFIG->database_cache->touch($id, $CONFIG->database_cache_time);
					// disk cached
					$CONFIG->database_counter['cached']++;
					if($CONFIG->database_log_queries) $CONFIG->database_log['cached'][] = $sql;

					return $rows;
				}
			}
		}
		if($is_update && $CONFIG->database_run_cache_autoclear) $CONFIG->database_run_cache = array();
	}

	$ret = array();

	if($CONFIG->database_timezone_executed !== $CONFIG->database_timezone && is_string($CONFIG->database_timezone)) {
		try {
			$_sql = "SET time_zone = '" . str_replace("'",'',$CONFIG->database_timezone) . "'";
			$CONFIG->database_counter['nocache']++;
			if($CONFIG->database_log_queries) $CONFIG->database_log['noncached'][] = $_sql;

			$CONFIG->db->query($_sql);
			$CONFIG->database_timezone_executed = $CONFIG->database_timezone;
		}
		catch(Exception $e) {}
	}

	if($CONFIG->database_names_executed !== $CONFIG->database_names && is_string($CONFIG->database_names)) {
		try {
			$_sql = 'SET NAMES ' . str_replace("'",'',$CONFIG->database_names);
			$CONFIG->database_counter['nocache']++;
			if($CONFIG->database_log_queries) $CONFIG->database_log['noncached'][] = $_sql;

			$CONFIG->db->query($_sql);
			$CONFIG->database_names_executed = $CONFIG->database_names;
		}
		catch(Exception $e) {}
	}

	// $t = microtime(true);
	if($res = $CONFIG->db->query($sql)) {
		//sql queries that can be cached
		if($is_select) $CONFIG->database_counter['cache']++;
		//sql queries that cannot be cached
		else 	 	   $CONFIG->database_counter['nocache']++;

		if($CONFIG->database_log_queries) $CONFIG->database_log['noncached'][] = $sql;

		// echo round(microtime(true) - $t, 4)."s :$sql\n";
		while($ob = $CONFIG->db->fetch($res,false, $class ? $class : 'stdClass')) {
			$ret[] = $ob;
		}

		//store cache
		if((is_array($CONFIG->database_run_cache) || $CONFIG->database_cache) && $is_select) {
			//set runtime cache
			$CONFIG->database_run_cache[$id] = serialize($ret);
			//set long term cache
			if($CONFIG->database_cache) $CONFIG->database_cache->set($id, $ret, $CONFIG->database_cache_time);
			if($CONFIG->database_cache_paused && !$CONFIG->database_cache_enabled) {
				$CONFIG->database_cache_enabled = true;
				$CONFIG->database_cache_paused = false;
			}
		}

		return $ret;
	}

	return false;
}

/**
 * Returns a limited-lenght list (sql SELECT) of objects from a table, can count items also
 *
 * Example
 * <code>
 * $list = m_sql_list("users",0,10,'*','active=1');
 * print_r($list);
 * </code>
 *
 * @param $table name of the table to search results
 * @param $offset the first result of the list (not used in <b>count</b> mode)
 * @param $limit max number of results after the $offset  (not used in <b>count</b> mode)
 * @param $count if <b>true</b>, then the function will return the total number of results of the current query
 * @param $fields fields to search
 * @param $where WHERE clause (filters)
 * @param $order order part of the SELECT
 */
function m_sql_list($table, $offset=0, $limit=100, $fields='*', $where='', $order='', $class='') {
	global $CONFIG;
	$offset = (int) $offset;
	$limit = (int) $limit;

	$sql = "SELECT $fields FROM $table";

	if($where) {
		if(is_array($where)) {
			$w = array();
			foreach($where as $k => $v) {
				$w[] = "`$k` = '".m_sql_escape($v)."'";
			}
			$where = implode(' AND ', $w);
		}
		$sql .= " WHERE $where";
	}

	if($order) $sql .= " ORDER BY $order";
	$sql .= " LIMIT $offset, $limit";

	$rows = m_sql_objects($sql, $class);

	return $rows;

}

/**
 * Returns a number of results from a sql SELECT list result
 * @param  string $table  table to list
 * @param  string $where  where clausule
 * @param  string $fields fields to embed in COUNT() * by default
 * @return integer        number of results (>=0)
 */
function m_sql_count($table, $where='', $fields='*') {
	global $CONFIG;

	$sql = "SELECT COUNT($fields) AS total FROM $table";

	if($where) {
		if(is_array($where)) {
			$w = array();
			foreach($where as $k => $v) {
				$w[] = "`$k` = '".m_sql_escape($v)."'";
			}
			$where = implode(' AND ', $w);
		}
		$sql .= " WHERE $where";
	}

	//echo $sql;

	$rows = m_sql_objects($sql);

	return $rows[0]->total;

}

/**
 * Execs a sql statement
 * @param $sql SQL query
 * @param $mode if empty, returns the result of mysql_query function (or false if fail)
 * 	- @b insert insert mode: return the new id
 * 	- @b update update mode: returns the number of results if it the operation success, no matter if it's really updated or not
 * 	- @b affected affected mode: returns the number of affected rows (not updated rows returns anything)
 * 	- @b deleted delete mode: returns the number of deleted rows (for DELETE querys)
 */
function m_sql_exec($sql, $mode='') {
	global $CONFIG;
	//open connection if not opened
	if(!m_sql_open()) return false;
	$is_select = ( strtolower(rtrim(substr(ltrim($sql),0 ,6))) === 'select' || strtolower(rtrim(substr(ltrim($sql),0 ,4))) === 'show' );
	if($is_select) $CONFIG->database_counter['select']++;

	//sql queries that cannot be cached
	$CONFIG->database_counter['nocache']++;
	if($CONFIG->database_log_queries) $CONFIG->database_log['noncached'][] = $sql;

	if($CONFIG->database_timezone_executed !== $CONFIG->database_timezone && is_string($CONFIG->database_timezone)) {
		try {
			$_sql = "SET time_zone = '" . str_replace("'",'',$CONFIG->database_timezone) . "'";
			$CONFIG->database_counter['nocache']++;
			if($CONFIG->database_log_queries) $CONFIG->database_log['noncached'][] = $_sql;

			$CONFIG->db->query($_sql);
			$CONFIG->database_timezone_executed = $CONFIG->database_timezone;
		}
		catch(Exception $e) {}
	}
	if($CONFIG->database_names_executed !== $CONFIG->database_names && is_string($CONFIG->database_names)) {
		try {
			$_sql = 'SET NAMES ' . str_replace("'",'',$CONFIG->database_names);
			$CONFIG->database_counter['nocache']++;
			if($CONFIG->database_log_queries) $CONFIG->database_log['noncached'][] = $_sql;

			$CONFIG->db->query($_sql);
			$CONFIG->database_names_executed = $CONFIG->database_names;
		}
		catch(Exception $e) {}
	}

	$res = $CONFIG->db->query($sql, $mode);
	if($CONFIG->database_cache_enabled) {
		$is_update = ( strtolower(rtrim(substr(ltrim($sql),0 ,6))) === 'update' || strtolower(rtrim(substr(ltrim($sql),0 ,6))) === 'delete' );

		if($is_update && $CONFIG->database_run_cache_autoclear) $CONFIG->database_run_cache = array();
	}

	return $res;
}

/**
 * Executes a delete
 * @param $table table from where the rows will be deleted
 * @param $where WHERE clause (filter), if not specified all data of the table will be deleted!
 */
function m_sql_delete($table, $where='') {
	global $CONFIG;
	if(!m_sql_open()) return false;

	$sql = "DELETE FROM `$table`";
	if($where) {
		if(is_array($where)) {
			$w = array();
			foreach($where as $k => $v) {
				$w[] = "`$k` = '".m_sql_escape($v)."'";
			}
			$where = implode(' AND ', $w);
		}
		$sql .= " WHERE $where";
	}
	//echo $sql;
	$res = m_sql_exec($sql,'delete');
	if($CONFIG->database_run_cache_autoclear) $CONFIG->database_run_cache = array();
	return $res;
}

/**
 * Executes a insert
 * @param $table table ot insert data
 * @param $insert array of pairs => values to be inserted into $table, pairs are the name of the fields, values the data
 * @param $as_insert specifies return mode is a <b>insert</b>
 * @param $escape auto-escapes the SQL fields & values
 */
function m_sql_insert($table, $insert=array(), $as_insert=true, $escape=true) {
	global $CONFIG;
	if(!m_sql_open()) return false;

	$inserts = array();
	foreach($insert as $k => $v) {
		if($escape) {
			$inserts["`$k`"] = "'".$CONFIG->db->escape($v)."'";
		}
		else {
			$inserts[$k] = $v;
		}
	}
	$sql = "INSERT INTO `$table`
	(".implode(',',array_keys($inserts)).') VALUES ('.implode(',', $inserts).')';

	return m_sql_exec($sql,($as_insert ? 'insert' : ''));
}

/**
 * Executes a update
 * @param $table table to update data
 * @param $insert array of pairs => values to be updated into $table, pairs are the name of the fields, values the data
 * @param $where where clause (filter) to update
 * @param $escape auto-escapes the SQL fields & values
 * @param $return_only_affected_rows if <b>true</b> only the number of affected rows will be returned (if not all the number of $where matched rows will be returned)
 */
function m_sql_update($table, $insert=array(), $where='', $escape=true, $return_only_affected_rows=false) {
	global $CONFIG;
	if(!m_sql_open()) return false;

	if(is_array($insert)) {
		$updates = array();
		foreach($insert as $k => $v) {
			if($escape) {
				$updates[] = "`$k` = '".$CONFIG->db->escape($v)."'";
			}
			else {
				$updates[] = "$k = $v";
			}
		}
		$sql = "UPDATE `$table` SET
			".implode(',', $updates);
	}
	else {
		$sql = "UPDATE `$table` SET $insert";
	}

	if($where) {
		if(is_array($where)) {
			$w = array();
			foreach($where as $k => $v) {
				$w[] = "`$k` = '".m_sql_escape($v)."'";
			}
			$where = implode(' AND ', $w);
		}
		$sql .= " WHERE $where";
	}

	$res = m_sql_exec($sql,($return_only_affected_rows ? 'affected' : 'update'));
	if($CONFIG->database_run_cache_autoclear) $CONFIG->database_run_cache = array();
	return $res;
}

/**
 * Executes a insert on duplicate key update, this allows to insert or auto-update a row if a duplicate error happens
 * @param $table table to insert
 * @param $insert array of pairs => values to be inserted into $table, pairs are the name of the fields, values the data
 * @param $escape auto-escapes the SQL fields & values
 * @param $custom_sql_update a SQL custom update part (if empty will be the same as insert)
 */
function m_sql_insert_update($table, $insert=array(), $escape=true, $custom_sql_update=array()) {
	global $CONFIG;
	if(!m_sql_open()) return false;

	$updates = array();
	$inserts = array();
	foreach($insert as $k => $v) {
		//print_r($k).print_r($v);echo "\n";
		if($escape){
			$inserts["`$k`"] = "'".$CONFIG->db->escape($v)."'";
			$updates[]       = "`$k` = '".$CONFIG->db->escape($v)."'";
		}
		else {
			$inserts[$k] = $v;
			$updates[]   = "$k = $v";
		}
	}
	if($custom_sql_update && is_array($custom_sql_update)) {
		$updates = array();
		foreach($custom_sql_update as $k => $v) {
			if($escape){
				$updates[] = "`$k` = '".$CONFIG->db->escape($v)."'";
			}
			else {
				$updates[] = "$k = $v";
			}
		}
		$custom_sql_update = '';
	}
	$sql = "INSERT INTO `$table`
	(".implode(',',array_keys($inserts)).') VALUES ('.implode(',', $inserts).')
	ON DUPLICATE KEY UPDATE
		' . ($custom_sql_update ? $custom_sql_update : implode(',', $updates));

	//echo "$sql\n";
	$res = m_sql_exec($sql);
	if($CONFIG->database_run_cache_autoclear) $CONFIG->database_run_cache = array();
	return $res;
}

/**
 * tells to log queries
 */
function m_sql_log_queries($log = null) {
	global $CONFIG;
	if($log) $CONFIG->database_log_queries = ($log ? true : false);
	return $CONFIG->database_log_queries;
}
/**
 * Sets the timezone
 */
function m_sql_timezone($timezone = null) {
	global $CONFIG;
	if($timezone) $CONFIG->database_timezone = $timezone;
	return $CONFIG->database_timezone;
}
/**
 * Sets the names charset
 * @param $names utf8, latin1, etc
 */
function m_sql_names($names = null) {
	global $CONFIG;
	if($names) $CONFIG->database_names = $names;
	return $CONFIG->database_names;
}

/**
 *  return stats
 */
function m_sql_stats() {
	global $CONFIG;
	$total = $CONFIG->database_counter['cache'] + $CONFIG->database_counter['nocache'] + $CONFIG->database_counter['cached'] + $CONFIG->database_counter['runcached'];
	$total_non_cached = $CONFIG->database_counter['cache'] + $CONFIG->database_counter['nocache'];
	$total_cached = $CONFIG->database_counter['cached'] + $CONFIG->database_counter['runcached'];


	//sql queries that can be cached
	$ret = array('sql_total' => $total,
				 'sql_select' => $CONFIG->database_counter['select'],
				 'sql_non_cached' => $total_non_cached,
				 'sql_cached' => $total_cached,
				 'sql_disk_cached' => $CONFIG->database_counter['cached'],
				 'sql_run_cached' => $CONFIG->database_counter['runcached']
				 );

	if($CONFIG->database_log_queries) {
		$ret['sql_log_non_cached'] = $CONFIG->database_log['noncached'];
		$ret['sql_log_cached']     = $CONFIG->database_log['cached'];
		$ret['sql_log_run_cached'] = $CONFIG->database_log['runcached'];
	}
	return $ret;
}
/**
 * Returns last error
 */
function m_sql_error() {
	global $CONFIG;
	return $CONFIG->db->getError();
}
