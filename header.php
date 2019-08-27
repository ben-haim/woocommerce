

	<div class="rowHeaderGatepay" style="cursor:pointer" onclick="document.location='<?php echo get_home_url(); ?>/checkout/'">
        <img class="validImgGatepay" src="<?php echo GATEPAY__PLUGIN_URL;?>assets/images/valide.png"  />
        
        <div class="headerGatepayTit">
            1. SHIPPING
        </div>
        <div class="lineHeaderGatepay" style="display:none" /></div>
    </div>
    
    <div class="rowHeaderGatepay" >
        <img class="validImgGatepay stepValid" id="validImgmethod" style="display:none" src="<?php echo GATEPAY__PLUGIN_URL;?>assets/images/valide.png" />
        <div style="height:16px" id="HidevalidImgGatepay"></div>
        <div class="headerGatepayTit">
            2. METHOD
        </div>
        <div class="lineHeaderGatepay" id="line_method" /></div>
    </div>
    
    
    <div class="rowHeaderGatepay" style="width:34%">
        <div style="height:16px"></div>
        
        <div class="headerGatepayTit">
            3. PAYMENT
        </div>
        <div class="lineHeaderGatepay" id="line_payment" style="display:none" /></div>
    </div>
