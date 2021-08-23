<?php

//require_once("html.class.php");

/*
 * this class helps with writing SQL statements and reading result tables
 * 
 * it is written for MySQL but might work with others too
 */

/**
 * private helper class, dont use!
 *
 */
class _SQLValueSignature {
    /* mysql field types */

    const TINYINT = 1;
    const SMALLINT = 2;
    const INT = 3;
    const FLOAT = 4;
    const DOUBLE = 5;
    const TIMESTAMP = 7;
    const BIGINT = 8;
    const MEDIUMINT = 9;
    const DATE = 10;
    const TIME = 11;
    const DATETIME = 12;
    const YEAR = 13;
    const BIT = 16;
    const DECIMAL = 246;
    const BLOB = 252;
    const VARBINARY = 253;
    const BINARY = 254;

    /* mysql field flags */
    const NOT_NULL_FLAG = 1;
    const PRI_KEY_FLAG = 2;
    const UNIQUE_KEY_FLAG = 4;
    const BLOB_FLAG = 16;
    const UNSIGNED_FLAG = 32;
    const ZEROFILL_FLAG = 64;
    const BINARY_FLAG = 128;
    const ENUM_FLAG = 256;
    const AUTO_INCREMENT_FLAG = 512;
    const TIMESTAMP_FLAG = 1024;
    const SET_FLAG = 2048;
    const NUM_FLAG = 32768;
    const PART_KEY_FLAG = 16384;
    const GROUP_FLAG = 32768;
    const UNIQUE_FLAG = 65536;

    static $signatures = array();
    static $typeindex = array();
    var $type = array();
    var $classname;

    function __construct($mysqltypes, $classname) {
        if (!is_array($mysqltypes)) {
            $this->type = array($mysqltypes);
        } else {
            foreach ($mysqltypes as $type)
                if (!in_array($type, $this->type))
                    $this->type[] = $type;
        }
        $this->classname = $classname;

        self::$signatures[] = $this;
        foreach ($this->type as $i) {
            if (!isset(self::$typeindex[$i]))
                self::$typeindex[$i] = $this;
        }
    }

    static function find($sqltype) {
        // find appropriate class for description
        if (isset(self::$typeindex[$sqltype->type]))
            return self::$typeindex[$sqltype->type];
        self::gripe("find", print_r($sqltype, true), "mysql type not found: {$sqltype->type}");
        return null;
    }

    public function override(_SQLValueSignature $sig) {
        // to override an SQL value type class with your own derivate.  This will usually be derived from the standard value class for that type
        array_unshift(self::$signatures, $sig);
    }

    private static function gripe($function, $arg, $msg) {
        die("<p><hr><strong>FAILED</strong> on _SQLValueSignature function <br><div style='text-indent:2em;'><samp>$function($arg)</samp></div><div style='text-indent:2em;'><samp>$msg</samp></div>");
    }

}

;

/**
 * abstract class SQLValue, internal, dont use!
 *
 * base class for all value types to be used to communicate with SQL
 * 	
 * this (super)class converts SQL data types to PHP and vice versa.
 * intended usage is mainly for creation of SQL commands such as SELECT statements and conversion of query results to PHP
 * there are (unfortunately) 3 representations 
 * - the PHP value (found in ->php)
 * - the SQL query syntax, which is a fully quoted string (found in ->sql)
 * - the PHP SQL return syntax, which is a usually a string with quotes removed.  There is no need to retrieve this syntax, as it is never used except in results
 *   there is no member thus.  However, there is a member to initialize a value using this notation
 * as a result, the assignPHP member will take a PHP representation and will create the sql representation from it
 * the assignResult member however, will take the PHP SQL return syntax(!) and create both the SQL representation and the PHP representation from it.
 * as a surprising result, it is NOT possible to convert back and forth between the ->sql member and the ->php member using assignPHP()!
 *
 * SQL usually provides various shorthands to represent data types.  Those are not supported.  The representation MySQL uses to retrieve and display data is used. 
 *
 * for some SQL datatypes, there is a ZERO value (as opposed to NULL, which means that it isnt set at all)
 * the SQL NULL is straight forwarded to null in PHP (this is done by PHP silently), whereas the ZERO value is type specific.
 *
 * example using the SQL DATETTIME data type
 * 
 * an SQL DATETIME is represented as 'YYYY-MM-DD HH:MM:SS', e.g. '0000-00-00 00:00:00'
 * when presented in an SQL result (e.g. in a mysql_fetch_array() result), it is shown as a string like "0000-00-00 00:00:00" (note: no ' quotes)
 * in PHP we convert this to (int) 0.
 * 
 */
abstract class SQLValue {

    /**
     * php representation.
     * @var string
     */
    var $php;

    /**
     * sql query syntax representation
     * @var string
     */
    var $sql;

    /**
     * zero value representation, type specific, disabled if equal to null
     * @var string
     */
    var $phpZero = null;

    /**
     * zero value representation, type specific, disabled if equal to null
     * @var string
     */
    var $sqlZero = null;

    /**
     * zero value representation, type specific, disabled if equal to null
     * @var string
     */
    var $sqlResultZero = null;

    /**
     * last initializer used to assign this object a value
     * @var mixed 
     */
    var $initializer = null;

    /**
     * some data types are in string syntax in SQL
     * @var bool 
     */
    var $useStringQuotes = false;
    protected static $defaultSQLConnection = null;

    /**
     * static member to keep "the one" sql connection (if no is supplied with constructor)
     * @var $SQLConnection 
     */
    protected $SQLConnection = null;

    /**
     * holds the (optional) field description as returned by fetch_fields
     * @var dbfieldspec
     */
    var $dbfieldspec;

    /**
     * @param mysqli $sql the mysqli connecto to be used
     * @return void
     */
    static function setSQLConnection(mysqli $sql) {
        self::$defaultSQLConnection = $sql;
    }

    /**
     * returns the types type signature
     * @return _SQLValueSignature
     */
    // /* abstract */ static function signature();

    /**
     * Constructor, note: to explicitly assign the NULL value, you need to call assignPHP()!
     *
     * @param mixed $value initial value to assign to object (using assignPHP()), so it must be the PHP-style representation of the value
     * @param mysqli $conn mysqli connector to be used
     */
    public function __construct($value = null, stdClass $field = null, mysqli $conn = null) {
        $this->SQLConnection = ($conn === null) ? self::$defaultSQLConnection : $conn;
        if ($value !== null) {
            $this->assignPHP($value);
        } else {
            $this->php = $this->sql = null;
        }
        $this->dbfieldspec = $field;
    }

    /**
     * assign using the PHP representation
     *
     * @param string $value PHP representation of value
     * @return void
     */
    public /* final */ function assignPHP($value) {
        if ($value === null) {
            $this->php = null;
            $this->sql = "NULL";
        } elseif ($value === $this->phpZero) {
            $this->php = $this->phpZero;
            $this->sql = $this->sqlZero;
        } else {
            $this->php = $value;
            $this->sql = $this->convertPHP2Query($value);
            if ($this->useStringQuotes) {
                $this->sql = "'" . $this->SQLConnection->real_escape_string($this->sql) . "'";
            }
        }
        $this->assigned(null, $value);
        $this->initializer = $value;
    }

    /**
     * assign using the PHP/SQL return representation
     *
     * @param string $value PHP/SQL return representation of value
     * @return void
     */
    public /* final */ function assignResult($value) {
        if ($value === null) {
            $this->php = null;
            $this->sql = "NULL";
        } elseif ($value === $this->sqlResultZero) {
            $this->php = $this->phpZero;
            $this->sql = $this->sqlZero;
        } else {
            $this->assignPHP($this->convertResult2PHP($value));
        }
        $this->assigned($value, null);
        $this->initializer = $value;
    }

    // conversion functions, will probably be overridden    
    /**
     * convert from result to php syntax
     *
     * @param mixed $sql value as returned from SQL
     * @return string 
     *
     */
    protected function convertResult2PHP($sql) {
        return $sql;
    }

    // 
    /**
     * convert from PHP to SQL query syntax
     *
     * @param mixed $php value as returned from SQL
     * @return string 
     */
    protected function convertPHP2Query($php) {
        return $php;
    }

    /**
     * assigned will be called when a new value has been assigned to the variable, not used by std classes but may be useful for user derived classes
     * @abstract
     * @param string $result 
     * @param string $php 
     * @return void
     *
     */
    protected function assigned($result, $php) {
        
    }

}

;

/**
 * just adds ability to be casted to (string)
 *
 */
class DateTimeEx extends DateTime {

    public function __toString() {
        return $this->format('r I (U) ') . $this->getTimezone()->getName(); // format as ISO 8601
    }

}

/**
 * ranges from '1000-01-01 00:00:00' to '9999-12-31 23:59:59' and cannot be represented by a UNIX time stamp thus (see SQLTimestampValue)
 */
class SQLDatetimeValue extends SQLTimestampValue /* inherits null and zero constants */ {

    static function signature() {
        return new _SQLValueSignature(_SQLValueSignature::DATETIME, __CLASS__);
    }

    /**
     * SQL Datetimes do not have a time zone associated.  defaultTimeZone is used to specify the time zone to be used when converting such values to PHP DateTimeEx
     * 'null' is used for the "local timezone"
     * 'new DateTimeZone("UTC")' for UTC
     * @var string $defaultTimeZone
     */
    static $defaultTimeZone = null;

    /**
     * TZ used to convert from PHP results (null is "localtime")
     * @var DateTimeZone 
     */
    protected $timezone;

    /**
     * UTC datetime zero
     * @var int
     */
    var $phpZero;

    public function __construct($value = null, stdClass $field = null, mysqli $conn = null, $timezone = false) {
        parent::__construct($value, $field, $conn);
        $this->phpZero = self::$defaultTimeZone === null ? new DateTimeEx($this->sqlResultZero) : new DateTime($this->sqlResultZero, self::$defaultTimeZone);
        if ($timezone === false) /* use class default */
            $this->timezone = self::$defaultTimeZone;
        else
            $this->timezone = $timezone;
    }

    protected function convertPHP2Query($php) {
        return $php->format('Y-m-d H:i:s');
    }

    protected function convertResult2PHP($sql) {
        return $this->timezone === null ?
                new DateTimeEx($sql) :
                new DateTimeEx($sql, $this->timezone);
    }

}

;
_SQLValueSignature::$signatures[] = SQLDatetimeValue::signature();

/**
 * 	in PHP, its a UTC timestamp (such as in time())
 *  in SQL, it looks like YYYY-MM-DD HH:MM:SS
 *                        0    5  8  11 14 17
 * is somehow identical to SQLDatetimeValue, except that the range is in the UNIX timestamp range (that is, '1970-01-01 00:00:01' UTC to '2038-01-19 03:14:07') whereas
 * a DATETIME ranges from '1000-01-01 00:00:00' to '9999-12-31 23:59:59'
 */
class SQLTimestampValue extends SQLValue {

    /**
     * UTC timestamp zero
     * @var int
     */
    var $phpZero = 0;

    /**
     * SQL zero time, '0000-00-00 00:00:00'
     * @var string 
     */
    var $sqlZero = "'0000-00-00 00:00:00'";

    /**
     * SQL zero time query result, 0000-00-00 00:00:00
     * @var string 
     */
    var $sqlResultZero = "0000-00-00 00:00:00";

    /**
     * true
     * @var bool
     */
    var $useStringQuotes = true;

    /**
     * This is method signature
     *
     * @return mixed This is the return value description
     *
     */
    static function signature() {
        return new _SQLValueSignature(_SQLValueSignature::TIMESTAMP, __CLASS__);
    }

    protected function convertPHP2Query($php) {
        return strftime("%Y-%m-%d %H:%M:%S", $php);
    }

    protected function convertResult2PHP($sql) {
        //     mktime($Stunde, $Minute, $Sekunde, $Monat, $Tag, $Jahr, $is_dst)
        return mktime(substr($sql, 11, 2), substr($sql, 14, 2), substr($sql, 17, 2), substr($sql, 5, 2), substr($sql, 8, 2), substr($sql, 0, 4));
    }

}

;
_SQLValueSignature::$signatures[] = SQLTimestampValue::signature();

class SQLDateValue extends SQLValue {

    // in PHP, its a UTC timestamp (such as in time())
    // in SQL, it looks like YYYY-MM-DD
    //                       0    5  8
    var $phpZero = 0;
    var $sqlZero = "'0000-00-00'";
    var $sqlResultZero = "0000-00-00";
    var $useStringQuotes = true;

    static function signature() {
        return new _SQLValueSignature(_SQLValueSignature::DATE, __CLASS__);
    }

    function convertPHP2Query($php) {
        return strftime("%Y-%m-%d", $php);
    }

    function convertResult2PHP($sql) {
        //     mktime($Stunde, $Minute, $Sekunde, $Monat, $Tag, $Jahr, $is_dst)
        return mktime(0, 0, 0, substr($sql, 5, 2), substr($sql, 8, 2), substr($sql, 0, 4));
    }

}

;
_SQLValueSignature::$signatures[] = SQLDateValue::signature();

class SQLTimeValue extends SQLValue {

    // a TIME value is actually a time difference measured in seconds 
    // in PHP, it is a signed integer
    // in SQL, it looks like HHH:MM:SS where HHH may be large (more than 2h, up to almost 35 days) and negative
    //                       0   4  7
    var $phpZero = 0;
    var $sqlZero = "'00:00:00'";
    var $sqlResultZero = "00:00:00";
    var $useStringQuotes = true;

    static function signature() {
        return new _SQLValueSignature(_SQLValueSignature::TIME, __CLASS__);
    }

    protected function convertPHP2Query($php) {
        $i = abs($php);
        $h = intval($i / (60 * 60));
        $m = intval(($i - ($h * 60 * 60)) / 60);
        $s = $i - ($h * 60 * 60) - ($m * 60);
        return (($php < 0) ? "-" : "") . $h . ":" . $m . ":" . $s;
    }

    protected function convertResult2PHP($sql) {
        $p = explode(":", $sql);
        return ((abs($p[0]) * 60 * 60 ) + ($p[1] * 60) + $p[2]) * (($sql[0] == "-") ? -1 : 1);
    }

}

;
_SQLValueSignature::$signatures[] = SQLTimeValue::signature();

class SQLYearValue extends SQLValue {

    // a YEAR value is a year between 1901 and 2155 
    // in PHP, it is an unsigned integer
    // in SQL, it looks like YYYY 
    var $phpZero = 0;
    var $sqlZero = "'0000'";
    var $sqlResultZero = "0000";
    var $useStringQuotes = true;

    static function signature() {
        return new _SQLValueSignature(_SQLValueSignature::YEAR, __CLASS__);
    }

    protected function convertPHP2Query($php) {
        return sprintf("%04d", $php);
    }

    protected function convertResult2PHP($sql) {
        return (int) ($sql + 0);
    }

}

;
_SQLValueSignature::$signatures[] = SQLYearValue::signature();

class SQLIntegerValue extends SQLValue {

    // there are many integer types in SQL but they are mapped to (int) anyway in PHP
    // bit(1) is interpreted as boolean
    protected $isbool = false;

    public function __construct($value = null, stdClass $field = null, mysqli $conn = null) {
        parent::__construct($value, $field, $conn);
        $this->isbool = ($field !== null && $field->length == 1 && $field->type == _SQLValueSignature::BIT);
    }

    static function signature() {
        return new _SQLValueSignature(
                        array(
                            _SQLValueSignature::INT,
                            _SQLValueSignature::TINYINT,
                            _SQLValueSignature::SMALLINT,
                            _SQLValueSignature::MEDIUMINT,
                            _SQLValueSignature::BIGINT,
                            _SQLValueSignature::BIT
                        ),
                        __CLASS__);
    }

    protected function convertPHP2Query($php) {
        if ($this->isbool)
            return($this->php ? "1" : "0");
        return (string) $php;
    }

    protected function convertResult2PHP($sql) {
        $res = $this->isbool ? ((bool) $sql + 0) : ((int) ($sql + 0));
        // if (is_int($res)) die("not numeric($sql): " . print_r($this, true));
        return ($res);
    }

}

;
_SQLValueSignature::$signatures[] = SQLIntegerValue::signature();

class SQLFloatValue extends SQLValue {

    // there are many number types in SQL, surprisingly, decimal(x,y) is a float (or double) too
    // btw: there is no such thing a sa "double" in PHP apparently

    static function signature() {
        return new _SQLValueSignature(
                        array(
                            _SQLValueSignature::FLOAT,
                            _SQLValueSignature::DOUBLE,
                            _SQLValueSignature::DECIMAL,
                        ),
                        __CLASS__);
    }

    protected function convertPHP2Query($php) {
        return (string) $php;
    }

    protected function convertResult2PHP($sql) {
        return (float) ($sql + 0);
    }

}

;
_SQLValueSignature::$signatures[] = SQLFloatValue::signature();

class SQLStringValue extends SQLValue {

    // there are many string types in SQL but they are mapped to (string) anyway in PHP
    var $useStringQuotes = true;

    static function signature() {
        return new _SQLValueSignature(
                        array(
                            _SQLValueSignature::BINARY,
                            _SQLValueSignature::VARBINARY,
                            _SQLValueSignature::BLOB,
                        ),
                        __CLASS__
        );
    }

    protected function convertPHP2Query($php) {
        return (string) $php;
    }

    protected function convertResult2PHP($sql) {
        return (string) $sql;
    }

}

;
_SQLValueSignature::$signatures[] = SQLStringValue::signature();

class SQLBinaryValue extends SQLValue {

    // binaries are either
    // 254 binary(8) col7 def= max_length=0 length=8 charsetnr=63 flags=128 decimals=0
    // 254 char(2) col30 def= max_length=0 length=2 charsetnr=8 flags=0 decimals=0
    // 254 enum('ja','nein') col38 def= max_length=0 length=4 charsetnr=8 flags=256 decimals=0
    // 254 set('rot','grün','blau') col39 def= max_length=0 length=13 charsetnr=8 flags=2048 decimals=0
    var $useStringQuotes = true;
    var $phpZero = array();

    static function signature() {
        return new _SQLValueSignature(_SQLValueSignature::BINARY, __CLASS__);
    }

    function __construct() {
        die("class SQLEnumValue not implemented");
    }

    protected function convertPHP2Query($php) {
        return (string) $php;
    }

    protected function convertResult2PHP($sql) {
        return (string) $sql;
    }

}

;
_SQLValueSignature::$signatures[] = SQLBinaryValue::signature();


/*
 * end of types
 */

class SQLObject {

    var $dict;
    var $value;

}

;

class MySQLDB extends mysqli {

    public function __construct($DBHost, $DBUser, $DBPW, $DBName) {
        parent::__construct($DBHost, $DBUser, $DBPW);
        if (!$this->connect_error) {
            if (!$this->select_db($DBName)) {
                $this->dieOnError("", $DBHost . ", " . $DBUser . ", " . $DBPW . ", " . $DBName, false);
                $this->close();
                $this->connect_error = true;
            }
        } else {
            $this->dieOnError("", $DBHost . ", " . $DBUser . ", " . $DBPW . ", " . $DBName, false);
        }
    }

    private function dieOnError($function, $arg = "", $dontdie = true, $anyway = false) {
        if ($this->errno != 0 || $anyway) {
            $msg = ("<p><hr><strong>FAILED</strong> on mysqli function <br><div style='text-indent:2em;'><samp>$function($arg)</samp></div><br>Error #{$this->errno}:<br><div style='text-indent:2em;'><samp>{$this->error}</samp></div>");
            print $msg;
            if (!$dontdie)
                die("exit");
        }
    }

    /**
     * sends a statement to SQL and returns a stored result, raw access to parent class, just throws an error message
     *
     * @param string $sql entire query string, calls myqsli::query using mode MYSQLI_STORE_RESULT
     * @param bool $dontdie 
     * @return mixed Returns false on failure. For successful SELECT, SHOW, DESCRIBE or EXPLAIN queries mysqli_query will return a MySQLi_Result object. For other successful queries mysqli_query will return true.
     */
    public function query($sql, $dontdie = false) {
        // 
        $res = parent::query($sql /* using store_result */);
        $this->dieOnError("query", $sql, $dontdie);
        return $res;
    }

    /**
     * start a transaction
     * turns off autocommit and locks all given tables
     * @param array $tables if tables are set, they are locked first thing, 
     *                      using value as table name and key (if not numeric) as alias.
     *                      value must include the lock type!
     */
    function startTransaction($tables = array()) {
        if (!is_array($tables))
            $tables = array($tables);
        // disable auto commit
        $good = self::goodResult($this->query("SET autocommit=0"));
        // if required, lock tables
        if (count($tables) > 0) {
            $query = "LOCK TABLES ";
            $names = array();
            foreach ($tables as $alias => $name) {
                list($thistable, $thislock) = explode(" ", $name);
                if (!is_numeric($alias))
                    $thistable .= " AS $alias";
                $names[] = $thistable . " $thislock";
            }
            $query .= implode(", ", $names);
            $good = $good && self::goodResult($this->query($query));
        }
        return $good;
    }

    /**
     * end a transaction, unlock all tables, turns autocommit on!
     * @param bool $commit commits transaction if true, rollbacks otherwise
     *                     if non-bool, it is used as an SQL stmt (e.g. "COMMIT WORK")
     * @return bool returns true if the whole transaction was OK, that is, if a commit is done and succeeds
     *              so you can write "$ok = $sql->startTransaction() && $sql->query(); good = $sql->endTransaction($good)
     */
    function endTransaction($commit = true) {
        return self::goodResult($this->query(is_bool($commit) ? ($commit ? "COMMIT" : "ROLLBACK") : $commit)) &&
                self::goodResult($this->query("UNLOCK TABLES")) &&
                self::goodResult($this->query("SET autocommit=1")) &&
                $commit;
    }

    /**
     * just to simplify this strange !== operator
     * @param mixed $result
     * @return bool true if $result is === true (not == true!)  
     */
    static function goodResult($result) {
        return $result !== false;
    }

    /**
     * fetch all result rows into an indexed array of objects
     *
     * @param mixed $res a query() result
     * @param string $class class to map results into, defaults to stdClass
     * @param boolean $doclose does a free() on $res if true
     * @return array of objects of type $class
     *
     */
    public function fetchAll($res, $class = "stdClass", $doclose = true) {
        // 
        $rows = array();
        while (($obj = $res->fetch_object($class)) !== null) {
            $rows[] = $obj;
        }
        if ($doclose)
            $res->free();
        return $rows;
    }

    /**
     * fetch a row in to an object
     *
     * @param mixed $res query() result
     * @param array $fields (array of) field specs as returned by fetch_fields
     * @param string $class per-row result class
     * @return mixed object of type $class
     *
     */
    public function fetchRowTyped(mysqli_result $res, $fields, $class = "stdClass") {
        if (($row = $res->fetch_array(MYSQLI_ASSOC)) !== null) {
            $obj = new $class;
            $obj->_fields = new stdClass;
            // walk trough row and insert proper values into object
            $i = 0;
            foreach ($row as $name => $value) {
                $typename = _SQLValueSignature::find($fields[$i])->classname;
                $obj->_fields->$name = new $typename(null, $fields[$i], $this);
                $obj->_fields->$name->assignResult($value);
                $obj->$name = $obj->_fields->$name->php;

                $i++;
            }
            return $obj;
        } else
            return null;
    }

    private function checkFieldsArray($fields) {
        $fmembers = array();
        foreach ($fields as $key => $value) {
            if (isset($fmembers[$value->name])) {
                die(__CLASS__ . '::' . __METHOD__ . ": duplicate field '{$value->name}' in field spec: \n" .
                        print_r($value, true) . " vs. \n" .
                        print_r($fmembers[$value->name], true) .
                        "\nuse proper 'AS' clause in query to make result column names unique!");
            } else {
                $fmembers[$value->name] = $value;
            }
        }
        return $fmembers;
    }

    /**
     * fetch all results creating a typed object
     *
     * @param mixed $res query() result
     * @param array $fields (array of) field specs as returned by fetch_fields
     * @param string $class per-row result class
     * @param boolean $doclose does a $res->free() if true
     * @return mixed array of result objects (one per row)
     */
    public function fetchAllTyped($res, $fields, $class = "stdClass", $doclose = true) {
        // 
        $rows = array();
        // check fields
        $fmembers = $this->checkFieldsArray($fields);
        // read rows
        while (($obj = $this->fetchRowTyped($res, $fields, $class)) !== null) {
            $rows[] = $obj;
        }
        if ($doclose)
            $res->free();
        return $rows;
    }

    private function showObj($obj) {
        $show = gettype($obj) . ": ";
        if (is_object($obj)) {
            $inner = false;
            if (method_exists($obj, "__toString"))
                $inner = (string) $obj;
            else {
                foreach ($obj as $member => $value) {
                    $inner .= "[$member]=>" . print_r($value) . " ";
                }
                if (!$inner)
                    $inner = serialize($obj);
            }
            $show .= $inner;
        }
        else
            $show .= $obj;
        return $show;
    }

    /**
     * debug only, prints out a result from query()
     *
     * @param mixed $res 
     * @return void
     *
     */
    public function showTypedResult($res) {
        // 
        // print_r($res);
        print "\n<table border='1'>\n";
        foreach ($res as $key => $val) {
            print " <tr>\n  <td>";
            print "#$key";
            print "  </td>\n  <td>\n    <table border=1>\n";
            print "      <thead><tr><th>member</th><th>table</th><th>initializer</th><th>php value</th><th>SQL value</th></tr></thead>\n";
            foreach ($val as $name => $item) {
                // print "<pre>" . print_r($val->$name/*->__dict->type*/, true) . "</pre>";
                if ($name == "_fields")
                    continue;
                print "      <tr>\n        \n";
                print "          <td>" .
                        htmlentities($name) .
                        "</td><td>" .
                        htmlentities($val->_fields->$name->dbfieldspec->table) .
                        "</td><td>" .
                        htmlentities($this->showObj($val->_fields->$name->initializer)) .
                        "</td><td>" .
                        htmlentities($this->showObj($val->_fields->$name->php)) .
                        "</td><td>" .
                        htmlentities($val->_fields->$name->sql) .
                        "</td>\n";
                print "      </tr>\n";
            }
            print "    </table>\n  </td>\n  </tr>\n";
        }
        print "</table>\n";
    }

    /**
     * create an SQL value spec (that can be used in e.g. insert statements) for the object
     *
     * @param mixed $obj an object created by createObjectFromSpec()
     * @return string
     *
     */
    public function createSQLObjectValues($obj) {
        // 
        $v = "";
        $spec = $obj->__spec;
        foreach ($spec as $key => $column) {
            $name = $column->name;
            $sql = $obj->$name->sql;
            if ($sql !== null) {
                if ($v != "")
                    $v .= ", ";
                $v .= "$name = $sql";
            }
        }
        return $v;
    }

    private function getMeta($query) {
        $stmt = $this->stmt_init();
        if ((($ok = $stmt->prepare($query)) === false) ||
                (($meta = $stmt->result_metadata()) == false) ||
                (($fields = $meta->fetch_fields()) == false)) {
            $this->dieOnError("queryForObjects", $query, true, true);
            return null;
        }
        return $fields;
    }

    /**
     * get an array of objects defined by a query 
     * @param mixed $query a valid SQL statement
     * @return array the query result as returned by fetchAllTyped() (or null on error)
     *
     */
    public function queryForObjects($query, $rowClass = "stdClass", $doclose = true) {

        // get result meta data
        $fields = $this->getMeta($query);
        if ($fields === null)
            return null;
        // get real result
        $res = $this->query($query);
        $resobjs = $this->fetchAllTyped($res, $fields, $rowClass, $doclose);
        return $resobjs;
    }

    public function createEmptyObject($table = null, $query = null, $rowClass = 'stdClass') {
        // unfinished !
        if ($table === null) {
            if ($query === null) {
                $this->dieOnError("cannot createEmptyObject with neither table nor query");
            }
        } else {
            $query = "SELECT * FROM $table";
        }
        $fields = $this->getMeta($query);
        if ($fields === null)
            return null;
        return $fields;
    }

}

?>