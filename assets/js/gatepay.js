var checkOrder=false;
var isSlectedCrypto=false;
function select_crypto(crypt)
	{
		if(isSlectedCrypto)
			return false;
		isSlectedCrypto=true;
		jQuery(".loadingPayGate").hide();
		jQuery("#loading_"+crypt).show();
		jQuery('#qrCode2').html("");
		var xhr; 
		try {  xhr = new ActiveXObject('Msxml2.XMLHTTP');   }
		catch (e) 
		{
			try {   xhr = new ActiveXObject('Microsoft.XMLHTTP'); }
			catch (e2) 
			{
			   try {  xhr = new XMLHttpRequest();  }
			   catch (e3) {  xhr = false;   }
			 }
		}
	  
		xhr.onreadystatechange  = function() 
		{ 
		   if(xhr.readyState  == 4)
		   {
				if(xhr.status  == 200) 
				{
					nbSeconds_rest=nbMunitesExpire*60;
					jQuery(".loadingPayGate").hide();
					var resp=xhr.responseText.trim();
					/*{"result":"success","payment_protocol_uri":"bitcoin:1EJ19gH17K4upEephwJ4217bPaSop7fxL2?amount=0.003919","invoice_id":609382179802}*/
					try {
					
							json=JSON.parse(resp);
							if(!json.hasOwnProperty('result')){
								alert("error select_crypto :"+resp);
								return false;
								
							}
							if(json.result!="success")
							{
								alert("error select_crypto :"+resp);
								return false;
							}
							var cryptoCode=json.payment_protocol_uri;
							
							jQuery('#hrefqrCode').attr("href",cryptoCode);
							var tab=cryptoCode.split('?amount=');
							var fi=tab[0];
							tab2=fi.split(':');
							document.getElementById("msgInput").value=tab2[1];
							jQuery('#amountID').html(tab[1]);
							jQuery('#cryptoID').html(crypt);
							
							jQuery('#qrCode2').qrcode(cryptoCode);
							jQuery('#imgBarCode').attr("src",pluginURL+"assets/images/"+crypt+".png");
							
							jQuery('#copyAddress').hide();
							jQuery('#msgPayBitcoin').html(checkout_msg.replace("BTC",crypt));
							click_header("payment");
							checkOrder=true;
							setTimeout(function(){ isSlectedCrypto=false; }, 1000);
							timerExipired();
							//checkOrderStatus();
					} catch (e) {
						alert(resp);
					}
						
					
					
				}
				else
				{
					setTimeout(function(){ isSlectedCrypto=false; }, 1000);
					
					alert("Error code " + xhr.statusText);
					jQuery(".loadingPayGate").hide();
				}
			}
		}; 
	 
	   xhr.open("GET", checkOrderURL+"?action=editCrypto&currency="+myCurreny+"&amount="+myPrice+"&account_id="+accountID+"&token_id="+keyID+"&crypto="+crypt+"&order_id="+order_id);
	  
	   xhr.send(); 
		
	}
	function backGatePay(crypt)
	{
		checkOrder=false;
		jQuery(".loadingPayGate").hide();
		click_header("method");
	}
	function click_header(page)
	{
		
		nbSeconds_rest=nbSeconds_rest0;
		jQuery('.pagGatepay').hide("slow");
		jQuery('.lineHeaderGatepay').hide("slow");
		
		jQuery('.stepValid').hide("slow");		
		if(page=="payment")
		{
			jQuery("#HidevalidImgGatepay").hide();
			jQuery("#validImgmethod").show();
		}
		else
			jQuery("#HidevalidImgGatepay").show();
			
		jQuery("#line_"+page).show("slow");
		jQuery("#"+page+"Gatepay").show("slow");
		
	}
	function btc_address_click2()
	{
		
	  	document.getElementById("msgInput").select();	
	  	document.execCommand("copy");
		document.getElementById("copyAddress").style.display = "block";

	}
	function timerExipired()
	{
		
	  	if(!checkOrder)
			return false;
		var minutes = Math.floor(nbSeconds_rest / 60);
		var seconds = nbSeconds_rest - minutes * 60;
		if(minutes<10)
		minutes="0"+minutes;
		if(seconds<10)
		seconds="0"+seconds;
		document.getElementById("nbLeft").innerHTML = minutes+":"+seconds;
		nbSeconds_rest--;
		if(nbSeconds_rest>0)
			setTimeout(timerExipired, 1000);
		else
		{
			document.getElementById("orderLeft").innerHTML ="<strong style='color:#ff0000'>Order Expired</strong>";
			setTimeout(function(){ document.location=checkoutURL; }, 4000);
		}
		
		
	}
	
	function checkOrderStatus()
	{
		
	  	var xhr; 
		try {  xhr = new ActiveXObject('Msxml2.XMLHTTP');   }
		catch (e) 
		{
			try {   xhr = new ActiveXObject('Microsoft.XMLHTTP'); }
			catch (e2) 
			{
			   try {  xhr = new XMLHttpRequest();  }
			   catch (e3) {  xhr = false;   }
			 }
		}
	  
		xhr.onreadystatechange  = function() 
		{ 
		   if(xhr.readyState  == 4)
		   {
				if(xhr.status  == 200) 
				{
					var resp=xhr.responseText.trim();
					
					//document.getElementById("orderStt").innerHTML =resp;
					
					if(resp=="paid")
					{
						document.location=thank_you;
						return false;
					}
					if(resp=="expired")
					{
						/*alert("Order expired");
						setTimeout(function(){ document.location=shopURL; }, 3000);*/
						
						document.getElementById("orderLeft").innerHTML ="<strong style='color:#ff0000'>Order Expired</strong>";
						return false;
					}
					if(resp=="new")
					{
						
						setTimeout(checkOrderStatus, 5000);
						return false;
					}
					alert("ERROR get order status:"+xhr.responseText);
				}
				else
				{
					
					alert("Error : " + xhr.responseText);
				}
			}
		}; 
	 
	   xhr.open("GET", checkOrderURL+"?action=check_order&order_id="+order_id);
	  
	   xhr.send(); 
		
		
		
	}
	checkOrderStatus();
