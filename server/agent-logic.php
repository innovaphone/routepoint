<?php

/*
 * script to handle <exec> request from PBX / XML
 * requires confid argument (conferenceGuid of call)
 */

require_once('routepoint.class.php');
require_once 'config.php';

if (($confid = getparam("confid")) === null) {
    die("<error/> missing confid parameter");
}

$rp = new RoutepointAccess(DBHost, DBUser, DBPW, DBName);

/**
 * creates query for current calls
 * @param RoutepointAccess $rp
 * @return string
 */
function makeQuery(RoutepointAccess $rp) {
// show calls query
    $query = "
SELECT 
    *
FROM
    routepoint.call
WHERE true
";

    $str = new SQLStringValue("", null, $rp);

// restrict to given call if requested
    if (($opt = getparam("confid")) !== null) {
        $str->assignPHP($opt);
        $query .= "
    AND confid = " . $str->sql
        ;
    }

    return $query;
}

$answer = "<!-- none -->";

/**
 * send next command to calling xml script
 * must not be called twice in a single call
 * @param string $code cmd sent to xml script.  only raw code needed, decoration is implied
 */
function answer($code, $wait = 0) {
    global $answer;
    $code = '<assign out="ok" value="1"/><assign out="wait" value="' . $wait . '"/>' . "\n$code\n";
    if (getparam("debug") !== null)
        print htmlentities($code);
    return $answer = "<voicemail><function define='Main'>$code</function></voicemail>";
}

/**
 * enter new call into db
 * @global RoutepointAccess $rp
 * @param SQLStringValue $confid
 */
function newcall(SQLStringValue $confid) {
    // create call record
    global $rp;
    $sql = "INSERT INTO routepoint.call SET confid = $confid->sql";
    $rp->query($sql);
    // have script retry 
    print answer("<!-- new call entry created with confid $confid->php -->");
}

/**
 * remove call from db
 * @global RoutepointAccess $rp
 * @param stdClass $call
 * @param SQLStringValue $confid
 */
function cleanup($call, SQLStringValue $confid) {
    // delete call record
    global $rp, $answer;
    print answer("<!-- call with confid {$confid->php} deleted -->");
    logCall($call, $confid, $answer);
    $sql = "DELETE FROM routepoint.log WHERE confid = $confid->sql";
    $rp->query($sql);
    $sql = "DELETE FROM routepoint.call WHERE confid = $confid->sql";
    $rp->query($sql);
}

/**
 * handle DTMF 
 * @global RoutepointAccess $rp
 * @param type $call
 * @param type $dtmf
 * @param SQLStringValue $confid
 */
function dtmf($call, $dtmf, SQLStringValue $confid) {
    // save dtmf (just for debug, usually we'd have logic here)
    global $rp;
    $sqldtmf = new SQLStringValue($dtmf, null, $rp);
    if ($dtmf != '#') {
        $sql = "UPDATE routepoint.call SET cumulated_dtmf = concat(cumulated_dtmf, $sqldtmf->sql) WHERE confid = $confid->sql";
    } else {
        $sql = "UPDATE routepoint.call SET cumulated_dtmf = '' WHERE confid = $confid->sql";
    }
    $rp->query($sql);
    // have script retry 
    print answer("<!-- dtmf $dtmf seen on call $confid->php ($sql))-->");
}

/**
 * update db call entry with new values from parameters
 * @global RoutepointAccess $rp
 * @param stdClass $call
 * @param SQLStringValue $confid
 */
function update($call, SQLStringValue $confid) {
    global $rp;
    $toupdate = array();
    $str = new SQLStringValue(null, null, $rp);
    // update all request args to db (if they match)
    foreach ($_REQUEST as $name => $value) {
        if ($name[0] == "_")
            continue;
        $dbname = preg_replace("/[^a-zA-Z0-9_]/", "_", $name);
        if (!property_exists($call, $dbname)) {
            // print "$name($dbname): not a db field<br>";
            continue;
        }
        if ($value == ($call->$dbname)) {
            // print "$name($dbname): value not changed ('$value' == '{$call->$dbname}')<br>";
            continue;
        } else {
            // print "$name($dbname): value changed ('$value' <> '{$call->$dbname}')<br>";
        }
        $str->assignPHP($value);
        $toupdate[] = "$dbname = $str->sql";
    }
    if (count($toupdate)) {
        $sql = "
            UPDATE routepoint.call SET 
            " . implode(", ", $toupdate) . "
            WHERE confid = $confid->sql";
        $rp->query($sql);
    }
}

/**
 * log call activity
 * @param stdClass $call
 * @param SQLStringValue $confid
 * @param string $query
 * @param string $response
 */
function logCall(stdClass $call, SQLStringValue $confid, $response) {
    global $rp;
    $request = array();
    foreach ($_REQUEST as $p => $v)
        $request[] = "$p=$v";
    $request = implode(" & ", $request);
    $sqlrequest = new SQLStringValue($request, null, $rp);
    $sqlresponse = new SQLStringValue($response, null, $rp);
    $sql = "
        INSERT INTO routepoint.log
        SET confid = {$confid->sql}, query = {$sqlrequest->sql}, response = {$sqlresponse->sql}";
    $rp->query($sql);
}

$calls = $rp->queryForObjects(makeQuery($rp));
$sqlconfid = new SQLStringValue($confid, null, $rp);
if (count($calls) == 0) {
    newcall($sqlconfid);
    $calls = $rp->queryForObjects(makeQuery($rp));
    update($calls[0], $sqlconfid);
    $call = $calls[0];
} else {

    $call = $calls[0];
    update($calls[0], $sqlconfid);

// see if we have an event 
// print_r($_REQUEST);

    switch (getparam("event", "main-loop")) {
        case "call-end" :
            cleanup($call, $sqlconfid);
            exit;
        case "dtmf" :
            dtmf($call, getparam("dtmf"), $sqlconfid);
            break;
        case "main-loop" :
        default:
            // known call, if cmd, have script execute it
            if ($call->done == 1 || trim($call->cmd) == "") {
                // no cmd at present
                // have script retry 
                print answer("<!-- no current cmd -->", 1);
            } else {
                // cmd present, dispatch to script
                $sql = "UPDATE routepoint.call SET done = 1 WHERE confid = $sqlconfid->sql";
                $rp->query($sql);
                print answer("<!-- cmd dispatched -->$call->cmd");
            }
            break;
    }
}
logCall($call, $sqlconfid, $answer);
?>