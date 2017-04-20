#!/usr/bin/env php
<?php

if(!isset($argv[1])) {
    throw new exception("You need to specify the samaccount name on the command line.");
}

require_once "/etc/makemunki/readconfig.php";
$gconf = new ReadConfig("/etc/makemunki/config");
set_include_path(get_include_path() . ':'.$gconf->main->codehome);

require "mtm.php";
require "ldapgroups.php";
require "shib_auth.php";

$mtm = new MTM;
$ldg = new LdapGroups;
$sa = new Shib_Auth;

try {
    $res = $ldg->group_info_from_samaccountname($argv[1]);
    if($res['count']!=1) {
        throw new exception("AD/LDAP group not found.  Try a different samaccount name");
    }
    $adgroup = $res[0]['distinguishedname'][0];
    print "Adding Master Group: ".$adgroup."\n";
    $shibgroup = $sa->shib_group_from_ad($adgroup);
    print "Shib group: ".$shibgroup."\n";
} catch (exception $e) {
    print "Error: ".$e->getMessage()."\n";
}

// First create a global user group;
$globusergroup = [];
$globusergroup['name'] = "Global Admins";
$globusergroup['description'] =  "Auto-created admin group for base repository.";
$globgroupcreated = $mtm->add_usergroup($globusergroup,'root',MTM::FLAGS_RAW);
$globgroupraw = $globgroupcreated[0]['raw'];

$changes = [];
$changes['target_usergroup_id'] = $globgroupraw->ID;
$changes['portal_permission'] = 'RWD';

$mtm->change_usergroup_permission_for_usergroup($globgroupraw->ID,'root',$changes);

// Add the shibboleth group.
$sa->add_shibgroup_to_usergroup($adgroup,$globgroupraw->ID,'root');

$repo = new T_Repository;
$repo->name = 'global';
$repo->fullpath = "/global";
$repo->fileprefix = "global";
$repo->description =  'Base global repository, content available to all repos';
$repo->save();

$changes = [];
$changes['repository_id'] = $repo->ID;
$changes['portal_permission'] = 'VCGS';
$changes['repository_permission'] = 'RW';

$mtm->change_repository_permission_for_usergroup($globgroupraw->ID,'root',$changes);

