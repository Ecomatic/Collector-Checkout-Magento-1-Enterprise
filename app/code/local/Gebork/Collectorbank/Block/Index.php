<?php   
class Gebork_Collectorbank_Block_Index extends Mage_Core_Block_Template {   


	public function getIframeSrc(){
		return "https://checkout-uat.collector.se/collector-checkout-loader.js";
	}
	
	public function getTypeData(){
		$session = Mage::getSingleton('checkout/session');
		$typeData = $session->getTypeData();
		
		return $typeData;
	}
	
	public function getVarient(){
		$typeData = $this->getTypeData();
		$dataVariant = ' async';
		if(isset($typeData)){
			if($typeData['ctype'] == 'b2b'){
				$dataVariant = 'data-variant="b2b" async';
				
			}
		} 
		
		return $dataVariant;
	}
	
	public function getLanguage(){
		$typeData = $this->getTypeData();
		
		if(isset($typeData)){
			if($typeData['ctype'] == 'b2b'){				
				//$language = "nb-NO";
				$language = "sv";
			} else {
				$language = "sv";
			}
		} else {
			$language = "sv";
		}
		
		return $language;
	}
	
	public function getPublicTokeniFrame(){
		
		$session = Mage::getSingleton('checkout/session');
		$typeData = $this->getTypeData();		
		$cart = Mage::getModel('checkout/cart')->getQuote();

		if($typeData['ctype'] == 'b2b'){
			if($session->getBusinessPrivateId()){				
				if($session->getIsShppingChanged() == 1){
					$privateId = $session->getBusinessPrivateId();
					$updateFees = Mage::getModel('collectorbank/api')->getUpdateFees($typeData,$privateId);
				} else {
					$privateId = $session->getBusinessPrivateId();
					$tokenData = Mage::getModel('collectorbank/api')->getUpdateCart($typeData,$privateId);
					$updateFees = Mage::getModel('collectorbank/api')->getUpdateFees($typeData,$privateId);
				}
				
			} else {				
				$tokenData = Mage::getModel('collectorbank/api')->getPublicToken($typeData);
				$publicToken  =  $tokenData['publicToken'];	
				$privateId  =  $tokenData['privateId'];
				$hashstr  =  $tokenData['hashstr'];
				
				$session->setData('business_public_token', $publicToken);	
				$session->setData('business_private_id', $privateId);	
				$session->setData('business_hashstr', $hashstr);
			}
			
			if($publicToken == ''){
				$publicToken = $session->getBusinessPublicToken();
			}
			
		} else {			
			//For B2C
			if($session->getPrivateId()){
				if($session->getIsShppingChanged() == 1){
					$privateId = $session->getPrivateId();
					$updateFees = Mage::getModel('collectorbank/api')->getUpdateFees($typeData,$privateId);
				} else {
					$privateId = $session->getPrivateId();
					$tokenData = Mage::getModel('collectorbank/api')->getUpdateCart($typeData,$privateId);
					$updateFees = Mage::getModel('collectorbank/api')->getUpdateFees($typeData,$privateId);
				}
				
			} else {
				$tokenData = Mage::getModel('collectorbank/api')->getPublicToken($typeData);
				$publicToken  =  $tokenData['publicToken'];	
				$privateId  =  $tokenData['privateId'];
				$hashstr  =  $tokenData['hashstr'];
				
				$session->setData('public_token', $publicToken);	
				$session->setData('private_id', $privateId);	
				$session->setData('hashstr', $hashstr);					
			}
			
			if($publicToken == ''){
				$publicToken = $session->getPublicToken();
			}
		}

		$session->setData('is_shpping_changed',0);	
		return $publicToken;
	
	}


}