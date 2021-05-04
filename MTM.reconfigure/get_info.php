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

$SUBJECT = base64_decode($_GET['subject']);
#$SUBJECT =  $_SERVER['REMOTE_USER'];
#$SUBJECT = '/C=US/ST=Illinois/L=Urbana/O=University of Illinos/OU=Endpoint Services/CN=C07TK162G1J1';

try {
$computers = $mtm->computers_by_subject($SUBJECT);
header('Content-type: text/plain');
$repos = T_Repository::search('ID',$computers[0]['repository_id']);
if(count($repos)!=1) {
    throw new exception("Repo not found");
}
if($computers[0]['use_template'] == 1) {
    print "clientidentifier:\n";
} else {
    print 'clientidentifier: '.$computers[0]['forced_clientidentifier']."\n";
}
print 'rename: '.$computers[0]['rename_on_install']."\n";
print 'name: '.$computers[0]['name']."\n";
$newrepopath = str_replace("/","-",substr($repos[0]->fullpath,1));
print 'SoftwareRepoURL: '.$gconf->main->baseurl."/".$newrepopath."\n";
} catch (exception $e) {
    print "ERROR: ".$e->getMessage()."\n";

}
