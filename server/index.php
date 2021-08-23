<?php

/*
 * To change this license header, choose License Headers in Project Properties.
 * To change this template file, choose Tools | Templates
 * and open the template in the editor.
 */

require_once("routepoint.class.php");
require_once 'config.php';


// var_dump($_REQUEST);

/**
 * show a call (result from query) in a table row
 * @param stdObj $call
 * @param arry $xtra added columns
 */
function showCall(stdClass $call, array $logs, array $xtra = array(), $i = 0) {
    $html = "";
    $html .= "<tr class='calltablerow'>";
    $columns = array();
    // var_dump($call->_fields);
    foreach ($call as $var => $value)
        if ($var[0] != "_")
            $columns[$var] = htmlspecialchars(($call->_fields->$var instanceof SQLTimestampValue) ? $call->_fields->$var->sql : $value );
    foreach ($xtra as $var => $value)
        $columns[$var] = $value;

    foreach ($columns as $var => $value) {
        $html .= "<td class='calltablecell calltablecell-$var " . ($i ? ($i & 1 ? "odd" : "even") : "") . "'>$value</td>";
    }
    $html .= "</tr><tr class='tablerow logrow'><td class='logrow' colspan='20'>";
    $lastquery = "";
    $lastresponse = "";
    $skipped = 0;
    $firsttime = 0;
    foreach ($logs as $log) {
        if ($lastquery != $log->query || $lastresponse != $log->response) {
            if ($skipped) {
                $html .= "<div class='logrowfirst'> ... $skipped similar items skipped</div>"; $skipped = 0;
            }
            $q = htmlspecialchars($log->query);
            $q = preg_replace("/((event|cause|dtmf|cdpn)=[^ ]+)/", ' <strong>$1</strong> ', $q);
            $r = htmlspecialchars($log->response);
            if (!$firsttime) {
            $html .= "<div class='logrowfirst'>" . $log->_fields->timestamp->sql . "</div>";
            $firsttime = $log->timestamp;
            } else {
                $html .= "<div class='logrowfirst'>" . ($log->timestamp-$firsttime) . "</div>";
            }
            $html .= "<div>" . " -> " . $q . "</div>";
            $html .= "<div>" . " <- " . $r . "</div>";
        } else
            $skipped++;
        $lastquery = $log->query;
        $lastresponse = $log->response;
    }
    $html .= "</td></tr>";
    return $html;
}

/**
 * show a table header suitable for listing calls
 * @param stdObj $call
 * @return strings
 */
function showHeader($call, array $xtra = array()) {
    $html = "";
    $html .= "<thead><tr class='calltablehead'>";
    $columns = array();
    foreach ($call as $var => $value)
        if ($var[0] != "_")
            $columns[$var] = $value;
    foreach ($xtra as $var => $value)
        $columns[$var] = $value;

    foreach ($columns as $var => $value) {
        $html .= "<th class='calltablecolumn calltablecolumn-$var '>" .
                htmlspecialchars($var) .
                "</th>";
    }
    $html .= "</tr></thead>";
    return $html;
}

/**
 * show function links
 * @param stdObj $call
 */
function getCmds($call) {
    // known and valid cmds
    global $cmds;

    // options to pass (so we can redirect correctly)
    $passopts = array();
    foreach ($_REQUEST as $name => $value)
        $passopts[] = "$name=" . urlencode($value);

    // set confid to affected call
    $passopts[] = "objguid=$call->confid";

    // construct action links
    $html = array();
    if (trim($call->cmd) == "" || $call->done != 0) {
        foreach ($cmds as $prompt => $link) {
            $html[] = "<a href='?setcmd=" .
                    urlencode(trim(preg_replace("/[ \r\n]+/", " ", $link))) .
                    "&" .
                    implode("&", $passopts) .
                    "'>" .
                    htmlspecialchars($prompt) .
                    "</a>";
        }
    } else {
        $html[] = "<a href='?donecmd&" . implode("&", $passopts) . "'>set cmd 'done' state</a>";
    }

    // special links
    return implode(" | ", $html);
}

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

    if (($opt = getparam("caller")) !== null) {
        $str->assignPHP($opt);
        $query .= "
    AND caller_e164 = " . $str->sql
        ;
    }

    return $query;
}

function redirect() {
    // redirect back to index page
    $opts = $_REQUEST;
    unset($opts["setcmd"]);
    foreach ($opts as $opt => $val)
        $opts[$opt] = urlencode($val);
    header("Location: ?" . implode("&", $opts));
}

$rp = new RoutepointAccess(DBHost, DBUser, DBPW, DBName);

if (($cmd = getparam("setcmd")) !== null && (($confid = getparam("objguid")) !== null)) {
    // write cmd to db
    $cmd = new SQLStringValue($cmd, null, $rp);
    $confid = new SQLStringValue($confid, null, $rp);
    $sql = "UPDATE routepoint.call SET cmd = $cmd->sql, done = 0 WHERE confid = $confid->sql";
    $rp->query($sql);
    redirect();
} elseif (($cmd = getparam("donecmd")) !== null && (($confid = getparam("objguid")) !== null)) {
    // write cmd to db
    $confid = new SQLStringValue($confid, null, $rp);
    $sql = "UPDATE routepoint.call SET done = 1 WHERE confid = $confid->sql";
    $rp->query($sql);
    redirect();
}

$calls = $rp->queryForObjects(makeQuery($rp));

// print "query: <pre>$query</pre>";
// var_dump($calls);
print "
<HTML>
  <HEAD>
    <LINK href='routepoint.css' rel='stylesheet' type='text/css'>
      <meta http-equiv='Content-Type' content='text/html; charset=UTF-8'/>" .
        ((count($calls) || !$stoprefreshonnocalls) ? "<meta http-equiv='refresh' content='$refreshrate; URL='>" : "") . "
  </HEAD>
  <body class='routepoint-app'>";
if (count($calls)) {
    print "<table class='routepoint-table'>" . showHeader($calls[0], array("action" => ""));
    $i = 1;
    foreach ($calls as $call) {
        // get logs
        $confid = new SQLStringValue($call->confid, null, $rp);
        $sql = "SELECT * FROM routepoint.log WHERE confid = {$confid->sql} ORDER BY timestamp DESC, id DESC LIMIT 60 ";
        $logs = $rp->queryForObjects($sql);
        print showCall($call, $logs, array("action" => getCmds($call)), $i);
        $i++;
    }
    print "</table>";
} else {
    print "No calls" . ($stoprefreshonnocalls ? " - refresh stopped" : "");
}
print "</body></html>";
?>