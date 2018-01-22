<?php

include_once "/etc/makemunki/readconfig.php";

class Manifest_Template {

    private $gconf;

    public function __construct() {
        $gconf = new ReadConfig('/etc/makemunki/config');
        $this->gconf = $gconf;
    }

    public function get_template_options($in_repo_path) {
        $fspath = $this->gconf->main->fullrepopath.$in_repo_path.'/manifests/templates';


        if(!is_dir($fspath)) {
            throw new exception("get_template_options: Invalid directory specification");
        }

        $files = array_diff(scandir($fspath), array('..', '.'));
        $ret = [];
        $i = 0;
        foreach($files as $file) {
            $tmp = [];
            $tmp['displayname'] = $file;
            $tmp['id'] = $i++;
            $ret[] = $tmp;
        }
        return $ret;
    }

    public function copy_template_file($in_repo_path,$in_manifest_name,$in_template_name,$force_retemplate) {
        $srcpath = $this->gconf->main->fullrepopath.$in_repo_path.'/manifests/templates/'.$in_template_name;
        $dstpath = $this->gconf->main->fullrepopath.$in_repo_path.'/manifests/'.$in_manifest_name;

        if(is_file($dstpath) && $force_retemplate == 0) {
            return 0;
        }

        if(!is_file($srcpath)) {
            throw new exception('copy_template_file: template file not found');
        }

        if(!copy($srcpath,$dstpath)) {
            throw new exception('copy_template_file: template file failed to copy');
        }
        return 0;
    }

}