#!/usr/bin/env php
<?php

require_once "/etc/makemunki/readconfig.php";
$gconf = new ReadConfig("/etc/makemunki/config");
set_include_path(get_include_path() . ':'.$gconf->main->codehome);

require_once "ca_access.php";

$ca = new CA_Access;

if(!$ca) {
    throw new exception("Can't get a CA_Access handle.");
}

// Get the first argument, and cast it as an INT to make sure
// no funny business is going on.
$id = (int)$argv[1];

// Generate a new Certificate table;
$certs = T_Certificate::Search('ID',$id);
if(count($certs)!=1) {
    throw new exception("Can't find the certificate ID");
}

$ca->gen_certificate($certs[0]->ID);

