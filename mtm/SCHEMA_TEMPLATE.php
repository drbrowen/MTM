<?php

$oldincludepath = set_include_path(dirname(__FILE__));

%INCLUDES%
set_include_path($oldincludepath);

class %SCHEMANAME% {

  static protected $dbh;
  static private $transactionlevel;

  static protected $globalvars = [ %GLOBALVARS% ];

  function __construct($params) {
    if(!isset($params['host']) ||
       !isset($params['db']) ||
       !isset($params['user']) ||
       !isset($params['pass'])) {
      throw new exception("%SCHEMANAME%::__construct: Invalid args.  Pass array containing 'host', 'db', 'user', and 'pass'");
    }

    try {
      %SCHEMANAME%::$dbh = new PDO("mysql:host=".$params['host'].";dbname=".$params['db'],
			    $params['user'],
			    $params['pass']);
    }
    catch (PDOException $e) {
      print "Error!: ". $e->getMessage() . "<br/>\n";
      exit(1);
    }

    %SCHEMANAME%::$dbh->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    %SCHEMANAME%::$transactionlevel = 0;

  }

  public function __set($key,$value) {
    if(isset(%SCHEMANAME%::$globalvars[$key])) {
      %SCHEMANAME%::$globalvars[$key] = $value;
      return;
    }

    throw new exception("%SCHEMANAME%::__set() key '$key' is not a global attribute.");
  }


  public function __get($key) {
    if(isset(%SCHEMANAME%::$globalvars[$key])) {
      return %SCHEMANAME%::$globalvars[$key];
    }

    throw new exception("%SCHEMANAME%::__get() key '$key' not a global attribute.");
  }

  public function getDbHandle() {
    return %SCHEMANAME%::$dbh;
  }

  public function beginTransaction() {
    if(%SCHEMANAME%::$transactionlevel == 0)
      {
	%SCHEMANAME%::$dbh->beginTransaction();
      }
    %SCHEMANAME%::$transactionlevel++;

  }

  public function commit() {
    %SCHEMANAME%::$transactionlevel--;

    if(%SCHEMANAME%::$transactionlevel == 0)
      {
	%SCHEMANAME%::$dbh->commit();
      }

  }

  public function rollback() {
    %SCHEMANAME%::$dbh->rollback();
    %SCHEMANAME%::$transactionlevel = 0;
  }

}
