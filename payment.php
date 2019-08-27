<div style="width:100%; text-align:center;">
	Amount: <strong><span id="amountID"></span></strong> (<span id="cryptoID"></span>)
</div>

<div  style="background:#FFF; border-radius: 15px; padding:10px; width:280px; margin:auto; margin-top:20px">
    <a href="#" id="hrefqrCode">
        <div style="width: 250px; height:250px;margin:auto; text-align:center;">
            <div id="qrCode2" ></div>
            <div style="position:relative; width:73px; height:73px; padding-left:0px;padding-top:0px; margin-top:-170px; margin-left:90px; z-index:99999;
 background:url('<?php echo GATEPAY__PLUGIN_URL;?>assets/images/circle.png')">
            <img src="" id="imgBarCode" style="width:70px" />
            </div>
        </div>
    </a>
</div>

<div style="margin:auto;  width:250px; margin-top:10px; text-align:center">
<span id="msgPayBitcoin" style="font-size:12px;"></span>
<input type="text" id="msgInput" onclick="btc_address_click2()" style="width:100%;border:solid 1px #06C; background-color:#fff; text-align:center" />
</div>
<div  id="copyAddress" style="display:none;margin:auto; width:80%; text-align:center"><small>Copied to clipboard</small></div>
<div style="width:100%;   margin-top:20px; text-align:center;">
	<div style="margin:auto; width:210px">
        <div style="float:left; margin-top:5px">
        <img src="<?php echo GATEPAY__PLUGIN_URL;?>assets/images/loading.gif" style="width:16px" />
        </div>
        <div style="float:left; margin-left:5px">
     	<span id='orderLeft'><strong id="nbLeft">xx</strong> <span style="color:#A9ACAE"> Awaiting payment...</span></span>
        </div>
     </div>
</div>
<div style=" clear:both"></div>
<div style="margin:auto;  margin-top:20px;  width:70px; cursor:pointer" onclick="backGatePay();"> &larr; BACK</div>
