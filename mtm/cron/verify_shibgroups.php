#!/usr/bin/env php
<?php
require_once "/etc/makemunki/readconfig.php";

$gconf = new ReadConfig("/etc/makemunki/config");

set_include_path(get_include_path() . ':'.$gconf->main->codehome);

$debug = 0;

include_once 'mtm.php';
include_once 'shib_auth.php';

$mtm = new MTM;
$sha = new Shib_Auth;
$ldgroups = new LdapGroups;

$names = $ldgroups->ldap_names();
$lds = [];
foreach($names as $ldname) {
    $lds[$ldname] = $ldgroups->ldap_config($ldname);
    $adcheck[$ldname] = [];
}

$shgs = T_ShibGroup::search('ID',0,'>=');

foreach($lds as $ldname=>$ld) {
    $complength = strlen($ld->basedn);
    foreach($shgs as $shg) {
        $shibgrouplen=strlen($shg->ad_path);
        if($shibgrouplen > $complength && substr_compare($shg->ad_path,$ld->basedn,$shibgrouplen-$complength,$complength) == 0) {
            $adcheck[$ldname][] = $shg->ad_path;
        }
    }
}

$globalcount = 0;
$message = "Hi mom.  I found some groups that I just do not like.  Boo hoo.\n\n";
foreach($adcheck as $ldname=>$groups) {
    foreach($groups as $group) {
        $check = $ldgroups->verify_ldap_group($group,$ldname);
        if($check == 0) {
            $globalcount = 1;
            $message .= $group."\n\n";
        }
    }
}

if($globalcount >0) {
    mail("munki-admins@illinois.edu","Munki groups that are orphaned",$message);
}