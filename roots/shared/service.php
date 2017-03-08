<?php

# This was going to be more involved than it needed to be, so
# this code mostly just does an exit(0);

if($_SERVER['REQUEST_METHOD'] === "GET") {
    echo "You wanted ".$_GET['file'].". You shouldn't have been redirected here.";
    exit(0);
}

if($_SERVER['REQUEST_METHOD'] === "PROPFIND") {
    echo "Propfind shouldn't redirect here.";
    exit(0);
}

if($_SERVER['REQUEST_METHOD'] === "DELETE") {
    http_response_code(200);
    exit(0);
}

if($_SERVER['REQUEST_METHOD'] === "MOVE") {
    http_response_code(200);
    exit(0);
}
