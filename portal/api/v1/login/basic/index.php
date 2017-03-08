<?php

if(isset($_SERVER['REMOTE_USER'])) {
//    session_start();
//    $_SESSION['user'] = $_SERVER['REMOTE_USER'];
    echo "<html><head></head><body><p>You are logged in.</p></body></html>";
    exit();
}

?><p>The system does not know how to log you in</p>
