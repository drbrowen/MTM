<?php

//phpinfo();

include_once '/etc/makemunki/readconfig.php';
$gconf = new ReadConfig("/etc/makemunki/config");
set_include_path(get_include_path() . ':'.$gconf->main->codehome);

include_once 'mtm.php';

$mtm = new Mtm;

if(!isset($mtm)) {
    throw new exception("Can't get new mtm structure");
}

$SUBJECT =  $_SERVER['REMOTE_USER'];

try {
$computers = $mtm->computers_by_subject($SUBJECT);
header('Content-type: text/plain');

if($computers[0]['use_template'] == 1) {
    print "clientidentifier:\n";
} else {
    print 'clientidentifier: '.$computers[0]['forced_clientidentifier']."\n";
}
print 'rename: '.$computers[0]['rename_on_install']."\n";
print 'name: '.$computers[0]['name']."\n";
} catch (exception $e) {
    print "ERROR: ".$e->getMessage()."\n";
}
