<p>Choose your preferred crypto option</p>
<?php
foreach($Network_Cryptocurrencies as $tit=>$val)
if($gatepay_checkout_options[$tit.'_GatePay']=="yes")
{
	?>
	<div class="listBitcoinsImg " onclick="select_crypto('<?php echo $tit;?>');"> 
  		<div style="float:left">
        <img style="margin:auto; height:53px" src="<?php echo GATEPAY__PLUGIN_URL;?>assets/images/<?php echo $tit;?>.png" title="<?php echo $val;?>" />
        
        </div>
        <div style="float:left; margin-left:15px; padding-top:10px">
        	<?php echo $val;?>
        </div>
         <div style="float:right; margin-right:15px; padding-top:15px; display:none;" id="loading_<?php echo $tit;?>" class="loadingPayGate">
        	<img src="<?php echo GATEPAY__PLUGIN_URL;?>assets/images/loading.gif" style="width:16px" />
        </div>
  	</div>
    <div style="height:10px; clear:both"></div>
	<?php
}
?>
