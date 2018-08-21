<?php
// see LICENSE.txt for license.

include_once "mtm.php";
include_once "ldapgroups.php";

class Shib_Auth {

    private static $mdh = '0';
    private $gconf;

    // Constructor opens connection to the database.
    public function __construct() {
        $gconf = new ReadConfig('/etc/makemunki/config');
        set_include_path(get_include_path() . ':'.$gconf->main->codehome.'/MunkiCert');
        $this->gconf = $gconf;
        include_once 'munkicert.php';

        if(Shib_Auth::$mdh === '0') {
            $params = array( 'host' => $gconf->db->dbhost,
            'db'   => $gconf->db->dbname,
            'user' => $gconf->db->dbuser,
            'pass' => $gconf->db->dbpass);

            Shib_Auth::$mdh = new MunkiCert($params);
        }
    }

// adds a Shib group to a UserGroup
    public function add_shibgroup_to_usergroup($in_shibgroup_adpath, $in_usergroup_ID, $in_ldap_name, $in_user = 'root') {
       $mtm = new MTM;
       // Several steps.
       // Determine if user has permission in usergroup.
       // Determine if shib group already exists.
       // Create shib group if necessary, but only if we have permission
       // See if the group is already linked.
       // Link shib group if needed
       $ugs = T_UserGroup::search('ID',$in_usergroup_ID);
       if(count($ugs) != 1) {
           throw new exception("add_shibgroup_to_usergroup: Can't find usergroup");
       }
       $ug = $ugs[0];
       if($in_user !== 'root') {
           if(!($mtm->check_user_perm_for_usergroup($in_user,$in_usergroup_ID,'P','W'))) {
               throw new exception("add_shibgroup_to_usergroup: Permission denied.");
           }
       }

       $norm_ad_path = $this->normalize_ad_group($in_shibgroup_adpath);

       $shgs = T_ShibGroup::search('ad_path',$norm_ad_path);
       if(count($shgs) != 1) {
           $new_shg = new T_ShibGroup;
           $new_shg->ad_path = $norm_ad_path;
           $new_shg->shib_path = $this->shib_group_from_ad($norm_ad_path,$in_ldap_name);
           $new_shg->save();
           $shg = $new_shg;
       } else {
           $shg = $shgs[0];
       }
           
       $sgiugs = T_ShibGroup_in_UserGroup::search(['UserGroup_ID','ShibGroup_ID'],[$ug->ID,$shg->ID],['=','=']);

       if(count($sgiugs)==0) {
           $new_shibgroup_usergroup = new T_ShibGroup_in_UserGroup();
           $new_shibgroup_usergroup->UserGroup_ID = $ug;
           $new_shibgroup_usergroup->ShibGroup_ID = $shg;
           $new_shibgroup_usergroup->save();
       }
   }

// delete a Shib group from a UserGroup
   public function del_shibgroup_from_usergroup($in_shibgroup_id,$in_usergroup_id,$in_user = 'root') {
       $mtm = new MTM;
       if($in_user !== 'root') {
           if(!($mtm->check_user_perm_for_usergroup($in_user,$in_usergroup_id,'P','W'))) {
               throw new exception("del_shibgroup_from_usergroup: Permission denied.");
           }
       }
       $sgiugs = T_ShibGroup_in_UserGroup::search(['ShibGroup_ID','UserGroup_ID'],[$in_shibgroup_id,$in_usergroup_id],['=','=']);
       if(count($sgiugs)==0) {
           throw new exception("del_shibgroup_from_usergroup: shibgroup not in usergroup");
       }
       // Should only be one, but just to clean up in case.
       foreach($sgiugs as $sgiug) {
           $sgiug->delete();
       }

       // Check if there are any remaining groups, or if the shibgroup should be deleted.
       $sgicheck = T_ShibGroup_in_UserGroup::search('ShibGroup_ID',$in_shibgroup_id);
       if(count($sgicheck) == 0) {
           $delgroup = T_ShibGroup::search('ID',$in_shibgroup_id);
           $delgroup[0]->delete();
       }

   }

// list usergroups with shib group
   public function get_usergroups_for_shibgroup_path($in_path,$in_user = 'root',$in_flags = 0) {
       $shgs = T_ShibGroup::search('shib_path',$in_path,'=');
       if(count($shgs)!=1) {
           throw new exception("get_usergroups_for_shibgroup_path: Can't find shibgroup.");
       }
       $shg = $shgs[0];
       return $this->_get_usergroups_for_shibgroup_raw($shg,$in_user,$in_flags);
   }

   public function get_usergroups_for_shibgroup($in_ID,$in_user = 'root',$in_flags = 0) {
       $shgs = T_ShibGroup::search('ID',$in_ID,'=');
       if(count($shgs)!=1) {
           throw new exception("get_usergroups_for_shibgroup: Can't find shibgroup.");
       }
       $shg = $shgs[0];
       return $this->_get_usergroups_for_shibgroup_raw($shg,$in_user,$in_flags);
   }

   private function _get_usergroups_for_shibgroup_raw($shg,$in_user,$in_flags) {
       $mtm = new MTM;
       $ugids = T_ShibGroup_in_UserGroup::search('ShibGroup_ID',$shg->ID,'=');
       $ugs = [];
       foreach($ugids as $ugid) {
           $tmp = T_UserGroup::search('ID',$ugid->UserGroup_ID,'=');
           if(count($tmp)!=1) {
               throw new exception("get_usergroups_for_shibgroup_raw: database out of sync");
           }
           $ugs[] = $tmp[0];
       }
       return $mtm->_usergroup_to_array($ugs,$in_flags);
   }

// list shib_groups with usergroup
   public function get_shibgroups_for_usergroup_name($in_usergroup,$in_user = 'root',$in_flags) {
       // Look up usergroup to get ID
       $ugs = T_UserGroup::search('name',$in_usergroup,'=');
       if(count($ugs) != 1){
           throw new exception("get_shibgroups_for_usergroup_name: Can't find usergroup.");
       }
       $ug = $ugs[0];

       return $this->_get_shibgroups_for_usergroup_raw($ug,$in_user,$in_flags);
   }

   public function get_shibgroups_for_usergroup($in_ID,$in_user = 'root',$in_flags = 0) {
       // Look up usergroup to get ID
       $ugs = T_UserGroup::search('ID',$in_ID,'=');
       if(count($ugs) != 1){
           throw new exception("get_shibgroups_for_usergroup: Can't find usergroup.");
       }
       $ug = $ugs[0];

       return $this->_get_shibgroups_for_usergroup_raw($ug,$in_user,$in_flags);       
   }

   private function _get_shibgroups_for_usergroup_raw($in_ug,$in_user,$in_flags = 0) {
       if($in_user != 'root') {
           $mtm = new MTM; // Static data means we won't reconnect to the DB.
           if($mtm->check_user_perm_for_usergroup($in_user,$in_ug->ID,'P','R') == false) {
               throw new exception("get_shibgroups_for_usergroup_raw: you do not have permission.");
           }
       }
       $shgids = T_ShibGroup_in_UserGroup::search('UserGroup_ID',$in_ug->ID,'=');
       $shgs = [];
       foreach($shgids as $shgid) {
           $tmp = T_ShibGroup::search('ID',$shgid->ShibGroup_ID,'=');
           if(count($tmp)!=1) {
               throw new exception("get_shibgroups_for_usergroup_raw: database out of sync");
           }
           $shgs[] = $tmp[0];
       }
       return $this->_shibgroups_to_array($shgs,$in_flags);
   }

// Formats the printing of an array of shibgroups
   private function _shibgroups_to_array($in_shibgroups,$in_flags) {
       $ret = [];
       foreach($in_shibgroups as $shg) {
           $tmp = [];
           $tmp['ad_path'] = $shg->ad_path;
           $tmp['shib_path'] = $shg->shib_path;
           $tmp['description'] = $shg->description;
           $tmp['id'] = $shg->ID;
           if($in_flags & MTM::FLAGS_RAW) {
               $tmp['raw'] = $shg;
           }
           $ret[] = $tmp;
       }

       return $ret;
   }


   public function normalize_ad_group($in_adgroup) {
       $parts = explode(',',$in_adgroup);
       $ret = [];
       foreach($parts as $part) {
           $double=explode('=',$part,2);
           $ret[] = strtoupper($double[0]).'='.$double[1];
       }
       return implode(',',$ret);
   }
           

// 
   public function shib_group_from_ad($in_adgroup,$in_ldapname) {
        $lg = new LdapGroups;
        
        $ldconfig= $lg->ldap_config($in_ldapname);
        $adparts = explode(',',$ldconfig->basedn);
        $adsize = count($adparts);
        $parts = explode(',',$in_adgroup);
        $size = count($parts);
        $shib_group=$ldconfig->shib_group_base;
        
        for($i=1;$i <= $adsize; $i++) {
            if($parts[$size-$i] !== $adparts[$adsize-$i]) {
                throw new exception ("I can't figure out the group '$in_adgroup'.");
            }
        }

        for ($i=$size-$adsize-1;$i>=0;$i--) {
            $subsections = explode("=",$parts[$i]);
            $shib_group .= ':'.strtolower($subsections[1]);
        }
        return $shib_group;
    }

    public function check_for_shib_group($in_group,$in_shib_groups) {
        $shib_groups = explode(';',$in_shib_groups);
        foreach ($shib_groups as $shib_group) {
            if($in_group === $shib_group) {
                return 1;
            }
        }
        return 0;
    }

    public function get_ad_group_dn($in_group,$in_ldapname) {
        $lg = new LdapGroups;
        $ress = $lg->group_info_from_samaccountname($in_group,$in_ldapname);

        if($ress['count']!=1) {
            throw new exception("get_ad_group_dn: group not found or multiple groups found.");
        }
        return $ress[0]['distinguishedname'][0];
    }

}