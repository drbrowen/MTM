#!/usr/bin/env php
<?php

require_once "/etc/makemunki/readconfig.php";

$gconf = new ReadConfig("/etc/makemunki/config");

set_include_path(get_include_path() . ':'.$gconf->main->codehome);


include "ca_access.php";

$ca = new CA_Access;

$computers = T_Computer::search('ID',0,'>=');

$passfile = "";

$groups = [];

foreach($computers as $computer) {
    if($computer->Certificate_ID > 0) {
        $certs = T_Certificate::search('ID',$computer->Certificate_ID,'=');
        if(count($certs) == 0) {
            continue;
        }
        if($certs[0]->status === 'V' && strlen($certs[0]->hash) > 0) {
            $passfile .= $computer->identifier.':'.'{SHA}' . base64_encode(sha1($certs[0]->hash, TRUE))."\n";
        }
        
        $repoid = $computer->Repository_ID;
        if($repoid > 0) {
            if (!isset($groups[$repoid])) {
                $repos = T_Repository::search('ID',$repoid,'=');
                $groups[$repoid]['path'] = $repos[0]->fullpath;
                $groups[$repoid]['members'] = [];
            }
            $groups[$repoid]['members'][] = $computer->identifier;
        }
    }
}

$groupfile = '';
foreach ($groups as $group) {
    $groupfile .= $group['path'].': '.implode(' ',$group['members'])."\n";
}

file_put_contents("/etc/makemunki/htpasswd.tmp",$passfile);
file_put_contents("/etc/makemunki/htgroup.tmp",$groupfile);

rename("/etc/makemunki/htpasswd.tmp","/etc/makemunki/htpasswd");
rename("/etc/makemunki/htgroup.tmp","/etc/makemunki/htgroup");
