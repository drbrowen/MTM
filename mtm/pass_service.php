<?php

class Pass_Service {

  private $iv;
  // Change this for each application.
  private $appkey;
  private $method = 'aes-256-cbc';

  public function __construct($appkey) {
    $this->appkey = $appkey;
    $rawiv = file("/sys/class/net/eth0/address",FILE_IGNORE_NEW_LINES);

    $this->iv = substr($rawiv[0]."489ddfskeksigvie",0,16);
    
  }

  public function pass_from_cipher($cipher) {
    $raw = base64_decode($cipher);
    return openssl_decrypt($raw,$this->method,$this->appkey,true,$this->iv);
  }

  public function cipher_from_pass($pass) {
    $raw = openssl_encrypt($pass,$this->method,$this->appkey,true,$this->iv);
    return base64_encode($raw);
  }


}
