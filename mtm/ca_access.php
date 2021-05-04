<?php

include_once '/etc/makemunki/readconfig.php';

class CA_Access {
    private static $mdh = 0;
    private $gconf;

    public function get_mdh() {
        return CA_Access::$mdh;
    }
    
    public function __construct($handle = '') {
        $this->gconf = new ReadConfig('/etc/makemunki/config');

        set_include_path(get_include_path() . ':'.$this->gconf->main->codehome.'/MunkiCert');

        include_once 'munkicert.php';

        if(CA_Access::$mdh == 0) {
            if($handle === '') {
                $params = array( 'host' => $this->gconf->db->dbhost,
                'db'   => $this->gconf->db->dbname,
                'user' => $this->gconf->db->dbuser,
                'pass' => $this->gconf->db->dbpass);

                CA_Access::$mdh = new MunkiCert($params);

            } else {
                CA_Access::$mdh = $handle;
            }            

            date_default_timezone_set($this->gconf->main->timezone);

        }
    }

    public function gen_private_key($bits = 0) {
        if($bits == 0) {
            $bits = $this->gconf->CA->certbits;
        }

        return openssl_pkey_new(array('private_key_bits'=>(int)$bits));
        
    }

    public function gen_certificate($index) {
        $certs = T_Certificate::Search('ID',$index);
        if(count($certs)!=1) {
            throw new exception("Can't find index number.");
        }
        $cert = $certs[0];

        if(isset($cert->certificate) || $cert->status === 'V') {
            return;
        }
        
        $cacertraw = file_get_contents($this->gconf->CA->cacertpath);
        if(!($cacertraw)) {
            throw new exception("Can't open CA Certificate.");
        }
        $cacert = openssl_x509_read($cacertraw);

        if(!($cacert)) {
            throw new exception("Can't parse CA Certificate: ".openssl_error_string());
        }

        $cakey = openssl_pkey_get_private("file://".$this->gconf->CA->cakeypath);

        if(!($cakey)) {
            throw new exception("Can't open private key: ".openssl_error_string());
        }

        $thesubject = $cert->subject;
        print "Subject = '".$thesubject."'\n";
        if($thesubject === "") {
            throw new exception("Certificate needs a subject");
        }

        if(substr($thesubject,0,1) !== '/') {
            throw new exception("Certificate subject must start with a '/'");
        }
        
        $subjectparts = explode("/",$thesubject);
        array_shift($subjectparts);
        if(count($subjectparts)<2) {
            throw new exception("Certificate subject bad form.");
        }
        $dn = [];
        foreach($subjectparts as $subjectpart) {
            $vals = explode('=',$subjectpart,2);
            if(!isset($vals[1])) {
                throw new exception("Certificate subject bad form.");
            }
            $dn[$vals[0]] =  $vals[1];
        }
        
        $check = T_Certificate::Search(['subject','status'],[$cert->subject,'V'],['=','=']);
        if(count($check)>1 || (count($check) == 1 && $check[0]->ID != $cert->ID)) {
            throw new exception("Certificate subject already exists with another ID.");
        }

        if(!isset($cert->privatekey)) {
            $privkeyraw = $this->gen_private_key();
            openssl_pkey_export($privkeyraw,$privkey);
            $cert->privatekey = $privkey;
        } else {
            $privkeyraw = openssl_pkey_get_private($cert->privatekey);
        }

        ob_start();
        print var_dump($dn);
        $debug = ob_get_clean();
        file_put_contents("/tmp/args",$debug);        

        $csrraw = openssl_csr_new($dn,$privkeyraw);
        if(!$csrraw) {
            throw new exception("Can't create CSR: ".$openssl_error_string());
        }

        openssl_csr_export($csrraw,$csr);
        $cert->signrequest = $csr;

        $certconfig = array('config'=>$this->gconf->CA->sslconffile,'config_section_name'=>$this->gconf->CA->cadefaultsection);       

        ob_start();
        var_dump($csrraw);
        var_dump($cacert);
        var_dump($cakey);
        var_dump($this->gconf->CA->cadays);
        var_dump($certconfig);
        var_dump($cert->ID);
        $rawdata = ob_get_clean();
        file_put_contents("/var/storage/phpsessions/certparams",$rawdata);
        
        $rawcert = openssl_csr_sign($csrraw,$cacert,$cakey,$this->gconf->CA->cadays,$certconfig,$cert->ID);

        ob_start();
        var_dump($csrraw);
        var_dump($cacert);
        var_dump($cakey);
        var_dump($this->gconf->CA->cadays);
        var_dump($certconfig);
        var_dump($cert->ID);
        var_dump($rawcert);
        print openssl_error_string();
        $rawdata = ob_get_clean();
        file_put_contents("/var/storage/phpsessions/certparams",$rawdata);

        $vals = openssl_x509_export($rawcert,$pem);
        $cert->certificate = $pem;
        $cookedcert = openssl_x509_read($pem);
        $cert->hash = openssl_x509_fingerprint($cookedcert);
        $cert->status = 'V';
        $parsevals = openssl_x509_parse($rawcert);

        $cert->valid_from = date('Y-m-d H:i:s',$parsevals['validFrom_time_t']);
        $cert->valid_until = date('Y-m-d H:i:s',$parsevals['validTo_time_t']);
        print $cert->valid_from."\n";
        $cert->save();
        
        return;
    }

    public function revoke_certificate($index) {
        $cert = T_Certificate::Search('ID',$index);
        if(count($cert) != 1) {
            throw new exception("Cannot find certificate.");
        }

        $cert[0]->status = 'R';
        $cert[0]->save();
    }

    public function retrieve_fingerprint($index) {
        $cert = T_Certificate::Search('ID',$index);
        if(count($cert) != 1) {
            throw new exception("Cannot find certificate.");
        }
        $cookedcert = openssl_x509_read($cert[0]->certificate);
        return openssl_x509_fingerprint($cookedcert);
    }
}
