<?php
/**
* @file functions/sql.php
* @author Ivan Vergés
* @brief SQL functions\n
* This functions uses the class mMySQL defined in the file classes/mysql.php
*
* @section usage Usage
* //sets the connection data:\n
* $CONFIG->dbhost = "localhost";\n
* $CONFIG->dbname = "my_database";\n
* $CONFIG->dbuser = "my_username";\n
* $CONFIG->dbpass = "my_password";\n
* \n
* $list = m_sql_list("users",0,10,false,'*','active=1');\n
* print_r($list);
*/
function m_sql_set_database($dbhost='', $dbname='', $dbuser='', $dbpass='', $type='mysql') {
	global $CONFIG;

	//print_r("Set DB: " . $dbhost . " " . $dbname . " " . $dbuser . " " . $dbpass . " " );
	$CONFIG->dbhost = $dbhost;
	$CONFIG->dbname = $dbname;
	$CONFIG->dbuser = $dbuser;
	$CONFIG->dbpass = $dbpass;

	$CONFIG->default_database = 'mysql';
}
/**
 * Opens a database connection, returns the link resource
 * @param $type Database type (currently only \b mysql)
 */
function m_sql_open($type='') {
	global $CONFIG;

	if(empty($type)) $type = $CONFIG->default_database;
	if($type == 'mysql') {
		// Aixo no va amb les funcions mysql_ (que estan obsoletes!)
		// if($CONFIG->db instanceOf mMySQL) {
		// 	if($CONFIG->db->is_same($CONFIG->dbhost, $CONFIG->dbname, $CONFIG->dbuser, $CONFIG->dbpass) ) {
		// 		return $CONFIG->db->open();
		// 	}
		// }
		$CONFIG->db = new mMySQL($CONFIG->dbhost, $CONFIG->dbname, $CONFIG->dbuser, $CONFIG->dbpass);
	}

	return $CONFIG->db->open();
}
/**
 * Escapes a string to be used
 */
function m_sql_escape($val) {
	global $CONFIG;
	if($CONFIG->default_database == 'mysql') {
		if($CONFIG->db instanceOf mMySQL && $CONFIG->db->is_open()) return $CONFIG->db->escape($val);
		else return mMySQL::escape($val);
	}
	return mSQL::escape($val);
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
function m_create_table($table, $keys=array(), $pk='', $unique='', $fulltext='', $engine="MyISAM", $default_charset="utf8") {
	global $CONFIG;

	$sql = "CREATE TABLE IF NOT EXISTS `$table` (";
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
			$type = "INT";
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
	m_sql_open();

	$ret = array();
	if($res = $CONFIG->db->query($sql)) {
		while($ob = $CONFIG->db->fetch($res,false, $class ? $class : 'stdClass')) {
			$ret[] = $ob;
		}
	}
	elseif($CONFIG->debug) {
		//print_r($CONFIG->db->getError());
		return $CONFIG->db->getError();
	}
	return $ret;
}

/**
 * Returns a limited-lenght list (sql SELECT) of objects from a table, can count items also
 * @param $table name of the table to search results
 * @param $offset the first result of the list (not used in \b count mode)
 * @param $limit max number of results after the $offset  (not used in \b count mode)
 * @param $count if \b true, then the function will return the total number of results of the current query
 * @param $fields fields to search
 * @param $where WHERE clause (filters)
 * @param $order order part of the SELECT
 */
function m_sql_list($table, $offset=0, $limit=100, $count=false, $fields='*', $where='', $order='', $class='') {
	global $CONFIG;
	$offset = (int) $offset;
	$limit = (int) $limit;

	if($count) {
		$sql = "SELECT COUNT($fields) AS total FROM $table";
	}
	else $sql = "SELECT $fields FROM $table";

	if($where) {
		if(is_array($where)) {
			$w = array();
			foreach($where as $k => $v) {
				$w[] = "`$k` = '".m_sql_escape($v)."'";
			}
			$where = implode(" AND ", $w);
		}
		$sql .= " WHERE $where";
	}

	//echo $sql;
	if($count) {
		$rows = m_sql_objects($sql);
		return $rows[0]->total;
	}
	else {
		if($order) $sql .= " ORDER BY $order";
		$sql .= " LIMIT $offset, $limit";
		$rows = m_sql_objects($sql, $class);
		return $rows;
	}
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
	m_sql_open();

	$res = $CONFIG->db->query($sql, $mode);

	return $res;
}

/**
 * Executes a delete
 * @param $table table from where the rows will be deleted
 * @param $where WHERE clause (filter), if not specified all data of the table will be deleted!
 */
function m_sql_delete($table, $where='') {
	global $CONFIG;
	m_sql_open();

	$sql = "DELETE FROM `$table`";
	if($where) {
		if(is_array($where)) {
			$w = array();
			foreach($where as $k => $v) {
				$w[] = "`$k` = '".m_sql_escape($v)."'";
			}
			$where = implode(" AND ", $w);
		}
		$sql .= " WHERE $where";
	}
	//echo $sql;
	return m_sql_exec($sql,'delete');
}
/**
 * Executes a insert
 * @param $table table ot insert data
 * @param $insert array of pairs => values to be inserted into $table, pairs are the name of the fields, values the data
 * @param $as_insert specifies return mode is a \b insert
 * @param $escape auto-escapes the SQL fields & values
 */
function m_sql_insert($table, $insert=array(), $as_insert=true, $escape=true) {
	global $CONFIG;
	m_sql_open();

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
	(".implode(",",array_keys($inserts)).") VALUES (".implode(",", $inserts).")";

	return m_sql_exec($sql,($as_insert ? 'insert' : ''));
}
/**
 * Executes a update
 * @param $table table to update data
 * @param $insert array of pairs => values to be updated into $table, pairs are the name of the fields, values the data
 * @param $where where clause (filter) to update
 * @param $escape auto-escapes the SQL fields & values
 * @param $return_only_affected_rows if \b true only the number of affected rows will be returned (if not all the number of $where matched rows will be returned)
 */
function m_sql_update($table, $insert=array(), $where='', $escape=true, $return_only_affected_rows=false) {
	global $CONFIG;
	m_sql_open();

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
		".implode(",", $updates);

	if($where) {
		if(is_array($where)) {
			$w = array();
			foreach($where as $k => $v) {
				$w[] = "`$k` = '".m_sql_escape($v)."'";
			}
			$where = implode(" AND ", $w);
		}
		$sql .= " WHERE $where";
	}

	return m_sql_exec($sql,($return_only_affected_rows ? 'affected' : 'update'));
}
/**
 * Executes a insert on duplicate key update, this allows to insert or auto-update a row if a duplicate error happens
 * @param $table table to insert
 * @param $insert array of pairs => values to be inserted into $table, pairs are the name of the fields, values the data
 * @param $escape auto-escapes the SQL fields & values
 * @param $custom_sql_update a SQL custom update part (if empty will be the same as insert)
 */
function m_sql_insert_update($table, $insert=array(), $escape=true, $custom_sql_update='') {
	global $CONFIG;
	m_sql_open();

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
	$sql = "INSERT INTO `$table`
	(".implode(",",array_keys($inserts)).") VALUES (".implode(",", $inserts).")
	ON DUPLICATE KEY UPDATE
		" . ($custom_sql_update ? $custom_sql_update : implode(",", $updates)) . "
	";
	//echo "$sql\n";
	return m_sql_exec($sql);
}
/**
 * Returns last error
 */
function m_sql_error() {
	global $CONFIG;
	return $CONFIG->db->getError();
}
?>
