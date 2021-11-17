#!/usr/bin/env php
<?php

namespace CFPropertyList;

include_once '/etc/makemunki/readconfig.php';
$gconf = new \ReadConfig('/etc/makemunki/config');

#error_reporting( E_ALL );
ini_set( 'display_errors', 'on' );

date_default_timezone_set($gconf->main->timezone);
$update = date(DATE_ATOM);

require_once __DIR__."/../vendor/autoload.php";

/*
$pliststoinclude = [
'/var/storage/roots/repos/global/catalogs/all',
'/var/storage/roots/repos/global/UofI/catalogs/all',
'/var/storage/roots/repos/global/UofI/UIUC/catalogs/all'];
*/

$pliststoinclude = ['/var/storage/roots/repos/global/UofI/UIUC/catalogs/all'];

$plists = [];

foreach ($pliststoinclude as $inc) {
    $plists[] = new CFPropertyList($inc, CFPropertyList::FORMAT_XML);
}

$packs = [];
foreach($plists as $plist) {

    foreach($plist->toArray() as $pack) {
        if(!isset($pack['name'])) {
            continue;
        }
        $name = $pack['name'];
        $tagname = str_replace('.','_',$name);
        $catalogs = $pack['catalogs'];
        array_multisort($catalogs);
        #stop if hidefromsoftwarelist catalog is present
        if(in_array("hidefromsoftwarelist", $catalogs)) {
            continue;
        }
        if(!array_key_exists($name,$packs)) {
            #print "Adding array for $name\n";
            $packs[$name] = [ 'name'=>$name,'versions'=>[],'display_name'=>$pack['display_name'],'tagname'=>$tagname ];
        }
        #$catalogs = $pack['catalogs'];
        #array_multisort($catalogs);
        $version_info = [ 'version'=>$pack['version'],'catalogs' => $catalogs ];
        $packs[$name]['versions'][] = $version_info;
        if(isset($pack['description'])) {
            $description = str_replace("\n",' ',$pack['description']);
            $packs[$name]['description'] = $description;
        }
        if(isset($pack['icon_name'])) {
            $icon = 'icons/'.$pack['icon_name'];
            if(!strpos('.',$icon)) {
                $icon = $pack['icon_name'];
            }
            $packs[$name]['icon'] = $icon;
        }    
    }
}
$packs['pageinfo'] = [];
$packs['pageinfo']['name'] = 'pageinfo';
$packs['pageinfo']['lastupdated'] = $update;

$apps = [];

foreach($packs as $key=>$pack) {
    $apps[] = $pack;
}

$out = json_encode($apps);
print $out;

