#!/usr/bin/env php
<?php

require_once '/etc/makemunki/readconfig.php';

$gconf = new ReadConfig('/etc/makemunki/config');

$res = shell_exec("mysqldump -u".$gconf->db->dbuser." -p".$gconf->db->dbpass." ".$gconf->db->dbname);

file_put_contents($gconf->db->backupdir."/".$gconf->db->dbname."-backup.sql",$res);

