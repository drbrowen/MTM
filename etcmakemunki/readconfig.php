<?php

class ReadConfig {

    private $vars;

    public function __construct($filename) {
        if(is_array($filename)) {
            $this->vars = $filename;
            return;
        }

        if(!(file_exists($filename))) {
            throw new exception("Can't find config file '".$filename."'");
        }

        $this->vars = parse_ini_file($filename,true);
        if(!($this->vars)) {
            throw new exception("Can't parse INI file '".$filename."'");
        }

        
    }

    public function __get($key) {
        if(isset($this->vars[$key])) {
            if(is_array($this->vars[$key])) {
                $checks = array_keys($this->vars[$key]);
                foreach($checks as $check) {
                    if(!is_numeric($check)) {
                        return new ReadConfig($this->vars[$key]);
                    }
                }
                return $this->vars[$key];
            } else {
                return $this->vars[$key];
            }
        }
        throw new exception("I can't give you a value that wasn't in the config file!");
    }

    public function __isset($key) {
        if(isset($this->vars[$key])) {
            return true;
        }
        return false;
    }
 
}

