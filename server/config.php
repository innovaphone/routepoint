<?php

/**
 * cmds in call context menu
 */
$cmds = array(
    "play announcement 'wellcome'" => "
            <store-get root='prompts' name='wellcome.g711a' out-url='\$ctrl'/>
            <pbx-prompt repeat='true' url='\$ctrl' sec='1' barge-in='true'/>
            ",
    "play announcement 'en_please_enter_your_pin'" => "
            <store-get root='prompts' name='en_please_enter_your_pin.g711a' out-url='\$ctrl'/>
            <pbx-prompt repeat='true' url='\$ctrl' sec='1' barge-in='true'/>
            ",
    "play MOH" => "
            <pbx-prompt url='MOH?repeat=true' barge-in='true' sec='1'/>
            ",
    "play TONE" => "
            <pbx-prompt url='TONE' sec='1'/>
            ",
    "->call dialed number" => "call",
    "->call dialed number (supervised)" => "call_supervised",
    "disconnect caller (busy)" => "
            <pbx-disc cause='17'/>
            ",
    "disconnect caller (reject)" => "
            <pbx-disc cause='21'/>
            ",
    "end main loop" => "
            <assign out='stop' value='true'/>
            ",
);

/**
 * html refresh rate
 */
$refreshrate = 3;

/**
 * stop refresh when there are no calls?
 */
$stoprefreshonnocalls = false;

/**
 * DB access
 */
const DBHost = "inno-db";
const DBUser = "user";
const DBPW = "password";
const DBName = "routepoint";  // if you change this, you must change the code too!  So, don't change it :-)
