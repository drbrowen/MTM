<?php
require_once 'mtm.php';
require_once 'shib_auth.php';

require_once 'API.class.php';


class MyAPI extends API
{
    protected $User;

    public function __construct($request, $origin) {
        parent::__construct($request);

	// This is an example of how to get an API key
	// and user for both auth and cors.  This app uses
	// CAS and no cors so these are left in for documentation
	// only.
        // Abstracted out for example
        //$APIKey = new Models\APIKey();
        //$User = new Models\User();

    }

    /**
     * Example of an Endpoint
     */
    protected function whoami() {
        if ($this->method == 'GET') {
            return ['user'=>$_SESSION['user']];
        } else {
            return [ 'error' => 'Only accepts GET to read-only interface' ];
        }
    }

    protected function users() {
        switch($this->method) {
        case 'GET':
            switch($this->verb) {
            case 'repositories':
                $mtm = new MTM;
                $groups = $mtm->get_repositories_for_user($_SESSION['user']);
                if(count($groups) == 0) {
                    return ['status'=>['error'=>1,'text'=>'You do not have any permissions']];
                }
                return $groups;

            case 'usergroups':
                $mtm = new MTM;
                $groups = $mtm->get_usergroups_for_user($_SESSION['user']);
                if(count($groups) == 0) {
                    return ['status'=>['error'=>1,'text'=>'You do not have any permissions']];
                }
                return $groups;

            default:
                return ['status'=>['error'=>1,'text'=>'I do not know what you mean']];
                break;
            }
        }
    }

    protected function computers() {
        switch($this->method) {
        case 'GET':
            switch($this->verb) {
            case '':
                $mtm = new MTM;
                $repos = $mtm->get_repositories_for_user($_SESSION['user']);
                if(count($repos) == 0) {
                    return ['status'=>['error'=>1,'text'=>'You do not have any permissions']];
                }
                $ret = [];
                foreach($repos as $repo) {
                    // We've already checked permissions above, no need to do it again.
                    $tmp =$mtm->computers_by_repository($repo['id'],'root',MTM::FLAGS_REPO_PATH);
                    $ret = array_merge($ret,$tmp);
                }
                return $ret;
                break;

            case 'id':
                $mtm = new MTM;
                try {
                    return $mtm->computers_by_ID($this->args[0],$_SESSION['user'],MTM::FLAGS_REPO_PATH);
                } catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;

            case 'repo':
                $mtm = new MTM;
                try {
                    return $mtm->computers_by_repository($this->args[0],$_SESSION['user'],MTM::FLAGS_REPO_PATH);
                } catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;

            case 'csv':
                $this->settype("text/csv");
                $mtm = new MTM;
                try {
                    return $mtm->get_csv_for_repository($this->args[0],$_SESSION['user']);
                } catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;

            default:
                $res['allowed_searches'] = array('id','repo');
                return $res;
                break;
            }
            break;

        case 'POST':
            $invarsraw = file_get_contents('php://input');
            $invars = json_decode($invarsraw);
            #ob_start();
            #var_dump($invars);
            #var_dump($this->verb);
            #var_dump($this->args);
            #$textdata = ob_get_clean();
            #file_put_contents('/var/storage/phpsessions/textdata',$textdata);

            switch($this->verb) {
            case '':
                // An update will have an ID
                if(isset($invars->ID)) {
                    if(!isset($invars->name)
                    || !isset($invars->identifier)
                    || !isset($invars->repository_id)) {
                        //ob_start();
                        //var_dump($invars);
                        //$textvars = ob_get_clean();
                        //file_put_contents('/tmp/bomb',$textvars);
                        return ['status'=>['error'=>1,'text'=>'Not all necessary parameters present']];
                    }

                    $mtm = new MTM;

                    try {
                        $computers = $mtm->computers_by_ID($invars->ID,$_SESSION['user'],MTM::FLAGS_RAW);
                        if(count($computers)!=1) {
                            return ['status'=>['error'=>1,'text'=>'Computer not found']];
                        }
                        $comp = $computers[0]['raw'];
                        if(isset($invars->window) && is_numeric($invars->window)) {
                            $mtm->readd_computer($invars->ID,
                            $_SESSION['user'],
                            $invars->window);
                        }

                        $mtm->update_computer_info($invars->ID,
                        $_SESSION['user'],
                        $invars);
                    }
                    catch(exception $e) {
                        #ob_start();
                        #var_dump($e);
                        #$log = ob_get_clean();
                        #file_put_contents('/tmp/exception',$log);
                        return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                    }

                    return ['status'=>['error'=>0,'text'=>'OK']];
                    
                } else {
                    if(!isset($invars->name)
                    || !isset($invars->identifier)
                    || !isset($invars->repository_id)
                    || !isset($invars->window)) {
                        //file_put_contents('/tmp/bomb','bombed out');
                        return ['status'=>['error'=>1,'text'=>'Not all necessary parameters present']];
                    }
                    
                    $params['name'] = $invars->name;
                    $params['identifier'] = $invars->identifier;
                    $params['repository_id'] = $invars->repository_id;
                    $params['window'] = intval($invars->window);
                    if(isset($invars->forced_clientidentifier)) {
                        $params['forced_clientidentifier'] =
                            $invars->forced_clientidentifier;
                    }
                    if(isset($invars->rename_on_install)) {
                        $params['rename_on_install'] =
                            $invars->rename_on_install;
                    }
                    if(isset($invars->use_template)) {
                        $params['use_template'] =
                            $invars->use_template;
                    }
                    if(isset($invars->force_retemplate)) {
                        $params['force_retemplate'] = 
                            $invars->force_retemplate;
                    }
                    $mtm = new MTM;
                    //file_put_contents('/tmp/doingit','Created cert, ready to add computer');
                    try{
                        return $mtm->add_computer($params,$_SESSION['user']);
                    }
                    catch(exception $e) {
                        //file_put_contents('/tmp/exception',$e);
                        return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                    }
                }
                break;

            case 'csv':
                $mtm = new MTM;
                ob_start();
                var_dump($invars);
                $debug = ob_get_clean();
                file_put_contents('/var/storage/phpsessions/startcsv',$debug);
                try {
                    $params = [];
                    if(isset($invars->csv)) {
                        $params['csv'] = $invars->csv;
                    }
                    if(isset($invars->add)) {
                        $params['add'] = $invars->add;
                    }
                    if(isset($invars->name)) {
                        $params['name'] = $invars->name;
                    }
                    if(isset($invars->window)) {
                        $params['window'] = $invars->window;
                    }
                    if(isset($invars->clientid)) {
                        $params['rename'] = $invars->rename;
                    }
                    if(isset($invars->clientid)) {
                        $params['clientid'] = $invars->clientid;
                    }
                    if(isset($invars->repository)) {
                        $params['repository'] = $invars->repository;
                    }
                        
                    $val = $mtm->load_csv_for_repository($params,$_SESSION['user']);
                    return ['status'=>['error'=>0,'text'=>'OK']];
                } catch (exception $e) {
                        return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;

            default:
                return ['status'=>['error'=>1,'text'=>'I do not understand the request']];
                break;
                 
            }
            break;
            
        case 'DELETE':
            switch($this->verb) {
            case 'id':
                if(isset($this->args[0])) {
                    try {
                        file_put_contents("/var/storage/phpsessions/deleteid",$this->args[0]);
                        $mtm = new MTM;
                        $mtm->delete_computer($this->args[0],$_SESSION['user']);
                        return ['status'=>['error'=>0,'text'=>'OK']];
                    } catch (exception $e) {
                        return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                    }
                } else {
                    return ['status'=>['error'=>1,'text'=>'ID not given']];
                }
                   
                break;

            default:
                return ['status'=>['error'=>1,'text'=>'I do not understand the request']];
                break;

            }
            break;
        }
    }

    protected function repositories() {
        switch($this->method) {
        case 'GET':
            switch($this->verb) {
            case '':
                $mtm = new MTM;
                try {
                    return $mtm->get_repositories_for_user($_SESSION['user']);
                }
                catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                
                break;

            case 'id':case 'ID':
                $mtm = new MTM;
                try {
                    return $mtm->repositories_by_id($this->args[0]);
                }
                catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                
                break;

            case 'perms':
                $mtm = new MTM;
                try {
                    return $mtm->get_usergroup_permissions_for_repository($this->args[0],$_SESSION['user']);
                }
                catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                
            default:
                return ['status'=>['error'=>1,'text'=>'I do not understand the request']];
                break;
            }

        case 'POST':
            $invarsraw = file_get_contents('php://input');
            $invars = json_decode($invarsraw);
            ob_start();
            var_dump($invars);
            var_dump($this->verb);
            var_dump($this->args);
            $textdata = ob_get_clean();
            file_put_contents('/var/storage/phpsessions/textdata',$textdata);

            switch($this->verb) {
            case 'addperms':
                try {
                    $mtm = new MTM;
                    if(!isset($invars->repository_id) ||
                    !isset($invars->usergroup_id) ||
                    !isset($invars->portal_permbits) ||
                    !isset($invars->repository_permbits)) {
                        return ['status'=>['error'=>1,'text'=>"Not enough parameters to add a permission"]];
                    }
                    $changes['repository_id'] = $invars->repository_id;
                    $changes['portal_permission'] = $invars->portal_permbits;
                    $changes['repository_permission'] = $invars->repository_permbits;
                    $mtm->change_repository_permission_for_usergroup($invars->usergroup_id,$_SESSION['user'],$changes);
                    return ['status'=>['error'=>0,'text'=>'OK']];
                }
                catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;

            case 'add':
                try {
                    $mtm = new MTM;
                    if(!isset($invars->repository_fullpath,$invars->manager_usergroup_id,$invars->repository_fileprefix)) {
                        return ['status'=>['error'=>1,'text'=>"Not enough parameters to add a repository. (need repository_fileprefix, repository_fullpath, and manager_usergroup_id)"]];
                    }
                    $newgrp['fullpath'] = $invars->repository_fullpath;
                    $newgrp['manager_usergroup_id'] = $invars->manager_usergroup_id;
                    $newgrp['fileprefix'] = $invars->repository_fileprefix;
                    if(isset($invars->repository_description)) {
                        $newgrp['description'] = $invars->repository_description;
                    }
                    $ret = $mtm->add_repository_with_managergroup($newgrp,$_SESSION['user']);
                    $ret[0]['status'] = ['error'=>0,'text'=>'OK'];
                    return $ret;
                }
                catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;

            case '':
                try {
                    $mtm = new MTM;
                    if(!isset($invars->repository_id) || (!isset($invars->fileprefix) && !isset($invars->description))) {
                        return ['status'=>['error'=>1,'text'=>'Not enough arguments to edit a repository']];
                    }
                    $edits = [];
                    if(isset($invars->fileprefix)) {
                        $edits['fileprefix'] = $invars->fileprefix;
                    }
                    if(isset($invars->description)) {
                        $edits['description'] = $invars->description;
                    }
                    $ret = $mtm->update_repository_info($invars->repository_id,$_SESSION['user'],$edits);
                    $ret['status'] = ['error'=>0,'text'=>'OK'];
                    return $ret;
                }
                catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;
                

            default:
                return ['status'=>['error'=>1,'text'=>'I do not understand the request']];
                break;
            }

        }
    }

    protected function usergroups() {
        switch($this->method) {
        case 'GET':
            switch($this->verb) {
            case '':
                $mtm = new MTM;
                try {
                    $ret = [];
                    $ingrps = $mtm->get_usergroups_for_user($_SESSION['user']);
                    foreach ($ingrps as $ingrp) {
                        $ret[$ingrp["id"]] = $ingrp;
                    }
                    $permgrps = $mtm->get_usergroups_having_user_permissions($_SESSION['user']);
                    foreach ($permgrps as $ingrp) {
                        $ret[$ingrp["id"]] = $ingrp;
                    }
                    $tmp = [];
                    foreach($ret as $rettmp) {
                        $tmp[] = $rettmp;
                    }
                    return $tmp;
                }
                catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                
                break;

            case 'id':
                $mtm = new MTM;
                try {
                    return $mtm->usergroups_by_id($this->args[0]);
                }
                catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                
                break;

            case 'repoperms':
                $mtm = new MTM;
                try {
                    if(isset($this->args[1])) {
                        return $mtm->get_repository_permission_between_groups($this->args[0],$this->args[1],$_SESSION['user']);
                    } else {
                        return $mtm->get_repository_permissions_for_usergroup($this->args[0],$_SESSION['user']);
                    }
                }
                catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;
                
            case 'usergperms':
                $mtm = new MTM;
                if(!isset($this->args[0])) {
                    return ['status'=>['error'=>1,'text'=>'Not enough arguments for usergperms']];
                }
                try {
                    if(isset($this->args[1])) {
                        return $mtm->get_usergroup_permission_between_groups($this->args[0],$this->args[1],$_SESSION['user']);
                    } else {
                        return $mtm->get_usergroup_permissions_for_usergroup($this->args[0],$_SESSION['user']);
                    }
                }

                catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;
                
            case 'shibgroups':
                $shauth = new Shib_Auth;
                try {
                    return $shauth->get_shibgroups_for_usergroup($this->args[0],$_SESSION['user']);
                }
                catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;

            default:
                return ['status'=>['error'=>1,'text'=>'I do not understand the request']];
                break;
            }

        case 'POST':
            $invarsraw = file_get_contents('php://input');
            $invars = json_decode($invarsraw);
            ob_start();
            var_dump($invars);
            var_dump($this->verb);
            var_dump($this->args);
            $textdata = ob_get_clean();
            file_put_contents('/var/storage/phpsessions/textdata',$textdata);

            switch($this->verb) {
            case 'addperms':
                try {
                    $mtm = new MTM;
                    if(!isset($invars->target_usergroup_id) ||
                    !isset($invars->acting_usergroup_id) ||
                    !isset($invars->portal_permission)) {
                        return ['status'=>['error'=>1,'text'=>"Not enough parameters to add a permission"]];
                    }
                    $changes['target_usergroup_id'] = $invars->target_usergroup_id;
                    $changes['portal_permission'] = $invars->portal_permission;
                    $mtm->change_usergroup_permission_for_usergroup($invars->acting_usergroup_id,$_SESSION['user'],$changes);
                    return ['status'=>['error'=>0,'text'=>'OK']];
                }
                catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;

            case 'addshibgroup':
                try {
                    $shib_auth = new Shib_Auth;
                    if(!isset($invars->ad_path) ||
                    !isset($invars->id)) {
                        return ['status'=>['error'=>'1','text'=>'Not enough parameters to add shib group']];
                    }
                    $shib_auth->add_shibgroup_to_usergroup($invars->ad_path,$invars->id,$_SESSION['user']);
                    return ['status'=>['error'=>0,'text'=>'OK']];
                }
                catch (exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;

            case 'add':
                try {
                    $mtm = new MTM;
                    if(!isset($invars->new_usergroup_name) ||
                    (!isset($invars->manager_usergroup_id) && !isset($invars->manager_usergroup_name))) {
                        return ['status' => ['error'=>1,'text'=>'Not enough parameters to add a new usergroup.']];
                    }
                    $mgid = 0;
                    if(isset($invars->manager_usergroup_id)) {
                        $mgid = $invars->manager_usergroup_id;
                    } else {
                        $mgids = T_UserGroup::search($invars->manager_usergroup_name);
                        if(count($mgids)!=1) {
                            return ['status'=>['error'=>1,'text'=>'Invalid usergroup name']];
                        }
                        $mgid = $mgids[0];
                    }
                    $ret = $mtm->add_usergroup_with_managergroup($invars->new_usergroup_name,$_SESSION['user'],$mgid);
                    $ret[0]['status'] = ['error'=>0,'text'=>'OK'];
                    return $ret;
                }
                catch (exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;
                
            case '':
                try {
                    $mtm = new MTM;
                    if(!isset($invars->usergroup_id) || !isset($invars->description)) {
                        return ['status'=>['error'=>1,'text'=>'Not enough arguments to edit a usergroup']];
                    }
                    $edits = [];
                    $edits['description'] = $invars->description;
                    $ret = $mtm->update_usergroup_info($invars->usergroup_id,$_SESSION['user'],$edits);
                    $ret['status'] = ['error'=>0,'text'=>'OK'];
                    return $ret;
                }
                catch(exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;
                
            default:
                return ['status'=>['error'=>1,'text'=>'I do not understand the request']];
                break;
            }

            break;

        case 'DELETE':
            switch($this->verb) {
            case 'id':
                try {
                    if(!isset($this->args[0])) {
                        return ['status'=>['error'=>1,'text'=>'Need a valid usergroup ID to delete']];
                    }
                    $mtm = new MTM;
                    $mtm->delete_usergroup($this->args[0],$_SESSION['user']);
                    return ['status'=>['error'=>0,'text'=>'OK']];
                }
                catch (exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;

            case 'shibgroup':
                try {
                    $shib_auth = new Shib_Auth;
                    if(!isset($this->args[0]) ||
                    !isset($this->args[1])) {
                        return ['status'=>['error'=>'1','text'=>'Not enough parameters to delete shib group']];
                    }
                    $shib_auth->del_shibgroup_from_usergroup($this->args[1],$this->args[0],$_SESSION['user']);
                    return ['status'=>['error'=>0,'text'=>'OK']];
                }
                catch (exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;

            default:
                return ['status'=>['error'=>1,'text'=>'I do not understand the request']];
                break;

            }


        }

    }

    protected function adgroups() {
        switch($this->method) {
        case 'GET':
            switch($this->verb) {
            case 'samaccountname':
                $shib_auth = new Shib_Auth;
                if(!(isset($this->args[0]))) {
                    return ['status'=>['error'=>'1','text'=>'No group specified']];
                }

                try {
                    $res = $shib_auth->get_ad_group_dn($this->args[0]);
                    $sg = $shib_auth->shib_group_from_ad($res);
                    return ['distinguishedname'=>$res,'shib_group'=>$sg];
                }
                catch (exception $e) {
                    return ['status'=>['error'=>1,'text'=>$e->getMessage()]];
                }
                break;

            default:
                return ['status'=>['error'=>1,'text'=>'I do not understand the request']];
                break;
            }

        default:
            return ['status'=>['error'=>1,'text'=>'I do not understand the request']];
            break;

        }
    }

}

