<?php

require_once "/etc/makemunki/readconfig.php";

$gconf = new ReadConfig("/etc/makemunki/config");

set_include_path(get_include_path() . ':'.$gconf->main->codehome);

include_once "mtm.php";
include_once "shib_auth.php";

if(!isset($gconf->main->fullrepopath)) {
    throw new exception("You must set 'fullrepopath' under the 'main' section of the config file.");
}


function fullpathcompare($a,$b) {
    return strcmp($a['fullpath'],$b['fullpath']);
}

function create_repo_dir($repodir) {
    mkdir($repodir);
    $subdirs = [ 'pkgs','pkgsinfo','catalogs','manifests','icons','client_resources'];
    foreach ($subdirs as $subdir) {
        mkdir($repodir.'/'.$subdir);
    }

}

function gen_alias_lines($fullpath,$repos,$gconf) {
    $reponame = str_replace('/','-',substr($fullpath,1));
    $pathpieces = explode('/',substr($fullpath,1));
    $ret = '';
    while(($currentpiece = array_pop($pathpieces))) {
        if(count($pathpieces) == 0) {
            continue;
        }
        $parentpath = '/'.implode('/',$pathpieces);
        $parentname = str_replace('/','-',substr($parentpath,1));
        $ret .= "RewriteRule \"^".$gconf->main->urlrepobase.$fullpath.'/([^/]+)/'.$repos[$parentpath]['fileprefix']."(.*)\" ".$gconf->main->fullrepopath.$parentpath.'/$1/'.$repos[$parentpath]['fileprefix'].'$2'."\n";
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
RewriteCond %{REQUEST_METHOD} "!COPY"
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

$rawrepos = $mtm->repositories_by_ID('*');

$repos = [];
foreach($rawrepos as $repo) {
    $repos[$repo['fullpath']] = $repo;
}

uasort($repos,'fullpathcompare');

$protorepofile = file_get_contents("/etc/makemunki/conf-per-repo-proto");
//$davheaderfile = file_get_contents("/etc/makemunki/conf-per-dav-header");
//$davredirectfile = file_get_contents("/etc/makemunki/conf-per-dav-redirect");

foreach($repos as $repo) {
    $reponame = str_replace('/','-',substr($repo['fullpath'],1));
    $repodir = $gconf->main->fullrepopath.$repo['fullpath'];
    if(!is_dir($repodir)) {
        create_repo_dir($repodir);
    }

    $mods = [ '%%REPOBASEPATH%%'=>$gconf->main->fullrepopath,
    '%%REPONAME%%'=>$reponame,
    '%%FULLPATH%%' => $repo['fullpath'],
    '%%DESCRIPTION%%' => str_replace("\n","\n##",$repo['description'])];

    $outfile = $protorepofile;
    foreach($mods as $key=>$repl) {
        $tmp = str_replace($key,$repl,$outfile);
        $outfile = $tmp;
    }

    $aliaslines = gen_alias_lines($repo['fullpath'],$repos,$gconf);

    $outfile .= $aliaslines;

    file_put_contents($gconf->apache->per_repo_config."/munkirepo-".$reponame.".conf",$outfile);

    $perms = $mtm->get_usergroup_permissions_for_repository($repo['id']);
    $requireWrepos = [];
    $requireRrepos = [];
    foreach($perms as $perm) {
        if(ereg('W',$perm['repository_permission'])) {
            $shibgroups = $sha->get_shibgroups_for_usergroup($perm['usergroup_id']);
            foreach($shibgroups as $shibgroup) {
                $requireWgroups[] = $shibgroup['ad_path'];
            }
        } elseif(ereg('R',$perm['repository_permission'])) {
            $shibgroups = $sha->get_shibgroups_for_usergroup($perm['usergroup_id']);
            foreach($shibgroups as $shibgroup) {
                $requireRrepos[] = $shibgroup['ad_path'];
            }
        }
    }
    $davconf = "";
    $davconf .= "<Directory \"".$gconf->main->fullrepopath."/".$reponame."\">\n";
    $davconf .= "  <LimitExcept PROPFIND GET OPTIONS>\n";
    // Only write individual requirements if we have at least 1 write group
    if(count($requireWrepos)>0) {
        foreach($requireWrepos as $requireWrepo) {
            $davconf .= "    require ldap-group $requireWrepo\n";
        }
    } else {
        $davconf .= "    require all denied\n";
    }
    $davconf .= "  </LimitExcept>\n</Directory>\n";


    $davconf .= "<Directory \"".$gconf->main->fullrepopath."/".$reponame."/pkgs\">\n";
    // First check if there are any read-only groups.
    if(count($requireRrepos)>0) {
        // Only output these lines if a write group exists
        if(count($requireWrepos)>0) {
            $davconf .= "  <LimitExcept PROPFIND GET OPTIONS>\n";
            
            foreach($requireWrepos as $requireWrepo) {
                $davconf .= "    require ldap-group $requireWrepo\n";
            }
            $davconf .= "  </LimitExcept>\n";
        }

        // Always output these lines, as we have at least one read-only group.
        $davconf .= "  <Limit PROPFIND GET OPTIONS>\n";
        foreach($requireRrepos as $requireRrepo) {
            $davconf .= "    require ldap-group $requireRrepo\n";
        }
        foreach($requireWrepos as $requireWrepo) {
            $davconf .= "    require ldap-group $requireWrepo\n";
        }
        $davconf .= "  </Limit>\n";

    } else {
        // no read-only groups, check for a valid write group.
        if(count($requireWrepos)>0) {
            // With only write groups, no need for <Limit> or <LimitExcept>
            foreach($requireWrepos as $requireWrepo) {
                $davconf .= "  require ldap-group $requireWrepo\n";
            }
        } else {
            // No read or write groups, so no permissions
            $davconf .= "  require all denied\n";
        }
    }

    $davconf .= "</Directory>\n";

    $davconf .= gen_rewrite_lines($repo['fullpath'],$repos,$gconf);

    $davconf .= $aliaslines;

    file_put_contents($gconf->apache->per_repo_config."/munkirepo-".$reponame.".dav",$davconf);

    
}

