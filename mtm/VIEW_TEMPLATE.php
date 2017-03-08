<?php

class %CLASSNAME% extends %SCHEMANAME% {

%VARDEFS%

  private $realvals = array();

  static protected $allvars = [ %ALLVARS% ];

  static protected $FKvars = [ %FKVARS% ];

  function __construct() {
    foreach ( %CLASSNAME%::$allvars as $attribute => $value) {
        if(isset($this->$attribute)) {
            $this->realvals[$attribute] = $this->$attribute;
        }
    }

  }

  public function __get($key) {
    if(isset($this->realvals[$key])) {
      return $this->realvals[$key];
    }
    if(isset(%CLASSNAME%::$allvars[$key])) {
      return;
    }
    throw new exception("%CLASSNAME%::__get(): You're asking for something I just can't give you. (".$key.")");
  }

  public function search($key,$val,$compare = "=") {
    if(is_array($key) ||
       is_array($val) ||
       is_array($compare)) {
      if(!(is_array($key)) ||
	 !(is_array($val)) ||
	 !(is_array($compare)) ||
	 (count($key) != count($val)) || (count($key)!=count($compare))) {
	throw new exception("%CLASSNAME%::search() different number of elements in key and value arrays.");
      }
      foreach($key as $check) {
	if(!isset(%CLASSNAME%::$allvars[$check])) {
	  return;
	}
      }
      return %CLASSNAME%::_search($key,$val,$compare);
    }

    if(isset(%CLASSNAME%::$allvars[$key])) {
      return %CLASSNAME%::_search([$key],[$val],[$compare]);
    }

  }

  private function _search($keys,$compvals,$compares) {
    $val = array();
    $wheres = array();
    foreach($keys as $ord => $key) {
      
      if($compares[$ord] !== "=" &&
	 $compares[$ord] !== ">=" &&
	 $compares[$ord] !== "<=" &&
	 $compares[$ord] !== "NULL") {
	throw new exception("%CLASSNAME%::_search() only comparisons are =, >=, <=, or NULL");
	return;
      }

      // Handle foreign keys a bit differently.
      if($compares[$ord] === "NULL") {
	$where[] = $key." IS NULL";
      } elseif(isset(%CLASSNAME%::$FKvars[$key]) && is_object($compvals[$ord])) {
	if(get_class($compvals[$ord]) === %CLASSNAME%::$FKvars[$key]) {
	  $val[":".$ord] = $compvals[$ord]->ID;
	  $where[] = $key." ".$compares[$ord]." :".$ord;
	} else {
	  throw new exception("%CLASSNAME%::_search() Attempted foreign key search with wrong object type.");
	}	
      } else {
	$val[":".$ord] = $compvals[$ord];
	$where[] = $key." ".$compares[$ord]." :".$ord;
      }
    }

    $wherestring = implode(" AND ",$where);

    $sth = %SCHEMANAME%::$dbh->prepare("SELECT *
                          FROM %TABLENAME%
                          WHERE ".$wherestring);
      

    $sth->execute($val);

    $ret = array();
    while($tmp = $sth->fetchObject('%CLASSNAME%'))
      {
	$ret[] = $tmp;
      }

    return $ret;

  }

}
