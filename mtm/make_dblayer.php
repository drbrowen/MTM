<?php

class xx_xml {

  // XML parser variables
  var $parser;
  var $data = array();
  var $stack = array();
  var $curattr;
  var $attrstack = array();
  var $keys;
  var $path;
  var $state = 0;
   
    // either you pass url atau contents.
    // Use 'url' or 'contents' for the parameter
    var $type;

    // function with the default parameter value
    function __construct($url='', $type='url') {
      $this->type = $type;
      $this->url  = $url;
      $this->parse();
    }
   
    // parse XML data
    function parse()
    {
        $data = '';
        $this->parser = xml_parser_create();
        xml_set_object($this->parser, $this);
        xml_set_element_handler($this->parser, 'startXML', 'endXML');
        xml_set_character_data_handler($this->parser, 'charXML');

        xml_parser_set_option($this->parser, XML_OPTION_CASE_FOLDING, false);

        if ($this->type == 'url') {
            // if use type = 'url' now we open the XML with fopen
           
            if (!($fp = @fopen($this->url, 'rb'))) {
                $this->error("Cannot open {$this->url}");
            }

            while (($data = fread($fp, 8192))) {
                if (!xml_parse($this->parser, $data, feof($fp))) {
                    $this->error(sprintf('XML error at line %d column %d',
                    xml_get_current_line_number($this->parser),
                    xml_get_current_column_number($this->parser)));
                }
            }
        } else if ($this->type == 'contents') {
            // Now we can pass the contents, maybe if you want
            // to use CURL, SOCK or other method.
            $lines = explode("\n",$this->url);
            foreach ($lines as $val) {
                if (trim($val) == '')
                    continue;
                $data = $val . "\n";
                if (!xml_parse($this->parser, $data)) {
                    $this->error(sprintf('XML error at line %d column %d',
                    xml_get_current_line_number($this->parser),
                    xml_get_current_column_number($this->parser)));
                }
            }
        }
    }

    function startXML($parser, $name, $attr) {
      $this->stack[] = $this->data;
      $this->data = array();
      $this->attrstack[] = $this->curattr;
      $this->curattr = $attr;
    }

    function endXML($parser, $name) {
      $tmp = array_pop($this->stack);
      $tmp[$name][] = $this->data;
      $this->data = $tmp;

      $tmp = array_pop($this->attrstack);
      $tmp[$name][] = $this->curattr;
      $this->curattr = $tmp;
    }

    function charXML($parser, $data) {
      if (trim($data) != '') {
	  $this->data = trim(str_replace("\n", '', $data));
      }
    }

    function error($msg)    {
        echo "<div align=\"center\">
            <font color=\"red\"><b>Error: $msg</b></font>
            </div>";
        exit();
    }
}
$contents = new xx_xml($argv[1],'url');
//print "DB Name = ".$contents->data['dbconfig|schema|name']['data'][0]."\n";
//print "Tables = " . implode(', ',$contents->data['dbconfig|schema|tables|table|dbname']['data'])."\n";

#print var_dump($contents->data);
#print var_dump($contents->curattr);
#exit(0);


// Make sure we have a valid schema XML file before proceeding.
if(!isset($contents->data['dbconfig'][0]['schema'])) {
  throw new exception("The config file didn't specify any schemata");
}

foreach($contents->data['dbconfig'][0]['schema'] as $schema) {
  print "Found a schema with name ".$schema['name'][0]."\n";
  $cerl = error_reporting();
  error_reporting(0);
  $ret = stat($schema['name'][0]);
  error_reporting($cerl);
  if($ret) {
    throw new exception("Directory for ".$schema['name'][0]." already exists.");
  }
}


foreach($contents->data['dbconfig'][0]['schema'] as $schemakey => $schema) {
  mkdir($schema['name'][0]);
  $sfh = fopen($schema['name'][0]."/".strtolower($schema['name'][0]).".php","w");
  if(!$sfh) {
    throw new exception("Can't open filename.");
  }
  $tableincludes = "";

  foreach($schema['tables'][0]['table'] as $tablekey => $table) {
    print "  Procesing class ".$table['classname'][0]." for table ".$table['dbname'][0]."\n";
    if(!isset($table['primarykey'][0])) {
      throw new exception("No primary key for ".$table['dbname'][0]);
    }

    // Pull the foreign keys out of the config
    $fkarray = array();
    $createglobals = array();
    $updateglobals = array();
    if(isset($table['fkattributes'][0]['fkattribute'])) {
      foreach($table['fkattributes'][0]['fkattribute'] as $key => $fks) {
	$fkarray[$fks['fk'][0]] = $fks['pt'][0];
	$attrlist = $contents->curattr['dbconfig'][0]['schema'][$schemakey]['tables'][0]['table'][$tablekey]['fkattributes'][0]['fkattribute'][$key];
	if(count($attrlist)>0) {
	  if(isset($attrlist['updateglobal'])) {
	    $updateglobals[] = "'".$fks['fk'][0]."' => '".$attrlist['updateglobal']."'";
	  }
	  if(isset($attrlist['createglobal'])) {
	    $createglobals[] = "'".$fks['fk'][0]."' => '".$attrlist['createglobal']."'";
	  }
	}
      }
    }
    $attrarray = array();
    $autoupdatedate = array();
    $autoupdatetimestamp = array();
    $autocreatedate = array();
    $autocreatetimestamp = array();
    if(isset($table['attributes'][0]['attr'])) {
      foreach($table['attributes'][0]['attr'] as $key => $attr) {
	$attrarray[] = $attr;
	$attrlist = $contents->curattr['dbconfig'][0]['schema'][$schemakey]['tables'][0]['table'][$tablekey]['attributes'][0]['attr'][$key];
	if(count($attrlist)>0) {

	  if(isset($attrlist['updateglobal'])) {
	    $updateglobals[] = "'$key' => '".$attrlist['updateglobal']."'";
	  }

	  if(isset($attrlist['createglobal'])) {
	    $updateglobals[] = "'$key' => '".$attrlist['createglobal']."'";
	  }

	  if(isset($attrlist['type'])) {
	    switch($attrlist['type']) {
	    case "autocreatedate":
	      $autocreatedate[] = $attr;
	      break;

	    case "autoupdateedate":
	      $autoupdatedate[] = $attr;
	      break;

	    case "autocreatetimestamp":
	      $autocreatetimestamp[] = $attr;
	      break;

	    case "autoupdatetimestamp":
	      $autoupdatetimestamp[] = $attr;
	      break;
	    }
	  }
	}
      }
    }

    $fh = fopen($schema['name'][0]."/".strtolower($table['classname'][0]).".php","w");

    if(!$fh) {
      throw new exception("Can't open filename.");
    }

    make_table($schema['name'][0],
	       $table['classname'][0],
	       $table['dbname'][0],
	       $attrarray,
	       $fkarray,
	       $table['primarykey'][0],
	       $updateglobals,
	       $createglobals,
	       $autocreatedate,
	       $autocreatetimestamp,
	       $autoupdatedate,
	       $autoupdatetimestamp,
	       $fh);

    fclose($fh);
    $tableincludes .= 'include_once "'.strtolower($table['classname'][0]).'.php";'."\n";
  }

  foreach($schema['views'][0]['view'] as $viewkey => $view) {
    print "  Procesing class ".$view['classname'][0]." for view ".$view['dbname'][0]."\n";
    // Pull the foreign keys out of the config
    $fkarray = array();
    if(isset($view['fkattributes'][0]['fkattribute'])) {
        foreach($view['fkattributes'][0]['fkattribute'] as $key => $fks) {
            $fkarray[$fks['fk'][0]] = $fks['pt'][0];
            $attrlist = $contents->curattr['dbconfig'][0]['schema'][$schemakey]['views'][0]['view'][$viewkey]['fkattributes'][0]['fkattribute'][$key];
            
            $attrarray = array();
            if(isset($view['attributes'][0]['attr'])) {
                foreach($view['attributes'][0]['attr'] as $key => $attr) {
                    $attrarray[] = $attr;
                    $attrlist = $contents->curattr['dbconfig'][0]['schema'][$schemakey]['views'][0]['view'][$viewkey]['attributes'][0]['attr'][$key];
                    if(count($attrlist)>0) {
                        
                    }
                }
            }
        }
    }

    $fh = fopen($schema['name'][0]."/".strtolower($view['classname'][0]).".php","w");

    if(!$fh) {
        throw new exception("Can't open filename.");
    }

    make_view($schema['name'][0],
	       $view['classname'][0],
	       $view['dbname'][0],
	       $attrarray,
	       $fkarray,
	       $fh);

    fclose($fh);
    $tableincludes .= 'include_once "'.strtolower($view['classname'][0]).'.php";'."\n";
  }

  $globalvars = array();
  foreach($schema['globals'][0]['global'] as  $var) {
    $globalvars[] = "'".$var['name'][0]."' => '".$var['default'][0]."'";
  }

  $globalvarstring = implode(", ",$globalvars);


  make_schema($schema['name'][0],$tableincludes,$globalvarstring,$sfh);
  fclose($sfh);
}

function make_schema($schemaname,$tableincludes,$globalvars,$fh) {
  $input = file("SCHEMA_TEMPLATE.php");

  $originals = array( '%SCHEMANAME%' , '%INCLUDES%', '%GLOBALVARS%');
  $subs = array($schemaname,$tableincludes,$globalvars);
  foreach ($input as $ord => $line) {
    fwrite($fh,str_replace($originals,$subs,$line));
  }
  
  
}

function make_table($schemaname,$classname,$tablename,$changevars,$fkvars,$pk,
		    $updateglobals,$createglobals,
		    $autocreatedate,$autocreatetimestamp,$autoupdatedate,$autoupdatetimestamp,$fh) {

  $varstring = "  private \$$pk;\n";
  $allvars[ $pk ] = "'$pk' => 1";

  foreach ($changevars as $var) {
    $varstring .= "  private \$$var;\n";
    $allvars[ $var ] = "'$var' => 1";
    $changevarstrings[ $var ] = "'$var' => 1";
  }

  $fkvarstring = array();
  foreach ($fkvars as $var => $val) {
    $varstring .= "  private \$$var;\n";
    $allvars[ $var ] = "'$var' => 1";
    array_push($fkvarstring, " '$var' => '$val'");
  }

  $allvarstring = implode (',',$allvars);
  $fksubstring = implode(',',$fkvarstring);
  if(isset($changevarstrings)) {
      $changevarstring = implode(',',$changevarstrings);
  } else {
      $changevarstring = '';
  }

  $updateglobalstring = implode(",",$updateglobals);
  $createglobalstring = implode(",",$createglobals);

  if(count($autocreatedate)>0) {
    $acdstring = "'".implode("' => 1, ",$autocreatedate)."' => 1";
  } else {
    $acdstring = "";
  }

  if(count($autocreatetimestamp)>0) {
    $actstring = "'".implode("' => 1, ",$autocreatetimestamp)."' => 1";
  } else {
    $actstring = "";
  }
  if(count($autoupdatedate)>0) {
    $audstring = "'".implode("' => 1, ",$autoupdatedate)."' => 1";
  } else {
    $audstring = "";
  }
  if(count($autoupdatetimestamp)>0) {
    $autstring = "'".implode("' => 1, ",$autoupdatetimestamp)."' => 1";
  } else {
    $autstring = "";
  }


  //print "$fksubstring\n";
  //exit(0);

  $input = file("TABLE_TEMPLATE.php");

  $originals = array( '%SCHEMANAME%', '%CLASSNAME%', '%TABLENAME%','%VARDEFS%', '%PK%', '%FKVARS%','%ALLVARS%','%CHANGEVARS%', '%UPDATEGLOBALS%', '%CREATEGLOBALS%', '%AUTOCREATEDATE%', '%AUTOCREATETIMESTAMP%', '%AUTOUPDATEDATE%', '%AUTOUPDATETIMESTAMP%' );
  $subs = array($schemaname,$classname,$tablename,$varstring,$pk,$fksubstring,$allvarstring,$changevarstring,$updateglobalstring,$createglobalstring,$acdstring,$actstring,$audstring,$autstring);
  foreach ($input as $ord => $line) {
    fwrite($fh,str_replace($originals,$subs,$line));
  }
  
}

function make_view($schemaname,$classname,$viewname,$attributes,$fkvars,$fh) {

    $varstring = '';
    
  foreach ($attributes as $var) {
    $varstring .= "  private \$$var;\n";
    $allvars[ $var ] = "'$var' => 1";
  }

  $fkvarstring = array();
  foreach ($fkvars as $var => $val) {
    $varstring .= "  private \$$var;\n";
    $allvars[ $var ] = "'$var' => 1";
    array_push($fkvarstring, " '$var' => '$val'");
  }

  $allvarstring = implode (',',$allvars);
  $fksubstring = implode(',',$fkvarstring);
  if(isset($attributestrings)) {
      $attributestring = implode(',',$attributestrings);
  } else {
      $attributestring = '';
  }

  //print "$fksubstring\n";
  //exit(0);

  $input = file("VIEW_TEMPLATE.php");

  $originals = array( '%SCHEMANAME%', '%CLASSNAME%', '%TABLENAME%','%VARDEFS%', '%FKVARS%','%ALLVARS%' );
  $subs = array($schemaname,$classname,$viewname,$varstring,$fksubstring,$allvarstring);
  foreach ($input as $ord => $line) {
    fwrite($fh,str_replace($originals,$subs,$line));
  }
  
}
?>
