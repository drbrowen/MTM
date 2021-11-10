<?php

class %CLASSNAME% extends %SCHEMANAME% {

%VARDEFS%

  private $realvals = array();

  private $reset = array();

  static protected $allvars = [ %ALLVARS% ];

  static protected $changevars = [ %CHANGEVARS% ];

  static protected $FKvars = [ %FKVARS% ];

  static protected $updatevars = array();

  static protected $PK = '%PK%';

  static protected $autocreatedate = [ %AUTOCREATEDATE% ];
  static protected $autocreatetimestamp = [ %AUTOCREATETIMESTAMP% ];

  static protected $autoupdatedate = [ %AUTOUPDATEDATE% ];
  static protected $autoupdatetimestamp = [ %AUTOUPDATETIMESTAMP% ];

  static protected $createglobals = [ %CREATEGLOBALS% ];

  static protected $updateglobals = [ %UPDATEGLOBALS% ];

  function __construct() {

    if(count(%CLASSNAME%::$updatevars)==0) {
      foreach ( %CLASSNAME%::$allvars as $attribute => $value )
	{
	  if($attribute != %CLASSNAME%::$PK)
	    {
	      %CLASSNAME%::$updatevars[] = $attribute;
	    }
	}
    }

    foreach ( %CLASSNAME%::$allvars as $attribute => $value) {
      $this->reset[$attribute] = "FALSE";
      if(isset($this->$attribute)) {
	$this->realvals[$attribute] = $this->$attribute;
      }
    }
    $mypk = %CLASSNAME%::$PK;
    if(!(isset($this->$mypk))) {
      $this->realvals[$mypk] = 0;
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

  public function __set($key,$value) {
    if(isset(%CLASSNAME%::$changevars[$key])) {
      $this->realvals[$key] = $value;
      $this->reset[$key] = "TRUE";
      return;
    }
    if(isset(%CLASSNAME%::$FKvars[$key])) {
      $mytype = get_class($value);
      if(%CLASSNAME%::$FKvars[$key] === $mytype) {
	$tmpfk = $value->getPK();
	if($tmpfk>0) {
	  $this->realvals[$key] = $tmpfk;
	  $this->reset[$key] = "TRUE";
	  return;
	} else {
	  throw new exception("%CLASSNAME%::_set() Forein key for $mytype not set before adding.");
	}
      } else{
	throw new exception("%CLASSNAME%::_set() Attempt to set foreign key without a db object.");
      }
    }

    throw new exception("%CLASSNAME%::__set() key '$key' is not a settable attribute.");
  }

  public function set_null($key) {
    if(isset(%CLASSNAME%::$changevars[$key])) {
      unset($this->realvals[$key]);
      $this->reset[$key] = "NULL";
    }
    if(isset(%CLASSNAME%::$FKvars[$key])) {
      unset($this->realvals[$key]);
      $this->reset[$key] = "NULL";
    }

  }

  public function getPK() {
    return $this->realvals[%CLASSNAME%::$PK];
  }

  public static function search($key,$val,$compare = "=") {
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

  private static function _search($keys,$compvals,$compares) {
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

  public function save() {
    $this->beginTransaction();

    // Check if this is a new insert or an update
    if($this->realvals[%CLASSNAME%::$PK] > 0) {
      // Update
      foreach (%CLASSNAME%::$updatevars as $canchange) {
	// Update any field that has been explicitly changed or
	// any field that is listed as a global update field.
	if($this->reset[$canchange] == "TRUE" ||
	   isset(%CLASSNAME%::$updateglobals[$canchange])) {

	  $sth = %SCHEMANAME%::$dbh->prepare("UPDATE %TABLENAME% SET $canchange = :$canchange WHERE ".%CLASSNAME%::$PK." = :".%CLASSNAME%::$PK );

	  // Check if we get the value from a global or from what was set.
	  if(isset(%CLASSNAME%::$updateglobals[$canchange])) {

	    $sth->execute([ ":$canchange" => %SCHEMANAME%::$globalvars[%CLASSNAME%::$updateglobals[$canchange]],
			    ':'.%CLASSNAME%::$PK => $this->realvals[%CLASSNAME%::$PK] ]);

	  } else {
	    
	    $sth->execute([ ":$canchange" => $this->realvals[$canchange],
			    ':'.%CLASSNAME%::$PK => $this->realvals[%CLASSNAME%::$PK] ]);
	  }

	  // Handle error?
	  $this->reset[$canchange] = "FALSE";

	  // If the reset var is set to "NULL" then null it out.
	} elseif ($this->reset[$canchange] == "NULL") {
	  // This is for unsetting something to NULL.
	  $sth = %SCHEMANAME%::$dbh->prepare("UPDATE %TABLENAME% SET $canchange = NULL WHERE ".%CLASSNAME%::$PK." = :".%CLASSNAME%::$PK );
	  $sth->execute([ ':'.%CLASSNAME%::$PK => $this->realvals[%CLASSNAME%::$PK] ]);
	  $this->reset[$canchange] = "FALSE";

	}
      }
      foreach (%CLASSNAME%::$autoupdatedate as $aud => $true) {
	$sth = %SCHEMANAME%::$dbh->prepare("UPDATE %TABLENAME% SET $aud = CURDATE() WHERE ".%CLASSNAME%::$PK." = :".%CLASSNAME%::$PK );
	$sth->execute([ ':'.%CLASSNAME%::$PK =>
			$this->realvals[%CLASSNAME%::$PK] ]);
      }

      foreach (%CLASSNAME%::$autoupdatetimestamp as $aut => $true) {
	$sth = %SCHEMANAME%::$dbh->prepare("UPDATE %TABLENAME% SET $aut = NOW() WHERE ".%CLASSNAME%::$PK." = :".%CLASSNAME%::$PK );
	$sth->execute([ ':'.%CLASSNAME%::$PK =>
			$this->realvals[%CLASSNAME%::$PK] ]);
      }

    }else{
      // New Insert
      //print "Inserting...\n";
      $inserts = array();
      $tmpparams = array();

      foreach(%CLASSNAME%::$createglobals as $key => $val) {
	$this->realvals[$key] = %SCHEMANAME%::$globalvars[$val];
	$reset[$key] = "TRUE";
      }

      foreach($this->realvals as $key=>$value) {
	if($key != %CLASSNAME%::$PK) {
	  $inserts[] = $key;
	  $tmpparams[":".$key] = $value;
	  $reset[$key] = "FALSE";
	}
      }

      $insertfields = implode(",",$inserts);
      $insertplaces = ":".implode(", :",$inserts);

      foreach(%CLASSNAME%::$autocreatedate as $acd => $true) {
	$insertfields .= ",$acd";
	$insertplaces .= ", CURDATE()";
      }

      foreach(%CLASSNAME%::$autocreatetimestamp as $act => $true) {
	$insertfields .= ",$act";
	$insertplaces .= ", NOW()";
      }

      $sth = %SCHEMANAME%::$dbh->prepare("INSERT INTO %TABLENAME% (".$insertfields.")
      VALUES (".$insertplaces.")");

      $sth->execute($tmpparams);
      // Handle error?

      $this->realvals[%CLASSNAME%::$PK] = %SCHEMANAME%::$dbh->lastInsertId();
      // Handle error?

    }

    $this->commit();

  }

  public function delete() {
    if($this->realvals[%CLASSNAME%::$PK] == 0) {
      return;
    } else {
      $sth = %SCHEMANAME%::$dbh->prepare("DELETE FROM %TABLENAME%
                                  WHERE ".%CLASSNAME%::$PK." = :PK");
      $sth->execute([ ':PK' => $this->realvals[%CLASSNAME%::$PK] ]);

      $this->realvals[%CLASSNAME%::$PK] = 0;

    }
  }
}
