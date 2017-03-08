<?php

$REMOTEUSER = $_SERVER['REMOTE_USER'];
$SHIBMEMBERS = $_SERVER['member'];
//$REMOTEUSER = 'owen';
//$SHIBMEMBERS = 'urn:mace:urbana:gslis:user groups:munki admins';

include_once "/etc/makemunki/readconfig.php";

$gconf = new ReadConfig('/etc/makemunki/config');
set_include_path(get_include_path() . ':'.$gconf->main->codehome);

include_once "mtm.php";
include_once "shib_auth.php";

if(!isset($SHIBMEMBERS)) {
    print "<html><head><title>Shib not configured.</title></head><body><h1>Shibboleth is not configured for the isMemberOf attribute.</h1></body></html>\n";
    exit(0);
}

if(isset($REMOTEUSER)) {
    session_start();
    $session_lifetime = 86400;
    if(!isset($gconf->portal->session_lifetime)) {
        $session_lifetime = $gconf->portal->session_lifetime;
    }
    if(!isset($_SESSION['expiretimestamp']) || $_SESSION['expiretimestamp'] < time()) {
        session_regenerate_id();
    }
    $_SESSION['expiretimestamp'] = time() + $session_lifetime;
    $_SESSION['user'] = $REMOTEUSER;

    $mtm = new MTM;


    try {
        // Get the users entry, or create one if one doesn't exist.
        $user_id = $mtm->add_user($REMOTEUSER,"Sync from Shibboleth");

        $curgroups = $mtm->get_usergroups_for_user($REMOTEUSER);
    } catch (exception $e) {
        print "Sorry, you are not logged in.";
        session_destroy();
        exit(0);
    }
    //print "<pre>curgroups = \n";
    //var_dump($curgroups);
    //print "</pre>";

    $shib_groups = explode(';',$SHIBMEMBERS);

    $shib_auth = new Shib_Auth;

    // Generate a list of usergroups associated with the shib groups. Many
    // shib groups won't have associated user groups.  Some user groups
    // may be counted twice.  Create an array based on ID to not double-count.
    $shib_user_groups = [];
    foreach($shib_groups as $shib_group) {
        try {
            //print "<pre>";
            $ugs = $shib_auth->get_usergroups_for_shibgroup_path($shib_group);
            //print $shib_group."\n";
            //var_dump($ugs);
            //print "</pre>";
        } catch (exception $e) {
            continue;
        }
        foreach($ugs as $ug) {
            //print "<p>Found a usergroup -> ".$ug['name'];
            $shib_user_groups[$ug['ID']] = $ug;
        }
    }

    if(count($shib_user_groups) == 0) {
        print "Sorry, you do not have any permissions in this portal.\n";
        session_destroy();
        exit(0);
    }        

    //print "<pre>";
    //var_dump($shib_user_groups);
    //print "</pre>";
    // Look for groups needing to be added
    foreach($shib_user_groups as $shib_user_group) {
        //var_dump($shib_user_group);
        $found = 0;
        foreach($curgroups as $curgroup) {
            //print "Check if '".$curgroup['ID']."' === '".$shib_user_group['ID']."'\n";
            if($curgroup['ID'] === $shib_user_group['ID']) {
                $found = 1;
            }
        }
        if($found == 0) {
            $mtm->put_user_in_usergroup($user_id,$shib_user_group['ID']);
            //print "Added usergroup\n";
        }
    }

    // See what needs to be removed
    foreach($curgroups as $curgroup) {
        $found = 0;
        foreach($shib_user_groups as $shib_user_group) {
            if($curgroup['ID'] === $shib_user_group['ID']) {
                $found = 1;
            }
        }
        if($found == 0) {
            $mtm->pull_user_from_usergroup($user_id,$curgroup['ID']);
        }
    }

    // Groups should be in sync now.  Send message.

    print "<html><head>";
    //if(isset($_SESSION['referer'])) {
    //    print "<meta http-equiv=\"refresh\" content=\"5; url=".$_SESSION['referer']."\">";
    //}
    print "</head><body><p>You are logged in.</p>";
    if(isset($_SESSION['referer'])) {
        print "<p><a href=\"".$_SESSION['referer']."\">Go here:".$_SESSION['referer']."</a></p>";
    }
    print "</body></html>\n";
    exit();
}

?><p>The system does not know how to log you in</p>
