<?php get_header();
echo '<link rel="stylesheet" type="text/css" href="'.GATEPAY__PLUGIN_URL.'assets/css/gatepay.css">';

?>
<div>
    <div class="comntainerGatPay">
        <div id="headerGatepay">
        
            <?php require_once("header.php");?>
         </div>
         <div style="clear:both"></div>
        <div id="contentGatepay">
            <div id="methodGatepay" class="pagGatepay">
            <?php require_once("method.php");?>
            </div>
             <div id="paymentGatepay" class="pagGatepay" style="display:none">
            <?php require_once("payment.php");?>
            </div>
            <div style="width:140px; margin:auto; margin-top:10px"><img src="<?php echo GATEPAY__PLUGIN_URL;?>assets/images/powered_by_gatepay.png" /></div>
        </div>
        
        
    </div>
    <div style="height:20px; clear:both"></div>
</div>
    <script>
	var nbSeconds_rest=60*<?php echo $nb_minutes;?>;    
	var nbSeconds_rest0=nbSeconds_rest;   
	var nbMunitesExpire=<?php echo $nb_minutes;?>;    
	var thank_you="<?php echo $params->redirectURL; ?>"; 
	
	var cartURL="<?php echo get_home_url(); ?>/cart/"; 
	var shopURL="<?php echo get_home_url(); ?>/shop/";
	var checkoutURL="<?php echo get_home_url(); ?>/checkout/";
	var order_id=<?php echo $order_id; ?>;
	var pluginURL="<?php echo GATEPAY__PLUGIN_URL;?>"
	var checkOrderURL="<?php echo GATEPAY__PLUGIN_URL;?>check_order.php"
	var mycode="";
	
	var URL_get_bitcoinVal="";
	var accountID="<?php echo $account_id; ?>";
	var keyID="<?php echo $keyID; ?>";
	var myCurreny="<?php echo $params->currency; ?>";
	var myPrice="<?php echo $params->price; ?>";
	var checkout_msg="<?php echo delete_spacesNew($checkout_msg); ?>";
	var checkOrderStarted=false;
	

    </script>
    


<?php
echo "<script type='text/javascript' src='".GATEPAY__PLUGIN_URL."assets/js/gatepay.js'></script>";
echo "<script type='text/javascript' src='".GATEPAY__PLUGIN_URL."assets/js/qrcodeGateapay.js'></script>";



?>
<?php get_footer();?>
