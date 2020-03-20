app.controller('menuController',function($scope,$location,$window,Authentication) {
    $scope.navitems = [];
    $scope.rnavitems = [];
    $scope.username = '';

    Authentication.User().then(function(data) {
	$scope.username = data;
    });

    // A 'perm' category is for future expansion where only some items show up.
    var rawnavitems = [{url:'#/computers/search',name:'Manage Computers',location:'L',perm:['']},
		       {url:'#/usergroups/search',name:'Manage Groups and Permissions',location:'L',perm:['']},
		       {url:'#/repositories/search',name:'Manage Repositories',location:'L',perm:['']},
		       {url:'#/downloads',name:'Downloads',location:'L',perm:['']},
		       {url:'https://munkireport.eps.uillinois.edu',name:'Reporting',location:'L',perm:['']},
		       {url:'https://answers.uillinois.edu/search.php?q=munki+-FAA',name:'Documentation',location:'L',perm:['']},
                       {url:'packages.html',name:'Available Software',location:'L',perm:['']},
		      ];

    angular.forEach(rawnavitems,function(value) {
	if(value.location == 'L') {
	    $scope.navitems = $scope.navitems.concat(value);
	} else if(value.location == 'R') {
	    $scope.rnavitems = $scope.rnavitems.concat(value);
	}
    });

    $scope.goto = function(newurl) {
        if(newurl.substr(0,1) == '#') {
            $location.url(newurl.substr(1,newurl.length));
        } else {
            $window.location.href = newurl;
        }
    }

    $scope.loginout = function() {
	Authentication.loginout();
    }

});
