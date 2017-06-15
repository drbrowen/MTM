<?php

include_once "/etc/makemunki/readconfig.php";

$gconf = new ReadConfig('/etc/makemunki/config');


session_start();
session_unset();
session_write_close();

$url = $gconf->main->baseurl.'/Shibboleth.sso/Logout';

if(isset($_SERVER["eppn"])) {
    header('Location: '.$url);
}

print '<html><head><title>Log Out</title></head><body><p>Redirecting to <a href="'.$url.'">'.$url.'</a></body></html>';

