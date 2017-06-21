<?php

include_once 'mtm.php';

class MunkiPackage {

    private $mtm;
    private $gconf;

    public function __construct() {
        $mtm = new MTM;
        $this->gconf = $mtm->get_globals();
    }


    public function gen_config($in_id) {
        $comps = T_Computer::Search('ID',$in_id);
        if(count($comps)!=1) {
            throw new exception('gen_config: Cannot find a computer to match the given identifier');
        }

        $comp = $comps[0];
        $repos = T_Repository::Search('ID',$comp->Repository_ID);
        if(count($repos)!=1) {
            throw new exception("Computer isn't in a computer group.");
        }
        $repo = $repos[0];
        

        $plistout = '<!DOCTYPE plist PUBLIC "-//Apple/DTD PLIST 1.0//EN" "http://www.apple.com/DTDs/PropertyList-1.0.dtd">
<plist version="1.0">
<dict>
  <key>SuccessfulInstall</key>
  <true/>
  <key>AppleSoftwareUpdatesOnly</key>
  <false/>
  <key>InstallAppleSoftwareUpdates</key>
  <true/>
  <key>UseClientCertificate</key>
  <true/>
  <key>LogToSyslog</key>
  <false/>
  <key>ClientKeyPath</key>
  <string>/Library/Managed Installs/ssl/munki.key</string>
  <key>LogFile</key>
  <string>/Library/Managed Installs/Logs/ManagedSoftwareUpdate.log</string>
  <key>ManagedInstallDir</key>
  <string>/Library/Managed Installs</string>
  <key>ClientCertificatePath</key>
  <string>/Library/Managed Installs/ssl/munki.pem</string>
  <key>FollowHTTPRedirects</key>
  <string>https</string>
  <key>SoftwareRepoURL</key>
  <string>';
            
        $URLpath = str_replace('/','-',substr($repo->fullpath,1));
        $URL = $this->gconf->main->baseurl.'/'.$URLpath;

        $plistout .= $URL."</string>\n";
        $fci = $comp->forced_clientidentifier;
        if(isset($fci) && $fci !== '') {
            $plistout .= "  <key>ClientIdentifier</key>\n  <string>".$fci."</string>\n";
        }
        $plistout .= "</dict>\n</plist>\n";

        return $plistout;
        
    }
        
    public function gen_package($in_id) {
        // Get the computer object first.
        if(posix_getuid() == 0) {
            throw new exception("Can't run this as root!");
        }
        $comps = T_Computer::Search('ID',$in_id);
        if(count($comps)!=1) {
            throw new exception('gen_config: Cannot find a computer to match the given identifier');
        }

        $comp = $comps[0];
        $certificates = T_Certificate::Search('ID',$comp->Certificate_ID);
        if(count($certificates)!=1) {
            throw new exception("Can't find certificates.");
        }
        $certificate = $certificates[0];

        $tempdir = $this->gconf->main->mkpackagedir.'/tmp';
        $tempdirparts = explode('/',$tempdir);
        $tmpname = tempnam($tempdir,"pkg");
        if(!($tmpname)) {
            throw new exception("Can't create a temp directory for package");
        }

        $tmpnameparts = explode('/',$tmpname);

        $i=0;
        foreach($tempdirparts as $tmpdirpart) {
            if($tmpdirpart !== $tmpnameparts[$i++]) {
                throw new exception("Can't create temp directory for package: ".$tmpname);
            } 
        }

        if(!(mkdir($tmpname.'.d',0700))) {
            throw new exception("Can't create temp directory for the package");
        }

        $pkgsource = $this->gconf->main->mkpackagesrc;

        // Not too necessary at first, but if we need to prepackage shell scripts,
        // having a stub to copy from would be quite handy.
        $res = shell_exec("cp -r '".$pkgsource."/.' '".$tmpname.".d'");
        
        $plistconfig = $this->gen_config($comp->ID);
        file_put_contents($tmpname.".d"."/Contents/Library/Managed Installs/initial-config/ManagedInstalls.plist",$plistconfig);
        $keyfile = $tmpname.".d"."/Contents/Library/Managed Installs/ssl/munki.key";
        file_put_contents($keyfile,$certificate->privatekey);
        chmod($keyfile,0600);
        $certfile = $tmpname.".d"."/Contents/Library/Managed Installs/ssl/munki.pem";
        file_put_contents($certfile,$certificate->certificate);

        // These commands are systemy difficult-to-implement in php, so
        // shell out to do the actual packaging.
        $pack = shell_exec($this->gconf->main->mkpackagedir."/package-munki-package.sh '".$tmpname.".d'");

        shell_exec("rm -rf '".$tmpname.".d' '".$tmpname."'");

        return $pack;
        
    }
    
}