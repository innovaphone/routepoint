<?php
/*
 * 
 */

if (!class_exists("DateTimeEx")) require_once("classes/sql.class.php");

/**
 * this class defines some query access to mantis data in our installation, usually derived from
 *
 */
class RoutepointAccess extends MySQLDB {
	/* defaults to a std query access to our class installation */
	const DBHost = "your-db-server"; 
	const DBUser = "your-db-server-account"; 
	const DBPW = "your-db-server-pw"; 
	const DBName = "your-schema-name";
	
	function __construct($DBHost = self::DBHost, $DBUser = self::DBUser, $DBPW = self::DBPW, $DBName = self::DBName) {
		parent::__construct($DBHost, $DBUser, $DBPW, $DBName);
	}
	
}


/**
 * get a GET or POST option, return default if not set
 * @param string $name name of option
 * @param mixed $default return if not set
 * @return string
 */
function getparam($name, $default = null) {
    if (isset($_REQUEST[$name]))
        return $_REQUEST[$name];
    else
        return $default;
}
?>