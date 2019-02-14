<?php

include_once '/etc/makemunki/readconfig.php';

class LdapGroups  {

    static private $gconf;
    static private $ldapconn;
    static private $ldapnames;

    public function __construct() {
        if(isset(LdapGroups::$gconf)) {
            return;
        }
        $gconf = new ReadConfig('/etc/makemunki/config');
        set_include_path(get_include_path() . ':'.$gconf->main->codehome.'/MunkiCert');
        LdapGroups::$gconf = $gconf;
        include "pass_service.php";

        $sitekey = file_get_contents("/etc/makemunki/sitekey");
        LdapGroups::$ldapconn = [];
        LdapGroups::$ldapnames = $gconf->ldap->name;
        foreach(LdapGroups::$ldapnames as $name) {
            $ps = new Pass_Service($sitekey);
            if(!isset($gconf->ldap->$name->password, $gconf->ldap->$name->username, $gconf->ldap->$name->ldapuri, $gconf->ldap->$name->basedn)) {
                throw new exception("You must set ldap ldapuri, basedn, username, and password for $name in config file.");
            }
            LdapGroups::$ldapconn[$name] = ldap_connect($gconf->ldap->$name->ldapuri);
            ldap_set_option(LdapGroups::$ldapconn[$name],LDAP_OPT_PROTOCOL_VERSION,3);
            if(!isset(LdapGroups::$ldapconn[$name])) {
                throw new exception("Can't connect to ldap server.");
            }
            $pass_to_use = $ps->pass_from_cipher($gconf->ldap->$name->password);
            if(!isset($pass_to_use) || $pass_to_use === '') {
                throw new exception("Bind password not given for $name");
            }
            $bind = ldap_bind(LdapGroups::$ldapconn[$name],$gconf->ldap->$name->username,$pass_to_use);
            if(!$bind) { 
                throw new exception("Can't bind to LDAP server.");
            }
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

    public function group_info_from_samaccountname($in_group,$in_name) {
        //file_put_contents("/var/storage/phpsessions/samaccountname","$in_group and $in_name");
        if(!isset(LdapGroups::$gconf->ldap->$in_name,LdapGroups::$ldapconn[$in_name])) {
            throw new exception("No config for ldap name $in_name");
        }
        $filter = "(".LdapGroups::$gconf->ldap->$in_name->dnattribute."=".$this->my_ldap_escape($in_group).")";
        $retarray = array("member","sAMAccountName","distinguishedName");
        $res = ldap_search(LdapGroups::$ldapconn[$in_name],LdapGroups::$gconf->ldap->$in_name->basedn,$filter,$retarray);
        $info = ldap_get_entries(LdapGroups::$ldapconn[$in_name],$res);
        return $info;
    }

    public function verify_ldap_group($in_group,$in_name) {
        try { 
            $retarray = array("distinguishedName");
            $res = @ldap_search(LdapGroups::$ldapconn[$in_name],$in_group,'objectClass=*',$retarray);
            $info = @ldap_get_entries(LdapGroups::$ldapconn[$in_name],$res);
            return count($info)."\n" ;
        } catch (exception $e) {
            return 0;
        }
    }

    public function ldap_names() {
        return LdapGroups::$ldapnames;
    }

    public function ldap_config($in_name) {
        if(isset(LdapGroups::$gconf->ldap->$in_name)) {
            return LdapGroups::$gconf->ldap->$in_name;
        }
        throw new exception("Ldap connection name $in_name not found");
    }

}
