<?php

include 'ca_access.php';
include 'mtm.php';

$ca = new CA_Access;

$certs = T_Certificate::search('ID',0,'>=');

foreach($certs as $cert) {
    $fp = $ca->retrieve_fingerprint($cert->ID);
    $cert->hash = $fp;
    $cert->save();
}

