<?php

class Gebork_Collectorbank_Model_Api extends Mage_Core_Model_Abstract
{
	public function _construct()
    {
        parent::_construct();
        $this->_init('collectorbank/api');
    }
	
	public function getRedirectPageUri(){
		$getBaseUrl = Mage::getUrl();
		$redirectionUrl = $getBaseUrl."collectorbank/index/success";
		return $redirectionUrl;
	}
	
	
	public function getBusinessRedirectPageUri(){
		$getBaseUrl = Mage::getUrl();
		$redirectionUrl = $getBaseUrl."collectorbank/index/bsuccess";	
		return $redirectionUrl;
	}
	
	
	public function getMerchantTermsUri(){
		$getBaseUrl = Mage::getUrl();
		$termsUrl = Mage::getStoreConfig('gebork_collectorbank/invoice/terms_url');
		$MerchantTermsUrl = $getBaseUrl.$termsUrl;
		return $MerchantTermsUrl;
	}
	
	
	public function getNotificationUri(){
		$getBaseUrl = Mage::getUrl();
		$notifyUrl = $getBaseUrl."collectorbank/index/notification";
		return $notifyUrl;
	}
	
	
	public function getTaxByPercent($taxclassid){
		$store = Mage::app()->getStore('default');
		$request = Mage::getSingleton('tax/calculation')->getRateRequest(null, null, null, $store);
		$percent = Mage::getSingleton('tax/calculation')->getRate($request->setProductClassId($taxclassid));
		return $percent;
	}
	
	public function getCartItems($cart){
		
		$discount = 0;		
		if($cart->getAllVisibleItems()){
			foreach ($cart->getAllVisibleItems() as $item){		
				$_product = Mage::getModel('catalog/product')->load($item->getProductId());
				//$cartt["id"] = $item->getProductId();
				$cartt["id"] = $item->getSku();
				$cartt["description"] = $item->getName();
				$cartt["unitPrice"] = round($item->getPriceInclTax(),2);
				$cartt["quantity"] = $item->getQty();
				$taxClassId = $_product->getTaxClassId();
				$percent = $this->getTaxByPercent($taxClassId);
				$cartt["vat"] = round($percent,2);
				//$cartt["vat"] = round($item->getPriceInclTax() - $item->getPrice(),2);
				$cartarray[] = $cartt;
				
				
				$percentage = $item->getTaxPercent();
				$discount = $discount  + ($item->getDiscountAmount() + (($percentage / 100) * ($item->getDiscountAmount())));
				
			}
			
			if($discount){
				$cartt["id"] = 'discount';
				$cartt["description"] = 'Applied discount amount';
				$cartt["unitPrice"] = '-'.$discount;
				$cartt["quantity"] = 1;
				$cartt["vat"] = 0;
				$cartarray[] = $cartt;
			}
			
			return $cartarray;
		}
		
	}
	
	public function getUpdateCart($typeData,$privateId){
		
		$pusername = '';
		$psharedSecret='';
		$pstoreId ='';
		$array = array();
		$session = Mage::getSingleton('checkout/session');
		$cart = Mage::getModel('checkout/cart')->getQuote();
		
		if(isset($typeData)){
			if($typeData['ctype'] == 'b2b'){
				$pusername = trim(Mage::getModel('collectorbank/config')->getBusinessUsername());
				$psharedSecret = trim(Mage::getModel('collectorbank/config')->getBusinessSecretkey());
				$pstoreId = Mage::getModel('collectorbank/config')->getBusinessStoreId();
				$array['storeId'] = $pstoreId;
			} else {
				$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
				$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
				$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
				$array['storeId'] = $pstoreId;
			}
			
		} else {
			$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
			$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
			$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
			$array['storeId'] = $pstoreId;			
		}
		
		$array["items"] = $this->getCartItems($cart);
		
		$init = Mage::getModel('collectorbank/config')->getInitializeUrl();
		$path = '/merchants/'.$array['storeId'].'/checkouts/'.$privateId.'/cart';
		
		
		$json = json_encode($array);
		Mage::log('REQUEST FOR UPDATE CART -->'.$json, null,'cartiframe.log');
		$hash = $pusername.":".hash("sha256",$json.$path.$psharedSecret);
		$hashstr = 'SharedKey '.base64_encode($hash); 
		
		$ch = curl_init($init.$path);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'charset=utf-8','Authorization:'.$hashstr));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		$output = curl_exec($ch);
		Mage::log('RESPONSE FOR UPDATE CART -->'.$output, null,'cartiframe.log');	
		$data = json_decode($output,true);
		
		if(curl_error($ch)){
			Mage::log('ERROR FOR UPDATE CART -->'.curl_error($ch), null,'cartiframe.log');	
		}

		curl_close($ch);
	}
	
	
	public function getUpdateFees($typeData,$privateId){
		
		$pusername = '';
		$psharedSecret='';
		$pstoreId ='';
		$array = array();
		$session = Mage::getSingleton('checkout/session');
		$cart = Mage::getModel('checkout/cart')->getQuote();
	
		if(isset($typeData)){
			if($typeData['ctype'] == 'b2b'){
				$pusername = trim(Mage::getModel('collectorbank/config')->getBusinessUsername());
				$psharedSecret = trim(Mage::getModel('collectorbank/config')->getBusinessSecretkey());
				$pstoreId = Mage::getModel('collectorbank/config')->getBusinessStoreId();
				$array['storeId'] = $pstoreId;
			} else {
				$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
				$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
				$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
				$array['storeId'] = $pstoreId;
			}
			
		} else {
			$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
			$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
			$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
			$array['storeId'] = $pstoreId;			
		}
		
		$address =  $cart->getShippingAddress();
		
		$shippingTaxClassId = Mage::getStoreConfig('tax/classes/shipping_tax_class');
		$shippingTaxPercent = $this->getTaxByPercent($shippingTaxClassId);
		
		$selectedShipMethod = $address->getShippingMethod();		
		$sihpDesc = $address->getShippingDescription();
		$sihpAmount = $address->getShippingAmount();
		$sihpDiscAmount = $address->getShippingDiscountAmount();
		$sihpHidTaxAmount = $address->getShippingHiddenTaxAmount();
		$sihpInclTaxAmount = $address->getShippingInclTax();
		$sihpTaxAmount = $address->getShippingTaxAmount();
		
		$array["shipping"]["id"] = $selectedShipMethod;
		$array["shipping"]["description"] = Mage::helper('collectorbank/data')->cutStringAt($sihpDesc, 50);
		$array["shipping"]["unitPrice"] = round($sihpInclTaxAmount,2);
		$array["shipping"]["vat"] = round($shippingTaxPercent,2);
		
		$invoiceFee = Mage::getStoreConfig('gebork_collectorbank/invoice/invoice_fee');
		$invoicetaxclassid = Mage::getStoreConfig('gebork_collectorbank/invoice/invoice_fee_tax_class');
		
		$invoicepercent = $this->getTaxByPercent($invoicetaxclassid);
		
		
		
		$array["directinvoicenotification"]["id"] = "INVOICE_FEE";
		$array["directinvoicenotification"]["description"] = "Invoice fee";
		$array["directinvoicenotification"]["unitPrice"] = round($invoiceFee,2);
		$array["directinvoicenotification"]["vat"] = round($invoicepercent,2);
			
		
		$init = Mage::getModel('collectorbank/config')->getInitializeUrl();
		$path = '/merchants/'.$array['storeId'].'/checkouts/'.$privateId.'/fees';
		
		
		$json = json_encode($array);
		Mage::log('REQUEST FOR UPDATE FEES -->'.$json, null,'cartiframe.log');
		$hash = $pusername.":".hash("sha256",$json.$path.$psharedSecret);
		$hashstr = 'SharedKey '.base64_encode($hash); 
		
		$ch = curl_init($init.$path);
		curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
		curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "PUT");
		curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'charset=utf-8','Authorization:'.$hashstr));
		curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
		$output = curl_exec($ch);
		Mage::log('RESPONSE FOR UPDATE FEES -->'.$output, null,'cartiframe.log');	
		$data = json_decode($output,true);
		
		if(curl_error($ch)){
			Mage::log('ERROR FOR UPDATE FEES -->'.curl_error($ch), null,'cartiframe.log');	
		}

		curl_close($ch);
	}
	
	public function getPublicToken($typeData){
		
		$selectedShipMethod = '';
		$pusername = '';
		$psharedSecret='';
		$pstoreId ='';
		$array = array();
		
		if(isset($typeData)){
			if($typeData['ctype'] == 'b2b'){
				$pusername = trim(Mage::getModel('collectorbank/config')->getBusinessUsername());
				$psharedSecret = trim(Mage::getModel('collectorbank/config')->getBusinessSecretkey());
				$pstoreId = Mage::getModel('collectorbank/config')->getBusinessStoreId();
				$array['storeId'] = $pstoreId;
			} else {
				$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
				$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
				$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
				$array['storeId'] = $pstoreId;
			}
			
		} else {
			$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
			$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
			$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
			$array['storeId'] = $pstoreId;
			
		}
		
		$array['countryCode'] = Mage::getStoreConfig('general/country/default');
		
		
		$init = Mage::getModel('collectorbank/config')->getInitializeUrl();
		$path = '/checkout';		
		
		$session = Mage::getSingleton('checkout/session');
		$cart = Mage::getModel('checkout/cart')->getQuote();
		
		$cart->reserveOrderId();
		$nextOrderId = $cart->getReservedOrderId();
		$array['reference'] = $nextOrderId;
		
		
		
		if($typeData['ctype'] == 'b2b'){
			$array['redirectPageUri'] = $this->getBusinessRedirectPageUri();
		} else {
			$array['redirectPageUri'] = $this->getRedirectPageUri();
		}
		$array['merchantTermsUri'] = $this->getMerchantTermsUri();
		$array['notificationUri'] = $this->getNotificationUri();
		
		$address =  $cart->getShippingAddress();
		$selectedShipMethod = $address->getShippingMethod();
		
		
		if(!empty($selectedShipMethod)){
			
			$sihpDesc = $address->getShippingDescription();
			$sihpAmount = $address->getShippingAmount();
			$sihpDiscAmount = $address->getShippingDiscountAmount();
			$sihpHidTaxAmount = $address->getShippingHiddenTaxAmount();
			$sihpInclTaxAmount = $address->getShippingInclTax();
			$sihpTaxAmount = $address->getShippingTaxAmount();			
				  
			$array["cart"]["items"] = $this->getCartItems($cart);
			
			
			$shippingTaxClassId = Mage::getStoreConfig('tax/classes/shipping_tax_class');
			$shippingTaxPercent = $this->getTaxByPercent($shippingTaxClassId);
		 
			$array["fees"]["shipping"]["id"] = $selectedShipMethod;
			$array["fees"]["shipping"]["description"] = Mage::helper('collectorbank/data')->cutStringAt($sihpDesc, 50);
			$array["fees"]["shipping"]["unitPrice"] = round($sihpInclTaxAmount,2);
			$array["fees"]["shipping"]["vat"] = round($shippingTaxPercent,2);					
			
			
			$invoiceFee = Mage::getStoreConfig('gebork_collectorbank/invoice/invoice_fee');
			$invoicetaxclassid = Mage::getStoreConfig('gebork_collectorbank/invoice/invoice_fee_tax_class');
			$invoicepercent = $this->getTaxByPercent($invoicetaxclassid);			
			
			$array["fees"]["directinvoicenotification"]["id"] = "INVOICE_FEE";
			$array["fees"]["directinvoicenotification"]["description"] = "Invoice fee";
			$array["fees"]["directinvoicenotification"]["unitPrice"] = round($invoiceFee,2);
			$array["fees"]["directinvoicenotification"]["vat"] = round($invoicepercent,2);			
			
			
			$json = json_encode($array);
			Mage::log('REQUEST -->'.$json, null,'cartiframe.log');	
			$hash = $pusername.":".hash("sha256",$json.$path.$psharedSecret);
			$hashstr = 'SharedKey '.base64_encode($hash); 
			
			
			$ch = curl_init($init.$path);
			curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
			curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
			curl_setopt($ch, CURLOPT_POST, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'charset=utf-8','Authorization:'.$hashstr));
			curl_setopt($ch, CURLOPT_POSTFIELDS, $json);
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);
			$output = curl_exec($ch);
			
		
			Mage::log('RESPONSE -->'.$output, null,'cartiframe.log');	
			
			if(curl_error($ch)){
				Mage::log('ERROR -->'.curl_error($ch), null,'cartiframe.log');	
			}
			curl_close($ch);
			
			$data = json_decode($output,true);
			
		
			$session->setData('reference',$nextOrderId);			
			//set selected shipping method in checkout session
			if($session->getSelectedShippingmethod()){
				$session->unsSelectedShippingmethod();
				$session->setData('selected_shippingmethod',$selectedShipMethod);
			} else {
				$session->setData('selected_shippingmethod',$selectedShipMethod);
			}
			
			
			if($data["data"]){
				$result['code'] = 1;
				$result['publicToken'] = $data["data"]["publicToken"];
				$result['privateId'] = $data["data"]["privateId"];
				$result['hashstr'] = $hashstr;
				
			} else {
				$result['code'] = 0;
				$result['error'] = $data["error"];
				
			}
		} else {
			$result['code'] = -1;
			$result['error'] = "Please select Shipping Method for Collector Checkout";
		}
		
		return $result;
		
	}
	
	public function getOrderResponse() {
	
		$sessData = Mage::getSingleton('checkout/session')->getData();
		$typeData = $sessData['type_data'];
		
		if($typeData['ctype'] == 'b2b'){
			$privateId = $sessData['business_private_id'];
		} else {
			$privateId = $sessData['private_id'];
		}
		
		$init = Mage::getModel('collectorbank/config')->getInitializeUrl();
		if($privateId){
			
			if(isset($typeData)){
				if($typeData['ctype'] == 'b2b'){
					$pusername = trim(Mage::getModel('collectorbank/config')->getBusinessUsername());
					$psharedSecret = trim(Mage::getModel('collectorbank/config')->getBusinessSecretkey());
					$pstoreId = Mage::getModel('collectorbank/config')->getBusinessStoreId();
					$array['storeId'] = $pstoreId;
				} else {
					$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
					$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
					$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
					$array['storeId'] = $pstoreId;
				}
				
			} else {
				$pusername = trim(Mage::getModel('collectorbank/config')->getPrivateUsername());
				$psharedSecret = trim(Mage::getModel('collectorbank/config')->getPrivateSecretkey());
				$pstoreId = Mage::getModel('collectorbank/config')->getPrivateStoreId();
				$array['storeId'] = $pstoreId;
				
			}
					
			$path = '/merchants/'.$pstoreId.'/checkouts/'.$privateId;
			$hash = $pusername.":".hash("sha256",$path.$psharedSecret);
			$hashstr = 'SharedKey '.base64_encode($hash);
			
			Mage::log('REQUEST >>> Private id is '.$privateId .' with shared key --> '.$hashstr, null,'magentoorder.log');			

			$ch = curl_init($init.$path);
			curl_setopt($ch, CURLOPT_HTTPGET, 1);
			curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization:'.$hashstr));
			curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
			curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 30);

			$output = curl_exec($ch);
			Mage::log('RESPONSE >>> '.$output, null,'magentoorder.log');
			$data = json_decode($output,true);
			
			if($data["data"]){
				$result['code'] = 1;
				$result['id'] = $data["id"];
				$result['data'] = $data["data"];
				
			} else {
				$result['code'] = 0;
				$result['error'] = $data["error"];
				
			}			
			return $result;
		}
	}
	
	
}