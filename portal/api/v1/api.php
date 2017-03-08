<?php

require_once "/etc/makemunki/readconfig.php";
$gconf = new ReadConfig('/etc/makemunki/config');
set_include_path(get_include_path() . ':'.$gconf->main->codehome);
require_once "myapi.php";

// Requests from the same server don't have a HTTP_ORIGIN header
// This is a stub for cors, we may not use it.
if (!array_key_exists('HTTP_ORIGIN', $_SERVER)) {
    $_SERVER['HTTP_ORIGIN'] = $_SERVER['SERVER_NAME'];
}

try {
    $API = new MyAPI($_REQUEST['request'], $_SERVER['HTTP_ORIGIN']);
    echo $API->processAPI();
} catch (Exception $e) {
    echo json_encode(Array('error' => $e->getMessage()));
}
