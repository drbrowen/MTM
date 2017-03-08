ComputerService.factory("GroupPerms",function($resource,myErrorHandler) {
    var results = [];

    var GroupPerms = $resource("/api/v1/repositories/:how/:key",{},{
	'usergroups': {method:'GET',
		       url:"/api/v1/usergroups/:how/:key",
		       isArray:true,
		       transformResponse: function(data) {return myErrorHandler.format(data)},
		       interceptor: { responseError: myErrorHandler.doit}},

	'usergroupsave': {method:'POST',
			   url:"/api/v1/usergroups",
			   isArray:false,
			   transformResponse: function(data) {return myErrorHandler.format(data)},
			   interceptor: { responseError: myErrorHandler.doit}},

	'usergrouprepoperms': {method:'GET',
		       url:"/api/v1/repositories/perms/:key",
		       isArray:true,
		       transformResponse: function(data) {return myErrorHandler.format(data)},
			   interceptor: { responseError: myErrorHandler.doit}},

	'modperm': {method:'POST',
		    url:"/api/v1/repositories/perms"},

	'repoperms': {method:'GET',
			   url:"/api/v1/usergroups/repoperms/:key",
			   isArray:true,
			   transformResponse: function(data) {return myErrorHandler.format(data)},
			   interceptor: { responseError: myErrorHandler.doit}},

	'userrepoperms': {method:'GET',
			  url:"/api/v1/usergroups/repoperms/:uid/:gid",
			  isArray:true,
			  transformResponse: function(data) {return myErrorHandler.format(data)},
			  interceptor: { responseError: myErrorHandler.doit}},
	
	'saveuserrepoperms': {method:'POST',
			      url:"/api/v1/repositories/addperms",
			      isArray:false,
			      transformResponse: function(data) {return myErrorHandler.format(data)},
			      interceptor: { responseError: myErrorHandler.doit}},

	'useruserperms': {method:'GET',
			  url:"/api/v1/usergroups/usergperms/:uid/:gid",
			  isArray:true,
			  transformResponse: function(data) {return myErrorHandler.format(data)},
			  interceptor: { responseError: myErrorHandler.doit}},
	
	'saveuseruserperms': {method:'POST',
			      url:"/api/v1/usergroups/addperms",
			      isArray:false,
			      transformResponse: function(data) {return myErrorHandler.format(data)},
			      interceptor: { responseError: myErrorHandler.doit}},

	'usergroupshibgroups': {method:'GET',
			  url:"/api/v1/usergroups/shibgroups/:key",
			  isArray:true,
			  transformResponse: function(data) {return myErrorHandler.format(data)},
			  interceptor: { responseError: myErrorHandler.doit}},
	
	'addusershibgroup': {method:'POST',
			      url:"/api/v1/usergroups/addshibgroup/",
			      isArray:false,
			      transformResponse: function(data) {return myErrorHandler.format(data)},
			      interceptor: { responseError: myErrorHandler.doit}},

	'addusergroup': {method:'POST',
			 url:"/api/v1/usergroups/add/",
			 isArray:true,
			 transformResponse: function(data) {return myErrorHandler.format(data)},
			 interceptor: { responseError: myErrorHandler.doit}},

	'addrepository': {method:'POST',
			     url:"/api/v1/repositories/add/",
			     isArray:false,
			     transformResponse: function(data) {return myErrorHandler.format(data)},
			     interceptor: { responseError: myErrorHandler.doit}},

	'delusershibgroup': {method:'DELETE',
			      url:"/api/v1/usergroups/shibgroup/:key/:shibid",
			      isArray:false,
			      transformResponse: function(data) {return myErrorHandler.format(data)},
			      interceptor: { responseError: myErrorHandler.doit}},
	
	'adlookup': {method:'GET',
			 url:"/api/v1/adgroups/samaccountname/:key",
			 isArray:false,
			 transformResponse: function(data) {return myErrorHandler.format(data)},
			 interceptor: { responseError: myErrorHandler.doit}},


    });

    GroupPerms.setlastresults = function(current_results) {
	results = current_results;
    };

    GroupPerms.getlastresults = function() {
	return results;
    };

    return GroupPerms;

});

app.controller("RepositoryController",function($scope,$routeParams,GroupPerms,$location) {
    var editindex = 0;
    $scope.showadd = false;

    GroupPerms.query({how:$routeParams.how,
		      key:$routeParams.key},function(data){
			$scope.processdata(data);
		    });

    $scope.groups = [];

    var lastresults = GroupPerms.getlastresults();

    var checklastresults = function(theresults) {
	if(angular.isDefined(theresults.status)) {
	    $scope.lastresults = 1;
	    if(theresults['status']['error'] == 1){
		$scope.errortext = 'ERROR';
	    } else {
		$scope.errortext = 'OK';
	    }
	    $scope.resulttext = theresults['status']['text'];
	} else {
	    $scope.lastresults = 0;
	}
    }

    checklastresults(lastresults);

    $scope.processdata = function(data) {
	if(data.length < 1) {
	    $scope.goodresults = 0;
	    $scope.repositories = [];
	} else {
	    $scope.goodresults = 1;
	    $scope.repositories = data;
	    angular.forEach($scope.repositories,function(value,index) {
		if(value.portal_permbits.S == 1) {
		    $scope.showadd = true;
		}
	    });
		    
	}
	$scope.displayrepositories = [] . concat($scope.repositories);
    };

    $scope.setedit = function($activeID) {
	$location.url('/repositories/edit/ID/' + $activeID);
    }

    $scope.gousergroups = function() {
	$location.url('/usergroups/search');
    }

    $scope.goaddrepo = function() {
	$location.url('/repositories/add');
    }
    
});

app.controller("RepositoryAddController",function($scope,$routeParams,GroupPerms,$location) {
    $scope.showerror = false;

    var checklastresults = function() {
	var theresults = GroupPerms.getlastresults();
	if(angular.isDefined(theresults.status)) {
	    $scope.lastresults = 1;
	    if(theresults['status']['error'] == 1){
		$scope.errortext = 'ERROR';
	    } else {
		$scope.errortext = 'OK';
	    }
	    $scope.resulttext = theresults['status']['text'];
	} else {
	    $scope.lastresults = 0;
	}
    }

    checklastresults();

    GroupPerms.usergroups({},
			     function(data) {
				 $scope.processdata(data);
			     });

    $scope.processdata = function(data) {
	console.log(data);
	if(data.length < 1) {
	    $scope.goodresults = 0;
	    $scope.usergroups = [];
	} else {
	    $scope.goodresults = 1;
	    $scope.usergroups = data;
	}
	$scope.displayusergroups = [] . concat($scope.usergroups);
    };

    $scope.setmanagerid = function(activeID) {
	console.log(activeID);
	$scope.manager_usergroup_id = activeID;
    };

    $scope.addgroup = function() {
	if(!angular.isDefined($scope.manager_usergroup_id) ||
	   $scope.new_usergroup_name == '') {
	    $scope.showerror = true;
	} else {
	    GroupPerms.addrepository({manager_usergroup_id:$scope.manager_usergroup_id,
					 repository_fullpath:$scope.new_repository_fullpath,
					repository_description:$scope.new_repository_description,
					repository_fileprefix:$scope.new_repository_fileprefix},
					function(data) {
					    if(angular.isDefined(data.status)) {
						if(data.status.error == 0) {
						    GroupPerms.setlastresults('');
						    $location.url("/repositories/search");
						} else {
						    GroupPerms.setlastresults(data);
						    checklastresults();
						}
					    }
					});
	}
    };

});

app.controller("RepositoryEditController",function($scope,$routeParams,GroupPerms,$location) {
    GroupPerms.setlastresults('');

    var checklastresults = function() {
	var theresults = GroupPerms.getlastresults();
	if(angular.isDefined(theresults.status)) {
	    $scope.lastresults = 1;
	    if(theresults['status']['error'] == 1){
		$scope.errortext = 'ERROR';
	    } else {
		$scope.errortext = 'OK';
	    }
	    $scope.resulttext = theresults['status']['text'];
	} else {
	    $scope.lastresults = 0;
	}
    }

    checklastresults();

    GroupPerms.query({how:'id',
		      key:$routeParams.key},function(data){
			  $scope.processdata(data);
		      });
    
    GroupPerms.usergrouprepoperms({key:$routeParams.key},function(data){
	$scope.processpermdata(data);
    });

    $scope.processdata = function(data) {
	console.log(data);
	if(data.length < 1) {
	    $scope.goodresults = 0;
	    $scope.repository = [];
	} else {
	    $scope.goodresults = 1;
	    $scope.repository = data[0];
	}
	$scope.repository_fileprefix = $scope.repository.fileprefix;
	$scope.repository_description = $scope.repository.description;
    };
	       
    $scope.processpermdata = function(data) {
	console.log(data);
	if(data.length < 1) {
	    $scope.goodpermresults = 0;
	    $scope.groupperms = [];
	    $scope.displaygroupperms = [];
	} else {
	    $scope.goodpermresults = 1;
	    $scope.groupperms = data;
	    $scope.displaygroupperms = [] . concat(data);
	}
    }

    $scope.setedit_usergroup = function(activeID) {
	GroupPerms.setlastresults('');				 
	$location.url("/usergroups/edit/id/" + activeID);
    }

    $scope.setedit_usergroupperms = function(activeID) {
	GroupPerms.setlastresults('');				 
	$location.url("/userrepoperms/" + activeID + "/" + $routeParams.key + '/C');
    }

    $scope.fulllist= function() {
	GroupPerms.setlastresults('');				 
	$location.url('/repositories/search');
    }

    $scope.update_fileprefix = function() {
	var gp = new GroupPerms;
	gp.repository_id = $routeParams.key;
	gp.fileprefix = $scope.repository_fileprefix;
	gp.$save({how:''},
		 function(data) {
		     console.log(data);
		     GroupPerms.setlastresults(data);
		     checklastresults();
		     GroupPerms.query({how:'id',
				       key:$routeParams.key},function(data){
					   $scope.processdata(data);
				       });    
		 });
    }

    $scope.update_description = function() {
	var gp = new GroupPerms;
	gp.repository_id = $routeParams.key;
	gp.description = $scope.repository_description;
	gp.$save({how:''},
		 function(data) {
		     console.log(data);
		     GroupPerms.setlastresults(data);
		     checklastresults();
		     GroupPerms.query({how:'id',
				       key:$routeParams.key},function(data){
					   $scope.processdata(data);
				       });    
		 });
    }

});

app.controller("UserGroupController",function($scope,$routeParams,GroupPerms,$location) {
    GroupPerms.usergroups({how:$routeParams.how,
			   key:$routeParams.key},
			  function(data) {
			      $scope.processdata(data);
			  });

    $scope.processdata = function(data) {
	console.log(data);
	if(data.length < 1) {
	    $scope.goodresults = 0;
	    $scope.usergroups = [];
	} else {
	    $scope.goodresults = 1;
	    $scope.usergroups = data;
	}
	$scope.displayusergroups = [] . concat ($scope.usergroups);
    };    

    $scope.setedit = function($activeID) {
	$location.url("/usergroups/edit/id/" + $activeID);
    };

    $scope.gorepos = function() {
	GroupPerms.setlastresults('');
	$location.url('/repositories/search');
    }

    $scope.goadd = function() {
	GroupPerms.setlastresults('');
	$location.url('/usergroups/add/');
    }

});

app.controller("UserGroupAddController",function($scope,$routeParams,GroupPerms,$location) {
    $scope.showerror = false;

    var checklastresults = function() {
	var theresults = GroupPerms.getlastresults();
	if(angular.isDefined(theresults.status)) {
	    $scope.lastresults = 1;
	    if(theresults['status']['error'] == 1){
		$scope.errortext = 'ERROR';
	    } else {
		$scope.errortext = 'OK';
	    }
	    $scope.resulttext = theresults['status']['text'];
	} else {
	    $scope.lastresults = 0;
	}
    }

    checklastresults();

    GroupPerms.usergroups({},
			     function(data) {
				 $scope.processdata(data);
			     });

    $scope.processdata = function(data) {
	console.log(data);
	if(data.length < 1) {
	    $scope.goodresults = 0;
	    $scope.usergroups = [];
	} else {
	    $scope.goodresults = 1;
	    $scope.usergroups = data;
	}
	$scope.displayusergroups = [] . concat($scope.usergroups);
    };

    $scope.setmanagerid = function(activeID) {
	console.log(activeID);
	$scope.manager_usergroup_id = activeID;
    };

    $scope.addgroup = function() {
	console.log("Adding group.");
	console.log($scope.manager_usergroup_id);
	console.log($scope.new_usergroup_name);
	if(!angular.isDefined($scope.manager_usergroup_id) ||
	   $scope.new_usergroup_name == '') {
	    $scope.showerror = true;
	} else {
	    GroupPerms.addusergroup({manager_usergroup_id:$scope.manager_usergroup_id,
				    new_usergroup_name:$scope.new_usergroup_name},
				   function(data) {
				       if(angular.isDefined(data.status)) {
					   if(data.status.error == 0) {
					       $location.url("/usergroups/search");
					   } else {
					       GroupPerms.setlastresults(data);
					   }
				       }
				   });
	}
    };

});

///////////////////////////////

app.controller("UserGroupEditController",function($scope,$routeParams,GroupPerms,$location) {
    $scope.showshibadd = false;
    $scope.showshibdelete = false;

    GroupPerms.usergroups({how:'id',
			   key:$routeParams.key},
			  function(data) {
			      $scope.processdata(data);
			      GroupPerms.repoperms({how:'id',
							 key:$routeParams.key},
							function(data){
							    $scope.processrepopermdata(data);});
			      GroupPerms.useruserperms({uid:$routeParams.key,
								  key:$routeParams.key},
								 function(data){
								     $scope.processuserpermdata(data);});
			      GroupPerms.usergroupshibgroups({how:'id',
							      key:$routeParams.key},
							     function(data){
								 $scope.processshibgroupdata(data);});
			  });
    
    var checklastresults = function() {
	var theresults = GroupPerms.getlastresults();
	if(angular.isDefined(theresults.status)) {
	    $scope.lastresults = 1;
	    if(theresults['status']['error'] == 1){
		$scope.errortext = 'ERROR';
	    } else {
		$scope.errortext = 'OK';
	    }
	    $scope.resulttext = theresults['status']['text'];
	} else {
	    $scope.lastresults = 0;
	}
    }

    checklastresults();

    $scope.processdata = function(data) {
	console.log(data);
	if(data.length < 1) {
	    $scope.goodresults = 0;
	    $scope.usergroup = [];
	} else {
	    $scope.goodresults = 1;
	    $scope.usergroup = data[0];
	}
	$scope.displayusergroup = [] . concat ($scope.usergroup);
	$scope.usergroup_description = $scope.usergroup.description;
    };

    $scope.processrepopermdata = function(data) {
	console.log(data);
	if(data.length < 1) {
	    $scope.goodpermresults = 0;
	    $scope.repoperms = [];
	} else {
	    $scope.goodpermresults = 1;
	    $scope.repoperms = data;
	}
	$scope.displayrepoperms = [] . concat ($scope.repoperms);
    };

    $scope.processuserpermdata = function(data) {
	console.log(data);
	if(data.length < 1) {
	    $scope.gooduserpermresults = 0;
	    $scope.usergroupperms = [];
	} else {
	    $scope.gooduserpermresults = 1;
	    $scope.usergroupperms = data;
	}
	$scope.displayusergroupperms = [] . concat ($scope.usergroupperms);
    };

    $scope.processshibgroupdata = function(data) {
	console.log(data);
	if(data.length < 1) {
	    $scope.goodshibgroupresults = 0;
	    $scope.shibgroups = [];
	} else {
	    $scope.goodshibgroupresults = 1;
	    $scope.shibgroups = data;
	}
	$scope.displayshibgroups = [] . concat ($scope.shibgroups);
    }

    $scope.setedit_repository = function(activeID) {
	GroupPerms.setlastresults('');
	$location.url("/repositories/edit/id/" + activeID);
    }

    $scope.setedit_repoperms = function(activeID) {
	GroupPerms.setlastresults('');
	$location.url("/userrepoperms/" + $routeParams.key + '/' + activeID + '/U');
    }


    $scope.setedit_usergroup = function(activeID) {
	GroupPerms.setlastresults('');
	$location.url("/usergroups/edit/id/" + activeID);
    }

    $scope.newusergroupperms = function() {
	GroupPerms.setlastresults('');
	$location.url("/useruseraddperms/" + $routeParams.key);
    }

    $scope.newrepoperms = function() {
	GroupPerms.setlastresults('');
	$location.url("/userrepoaddperms/" + $routeParams.key);
    }

    $scope.enableshibdelete = function (level) {
	if(level == 1) {
	    $scope.showshibdelete = true;
	}
	if(level == 0) {
	    $scope.showshibdelete = false;
	}
    }

    $scope.update_description = function() {
	var gp = {};
	gp.usergroup_id = $routeParams.key;
	gp.description = $scope.usergroup_description;
	GroupPerms.usergroupsave(gp,
			 function(data) {
			     console.log(data);
			     GroupPerms.setlastresults(data);
			     checklastresults();
			     GroupPerms.usergroups({how:'id',
						    key:$routeParams.key},function(data){
							$scope.processdata(data);
						    });
			 });
    }

    $scope.shibdelete = function (shib_id) {
	GroupPerms.delusershibgroup({key:$routeParams.key,shibid:shib_id},
				    function(data) {
					    if(angular.isDefined(data.status)) {
						GroupPerms.setlastresults(data);
						checklastresults();
						//if(data.status.error == 0) {
						//    $location.url('/computers/search');
						//}
						if(data.status.error == 0) {
						    GroupPerms.usergroupshibgroups({how:'id',
										    key:$routeParams.key},
										   function(data){
										       $scope.processshibgroupdata(data);});
						}
					    }
					});					
    }

    $scope.add_shibgroup = function (level) {
	if(level == 1) {
	    $scope.newshibadgroup = '';
	    $scope.goodadlookup = false;
	    $scope.lookup_adgrouppath = '';
	    $scope.lookup_shibgroup = '';
	    $scope.showshibadd = true;
	}
	if(level == 2) {
	    $scope.lookup_adgrouppath = "";
	    $scope.lookup_shibgroup = "";
	    GroupPerms.adlookup({key:$scope.newshibadgroup},
				function(data) {
				    if(angular.isDefined(data.distinguishedname)) {
					$scope.goodadlookup = true;
					$scope.lookup_adgrouppath = data.distinguishedname;
					$scope.lookup_shibgroup = data.shib_group;
				    }
				});
	}
	if(level == 3) {
	    GroupPerms.addusershibgroup({id:$routeParams.key,ad_path:$scope.lookup_adgrouppath},
					function(data) {
					    if(angular.isDefined(data.status)) {
						GroupPerms.setlastresults(data);
						checklastresults();
						//if(data.status.error == 0) {
						//    $location.url('/computers/search');
						//}
						if(data.status.error == 0) {
						    GroupPerms.usergroupshibgroups({how:'id',
										    key:$routeParams.key},
										   function(data){
										       $scope.processshibgroupdata(data);});
						    $scope.showshibadd = false;
						    $scope.newshibadgroup = '';
						}
					    }
					});

	}
	if(level == 0) {
	    $scope.showshibadd = false;
	    $scope.newshibadgroup = '';
	}
    };

    $scope.gousergperms = function(activeID) {
	$location.url("/useruserperms/" + $routeParams.key + "/" + activeID + '/A');
    };

    $scope.fulllist= function() {
	GroupPerms.setlastresults('');
	$location.url('/usergroups/search');
    }


});

//////////////////////////////

app.controller("RepoPermAddController",function($scope,$routeParams,GroupPerms,$location) {
    GroupPerms.usergroups({how:'id',key:$routeParams.uid},
			  function(data) {
			      $scope.processbasedata(data);
			  });

    GroupPerms.query({},
		     function(data) {
			 $scope.processdata(data);
		     });

    $scope.processbasedata = function(data) {
	console.log(data);
	if(angular.isDefined(data) && data.length!=1) {
	    $scope.basegoodresults = false;
	    $scope.baseusergroup = {};
	} else {
	    $scope.basegoodresults = true;
	    $scope.baseusergroup = data[0];
	}
    };
	    

    $scope.processdata = function(data) {
	console.log(data);
	if(data.length < 1) {
	    $scope.goodresults = false;
	    $scope.repos = [];
	} else {
	    $scope.goodresults = true;
	    $scope.repos = data;
	}
	$scope.displayrepos = [] . concat ($scope.repos);
    };

    $scope.goadd = function(activeID) {
	$location.url('/userrepoperms/' + $routeParams.uid + '/' + activeID + '/U');
    };

});

app.controller("RepoPermEditController",function($scope,$routeParams,GroupPerms,$location) {
    GroupPerms.userrepoperms({uid:$routeParams.uid,
			      gid:$routeParams.gid},
			     function(data) {
				 $scope.processdata(data);
			     });

    var checklastresults = function() {
	var theresults = GroupPerms.getlastresults();
	if(angular.isDefined(theresults.status)) {
	    $scope.lastresults = 1;
	    if(theresults['status']['error'] == 1){
		$scope.errortext = 'ERROR';
	    } else {
		$scope.errortext = 'OK';
	    }
	    $scope.resulttext = theresults['status']['text'];
	} else {
	    $scope.lastresults = 0;
	}
    }

    $scope.showback = false;
    if(angular.isDefined($routeParams.ret) && $routeParams.ret != '') {
	$scope.showback = true;
    }

    $scope.goback = function() {
	if($routeParams.ret == 'C') {
	    $location.url('/repositories/edit/id/' + $routeParams.gid);
	}
	if($routeParams.ret == 'U') {
	    $location.url('/usergroups/edit/id/' + $routeParams.uid);
	}
    }

    $scope.edit_save = function() {
	var portal_permbits = "";
	if($scope.portalpermbit_V) {
	    portal_permbits += "V";
	}
	if($scope.portalpermbit_C) {
	    portal_permbits += "C";
	}
	if($scope.portalpermbit_G) {
	    portal_permbits += "G";
	}
	if($scope.portalpermbit_S) {
	    portal_permbits += "S";
	}

	var repository_permbits = "";	
	if($scope.repositorypermbit_R) {
	    repository_permbits += "R";
	}
	if($scope.repositorypermbit_W) {
	    repository_permbits += "W";
	}

	var permupdate = {};
	permupdate.usergroup_id = $routeParams.uid;
	permupdate.repository_id = $routeParams.gid;
	permupdate.portal_permbits = portal_permbits;
	permupdate.repository_permbits = repository_permbits;
	GroupPerms.saveuserrepoperms(permupdate,
				     function(data) {
					 if(angular.isDefined(data.status)) {
					     GroupPerms.setlastresults(data);
					     checklastresults();
					     //if(data.status.error == 0) {
					     //    $location.url('/computers/search');
					     //}
					 }
				     });
    }

    $scope.processdata = function(data) {
	if(data.length<1) {
	    $scope.goodresults = 0;
	    $scope.perms = [];
	} else {
	    $scope.goodresults = 1;
	    $scope.perms = data[0];
	    if(angular.isDefined(data[0].portal_permbits)) {
		var portalbits = data[0].portal_permbits;

		$scope.portalpermbit_V = (portalbits['V']==1)?true:false;
		$scope.portalpermbit_C = (portalbits['C']==1)?true:false;
		$scope.portalpermbit_G = (portalbits['G']==1)?true:false;
		$scope.portalpermbit_S = (portalbits['S']==1)?true:false;
	    } else {
		$scope.portalpermbits_V = false;
		$scope.portalpermbits_C = false;
		$scope.portalpermbits_G = false;
		$scope.portalpermbits_S = false;
	    }
	    if(angular.isDefined(data[0].repository_permbits)) {
		var repositorybits = data[0].repository_permbits;
		$scope.repositorypermbit_R = (repositorybits['R']==1)?true:false;
		$scope.repositorypermbit_W = (repositorybits['W']==1)?true:false;
	    } else {
		$scope.repositorypermbit_R = false;
		$scope.repositorypermbit_W = false;
	    }
	}
	$scope.displayperms = [] . concat($scope.perms);    
	console.log(data);
    };
    
    $scope.gousergroup = function() {
	$location.url("/usergroups/edit/id/" + $routeParams.uid);
    };

    $scope.gorepo = function() {
	$location.url("/repositories/edit/id/" + $routeParams.gid);
    };

});

app.controller("UserGroupPermAddController",function($scope,$routeParams,GroupPerms,$location) {
    GroupPerms.usergroups({how:'id',key:$routeParams.uid},
			  function(data) {
			      $scope.processbasedata(data);
			  });

    GroupPerms.usergroups({},
			  function(data) {
			      $scope.processdata(data);
			  });

    $scope.processbasedata = function(data) {
	console.log(data);
	if(angular.isDefined(data) && data.length!=1) {
	    $scope.basegoodresults = false;
	    $scope.baseusergroup = {};
	} else {
	    $scope.basegoodresults = true;
	    $scope.baseusergroup = data[0];
	}
    };
	    

    $scope.processdata = function(data) {
	console.log(data);
	if(data.length < 1) {
	    $scope.goodresults = false;
	    $scope.usergroups = [];
	} else {
	    $scope.goodresults = true;
	    $scope.usergroups = data;
	}
	$scope.displayusergroups = [] . concat ($scope.usergroups);
    };

    $scope.goadd = function(activeID) {
	$location.url('/useruserperms/' + $routeParams.uid + '/' + activeID);
    };

});

app.controller("UserGroupPermEditController",function($scope,$routeParams,GroupPerms,$location) {
    GroupPerms.useruserperms({uid:$routeParams.uid,
			      gid:$routeParams.gid},
			     function(data) {
				 $scope.processdata(data);
			     });

    var checklastresults = function() {
	var theresults = GroupPerms.getlastresults();
	if(angular.isDefined(theresults.status)) {
	    $scope.lastresults = 1;
	    if(theresults['status']['error'] == 1){
		$scope.errortext = 'ERROR';
	    } else {
		$scope.errortext = 'OK';
	    }
	    $scope.resulttext = theresults['status']['text'];
	} else {
	    $scope.lastresults = 0;
	}
    }

    if(angular.isDefined($routeParams.ret) && $routeParams.ret != '') {
	$scope.showback = true;
    } else {
	$scope.showback = false;
    }

    $scope.goback = function() {
	if($routeParams.ret == 'T') {
	    $location.url('/usergroups/edit/id/' + $routeParams.gid);
	}
	if($routeParams.ret == 'A') {
	    $location.url('/usergroups/edit/id/' + $routeParams.uid);
	}
    };

    $scope.edit_save = function() {
	var portal_permbits = "";
	if($scope.portalpermbit_R) {
	    portal_permbits += "R";
	}
	if($scope.portalpermbit_W) {
	    portal_permbits += "W";
	}
	if($scope.portalpermbit_D) {
	    portal_permbits += "D";
	}
	var permupdate = {};
	permupdate.acting_usergroup_id = $routeParams.uid;
	permupdate.target_usergroup_id = $routeParams.gid;
	permupdate.portal_permission = portal_permbits;
	GroupPerms.saveuseruserperms(permupdate,
				     function(data) {
					 if(angular.isDefined(data.status)) {
					     GroupPerms.setlastresults(data);
					     checklastresults();
					     //if(data.status.error == 0) {
					     //    $location.url('/computers/search');
					     //}
					 }
				     });
    }

    $scope.processdata = function(data) {
	if(data.length<1) {
	    $scope.goodresults = 0;
	    $scope.perms = [];
	} else {
	    $scope.goodresults = 1;
	    $scope.perms = data[0];
	    if(angular.isDefined(data[0].portal_permbits)) {
		var portalbits = data[0].portal_permbits;

		$scope.portalpermbit_R = (portalbits['R']==1)?true:false;
		$scope.portalpermbit_W = (portalbits['W']==1)?true:false;
		$scope.portalpermbit_D = (portalbits['D']==1)?true:false;
	    } else {
		$scope.portalpermbits_R = false;
		$scope.portalpermbits_W = false;
		$scope.portalpermbits_D = false;
	    }
	}
	$scope.displayperms = [] . concat($scope.perms);
	console.log(data);
    };
    
    $scope.gousergroup = function() {
	$location.url("/usergroups/edit/id/" + $routeParams.uid);
    };

    $scope.gotargetgroup = function() {
	$location.url("/usergroups/edit/id/" + $routeParams.gid);
    };

});
