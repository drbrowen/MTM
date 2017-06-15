#!/usr/bin/env php
<?php

include_once '/etc/makemunki/readconfig.php';
$gconf = new ReadConfig('/etc/makemunki/config');
set_include_path(get_include_path() . ':'.$gconf->main->codehome.'/MunkiCert');
require_once "pass_service.php";

$sitekey = file_get_contents("/etc/makemunki/sitekey");

$ps = new Pass_Service($sitekey);

exec("stty -echo");

echo "Password: ";

$first = trim(fgets(STDIN));

echo "\n";

echo "Retype Password: ";

$second = trim(fgets(STDIN));

echo "\n";

exec("stty echo");

if($first !== $second) {
  throw new exception ("Your passwords do not match.");
}

$cipher = $ps->cipher_from_pass(str_replace(PHP_EOL,'',$first));
echo $cipher."\n";
