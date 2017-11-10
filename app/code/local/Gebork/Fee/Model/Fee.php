<?php
class Gebork_Fee_Model_Fee extends Varien_Object{
	
	public static function getFee(){
		return Mage::getStoreConfig('gebork_collectorbank/invoice/invoice_fee');
	}
	public static function canApply($address){
		
		return true;
		
	}
}