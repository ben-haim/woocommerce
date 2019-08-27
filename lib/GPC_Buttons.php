<?php

class GPC_Buttons { 
   function __construct() {   
}

function GPC_getButtons(){
   
   
   $button_url = 'https://gatepay.co/resources/paymentButtons';
   $ch = curl_init();
   curl_setopt($ch, CURLOPT_URL, $button_url);
   curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
   curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
   $result = curl_exec($ch);
   curl_close ($ch);
   return $result;
  
}


}

?>
