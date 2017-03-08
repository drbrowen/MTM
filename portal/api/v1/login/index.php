<?php

include_once "/etc/makemunki/readconfig.php";

$gconf = new ReadConfig('/etc/makemunki/config');

session_start();
session_regenerate_id();

$session_lifetime = 86400;
if(!isset($gconf->portal->session_lifetime)) {
    $session_lifetime = $gconf->portal->session_lifetime;
}

$_SESSION['expiretimestamp'] = time() + $session_lifetime;
if(isset($_SERVER['HTTP_REFERER'])) {
    $_SESSION['referer'] = $_SERVER['HTTP_REFERER'];
}

header("Location: " . $_SERVER["SCRIPT_URI"] . "shib/");
//phpinfo();
