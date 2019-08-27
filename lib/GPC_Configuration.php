<?php

class GPC_Configuration { 
   private $account_id;
   private $token;
   public $url;

   function __construct( $account_id, $token) {
	 
    $this->account_id = $account_id;
	$this->token = $token;
    
    $this->url = "https://gatepay.xyz/api/request.php ?account_id=".$this->account_id."&token_id=".$this->token."&network=BTC&currency_fiat=EUR&amount_fiat=";
   
}

function GPC_generateHash($data) {
    return hash_hmac('sha256', $data, sha1($this->GPC_getAPIToken()));
}

function GPC_checkHash($data,$hash_key) {
    if(hash_equals($hash_key,hash_hmac('sha256', $data, sha1($this->GPC_getAPIToken())))){
        return true;
    };
    return false;
}

function GPC_getAPIToken() {
    return $this->apiToken;
}

function GPC_getNetwork() {
    return $this->network;
}

public function GPC_getApiHostDev()
{
    return 'test.gatepay.com';
}

public function GPC_getApiHostProd()
{
    return 'gatepay.com';
}

public function GPC_getApiPort()
{
    return 443;
}

public function GPC_getInvoiceURL(){
    return $this->network.'/invoices';
}


} 
?>
