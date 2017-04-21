<?php
// See LICENSE.txt for license.

include_once '/etc/makemunki/readconfig.php';

class MTM  {
    const FLAGS_RAW = 1;
    const FLAGS_REPO_PATH = 2;
    
    private static $mdh = '0';
    private $gconf;
    
    // Constructor opens connection to the database.
    public function __construct() {
        $gconf = new ReadConfig('/etc/makemunki/config');
        set_include_path(get_include_path() . ':'.$gconf->main->codehome.'/MunkiCert');
        $this->gconf = $gconf;
        include_once 'munkicert.php';

        if(MTM::$mdh === '0') {
            $params = array( 'host' => $gconf->db->dbhost,
            'db'   => $gconf->db->dbname,
            'user' => $gconf->db->dbuser,
            'pass' => $gconf->db->dbpass);

            MTM::$mdh = new MunkiCert($params);
        }
        date_default_timezone_set($gconf->main->timezone);
    }

    public function get_globals() {
        return $this->gconf;
    }

    // This shells out with sudo to generate a certificate.
    public function generate_cert($in_computer_identifier) {
        $comps = T_Computer::search("identifier",$in_computer_identifier);

        if(count($comps) == 0) {
            throw new exception("Can't locate computer with given ID");
        }

        $comp = $comps[0];

        if($comp->status !== "ungenerated" &&
        $comp->status !== "reopened") {
            throw new exception("Can't generate a certificate in current state");
        }
        $subject = $this->gconf->CA->casubjectbase.'/CN='.$comp->identifier;
        $certs = T_Certificate::search(['subject','status'],[$subject,'V'],['=','=']);
        if(count($certs)!=0) {
            if($comp->status !== "reopened") {
                throw new exception("Certificate for this identifier already exists.");
            } else {
                $comp->status = 'issued';
                $comp->save();
                return $comp;
            }
        }

        $cert = new T_Certificate;
        
        $cert->subject = $subject;
        $cert->save();

        $results = shell_exec("sudo -u makemunki /etc/makemunki/sign_certificate.sh ".$cert->ID);
        // Hack as sudo isn't working properly on this machine.
        //$results = shell_exec("/etc/makemunki/sign_certificate.sh ".$cert->ID);
        file_put_contents("/var/storage/phpsessions/mtmout","Received results: ".$results."\n");

        $certs = T_Certificate::search('ID',$cert->ID);
        $cert = $certs[0];

        if($cert->status !== 'V') {
            throw new exception("Error signing certificate.");
        }

        $comp->status = 'issued';
        $comp->Certificate_ID = $cert;
        $comp->save();
        
        return $comp;

    }

    public function change_cert_status($in_certid,$status) {
        $certs = T_Computer::Search("ID",$in_certid);
        if(count($certs)==0) {
            throw new exception("Can't find certificate to update");
        }

        $cert = $certs[0];

        $cert->status = $status;
        return $cert->save();
    }
    
    public function revoke_cert($in_computer_identifier) {

    }
    
    public function add_computer($in,$in_user = 'root') {
        if(is_numeric($in['window'])) {
            $window =(int)$in['window'];
        } else {
            throw new exception('add_computer: in_window must be the number of minutes to leave the window open');
        }

        if($window > $this->gconf->portal->maximum_window) {
            $window = $this->gconf->portal->maximum_window;
        }

        $repos = T_Repository::Search('ID',$in['repository_id']);
        if(count($repos)==0) {
            throw new exception('add_computer: repository ID does not exist.');
        }

        // Check permissions. Should have the 'P'/portal permission of 'C'/manage computers.
        if($in_user !== 'root' && !$this->check_user_perm_for_repository($in_user,$repos[0]->fullpath,'P','C')) {
            throw new exception("add_computer: user does not have permission for this repository");
        }

        $check = T_Computer::Search(['name','Repository_ID'],[$in['name'],$repos[0]],['=','=']);

        if(count($check)>0) {
            throw new exception("add_computer: Computer already exists.");
        }

        $comp = new T_Computer();
        $comp->name = $in['name'];
        $comp->identifier = $in['identifier'];
        $comp->Repository_ID = $repos[0];
        $comp->status = 'ungenerated';
        if(isset($in['forced_clientidentifier'])
        && $in['forced_clientidentifier'] !== "") {
            $comp->forced_clientidentifier = $in['forced_clientidentifier'];
        }
        if(isset($in['rename_on_install'])) {
            $comp->rename_on_install = 1;
        } else {
            $comp->rename_on_install = 0;
        }
        
        $now = time();
        $comp->window_start_date = date("Y-m-d G:i:s",$now);
        $comp->window_close_date = date("Y-m-d G:i:s",$now + ($window * 60));
        $comp->save();

        return ['status'=>['error'=>0,'text'=>'OK']];
        
    }

    public function update_computer_info($in_ID,$in_user = 'root',$in_changes) {
        $computers = T_Computer::Search('ID',$in_ID);

        if(count($computers) != 1) {
            throw new exception("update_computer: can't find computer");
        }
        $comp = $computers[0];

        // Check permissions. Should have the 'P'/portal permission of 'C'/manage computers.
        if($in_user !== 'root' && !$this->check_user_perm_for_computer($in_user,$comp->ID,'P','C')) {
            throw new exception("update_computer: user does not have permission for this repository");
        }

        if(isset($in_changes->fullpath)) {
            if($in_user !== 'root' && !$this->check_user_perm_for_repository($in_user,$in_changes->fullpath,'P','C')) {
                throw new exception("update_computer: user does not have permission for the new repository");
            }
            $newgrp = T_Repository::Search('fullpath',$in_changes->fullpath);
            $comp->Repository_ID = $newgrp[0];
        }
        
        if(isset($in_changes->name)) {
            $comp->name = $in_changes->name;
        }

        if(isset($in_changes->identifier)) {
            $comp->identifier = $in_changes->identifier;
        }

        if(isset($in_changes->forced_clientidentifier)) {
            if($in_changes->forced_clientidentifier === '') {
                $comp->set_null('forced_clientidentifier');
            } else {
                $comp->forced_clientidentifier = $in_changes->forced_clientidentifier;
            }
        }

        if(isset($in_changes->rename_on_install) && $in_changes->rename_on_install == 1) {
            $comp->rename_on_install = 1;
        } else {
            $comp->rename_on_install = 0;
        }

        $comp->save();

        return ['status'=>['error'=>0,'text'=>'OK']];
        
    }
    
    public function readd_computer($in_ID,$in_user = 'root',$in_window) {
        if(is_numeric($in_window)) {
            $window =(int)$in_window;
        } else {
            throw new exception('add_computer: in_window must be the number of minutes to leave the window open');
        }

        if($window > $this->gconf->portal->maximum_window) {
            $window = $this->gconf->portal->maximum_window;
        }

        $computers = T_Computer::Search('ID',$in_ID);

        if(count($computers) != 1) {
            throw new exception("readd_computer: can't find computer");
        }
        $comp = $computers[0];

        // Check permissions. Should have the 'P'/portal permission of 'C'/manage computers.
        if($in_user !== 'root' && !$this->check_user_perm_for_computer($in_user,$comp->ID,'P','C')) {
            throw new exception("readd_computer: user does not have permission for this repository");
        }

        $now = time();
        $comp->window_start_date = date("Y-m-d G:i:s",$now);
        $comp->window_close_date = date("Y-m-d G:i:s",$now + ($window * 60));
        if($comp->status !== 'ungenerated') {
            $comp->status = 'reopened';
        }
        $comp->save();

    }

    public function computers_by_ID($in_ID,$in_user = 'root',$in_flags = 0) {
        //file_put_contents('/tmp/inID',$in_ID);
        $computers = T_Computer::search('ID',$in_ID);
        if(count($computers) != 1) {
            throw new exception('Computer does not exist');
        }
        $comp = $computers[0];
        
        if ($in_user !== 'root') {
            // Check permissions. We want 'V'
            if(!$this->check_user_perm_for_computer($in_user,$comp->ID,'P','V')) {
                throw new exception("computers: user does not have permission for this computer group");
            }
        }
        
        return $this->_computer_to_array($comp,$in_flags);
        
    }

    private function _computer_to_array($in_comps,$in_flags) {
        $comps = [];
        if(is_array($in_comps)) {
            $comps = $in_comps;
        } else {
            $comps[] = $in_comps;
        }

        $ret = [];

        foreach($comps as $comp) {
            $tmp = [];
            if($in_flags & MTM::FLAGS_RAW) {
                $tmp['raw'] = $comp;
            }
            if($in_flags & MTM::FLAGS_REPO_PATH) {
                $repos = T_Repository::search('ID',$comp->Repository_ID);
                if(count($repos)!=1) {
                    throw new exception("Computers: repository for computer does not exist.");
                }
                $tmp['repository_fullpath'] = $repos[0]->fullpath;
            }

            $tmp['id'] = $comp->ID;
            $tmp['name'] = $comp->name;
            $tmp['identifier'] = $comp->identifier;
            $tmp['repository_id'] = $comp->Repository_ID;
            $tmp['description'] = $comp->description;
            $tmp['status'] = $comp->status;
            $tmp['forced_clientidentifier'] = $comp->forced_clientidentifier;
            $tmp['rename_on_install'] = $comp->rename_on_install;
            $tmp['window_start_date'] = $comp->window_start_date;
            $tmp['window_close_date'] = $comp->window_close_date;
            $certs = T_Certificate::search('ID',$comp->Certificate_ID);
            if(count($certs)==1) {
                $cert = $certs[0];
                $tmp['valid_from'] = $cert->valid_from;
                $tmp['valid_until'] = $cert->valid_until;
                $tmp['subject'] = $cert->subject;
            }

            $ret[] = $tmp;
        }
        return $ret;
    }
            
        
    public function computers_by_identifier($in_identifier,$in_user = 'root',$in_flags = 0) {
        $computers = T_Computer::search('identifier',$in_identifier);

        if(count($computers) != 1) {
            throw new exception('Computer does not exist');
        }
        $comp = $computers[0];
        
        return $this->computers_by_ID($comp->ID,$in_user,$in_flags);
    }

    public function computers_by_subject($in_subject,$in_user = 'root',$in_flags = 0) {
        $certificates = T_Certificate::search('subject',$in_subject);
        if(count($certificates)!=1) {
            throw new exception('Computer certificate does not exist');
        }

        $computers = T_Computer::search('Certificate_ID',$certificates[0]->ID);

        if(count($computers) != 1) {
            throw new exception('Computer does not exist');
        }
        $comp = $computers[0];
        
        return $this->computers_by_ID($comp->ID,$in_user,$in_flags);
    }

    public function computers_by_repository($in_id,$in_user = 'root',$in_flags = 0) {
        $repos = T_Repository::Search('ID',$in_id);
        if(count($repos)!= 1) {
            throw new exception('computers_by_repository: repository does not exist');
        }
        $repo = $repos[0];

        // Check permissions. We want portal permission of 'V'.
        if($in_user !== 'root' && !$this->check_user_perm_for_repository($in_user,$repo->fullpath,'P','V')) {
            throw new exception("computers_by_repository: user does not have permission for this repository");
        }

        $comps = T_Computer::Search('Repository_ID',$repo);
        if(count($comps)==0) {
            return [];
        }
        
        $ret = $this->_computer_to_array($comps,$in_flags);
        return $ret;
    }

    public function get_repositories_for_user($in_user) {
        $perms = V_UserPermission::Search('User_name',$in_user);
        $ret = [];
        foreach($perms as $perm) {
            if(ereg('V',$this->_unpack_portal_permission($perm->portal_permission))) {
                $tmp = $this->repository_by_id($perm->Repository_ID);
                $tmp['portal_permission'] = $this->_unpack_portal_permission($perm->portal_permission);
                $tmp['portal_permbits'] = $this->_unpack_portal_permbits($perm->portal_permission);
                $tmp['repository_permission'] = $this->_unpack_portal_permission($perm->repository_permission);
                $tmp['repository_permbits'] = $this->_unpack_portal_permbits($perm->repository_permission);
                $ret[] = $tmp;
            }
        }
        return $ret;
    }

    public function get_repositories_editable_for_user($in_user) {
        $perms = V_UserPermission::Search('User_name',$in_user);
        $ret = [];
        foreach($perms as $perm) {
            if(ereg('G',$this->_unpack_portal_permission($perm->portal_permission))) {
                $tmp = [];
                $ret[] = $this->repository_by_id($perm->Repository_ID);
            }
        }
        return $ret;
    }

    public function repository_by_id($in_ID) {
        $groups = T_Repository::Search('ID',$in_ID);
        if(count($groups)!=1) {
            throw new exception("Cannot find a computer group");
        }
        $group = $groups[0];
        $ret = [];
        $ret['id'] = $group->ID;
        $ret['name'] = $group->name;
        $ret['fullpath'] = $group->fullpath;
        $ret['fileprefix'] = $group->fileprefix;
        $ret['description'] = $group->description;
        return $ret;
    }
    
    public function check_user_perm_for_repository($in_user,$in_path,$in_type,$in_perm) {
        if($in_type !== 'P' && $in_type !== 'R') {
            throw new exception("I don't know what permission you're looking for.");
        }

        $perms = V_UserPermission::Search(['User_name','Repository_fullpath'],[$in_user,$in_path],['=','=']);

        // figure out if we're looking for portal or repository permissions.
        if($in_type === 'R') {
            foreach($perms as $perm) {
                if(ereg($in_perm,$this->_unpack_repository_permission($perm->repository_permission))) {
                    return true;
                }
            }
            return false;
        }

        if($in_type === 'P') {
            foreach($perms as $perm) {
                if(ereg($in_perm,$this->_unpack_portal_permission($perm->portal_permission))) {
                    return true;
                }
            }
            return false;
        }

        throw new exception("I don't know what kind of permission you are asking for");
    }

    public function check_user_perm_for_computer($in_user,$in_computerID,$in_type,$in_perm) {
        if($in_type !== 'P' && $in_type !== 'R') {
            throw new exception("I don't know what permission you're looking for.");
        }
        $comps = T_Computer::Search('ID',$in_computerID);
        if(count($comps)!=1) {
            return false;
        }

        $perms = V_UserPermission::Search(['User_name','Repository_ID'],[$in_user,$comps[0]->Repository_ID],['=','=']);
        // figure out if we're looking for portal or repository permissions.
        if($in_type === 'R') {
            foreach($perms as $perm) {
                if(ereg($in_perm,$this->_unpack_repository_permission($perm->repository_permission))) {
                    return true;
                }
            }
            return false;
        }

        if($in_type === 'P') {
            foreach($perms as $perm) {
                if(ereg($in_perm,$this->_unpack_portal_permission($perm->portal_permission))) {
                    return true;
                }
            }
            return false;
        }

        throw new exception("I don't know what kind of permission you are asking for");

    }

    public function users_by_user($in_user,$in_flags = 0) {
        $users = T_User::search('name',$in_user);
        if(count($users)!=1) {
            throw new exception("Cannot find user.");
        }
        return $this->_user_to_array($users);
    }

    public function users_by_ID($in_user,$in_flags = 0) {
        $users = T_User::search('ID',$in_user);
        if(count($users)!=1) {
            throw new exception("User not found.");
        }
        return _user_to_array($users,$in_flags);
    }
        
    private function _user_to_array($in_users,$in_flags = 0) {
        $ret = [];
        foreach($in_users as $user) {
            $tmp = [];
            $tmp['id'] = $user->ID;
            $tmp['name'] = $user->name;
            $tmp['description'] = $user->description;
            if($in_flags & MTM::FLAGS_RAW) {
                $tmp['raw'] = $user;
            }
            $ret[] = $tmp;
        }
        return $ret;
    }
    
    public function usergroups_by_id($in_usergroupID,$in_user = 'root',$in_flags = 0) {
        $ugs = [];
        if($in_usergroupID === "*") {
            $ugs = T_UserGroup::search('ID',0,'>=');
        } else {
            $ugs = T_UserGroup::search('ID',$in_usergroupID);
        }
        if(count($ugs)<1) {
            throw new exception("usergroup not found");
        }
        return $this->_usergroup_to_array($ugs,$in_flags);
    }

   // Possible values of portal permissions are:
   // V - view computers and information
   // C - modify computers
   // G - manage computer group
   // S - create sub-group

    private function _unpack_portal_permission($in_permission) {
        $choices = array ('V'=>0,'C'=>1,'G'=>2,'S'=>3);
        $ret = '';
        foreach ($choices as $letter=>$bit) {
            if ($in_permission & (1<<$bit)) {
                $ret .= $letter;
            } else {
                $ret .= '-';
            }
        }
        return $ret;
    }

    private function _unpack_portal_permbits($in_permission) {
        $choices = array ('V'=>0,'C'=>1,'G'=>2,'S'=>3);
        $ret = [];
        foreach ($choices as $letter=>$bit) {
            if ($in_permission & (1<<$bit)) {
                $ret[$letter] = 1;
            } else {
                $ret[$letter] = 0;
            }
        }
        return $ret;
    }

    private function _pack_portal_permission($in_permission) {
        $choices = array ('V','C','G','S');
        $ret = 0;
        if($in_permission === "") {
            return 0;
        }
        $perms = str_split($in_permission);
        foreach ($perms as $id=>$perm) {
            if ($perm === $choices[$id]) {
                $ret += (1<<$id);
            } elseif ($perm !== '-') {
                throw new exception ('Invalid permission: unexpected letter');
            }
        }
        return $ret;
    }
    
    private function _pack_portal_permbits($in_permission) {
        $choices = array ('V','C','G','S');
        $ret = 0;
        foreach ($choices as $id=>$letter) {
            if (isset($in_permission[$letter]) && $in_permission[$letter]==1) {
                $ret += (1<<$id);
            }
        }
        return $ret;
    }

   // Possible values of repository permissions are:
   // R - read
   // W - write

    private function _unpack_repository_permission($in_permission) {
        $choices = array ('R'=>0,'W'=>1);
        $ret = '';
        foreach ($choices as $letter=>$bit) {
            if ($in_permission & (1<<$bit)) {
                $ret .= $letter;
            } else {
                $ret .= '-';
            }
        }
        return $ret;
    }
    
    private function _unpack_repository_permbits($in_permission) {
        $choices = array ('R'=>0,'W'=>1);
        $ret = [];
        foreach ($choices as $letter=>$bit) {
            if ($in_permission & (1<<$bit)) {
                $ret[$letter] = 1;
            } else {
                $ret[$letter] = 0;
            }
        }
        return $ret;
    }
    
    private function _pack_repository_permission($in_permission) {
        $choices = array ('R','W');
        $ret = 0;
        if($in_permission === "") {
            return 0;
        }
        $perms = str_split($in_permission);
        foreach ($perms as $id=>$perm) {
            if ($perm === $choices[$id]) {
                $ret += (1<<$id);
            } elseif ($perm !== '-') {
                throw new exception ('Invalid permission: unexpected letter');
            }
        }
        return $ret;
    }

    private function _pack_repository_permbits($in_permission) {
        $choices = array ('R','W');
        $ret = 0;
        foreach ($choices as $id=>$letter) {
            if (isset($in_permission[$letter]) && $in_permission[$letter]==1) {
                $ret += (1<<$id);
            }
        }
        return $ret;
    }

    public function get_repository_permissions_for_usergroup($in_usergroupID,$in_user = 'root') {
        $perms = T_UserGroup_has_Repository_Permission::search('UserGroup_ID',$in_usergroupID);
        $ret = [];
        foreach($perms as $perm) {
            $tmp = [];
            $cgs = T_Repository::search('ID',$perm->Repository_ID);
            if(count($cgs)!=1) {
                throw new exception("database out of sync.");
            }
            $tmp['repository_name'] = $cgs[0]->name;
            $tmp['repository_fullpath'] = $cgs[0]->fullpath;
            $tmp['repository_description'] = $cgs[0]->description;
            $tmp['repository_id'] = $perm->Repository_ID;
            $tmp['portal_permission'] = $this->_unpack_portal_permission($perm->portal_permission);
            $tmp['repository_permission'] = $this->_unpack_repository_permission($perm->repository_permission);
            $ret[] = $tmp;
        }
        return $ret;
    }

    public function get_repository_permission_between_groups($in_usergroupID,$in_repositoryID,$in_user = 'root') {
        $perms = T_UserGroup_has_Repository_Permission::search(['UserGroup_ID','Repository_ID'],[$in_usergroupID,$in_repositoryID],['=','=']);
        $ret = [];
        $cgs = T_Repository::search('ID',$in_repositoryID);
        if(count($cgs)!=1) {
            throw new exception("database out of sync.");
        }
        $ugs = T_UserGroup::search('ID',$in_usergroupID);
        if(count($ugs)!=1) {
            throw new exception("database out of sync.");
        }
        if(count($perms) == 0) {
                $tmp = [];
                $tmp['repository_name'] = $cgs[0]->name;
                $tmp['repository_fullpath'] = $cgs[0]->fullpath;
                $tmp['repository_description'] = $cgs[0]->description;
                $tmp['repository_id'] = $cgs[0]->ID;
                $tmp['portal_permission'] = $this->_unpack_portal_permission(0);
                $tmp['portal_permbits'] = $this->_unpack_portal_permbits(0);
                $tmp['repository_permission'] = $this->_unpack_repository_permission(0);
                $tmp['repository_permbits'] = $this->_unpack_repository_permbits(0);
                $tmp['usergroup_name'] = $ugs[0]->name;
                $tmp['usergroup_description'] = $ugs[0]->description;
                $tmp['usergroup_id'] = $ugs[0]->ID;
                $ret[] = $tmp;
        } else {   
            foreach($perms as $perm) {
                $tmp = [];
                $tmp['repository_name'] = $cgs[0]->name;
                $tmp['repository_fullpath'] = $cgs[0]->fullpath;
                $tmp['repository_description'] = $cgs[0]->description;
                $tmp['repository_id'] = $perm->Repository_ID;
                $tmp['portal_permission'] = $this->_unpack_portal_permission($perm->portal_permission);
                $tmp['portal_permbits'] = $this->_unpack_portal_permbits($perm->portal_permission);
                $tmp['repository_permission'] = $this->_unpack_repository_permission($perm->repository_permission);
                $tmp['repository_permbits'] = $this->_unpack_repository_permbits($perm->repository_permission);
                $tmp['usergroup_name'] = $ugs[0]->name;
                $tmp['usergroup_description'] = $ugs[0]->description;
                $tmp['usergroup_id'] = $perm->UserGroup_ID;
                $ret[] = $tmp;
            }
        }
        return $ret;
    }

    public function change_repository_permission_for_usergroup($in_usergroupID,$in_user = 'root',$in_changes) {
        if(!isset($in_changes['repository_id'])) {
            throw new exception("change_repository_permission_for_usergroup: repository not set");
        }

        $cgids = T_Repository::search('ID',$in_changes['repository_id']);
        if(count($cgids)!=1) {
            throw new exception("Database out of sync");
        }

        if($in_user !== 'root' && !$this->check_user_perm_for_repository($in_user,$cgids[0]->fullpath,'P','G')) {
            throw new exception("change_repository_permission_for_usergroup: Permission Denied.");            
        }

        $perms = T_UserGroup_has_Repository_Permission::search(['UserGroup_ID','Repository_ID'],[$in_usergroupID,$in_changes['repository_id']],['=','=']);

        if(count($perms)<1) {
            $perm = new T_UserGroup_has_Repository_Permission;
            $ugids = T_UserGroup::search('ID',$in_usergroupID);
            if(count($ugids)!=1) {
                throw new exception("Database out of sync");
            }
            $cgids = T_Repository::search('ID',$in_changes['repository_id']);
            if(count($cgids)!=1) {
                throw new exception("Database out of sync");
            }
            $perm->UserGroup_ID = $ugids[0];
            $perm->Repository_ID = $cgids[0];
            $perm->portal_permission = 0;
            $perm->repository_permission = 0;
        } else {
            $perm = $perms[0];
        }

        if(isset($in_changes['portal_permission'])) {
            $newperms = $this->_pack_portal_permission($in_changes['portal_permission']);
            if($newperms != $perm->portal_permission) {
                $perm->portal_permission = $newperms;
            }
        }

        if(isset($in_changes['repository_permission'])) {
            $newperms = $this->_pack_repository_permission($in_changes['repository_permission']);
            if($newperms !== $perm->repository_permission) {
                $perm->repository_permission = $newperms;
            }
        }

        if($perm->portal_permission == 0 && $perm->repository_permission == 0) {
            if($perm->ID > 0) {
                $perm->delete();
            }
        } else  {
            $perm->save();
        }

        $ret = [];
        $ret['portal_permission'] = $this->_unpack_portal_permission($perm->portal_permission);
        $ret['repository_permission'] = $this->_unpack_repository_permission($perm->repository_permission);
        return $ret;
        
    }

    public function get_usergroup_permissions_for_repository($in_repositoryID,$in_user = 'root') {
        $perms = T_UserGroup_has_Repository_Permission::search('Repository_ID',$in_repositoryID);
        $ret = [];
        foreach($perms as $perm) {
            $tmp = [];
            $ugs = T_UserGroup::search('ID',$perm->UserGroup_ID);
            if(count($ugs)!= 1) {
                throw new exception("Internal database inconsistency.");
            }
            $tmp['usergroup_name'] = $ugs[0]->name;
            $tmp['usergroup_description'] = $ugs[0]->description;
            $tmp['usergroup_id'] = $perm->UserGroup_ID;
            $tmp['portal_permission'] = $this->_unpack_portal_permission($perm->portal_permission);
            $tmp['repository_permission'] = $this->_unpack_repository_permission($perm->repository_permission);
            $cgs = T_Repository::search('ID',$perm->Repository_ID);
            if(count($cgs) != 1) {
                throw new exception("Internal database inconsistency.");
            }
            $tmp['repository_name'] = $cgs[0]->name;
            $tmp['repository_description'] = $cgs[0]->description;
            $tmp['repository_id'] = $perm->Repository_ID;
            $ret[] = $tmp;
        }
        return $ret;
        
    }

    // Gets a little confusing. $in_userID is the ID of the user to groupify
    // while $in_user is the name of the user requesting the change.
    public function put_user_in_usergroup($in_userID,$in_usergroupID,$in_user = 'root') {
        
        $users = T_User::search('ID',$in_userID);
        if(count($users)!=1) {
            throw new exception("Cannot find user");
        }
        $user = $users[0];

        $usergroups = T_UserGroup::search('ID',$in_usergroupID);
        if(count($usergroups)!=1) {
            throw new exception("Cannot find group");
        }
        $usergroup = $usergroups[0];

        // Search for the user in the group already.
        $uiugs = T_User_in_UserGroup::search(['User_ID','UserGroup_ID'],[$in_userID,$in_usergroupID],['=','=']);
        if(count($uiugs) < 1) {
            $uiug = new T_User_in_UserGroup;
            $uiug->User_ID = $user;
            $uiug->UserGroup_ID = $usergroup;
            $uiug->save();
        }

        return;
        
    }
        
    // Gets a little confusing. $in_userID is the ID of the user to groupify
    // while $in_user is the name of the user requesting the change.
    public function pull_user_from_usergroup($in_userID,$in_usergroupID,$in_user = 'root') {
        
        $users = T_User::search('ID',$in_userID);
        if(count($users)!=1) {
            throw new exception("Cannot find user");
        }
        $user = $users[0];

        $usergroups = T_UserGroup::search('ID',$in_usergroupID);
        if(count($users)!=1) {
            throw new exception("Cannot find group");
        }
        $usergroup = $users[0];

        // Search for the user in the group already.
        $uiugs = T_User_in_UserGroup::search(['User_ID','UserGroup_ID'],[$in_userID,$in_usergroupID],['=','=']);
        if(count($uiugs) != 0) {
            $uiug = $uiugs[0];
            $uiug->delete();
        }

        return;
        
    }
        
    public function add_usergroup($in_usergroup,$in_user = 'root',$in_flags = 0) {
        // Not sure how to check permissions for usergroups.
        
        if(!isset($in_usergroup['name'])) {
            throw new exception("add_usergroup: name not set.");
        }
        $check = T_UserGroup::search('name',$in_usergroup['name']);
        if(count($check)!=0) {
            throw new exception("add_usergroup: usergroup already exists.");
        }

        $ug = new T_UserGroup;
        $ug->name = $in_usergroup['name'];
        if(isset($in_usergroup['description'])) {
            $ug->description = $in_usergroup['description'];
        }
        $ug->save();
        return $this->_usergroup_to_array(array($ug),$in_flags);
    }

    // This function is used to ensure that the person creating a UserGroup can manage/edit the new UserGroup.
    public function add_usergroup_with_managergroup($new_usergroup_name,$in_user = 'root',$manager_usergroup_id) {
        $mg = T_UserGroup::search('ID',$manager_usergroup_id);
        if(count($mg)!=1) {
            throw new exception("add_usergroup_with_managergroup: cannot find manager_usergroup_id");
        }
        if($in_user!=='root') {
            $users = T_User::search('name',$in_user);
            if(count($users)!=1) {
                throw new exception("add_usergroup_with_managergroup: Cannot find user");
            }
            $ugs = T_User_in_UserGroup::search(['User_ID','UserGroup_ID'],[$users[0]->ID,$mg[0]->ID],['=','=']);
            if(count($ugs)!=1) {
                throw new exception("add_usergroup_with_managergroup: You are not in the manager group.");
            }
            if(!($this->check_user_perm_for_usergroup($in_user,$mg[0]->ID,'P','W'))) {
                throw new exception("add_usergroup_with_managergroup: You do not have permission to write to manager group");
            }
        }
        $params['name'] = $new_usergroup_name;
        $usergroup=$this->add_usergroup($params,$in_user); // do we want to add FLAGS_RAW here?
        $change_perm_args['target_usergroup_id']=$usergroup[0]['id'];
        $change_perm_args['portal_permission']='RWD';
        $this->change_usergroup_permission_for_usergroup($manager_usergroup_id,$in_user,$change_perm_args);
        return $usergroup;
    }

    public function update_usergroup_info($in_ID,$in_user = 'root',$in_changes) {
        if($in_user !== 'root') {
            if(!$this->check_user_perm_for_usergroup($in_user,$in_ID,'P','W')) {
                throw new exception("update_usergroup_info: You do not have permission");
            }
        }
        $ugs = T_UserGroup::search('ID',$in_ID);
        if(count($ugs)!=1) {
            throw new exception("update_usergroup_info: cannot find usergroup");
        }
        $ug = $ugs[0];

        if(isset($in_changes['name'])) {
            $ug->name = $in_changes['name'];
        }
        if(isset($in_changes['description'])) {
            $ug->description = $in_changes['description'];
        }
        $ug->save();
        return $this->_usergroup_to_array(array($ug));
    }

    public function _usergroup_to_array($in_usergroups,$in_flags = 0) {
        $ret = [];
        foreach($in_usergroups as $usergroup) {
            $tmp = [];
            $tmp['id'] = $usergroup->ID;
            $tmp['name'] = $usergroup->name;
            $tmp['description'] = $usergroup->description;
            if($in_flags & MTM::FLAGS_RAW) {
                $tmp['raw'] = $usergroup;
            }
            $ret[] = $tmp;
        }
        return $ret;
    }

    public function get_usergroups_for_user($in_user,$in_flags = 0) {
        $users = T_User::search('name',$in_user);
        if(count($users)!=1) {
            throw new exception("get_usergroups_for_user: Cannot find user");
        }
        $usergroupids = T_User_in_UserGroup::search('User_ID',$users[0]->ID);
        $retraw = [];
        foreach($usergroupids as $ugid) {
            $ugroups = T_UserGroup::search('ID',$ugid->UserGroup_ID);
            if(count($ugroups) == 1) {
                $retraw[] = $ugroups[0];
            }
        }

        return $this->_usergroup_to_array($retraw,$in_flags);
    }


  // Begin functions related to UserGroup permissions.


  // Check which permissions a user has on a UserGroup.
    public function check_user_perm_for_usergroup($in_user,$in_usergroup_id,$in_type,$in_perm) {
        if($in_type !== 'P') {
            throw new exception("I don't know what permission you're looking for.");
        }

        $perms = V_User_has_UserGroup_Permission::search(['User_name','Target_UserGroup_ID'],[$in_user,$in_usergroup_id],['=','=']);

        // may need to expand to look for other types later
        //if($in_type === 'P') {
            foreach($perms as $perm) {
                if(ereg($in_perm,$this->_unpack_usergroup_portal_permission($perm->portal_permission))) {
                    return true;
                }
            }
            return false;
	//}
    }

    // 
    public function get_usergroup_permissions_for_usergroup($acting_usergroup_id,$in_user = 'root') {
        $perms = T_UserGroup_has_UserGroup_Permission::search('Acting_UserGroup_ID',$acting_usergroup_id);
        $ret = [];
        $augs = T_UserGroup::search('ID',$acting_usergroup_id);
        if(count($augs)!=1) {
            throw new exception("get_usergroup_permissions_for_usergroup: acting user group does not exist.");
        }
        $aug = $augs[0];
        foreach($perms as $perm) {
            $tmp = [];
            $ugs = T_UserGroup::search('ID',$perm->Target_UserGroup_ID);
            if(count($ugs)!= 1) {
                throw new exception("Internal database inconsistency.");
            }
            $tmp['target_usergroup_name'] = $ugs[0]->name;
            $tmp['target_usergroup_description'] = $ugs[0]->description;
            $tmp['target_usergroup_id'] = $perm->Target_UserGroup_ID;
            $tmp['portal_permission'] = $this->_unpack_usergroup_portal_permission($perm->portal_permission);
            $tmp['portal_permbits'] = $this->_unpack_usergroup_portal_permbits($perm->portal_permission);
            $tmp['acting_usergroup_name'] = $aug->name;
            $tmp['acting_usergroup_description'] = $aug->description;
            $tmp['acting_usergroup_id'] = $aug->ID;
            $ret[] = $tmp;
        }
        return $ret;
        
    }

    public function get_usergroups_having_user_permissions($in_user) {
        if($in_user === 'root') {
            return $this->usergroups_by_id('*');
        }
        
        $users = T_User::search('name',$in_user);
        if(count($users) != 1) {
            throw new exception("get_usergroups_having_user_permission: can't find user");
        }

        $perms = V_User_has_UserGroup_Permission::search('User_ID',$users[0]->ID,'=');
        $ugids = [];
        foreach($perms as $perm) {
            $ugids[$perm->Target_UserGroup_ID] = $perm->Target_UserGroup_ID;
        }

        $ret = [];
        foreach($ugids as $ugid) {
            $tmp = $this->usergroups_by_id($ugid);
            $ret[] = $tmp[0];
        }

        return $ret;
    }

    public function get_usergroup_permission_between_groups($acting_usergroup_id,$target_usergroup_id,$in_user = 'root') {
        $perms = T_UserGroup_has_UserGroup_Permission::search(['Acting_UserGroup_ID','Target_UserGroup_ID'],[$acting_usergroup_id,$target_usergroup_id],['=','=']);
        $ret = [];
        $augs = T_UserGroup::search('ID',$acting_usergroup_id);
        if(count($augs)!=1) {
            throw new exception("get_usergroup_permissions_for_usergroup: acting user group does not exist.");
        }
        $aug = $augs[0];
        if(count($perms)==0) {
            $ugs = T_UserGroup::search('ID',$target_usergroup_id);
            if(count($ugs)!=1) {
                throw new exception("get_usergroup_permissions_for_usergroup: target user group does not exist.");
            }
            $tmp = [];
            $tmp['target_usergroup_name'] = $ugs[0]->name;
            $tmp['target_usergroup_description'] = $ugs[0]->description;
            $tmp['target_usergroup_id'] = $ugs[0]->ID;
            $tmp['portal_permission'] = $this->_unpack_usergroup_portal_permission(0);
            $tmp['portal_permbits'] = $this->_unpack_usergroup_portal_permbits(0);
            $tmp['acting_usergroup_name'] = $aug->name;
            $tmp['acting_usergroup_description'] = $aug->description;
            $tmp['acting_usergroup_id'] = $aug->ID;
            $ret[] = $tmp;
        } else {
            foreach($perms as $perm) {
                $tmp = [];
                $ugs = T_UserGroup::search('ID',$perm->Target_UserGroup_ID);
                if(count($ugs)!= 1) {
                    throw new exception("Internal database inconsistency.");
                }
                $tmp['target_usergroup_name'] = $ugs[0]->name;
                $tmp['target_usergroup_description'] = $ugs[0]->description;
                $tmp['target_usergroup_id'] = $perm->Target_UserGroup_ID;
                $tmp['portal_permission'] = $this->_unpack_usergroup_portal_permission($perm->portal_permission);
                $tmp['portal_permbits'] = $this->_unpack_usergroup_portal_permbits($perm->portal_permission);
                $tmp['acting_usergroup_name'] = $aug->name;
                $tmp['acting_usergroup_description'] = $aug->description;
                $tmp['acting_usergroup_id'] = $aug->ID;
                $ret[] = $tmp;
            }
        }
        return $ret;
        
    }

  // acting_usergroup_id is being given permissions for $in_changes('target_usergroup_id')
    public function change_usergroup_permission_for_usergroup($acting_usergroup_id,$in_user = 'root',$in_changes) {
        if(!isset($in_changes['target_usergroup_id'])) {
            throw new exception("change_usergroup_permission_for_usergroup: target_usergroup_id not set");
        }
        if(!isset($in_changes['portal_permission'])) {
            throw new exception("change_usergroup_permission_for_usergroup: (portal) permission not set");
        }
        
        $perms = T_UserGroup_has_UserGroup_Permission::search(['Acting_UserGroup_ID','Target_UserGroup_ID'],[$acting_usergroup_id,$in_changes['target_usergroup_id']],['=','=']);
        
        if(count($perms)<1) {
            $tgtids = T_UserGroup::search('ID',$in_changes['target_usergroup_id']);
            if(count($tgtids)!=1) {
                throw new exception("change_usergroup_permission_for_usergroup: can't look up usergroup");
            }
            $tgtid = $tgtids[0];
            $actids = T_UserGroup::search('ID',$acting_usergroup_id);
            if(count($actids)!=1) {
                throw new exception("change_usergroup_permission_for_usergroup: can't look up usergroup");
            }
            $actid = $actids[0];
            $perm = new T_UserGroup_has_UserGroup_Permission;
            $perm->Acting_UserGroup_ID = $actid;
            $perm->Target_UserGroup_ID = $tgtid;
            $perm->portal_permission = 0;
        } else {
            $perm = $perms[0];
        }

        $newperms = $this->_pack_usergroup_portal_permission($in_changes['portal_permission']);
        if($newperms !== $perm->portal_permission) {
            $perm->portal_permission = $newperms;
        }
        
        if($perm->portal_permission == 0) {
            if($perm->ID > 0) {
                $perm->delete();
            }
        } else  {
            $perm->save();
        }
        
        $ret = [];
        $ret['permission'] = $this->_unpack_usergroup_portal_permission($perm->portal_permission);
        return $ret;        
    }

  // Possible values of UserGroup portal permissions are:
  // R - read UserGroup
  // W - edit UserGroup membership (Shib groups or API users)
  // D - delete UserGroup (once it is empty)
  // C - create new UserGroups
    
    private function _unpack_usergroup_portal_permission($in_permission) {
        $choices = array ('R'=>0,'W'=>1,'D'=>2);
        $ret = '';
        foreach ($choices as $letter=>$bit) {
            if ($in_permission & (1<<$bit)) {
                $ret .= $letter;
            } else {
                $ret .= '-';
            }
        }
        return $ret;
    }

    private function _unpack_usergroup_portal_permbits($in_permission) {
        $choices = array ('R'=>0,'W'=>1,'D'=>2);
        $ret = [];
        foreach ($choices as $letter=>$bit) {
            if ($in_permission & (1<<$bit)) {
                $ret[$letter] = 1;
            } else {
                $ret[$letter] = 0;
            }
        }
        return $ret;
    }



    private function _pack_usergroup_portal_permission($in_permission) {
        $choices = array ('R','W','D');
        $ret = 0;
        $perms = str_split($in_permission);
        foreach ($perms as $id=>$perm) {
            if ($perm === $choices[$id]) {
                $ret += (1<<$id);
            } elseif ($perm !== '-') {
                throw new exception ('Invalid permission: unexpected letter');
            }
        }
        return $ret;
    }

  // end functions related to UserGroup permissions.


    private function _repository_to_array($in_repositories,$in_flags = 0) {
        $ret = [];
        foreach($in_repositories as $cg) {
            $tmp = [];
            $tmp['id'] = $cg->ID;
            $tmp['name'] = $cg->name;
            $tmp['fullpath'] = $cg->fullpath;
            $tmp['fileprefix'] = $cg->fileprefix;
            $tmp['description'] = $cg->description;
            if($in_flags & MTM::FLAGS_RAW) {
                $tmp['raw'] = $cg;
            }
            $ret[] = $tmp;
        }
//            ob_start();
//            print var_dump($ret);
//            $data = ob_get_clean();
//            file_put_contents("/tmp/c2a",$data);
        return $ret;
    }

    public function repositories_by_id($in_cgID,$in_user = 'root',$in_flags = 0) {
        $cgs = [];
        if($in_cgID === "*") {
            $cgs = T_Repository::search('ID',0,'>=');
        } else {
            $cgs = T_Repository::search('ID',$in_cgID);
        }
        if(count($cgs)<1) {
            throw new exception("Cannot find repository");
        }
        $ret = [];

        foreach ($cgs as $cg) {
            if($in_user === 'root' || $this->check_user_perm_for_repository($in_user,$cg->fullpath,'P','V')) {
                $tmp = $this->_repository_to_array(array($cg),$in_flags);
                $ret[] = $tmp[0];
            }
        }
//        ob_start();
//        print var_dump($ret);
//        $data = ob_get_clean();
//        file_put_contents("/tmp/cgbyid",$data);
        return $ret;
    }
        
    public function repositories_by_name($in_name,$in_user = 'root',$in_flags = 0) {
        $cgs = T_Repository::search('name',$in_name);
        if(count($cgs)<1) {
            throw new exception("Cannot find repository");
        }
        return $this->computers_by_ID($cgs[0]->name,$in_user,$in_flags);
    }
    
    public function add_repository_with_managergroup($in_params,$in_user = 'root') {
        if(!isset($in_params['manager_usergroup_id'])) {
            throw new exception("add_repository_with_managergroup: no manager group specified.");
        }
        $mugs = T_UserGroup::search('ID',$in_params['manager_usergroup_id']);
        if(count($mugs)!=1) {
            throw new exception("add_repository_with_managergroup: invalid managerusergroup.");
        }
        if($in_user !== 'root' && !$this->check_user_perm_for_usergroup($in_user,$mugs[0]->ID,'P','W')) {
            throw new exception("add_repository_with_managergroup: Permission denied in managergroup.");
        }
        $makegroup = $this->add_repository($in_params,$in_user,MTM::FLAGS_RAW);
        $changes['repository_id'] = $makegroup[0]['raw']->ID;
        $changes['portal_permission'] = 'VCGS';
        $changes['repository_permission'] = 'RW';

        $this->change_repository_permission_for_usergroup($mugs[0]->ID,'root',$changes);
        unset ($makegroup['raw']);
        return $makegroup;
    }

    public function add_repository($in_repository,$in_user = 'root',$in_flags = 0) {
        if(!isset($in_repository['fullpath'],$in_repository['fileprefix'])) {
            throw new exception("add_repository: insufficient information");
        }

        // Split the path to parents and name.
        $pathpieces = explode('/',$in_repository['fullpath']);
        $groupname = array_pop($pathpieces);
        $parentpath = implode('/',$pathpieces);

        // Search for the parent.  Make sure it exists.
        $parentsearch = T_Repository::search('fullpath',$parentpath);
        if(count($parentsearch)!=1) {
            throw new exception("add_repository: parent path not found.");
        }

        // Check the permissions here.  You should have "subgroup" permissions to create a subgroup.
        if($in_user !== 'root' && ! $this->check_user_perm_for_repository($in_user,$parentpath,'P','S')) {
            throw new exception("add_repository: You don't have permission in the parent.");
        }
        
        // Make sure it doesn't already exist.
        $groupsearch = T_Repository::search('fullpath',$in_repository['fullpath']);
        if(count($groupsearch)!=0) {
            throw new exception("add_repository: group already exists.");
        }
        $cg = new T_Repository;
        $cg->name = $groupname;
        $cg->fullpath = $in_repository['fullpath'];
        $cg->fileprefix = $in_repository['fileprefix'];
        if(isset($in_repository['description'])) {
            $cg->description = $in_repository['description'];
        }
        $cg->save();

        return $this->_repository_to_array(array($cg),$in_flags);
    }
        
    public function update_repository_info($in_ID,$in_user = 'root',$in_changes) {
        $cgs = T_Repository::search('ID',$in_ID);
        if(count($cgs)!=1) {
            throw new exception("update_repository_info: cannot find repository");
        }
        $cg = $cgs[0];

        if($in_user !== 'root' && ! $this->check_user_perm_for_repository($in_user,$cg->fullpath,'P','G')) {
            throw new exception("update_repository: You don't have permission to modify the group.");
        }
        // Only the description can easily be changed. Changing
        // a group name and/or path requires a lot of filesystem stuff
        // so we're omitting that from this API.
        if(isset($in_changes['description'])) {
            $cg->description = $in_changes['description'];
        }
        if(isset($in_changes['fileprefix'])) {
            $cg->fileprefix = $in_changes['fileprefix'];
        }

        $cg->save();
        return $this->_repository_to_array(array($cg));
    }

    // User management.  Mostly will be caches.  No permissions check here.
    public function add_user($in_user,$in_description = '') {
        $users = T_User::search('name',$in_user,'=');
        if(count($users)==1) {
            // User exists.
            return $users[0]->ID;
        }

        $add = new T_User;
        $add->name = $in_user;
        if($in_description !== '') {
            $add->description = $in_description;
        }
        $add->save();
        return $add->ID;
    }    

// This generates/uploads CSV files

    public function get_csv_for_repository($repositoryID,$in_user = 'root') {
        $repos = T_Repository::search('ID',$repositoryID);
        if(count($repos)!=1) {
            throw new exception("You do not have permissions for this repository.");
        }
        $repo = $repos[0];
        if($in_user !== 'root' && !$this->check_user_perm_for_repository($in_user,$repo->fullpath,'P','V')) {
            throw new exception("You do not have permissions for this repository.");
        }

        $computers = T_Computer::search('Repository_ID',$repo->ID);
        $output = "Repository,Name,Serial Number,Client Identifier,Rename on Install,Status(ro),Window Start(ro),Window Close(ro)\n";

        foreach($computers as $computer) {
            $output .= join(',',
            [$repo->fullpath,
            $computer->name,
            $computer->identifier,
            $computer->forced_clientidentifier,
            $computer->rename_on_install,
            $computer->status,
            $computer->window_start_date,
            $computer->window_close_date])."\n";
        }

        return $output;
            
    }


// Just separating slightly different types of thingies here.
    
    public function send_404_page($message) {
        header("HTTP/1.0 404 Not Found");
        if(isset($_SERVER['REQUEST_URI'])) {
        echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL '.$_SERVER['REQUEST_URI'].' was not found on this server.</p>
<hr>
<address>Apache/2.4.10 (Debian) Server at munkiserv.lis.illinois.edu Port 443</address>';
//        if($message !== "") {
//           echo "<p>".$message."</p>";
//        }
        } else {
        echo '<!DOCTYPE HTML PUBLIC "-//IETF//DTD HTML 2.0//EN">
<html><head>
<title>404 Not Found</title>
</head><body>
<h1>Not Found</h1>
<p>The requested URL was not found on this server.</p>
<hr>
<address>Apache/2.4.10 (Debian) Server at munkiserv.lis.illinois.edu Port 443</address>';
        }
        echo '</body></html>';
    }

}
