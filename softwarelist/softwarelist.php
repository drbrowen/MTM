#!/usr/bin/env php
<?php

namespace CFPropertyList;

error_reporting( E_ALL );
ini_set( 'display_errors', 'on' );

date_default_timezone_set($gconf->main->timezone);
$update = date(DATE_ATOM);

require_once __DIR__."/../vendor/autoload.php";

$plist = new CFPropertyList('/var/storage/roots/repos/global/catalogs/all', CFPropertyList::FORMAT_XML);

$packs = [];
foreach($plist->toArray() as $pack) {
    $name = $pack['name'];
    $tagname = str_replace('.','_',$name);
    if(!array_key_exists($name,$packs)) {
        print "Adding array for $name\n";
        $packs[$name] = [ 'name'=>$name,'versions'=>[],'tagname'=>$tagname ];
    }
    $catalogs = $pack['catalogs'];
    array_multisort($catalogs);
    $version_info = [ 'version'=>$pack['version'],'catalogs' => $catalogs ];
    $packs[$name]['versions'][] = $version_info;
    $description = str_replace("\n",' ',$pack['description']);
    if(isset($pack['description'])) {
        $packs[$name]['description'] = $description;
    }
    if(isset($pack['icon_name'])) {
        $icon = $pack['icon_name'];
        if(!strpos('.',$icon)) {
            $icon = $pack['icon_name'].'.png';
        }
        $packs[$name]['icon'] = $icon;
    }    

}
$packs['ppageinfo'] = [];
$packs['pageinfo']['name'] = 'pageinfo';
$packs['pageinfo']['lastupdated'] = $update;

$apps = [];

foreach($packs as $key=>$pack) {
    $apps[] = $pack;
}

$out = json_encode($apps);
print $out;
