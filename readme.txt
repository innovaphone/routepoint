
The system consists of a Voice Mail Object (VM) in a PBX and an XML script which is stored on this PBX's CF card.  The files are stored in the "pbx" tree.
In the central site, there is the call center server (CCS). The files are stored in the "server" tree.

The VM is controlled by the pbx/routepoint.xml script.
The CCS consists of server/index.php, server/agent-logic.php and server/config.php plus some utility classes in server/classes. 
The state-database "routepoint" can be created with the routepoint-creation.sql

Any configuration for the server can be done in server/config.php.

The configuration for the VM is done entirely in the "Script URL" parameter of the PBX VoiceMail object.  
Example: 

http://192.168.1.2/DRIVE/CF0/routepoint/routepoint.xml?
    $fallback-e164=3593&
    $server=http%3A%2F%2F192.168.1.100%3A8080%2Fwiki-src%2Fsample%2Froutepoint%2Fagent-logic.php

The Script URL of the VocieMail Object may have the following query arguments:

 - either $fallback-e164=<number>  number to transfer the call to on failure
 - or $fallback-name=<name> h323 (short name) to transfer the call to on failure
 - $server=<url> full URL to agent-logic interface (to be able to pass an URL as query argument, it must be URL encoded. In the above example the URL http://192.168.1.100:8080/wiki-src/sample/routepoint/agent-logic.php was URL encoded)


The VM continueously requests cmds from CCS by calling agent-logic.php.  With each call, it supplies a complete set of call related attributes.  
agent-logic.php will mainain an SQL database for each current call and

- create new DB entries for new calls
- delete DB entries for terminated calls
- update call attributes in the DB 
- return queued cmds for the call 

A cmd is queued using the CCS management UI server/index.php.  It shows all calls in the DB and provides action links to queue cmds to a call.
These cmds are retrieved by the XML script upon the next call to agent-logic.php and immediately executed.  

When there is no cmd available for the call, the VM is instructed to pause for a second. 

The PHP script assumes a MySQL DB.  
