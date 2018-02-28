var ComputerService = angular.module('ComputerService',['ngResource']);

//var OuService = angular.module('OuService',['ngResource']);

//var app = angular.module('myApp',['ComputerService','OuService','angularUtils.directives.dirPagination','smart-table','ngRoute']);
var app = angular.module('myApp',['ComputerService','angularUtils.directives.dirPagination','smart-table','ngRoute']);

app.config(function($routeProvider,$locationProvider) {
    $routeProvider

	.when('/', {
	    controller:'IndexController',
	    templateUrl:'index2.html',
	})

	.when('/computers', {
	    redirectTo:'/computers/search'
	})

	.when('/downloads', {
	    templateUrl:'downloads.html'
	})

	.when('/computers/edit/:how?/:id?', {
	    templateUrl:'computeredit.html',
	    controller:'ComputerEditController'
	})
    
	.when('/computers/search/:how?/:key?/:key2?', {
	    templateUrl:'computerlist.html',
	    controller:'ComputerController'
	})

	.when('/computers/csv', {
	    templateUrl:'computercsv.html',
	    controller:'ComputerCSVController'
	})

	.when('/repositories', {
	    redirectTo: '/repositories/search'
	})

	.when('/repositories/search/:how?/:key?', {
	    templateUrl:'repolist.html',
	    controller:'RepositoryController'
	})

	.when('/repositories/edit/:how?/:key?', {
	    templateUrl:'repoedit.html',
	    controller:'RepositoryEditController'
	})

	.when('/repositories/add', {
	    templateUrl:'repoadd.html',
	    controller:'RepositoryAddController'
	})

	.when('/usergroups/search/:how?/:key?', {
	    templateUrl:'usergrouplist.html',
	    controller:'UserGroupController'
	})

	.when('/usergroups', {
	    redirectTo:'/usergroups/search'
	})

	.when('/usergroups/edit/:how?/:key?', {
	    templateUrl:'usergroupedit.html',
	    controller:'UserGroupEditController'
	})

	.when('/usergroups/add/', {
	    templateUrl:'usergroupadd.html',
	    controller:'UserGroupAddController'
	})

	.when('/userrepoperms/:uid?/:gid?/:ret?', {
	    templateUrl:'userrepoperms.html',
	    controller:'RepoPermEditController'
	})

	.when('/userrepoaddperms/:uid', {
	    templateUrl:'userrepoaddperms.html',
	    controller:'RepoPermAddController'
	})

	.when('/useruserperms/:uid?/:gid?/:ret?', {
	    templateUrl:'useruserperms.html',
	    controller:'UserGroupPermEditController'
	})

	.when('/useruseraddperms/:uid', {
	    templateUrl:'useruseraddperms.html',
	    controller:'UserGroupPermAddController'
	})

	.when('/notfound', {
	    templateUrl:'notfound.html'
	})
    
	.otherwise({
	    redirectTo:'/notfound'
	    //redirectTo: '/computers/search'
	});
});

