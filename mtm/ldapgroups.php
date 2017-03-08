<?php

include_once '/etc/makemunki/readconfig.php';

class LdapGroups  {

    private $gconf;
    private $ldapconn;

    public function __construct() {
        $gconf = new ReadConfig('/etc/makemunki/config');
        set_include_path(get_include_path() . ':'.$gconf->main->codehome.'/MunkiCert');
        $this->gconf = $gconf;
        include "pass_service.php";

        $sitekey = file_get_contents("/etc/makemunki/sitekey");
        $ps = new Pass_Service($sitekey);
        if(!isset($gconf->ldap->password, $gconf->ldap->username, $gconf->ldap->ldapuri, $gconf->ldap->basedn)) {
            throw new exception("You must set ldap ldapuri, basedn, username, and password in config file.");
        }
        $this->ldapconn = ldap_connect($gconf->ldap->ldapuri);
        if(!isset($this->ldapconn)) {
            throw new exception("Can't connect to ldap server.");
        }
        $bind = ldap_bind($this->ldapconn,$gconf->ldap->username,$ps->pass_from_cipher($gconf->ldap->password));
        if(!isset($bind)) { 
            throw new exception("Can't bind to LDAP server.");
        }
    }

    public function my_ldap_escape($in_string) {
        // Note, the \\ MUST be done first, or the others will get screwed up.
        $subs = [ '\\'=>'\5C', '*'=>'\2A', '('=>'\28', ')'=>'\29', chr(0)=>'\00' ];
        foreach ($subs as $key=>$sub) {
            $new = str_replace($key,$sub,$in_string);
            $in_string = $new;
        }
        return $in_string;
    }

    public function group_info_from_samaccountname($in_group) {
        $filter = "(".$this->gconf->ldap->dnattribute."=".$this->my_ldap_escape($in_group).")";
        $retarray = array("member","sAMAccountName","distinguishedName");
        $res = ldap_search($this->ldapconn,$this->gconf->ldap->basedn,$filter,$retarray);
        $info = ldap_get_entries($this->ldapconn,$res);
        return $info;
    }

}


    