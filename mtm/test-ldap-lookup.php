#!/usr/bin/env php
<?php

if(!isset($argv[1])) {
    throw new exception("You need to specify the samaccount name on the command line.");
}

require_once "/etc/makemunki/readconfig.php";
$gconf = new ReadConfig("/etc/makemunki/config");
set_include_path(get_include_path() . ':'.$gconf->main->codehome);

require "ldapgroups.php";

$ldg = new LdapGroups;

try {
    $res = $ldg->group_info_from_samaccountname($argv[1]);
    if($res['count']==1) {
        print "Found Entry: ".$res[0]['distinguishedname'][0]."\n";
    }
} catch (exception $e) {
    print "Error: ".$e->getMessage();
}
