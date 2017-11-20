ComputerService.factory("Authentication",function($resource,$window,$q) {
    var Auth = $resource("/api/v1/whoami");

    var loggedin = false;

    Auth.setlastresults = function(current_results) {
	results = current_results;
    }

    Auth.getlastresults = function() {
	return results;
    }

    Auth.User = function() {
	var deferred = $q.defer();
	Auth.get({},function(data){
	    if(!angular.isDefined(data.status) || data.status.error == 0) {
		console.log(data);
		deferred.resolve("Log out " + data.user);
		loggedin = true;
	    } else {
		deferred.resolve("LOG IN");
		loggedin = false;
	    }
	});

	return deferred.promise;
    }


    Auth.loginout = function() {
	if(loggedin) { 
	    var landingURL = "https://" + $window.location.host + "/Portal/api/v1/logout";
	    $window.open(landingURL,"_self");
	} else {
	    var landingURL = "https://" + $window.location.host + "/Portal/api/v1/login";
	    $window.open(landingURL,"_self");
	}
    }

    return Auth;

});

app.controller("IndexController",function($scope,$location,Authentication) {
    $scope.username = "";
    Authentication.User().then(function(data) {
	$scope.username = data;
    });

    $scope.loginout = function() {
	Authentication.loginout();
    }

});
