#!/usr/bin/env php
<?php

include_once '/etc/makemunki/readconfig.php';
$gconf = new ReadConfig('/etc/makemunki/config');
set_include_path(get_include_path() . ':'.$gconf->main->codehome.'/MunkiCert');


$dbname = $gconf->db->dbname;
$dbhost = $gconf->db->dbhost;
$user = $gconf->db->dbuser;
$pass = $gconf->db->dbpass;
$backdir = $gconf->db->backupdir;
$maxback = $gconf->db->backupcopies;

for ($i=$maxback;$i>0;$i--) {
    $oldfile = "$backdir/$dbname.sql.".$i;
    if(file_exists("$oldfile")) {
        $tmp = $i + 1;
        $newfile = "$backdir/$dbname.sql.".$tmp;
        rename($oldfile,$newfile);
    }
}
if(file_exists("$backdir/$dbname.sql")) {
    rename("$backdir/$dbname.sql","$backdir/$dbname.sql.1");
}
system("mysqldump -u$user -p$pass -h $dbhost $dbname > $backdir/$dbname.sql");
