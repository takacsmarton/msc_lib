<?php
/**
 * This file is part of the msc_lib library (https://github.com/microstudi/msc_lib)
 * Copyright: Ivan Vergés 2011 - 2014
 * License: http://www.gnu.org/copyleft/lgpl.html
 *
 * File manipulation & information utils functions file
 *
 * @category MSCLIB
 * @package Utilities/File-Utils
 * @author Ivan Vergés
 */

/**
* Returns a secure name to store in file system, if the generated filename exists returns a non-existing one
* @param $name original name to be changed-sanitized
* @param $dir if specified, generated name will be changed if exists in that dir, if $dir is array, then will be check with in_array() func
*/
function m_check_filename($name='',$dir=null){
    // $name = preg_replace("/[^a-z0-9_~\.-]+/i","-",m_normalize($name));
	$name = preg_replace('/[^a-z0-9_~\.-]+/i','-',($name));
	if(is_array($dir)) {
		while ( in_array ($name, $dir )) {
            $name = preg_replace_callback( '/^(.+?)(_?)(\d*)(\.[^.]+)?$/', function($m){
                return $m[1] .'_' . ($m[3]+1) . $m[4];
            }, $name );
		}
	}
	elseif(is_dir($dir)) {
		while ( file_exists ( "$dir/$name" )) {
			$name = preg_replace_callback( '/^(.+?)(_?)(\d*)(\.[^.]+)?$/', function($m){
                return $m[1] .'_' . ($m[3]+1) . $m[4];
            }, $name );
		}
	}
	return $name;
}

/**
 * returns a string representing the php file upload error
 * @param $err PHP UPLOAD CODE
 * */
function m_get_string_for_upload_error($err) {
	if($err == UPLOAD_ERR_OK)             return 'upload_err_ok';
	elseif($err == UPLOAD_ERR_INI_SIZE)   return 'upload_err_ini_size';
	elseif($err == UPLOAD_ERR_FORM_SIZE)  return 'upload_err_form_size';
	elseif($err == UPLOAD_ERR_PARTIAL)    return 'upload_err_partial';
	elseif($err == UPLOAD_ERR_NO_FILE )   return 'upload_err_no_file';
	elseif($err == UPLOAD_ERR_NO_TMP_DIR) return 'upload_err_no_tmp_dir';
	elseif($err == UPLOAD_ERR_CANT_WRITE) return 'upload_err_cant_write';
	elseif($err == UPLOAD_ERR_EXTENSION)  return 'upload_err_extension';
	return '';
}

/**
 * This function transforms the php.ini notation for numbers (like '2M') to an integer (2*1024*1024 in this case)
 * @param $v string number represantation
 * */
function m_let_to_num($val){
    $val = trim($val);
    $last = strtolower($val[strlen($val)-1]);
    switch($last) {
        case 'y':
            $val *= 1024;
        case 'z':
            $val *= 1024;
        case 'e':
            $val *= 1024;
        case 'p':
            $val *= 1024;
        case 't':
            $val *= 1024;
        case 'g':
            $val *= 1024;
        case 'm':
            $val *= 1024;
        case 'k':
            $val *= 1024;
    }

    return $val;
}

/**
 * Transforms number notation to string representation (ie: 1024 => 1K)
 * @param $size number to convert to string
 * @param $bit units (<b>B</b> => <b>KB</b>, <b>b</b> => <b>Kb</b>)
 * @param $div divisor (1024 by default)
 * */
function m_num_to_let($bytes, $unit = '', $decimals = 2) {
	$units = array('B' => 0, 'KB' => 1, 'MB' => 2, 'GB' => 3, 'TB' => 4,
			'PB' => 5, 'EB' => 6, 'ZB' => 7, 'YB' => 8);

	$value = 0;
	if ($bytes > 0) {
		// Generate automatic prefix by bytes
		// If wrong prefix given
		if (!array_key_exists($unit, $units)) {
			$pow = floor(log($bytes)/log(1024));
			$unit = array_search($pow, $units);
		}

		// Calculate byte value by prefix
		$value = ($bytes/pow(1024,floor($units[$unit])));
	}

	// If decimals is not numeric or decimals is less than 0
	// then set default value
	if (!is_numeric($decimals) || $decimals < 0) {
		$decimals = 2;
	}

	// Format output
	return sprintf('%.' . $decimals . 'f '.$unit, $value);
}

/**
 * Deletes a dir recursvely
 * @param  string  the directory to delete (all content will be deleted)
 * @return boolean true or false
 */
function m_rmdir($dir) {
    if (!file_exists($dir)) return true;
    if (!is_dir($dir) || is_link($dir)) return unlink($dir);
        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') continue;
            if (!m_rmdir($dir . '/' . $item)) {
                chmod($dir . '/' . $item, 0777);
                if (!m_rmdir($dir . '/' . $item)) return false;
            };
        }
        return rmdir($dir);
}

/**
* Returns a array with all files in a directory, if this directory contains subdirectories, this ends in "/"
* Coud be recursive
* @param $dir dir to scan
* @param $iterative if <b>true</b> search will be recursive
*/
function m_list_dir($dir,$iterative=true){
	if(!is_dir($dir)) return false;
	$files=array();
	$handle=@opendir($dir);
	while ($file = @readdir($handle)) {
		if($file{0} != '.'){
			$file = $dir . $file;
			if(is_dir($file)) {
				$files[] = $file.'/';
				if($iterative) $files = array_merge($files, m_list_dir($file.'/'));
			}
			else $files[] = $file;
		}
	}
	@closedir($handle);
	return $files;
}

function m_url_exists($url_original, $check_alive = true) {
	$url = @parse_url($url_original);

    if (!$url) {
        return false;
    }

    if(!$check_alive) {
        if(defined('FILTER_VALIDATE_URL')) {
            return filter_var($url_original, FILTER_VALIDATE_URL);
        }
        else {
            return preg_match('/\b(?:(?:https?|ftp):\/\/|www\.)[-a-z0-9+&@#\/%?=~_|!:,.;]*[-a-z0-9+&@#\/%=~_|]/i', $url_original);
        }
    }

    $url = array_map('trim', $url);
    $url['scheme'] = strtolower($url['scheme']);
    $url['port'] = (!isset($url['port'])) ? ($url['scheme'] == 'https' ? 443 : 80) : (int)$url['port'];
    $path = (isset($url['path'])) ? $url['path'] : '';

    if ($path == '') {
        $path = '/';
    }

    $path .= (isset($url['query'])) ? "?$url[query]" : '';

    if (isset($url['host']) AND $url['host'] != gethostbyname($url['host'])) {
        if (function_exists('get_headers')) {
            $headers = @get_headers("$url[scheme]://$url[host]:$url[port]$path");
        }
        else {
            $fp = @fsockopen($url['host'], $url['port'], $errno, $errstr, 30);

            if (!$fp)
            {
                return false;
            }
            fputs($fp, "HEAD $path HTTP/1.1\r\nHost: $url[host]\r\n\r\n");
            $headers = fread($fp, 4096);
            fclose($fp);
        }
        $headers = (is_array($headers)) ? implode("\n", $headers) : $headers;
        return (bool)preg_match('#^HTTP/.*\s+[(200|301|302)]+\s#i', $headers);
    }
    return false;
}
