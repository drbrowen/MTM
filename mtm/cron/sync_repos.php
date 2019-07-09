#!/usr/bin/env php
<?php

require_once "/etc/makemunki/readconfig.php";

$gconf = new ReadConfig("/etc/makemunki/config");

set_include_path(get_include_path() . ':'.$gconf->main->codehome);

$debug = 0;

if(isset($argv[1]) && $argv[1] === '-d') {
    $debug = 1;
}

include_once "mtm.php";
include_once "shib_auth.php";

if(!isset($gconf->main->fullrepopath)) {
    throw new exception("You must set 'fullrepopath' under the 'main' section of the config file.");
}

if(!isset($gconf->apache->per_repo_config)) {
    throw new exception("You must set 'per_repo_config' under the 'apache' section of the config file.");
}

if(!isset($gconf->apache->restart)) {
    throw new exception("You must set 'restart' under the 'apache' section of the config file.");
}

$files = scandir($gconf->apache->per_repo_config."-temp");
foreach($files as $file) {
    if($file !== '.' && $file !== '..') {
        unlink($gconf->apache->per_repo_config."-temp/".$file);
    }
}


function debug_print($data) {
    global $debug;
    if($debug == 1) {
        print $data;
    }
}

function fullpathcompare($a,$b) {
    return strcmp($a['fullpath'],$b['fullpath']);
}

function create_repo_dir($repodir) {
    if(!is_dir($repodir)) {
        mkdir($repodir);
    }
    $subdirs = [ 'pkgs','pkgsinfo','catalogs','manifests','manifests/templates','icons','client_resources'];
    foreach ($subdirs as $subdir) {
        if(!is_dir($repodir.'/'.$subdir)) {
            mkdir($repodir.'/'.$subdir);
        }

    }
}

function gen_stub_entries($repos,$gconf) { 
    $subdirs = [ 'pkgs','pkgsinfo','catalogs','manifests','manifests/templates','icons','client_resources'];
    $masterfiles = [];
    $masterdirs = [];
    $allfiles = [];
    $alldirs = [];
    // Loop to see what we should have.
    foreach($repos as $repo) {
        debug_print(" Do ".$repo['fullpath']."\n");
        $keysize = strlen($repo['fileprefix']);
        $masterdirs[$repo['fullpath']] = [];
        $masterfiles[$repo['fullpath']] = [];
        $alldirs[$repo['fullpath']] = [];
        $allfiles[$repo['fullpath']] = [];
        foreach ($subdirs as $subdir) {
            $scandir = $gconf->main->fullrepopath.$repo['fullpath']."/".$subdir;
            $entries = scandir($scandir);
            $masterdirs[$repo['fullpath']][$subdir] = [];
            $masterfiles[$repo['fullpath']][$subdir] = [];
            $allfiles[$repo['fullpath']][$subdir] = [];
            $alldirs[$repo['fullpath']][$subdir] = [];
            foreach($entries as $entry) {
                if(is_dir($scandir."/".$entry)) {
                    //debug_print("Processing dir $entry\n");
                    $alldirs[$repo['fullpath']][$subdir][$entry] = 1;
                } else {
                    //debug_print("Processing file $entry\n");
                    $allfiles[$repo['fullpath']][$subdir][$entry] = 1;
                }
                if(strncmp($entry,$repo['fileprefix'],$keysize)==0) {
                    if(is_dir($scandir."/".$entry)) {
                        debug_print("matching dir $entry\n");
                        $masterdirs[$repo['fullpath']][$subdir][$entry] = 1;
                    } else {
                        debug_print("matching file $entry\n");
                        $masterfiles[$repo['fullpath']][$subdir][$entry] = 1;
                    }
                }
            }
        }
    }


    // Loop to see what's there.  Add/Delete as appropriate.
    foreach($repos as $repo) {
        debug_print( "Processing for ".$repo['fullpath']."\n");
        // Get each piece of the full path.  Note we drop the first '/' with a substr:
        $pathpieces = explode('/',substr($repo['fullpath'],1));
        while(($currentpiece = array_pop($pathpieces))) {
            if(count($pathpieces) == 0) {
                continue;
            }
            $parentpath = '/'.implode('/',$pathpieces);
            $parentkey = $repos[$parentpath]['fileprefix'];
            $parentkeysize = strlen($parentkey);
            $fullpath = $gconf->main->fullrepopath.$repo['fullpath'];
            debug_print("  processing $parentpath, looking for $parentkey\n");
            foreach($subdirs as $subdir) {
                // First do deletes:
                foreach($allfiles[$repo['fullpath']][$subdir] as $checkfile => $one) {
                    if(strncmp($checkfile,$parentkey,$parentkeysize)==0) {
                        if(!isset($masterfiles[$parentpath][$subdir][$checkfile])) {
                            // Remove the file.
                            debug_print( "Remove file $fullpath/$subdir/$checkfile\n");
                            unlink("$fullpath/$subdir/$checkfile");
                        }
                    }
                }
                
                foreach($alldirs[$repo['fullpath']][$subdir] as $checkfile => $one) {
                    if(strncmp($checkfile,$parentkey,$parentkeysize)==0) {
                        if(!isset($masterdirs[$parentpath][$subdir][$checkfile])) {
                            // Remove the file.
                            debug_print( "Remove dir $fullpath/$subdir/$checkfile\n");
                            rmdir("$fullpath/$subdir/$checkfile");
                        }
                    }
                }

                // now look for adds.
                foreach($masterfiles[$parentpath][$subdir] as $checkfile => $one) {
                    if(!isset($allfiles[$repo['fullpath']][$subdir][$checkfile])) {
                        // make the file
                        debug_print( "touch file $fullpath/$subdir/$checkfile\n");
                        touch("$fullpath/$subdir/$checkfile");
                    }
                }
                foreach($masterdirs[$parentpath][$subdir] as $checkfile => $one) {
                    if(!isset($alldirs[$repo['fullpath']][$subdir][$checkfile])) {
                        // make the file
                        debug_print( "mkdir $fullpath/$subdir/$checkfile\n");
                        mkdir("$fullpath/$subdir/$checkfile");
                    }
                }

            }
                    
        }            
    }

}

function gen_alias_lines($fullpath,$repos,$gconf) {
    $reponame = str_replace('/','-',substr($fullpath,1));
    // Get each piece of the full path.  Note we drop the first '/' with a substr:
    $pathpieces = explode('/',substr($fullpath,1));
    $ret = '';
    while(($currentpiece = array_pop($pathpieces))) {
        if(count($pathpieces) == 0) {
            continue;
        }
        $parentpath = '/'.implode('/',$pathpieces);
        $parentname = str_replace('/','-',substr($parentpath,1));

        $ret .= 'RewriteCond %{REQUEST_METHOD} "!LOCK"'."\n";
        $ret .= 'RewriteCond %{REQUEST_METHOD} "!UNLOCK"'."\n";
        $ret .= "RewriteRule \"^".$gconf->main->urlrepobase.$fullpath.'/([^/]+)/'.$repos[$parentpath]['fileprefix']."(.*)\" ".$gconf->main->fullrepopath.$parentpath.'/$1/'.$repos[$parentpath]['fileprefix'].'$2'."\n\n";
        $ret .= 'RewriteCond %{REQUEST_METHOD} "!LOCK"'."\n";
        $ret .= 'RewriteCond %{REQUEST_METHOD} "!UNLOCK"'."\n";
        $ret .= "RewriteRule \"^/".$reponame.'/([^/]+)/'.$repos[$parentpath]['fileprefix']."(.*)\" ".$gconf->main->fullrepopath.$parentpath.'/$1/'.$repos[$parentpath]['fileprefix'].'$2'."\n";
    }

    //$ret .= "AliasMatch \"^/".$fullpath.'/'."\" \"".$gconf->main->fullrepopath.$fullpath."/\"\n";
    //$ret .= "AliasMatch \"^/".$fullpath.'/(.*)'."\" \"".$gconf->main->fullrepopath.$fullpath."/$1\"\n";

    return $ret;
}

function gen_rewrite_lines($fullpath,$repos,$gconf) {
    $reponame = str_replace('/','-',substr($fullpath,1));
    $pathpieces = explode('/',substr($fullpath,1));
    $ret = '';
    while(($currentpiece = array_pop($pathpieces))) {
        if(count($pathpieces) == 0) {
            continue;
        }
        $parentpath = '/'.implode('/',$pathpieces);
        $parentname = str_replace('/','-',substr($parentpath,1));
        $ret .= 'RewriteCond %{REQUEST_METHOD} "!PROPFIND"
RewriteCond %{REQUEST_METHOD} "!GET"
RewriteCond %{REQUEST_METHOD} "!OPTIONS"
RewriteCond %{REQUEST_METHOD} "!MOVE"
RewriteCond %{REQUEST_METHOD} "!LOCK"
RewriteCond %{REQUEST_METHOD} "!UNLOCK"
RewriteCond %{REQUEST_FILENAME} "!service.php"
RewriteCond %{REQUEST_FILENAME} "!index.*"'."\n";
        $ret .= "RewriteRule \"^".$gconf->main->urlrepobase.$fullpath.'/([^/]+)/'.$repos[$parentpath]['fileprefix']."(.*)\" /shared/service.php?file=\$1 [END,QSA,NC,H=application/x-httpd-php]\n\n";

        $ret .= 'RewriteCond %{REQUEST_METHOD} "MOVE"'."\n";
        $ret .= 'RewriteCond %{HTTP:Destination} "'.$gconf->main->urlrepobase.$fullpath.'/([^/]+)/'.$repos[$parentpath]['fileprefix']."(.*)\"\n";
        $ret .= "RewriteRule \"^(.*)\" /shared/service.php?file=\$1 [END,QSA,NC,H=application/x-httpd-php]\n\n";

        $ret .= 'RewriteCond %{REQUEST_METHOD} "MOVE"'."\n";
        $ret .= "RewriteRule \"^".$gconf->main->urlrepobase.$fullpath.'/([^/]+)/'.$repos[$parentpath]['fileprefix']."(.*)\" /shared/service.php?file=\$1 [END,QSA,NC,H=application/x-httpd-php]\n\n";

        $ret .= 'RewriteCond %{REQUEST_METHOD} "COPY"'."\n";
        $ret .= 'RewriteCond %{HTTP:Destination} "'.$gconf->main->urlrepobase.$fullpath.'/([^/]+)/'.$repos[$parentpath]['fileprefix']."(.*)\"\n";
        $ret .= "RewriteRule \"^(.*)\" /shared/service.php?file=\$1 [END,QSA,NC,H=application/x-httpd-php]\n\n";

    }

    return $ret;
}

$mtm = new MTM;
$sha = new Shib_Auth;
$ldgroups = new LdapGroups;
$names = $ldgroups->ldap_names();
$lds = [];
foreach($names as $ldname) {
    $lds[$ldname] = $ldgroups->ldap_config($ldname);
}

$rawrepos = $mtm->repositories_by_ID('*');

$repos = [];
foreach($rawrepos as $repo) {
    $repos[$repo['fullpath']] = $repo;
    //debug_print("Repo = ".$repo['fullpath']."\n");
}

uasort($repos,'fullpathcompare');

$protorepofile = file_get_contents("/etc/makemunki/conf-per-repo-proto");
//$davheaderfile = file_get_contents("/etc/makemunki/conf-per-dav-header");
//$davredirectfile = file_get_contents("/etc/makemunki/conf-per-dav-redirect");

foreach($repos as $repo) {
    $reponame = str_replace('/','-',substr($repo['fullpath'],1));
    $repodir = $gconf->main->fullrepopath.$repo['fullpath'];
    create_repo_dir($repodir);
    
    $accesses = "";
    foreach($repos as $rfp => $repodata) {
        if(substr($rfp,0,strlen($repo['fullpath'])) === $repo['fullpath']) {
            $accesses .= "  require dbd-group ".$rfp."\n";
        }
    }
        
    $aliaslines = gen_alias_lines($repo['fullpath'],$repos,$gconf);

    $mods = [ '%%REPOBASEPATH%%'=>$gconf->main->fullrepopath,
    '%%REPONAME%%'=>$reponame,
    '%%FULLPATH%%' => $repo['fullpath'],
    '%%ACCESSES%%' => $accesses,
    '%%REWRITERULES%%' => $aliaslines,
    '%%RECONFIGPATH%%' => $gconf->main->mtmreconfigdir,
    '%%DESCRIPTION%%' => str_replace("\n","\n##",$repo['description'])];

    $outfile = $protorepofile;
    foreach($mods as $key=>$repl) {
        $tmp = str_replace($key,$repl,$outfile);
        $outfile = $tmp;
    }

    file_put_contents($gconf->apache->per_repo_config."-temp/munkirepo-".$reponame.".conf",$outfile);

    foreach ($lds as $ldname=>$ld) {
        $requireWrepos = [];
        $requireRrepos = [];
        $requireRpkgs =  [];
        $complength = strlen($ld->basedn);
        foreach ($repos as $permrepo) {
            if(strncmp($permrepo['fullpath'],$repo['fullpath'],strlen($repo['fullpath']))==0) {
                $perms = $mtm->get_usergroup_permissions_for_repository($permrepo['id']);
                foreach($perms as $perm) {
                    if(ereg('R',$perm['repository_permission'])) {
                        $shibgroups = $sha->get_shibgroups_for_usergroup($perm['usergroup_id']);
                        foreach($shibgroups as $shibgroup) {
                            $shibgrouplen=strlen($shibgroup['ad_path']);
                            if($shibgrouplen > $complength && substr_compare($shibgroup['ad_path'],$ld->basedn,$shibgrouplen-$complength,$complength) == 0) {
                                $requireRrepos[$shibgroup['ad_path']] = $shibgroup['ad_path'];
                            }
                        }
                    }
                }
            }
        }
        
        $perms = $mtm->get_usergroup_permissions_for_repository($repo['id']);
        foreach($perms as $perm) {
            if(ereg('W',$perm['repository_permission'])) {
                $shibgroups = $sha->get_shibgroups_for_usergroup($perm['usergroup_id']);
                foreach($shibgroups as $shibgroup) {
                    foreach($shibgroups as $shibgroup) {
                        $shibgrouplen=strlen($shibgroup['ad_path']);
                        if($shibgrouplen > $complength && substr_compare($shibgroup['ad_path'],$ld->basedn,$shibgrouplen-$complength,$complength) == 0) {
                            $requireWrepos[$shibgroup['ad_path']] = $shibgroup['ad_path'];
                            $requireRrepos[$shibgroup['ad_path']] = $shibgroup['ad_path'];
                            $requireRpkgs[$shibgroup['ad_path']] = $shibgroup['ad_path'];
                        }
                    }
                }
            } 
            if(ereg('R',$perm['repository_permission'])) {
                $shibgroups = $sha->get_shibgroups_for_usergroup($perm['usergroup_id']);
                foreach($shibgroups as $shibgroup) {
                    foreach($shibgroups as $shibgroup) {
                        $shibgrouplen=strlen($shibgroup['ad_path']);
                        if($shibgrouplen > $complength && substr_compare($shibgroup['ad_path'],$ld->basedn,$shibgrouplen-$complength,$complength) == 0) {
                            $requireRpkgs[$shibgroup['ad_path']] = $shibgroup['ad_path'];
                        }
                    }
                }
            } 
        }

        $davconf = "";
        $davconf .= "<Directory \"".$gconf->main->fullrepopath.$repo["fullpath"]."\">\n";
        $davconf .= "  <LimitExcept PROPFIND GET OPTIONS>\n";
        // Only write individual requirements if we have at least 1 write group
        if(count($requireWrepos)>0) {
            foreach($requireWrepos as $requireWrepo) {
                $davconf .= "    require ldap-group $requireWrepo\n";
            }
        } else {
            $davconf .= "    require all denied\n";
        }
        $davconf .= "  </LimitExcept>\n";
        $davconf .= "  <Limit PROPFIND GET OPTIONS>\n";
        if(count($requireRrepos)>0) {
            foreach($requireRrepos as $requireRrepo) {
                $davconf .= "    require ldap-group $requireRrepo\n";
            }
        } else {
            $davconf .= "    require all denied\n";
        }
        $davconf .= "  </Limit>\n";
        $davconf .= "</Directory>\n";


//    $davconf .= "<Directory \"".$gconf->main->fullrepopath.$repo["fullpath"]."/pkgs\">\n";
//    // First check if there are any read-only groups.
//    if(count($requireRrepos)>0) {
//        // Only output these lines if a write group exists
//        if(count($requireWrepos)>0) {
//            $davconf .= "  <LimitExcept PROPFIND GET OPTIONS>\n";            
//            foreach($requireWrepos as $requireWrepo) {
//                $davconf .= "    require ldap-group $requireWrepo\n";
//            }
//            $davconf .= "  </LimitExcept>\n";
//        }
//
//        // Always output these lines, as we have at least one read-only group.
//        $davconf .= "  <Limit PROPFIND GET OPTIONS>\n";
//        foreach($requireRrepos as $requireRrepo) {
//            $davconf .= "    require ldap-group $requireRrepo\n";
//        }
//        $davconf .= "  </Limit>\n";
//
//    } else {
//        // no read-only groups, check for a valid write group.
//        if(count($requireWrepos)>0) {
//            // With only write groups, no need for <Limit> or <LimitExcept>
//            foreach($requireWrepos as $requireWrepo) {
//                $davconf .= "  require ldap-group $requireWrepo\n";
//            }
//        } else {
//            // No read or write groups, so no permissions
//            $davconf .= "  require all denied\n";
//        }
//     }    
//
//    $davconf .= "</Directory>\n";

        $davconf .= gen_rewrite_lines($repo['fullpath'],$repos,$gconf);

        $davconf .= $aliaslines;

        file_put_contents($gconf->apache->per_repo_config."-temp/munkirepo-".$reponame.'.'.$ldname.".dav",$davconf);

    }
    
}

// This creates 0-lengh files or empty directories which will be redirected by apache but should be there for 'ls'.
gen_stub_entries($repos,$gconf);


// Compare each file generated to detect differences.
$oldfiles = scandir($gconf->apache->per_repo_config);
$newfiles = scandir($gconf->apache->per_repo_config."-temp");

$restart = 0;
foreach($oldfiles as $oldfile) {
    if(!in_array($oldfile,$newfiles)) {
        print "old file $oldfile not in newfiles\n";
        $restart = 1;
    }
}

// Don't bother checking if we found a difference
if($restart == 0) {
    foreach($newfiles as $newfile) {
        if(!in_array($newfile,$oldfiles)) {
            print "new file $newfile not in oldfile\n";
            $restart = 1;
        }
    }
}

// Don't bother checking if we found a difference.
if($restart == 0) {
    foreach($newfiles as $newfile) {
        if($newfile !== "." && $newfile !== "..") {
            $diff = shell_exec("diff -q ".$gconf->apache->per_repo_config."/$newfile ".$gconf->apache->per_repo_config."-temp/$newfile");
            if(isset($diff)) {
                debug_print("$diff");
                $restart = 1;
            }
        }
    }
}


if($restart == 1) {
    $files = scandir($gconf->apache->per_repo_config);

    foreach($files as $file) {
        if($file !== '.' && $file !== '..') {
            unlink($gconf->apache->per_repo_config."/".$file);
        }
    }

    $newfiles = scandir($gconf->apache->per_repo_config."-temp");
    foreach($newfiles as $file) {
        if($file !== '.' && $file !== '..') {
            rename($gconf->apache->per_repo_config."-temp/".$file,$gconf->apache->per_repo_config."/".$file);
        }
    }

    //print "Restart!\n";
    $res = shell_exec($gconf->apache->restart);

} else {
    $files = scandir($gconf->apache->per_repo_config."-temp");

    foreach($files as $file) {
        if($file !== '.' && $file !== '..') {
            unlink($gconf->apache->per_repo_config."-temp/".$file);
        }
    }
}