<?php

class GPC_Item { 
  function __construct($config,$item_params) {
      $this->token = $config->GPC_getAPIToken();
      $this->endpoint = $config->GPC_getNetwork();
      $this->item_params = $item_params;
      return $this->GPC_getItem();
}


function GPC_getItem(){
   $this->invoice_endpoint = $this->endpoint.'/invoices';
   $this->buyer_transaction_endpoint = $this->endpoint.'/invoiceData/setBuyerSelectedTransactionCurrency';
   $this->item_params->token = $this->token;
   return ($this->item_params);
}

}

?>
