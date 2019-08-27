<?php



require_once("includes.php");
if(!isset($_REQUEST["action"]))
	die("No action was passed");
switch($_REQUEST["action"])
{
	
	case "check_order":check_order();break;
	case "editCrypto":editCrypto();break;
}
function editCrypto()
{
	$order_id=$_REQUEST["order_id"];
	$account_id=$_REQUEST["account_id"];
	$token_id=$_REQUEST["token_id"];
	$amount=$_REQUEST["amount"];
	$network=$_REQUEST["crypto"];
	$currency=$_REQUEST["currency"];
	global $db;
	$trans=$db->db_selectOne("_gatepay_checkout_transactions","id,transaction_status,transaction_id","order_id='".$order_id."'  order by id desc");
	if($trans!==false)
	{
		
		if($trans["transaction_status"]=="paid")
		{
			die('{"result":"success","payment_protocol_uri":"bitcoin:paid?amount=xxx","invoice_id":'.$trans["transaction_id"].'}');
		}
		$url="https://gatepay.xyz/api/check.php?invoice_id=".$trans["transaction_id"];
		$res=runCurl_Json($url);
		$invoiceData = json_decode($res);
		
		if(!isset($invoiceData->result) || $invoiceData->result!="success")
			die($res);
			
		if($invoiceData->payment_status=="confirmed")
		{
			$db->db_query("update _gatepay_checkout_transactions set transaction_status='paid' where order_id=".$order_id);	
			die('{"result":"success","payment_protocol_uri":"bitcoin:paid?amount=xxx","invoice_id":'.$trans["transaction_id"].'}');
		}
	}
	$url = "https://gatepay.xyz/api/request.php?account_id=".$account_id."&token_id=".$token_id."&network=".$network."&currency_fiat=".$currency."&amount_fiat=".urlencode($amount);

	$res=runCurl_Json($url);
	$invoiceData = json_decode($res);
	if(!isset($invoiceData->result) || $invoiceData->result!="success")
		die($res);
	gatepay_checkout_insert_order_note($order_id,$invoiceData->invoice_id);
	die($res);
}

function gatepay_checkout_insert_order_note($order_id = null, $transaction_id = null)
{
    global $db;
	
	$trans=$db->db_selectOne("_gatepay_checkout_transactions","id,transaction_status,transaction_id","order_id='".$order_id."' order by id desc");
	$table_name = '_gatepay_checkout_transactions';
	if($trans!==false)
	{
		if($trans["transaction_status"]!="paid")
		{
			
			$url="https://gatepay.xyz/api/check.php?invoice_id=".$trans["transaction_id"];
			$res=runCurl_Json($url);
			$invoiceData = json_decode($res);
			
			if(!isset($invoiceData->result) || $invoiceData->result!="success")
				die($res);
				
			if($invoiceData->payment_status=="confirmed")
			{
				$db->db_query("update _gatepay_checkout_transactions set transaction_status='paid' where order_id=".$order_id);	
				
			}
		else
		$db->db_query("update ".$table_name." set transaction_id='".$transaction_id."',transaction_status='new', date_added=now() where order_id=".$order_id);
		}
		//$db->db_update($table_name, array('transaction_id'=>$transaction_id,'date_added'=>"now()"), array('order_id' => $order_id));
	}
	else
	{
		
		
			$db->db_insert(
				$table_name,
				array(
					'order_id' => $order_id,
					'transaction_id' => $transaction_id,
				)
			);
		
		
	}

}
function check_order()
{
	global $db;
	if(!isset($_REQUEST["order_id"]))
		die("No order_id was passed");
	$tran=$db->db_selectOne("_gatepay_checkout_transactions","id,transaction_status,transaction_id","order_id=".$_REQUEST["order_id"]." order by id desc");
	
	if($tran===false)
		die("new");
	if($tran["transaction_status"]!="paid")
		gatewapyjob_function2($tran["transaction_id"]);
	die($tran["transaction_status"]);
}

function gatewapyjob_function2($tran_id) {
	
	global $db;
	
	$url="https://gatepay.xyz/api/check.php?invoice_id=".$tran_id;
	$res=runCurl_Json($url);
	$invoiceData = json_decode($res);
	
	if(!isset($invoiceData->result) || $invoiceData->result!="success")
		die($res);
		
	if($invoiceData->payment_status=="confirmed")
	{
		$db->db_query("update _gatepay_checkout_transactions set transaction_status='paid' where transaction_id=".$tran_id);		
		die("paid");
	}
	$db->db_query("update _gatepay_checkout_transactions set transaction_status='new' where transaction_id=".$tran_id);	
	die("new");
	
	
	
}
function runCurl_Json($url)
{

	
	$ch = curl_init();
	curl_setopt($ch, CURLOPT_URL, $url);
	
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$result = curl_exec($ch);
	curl_close($ch);
	
	
	return $result;
}
?>
