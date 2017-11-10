<?php

class Gebork_Collectorbank_Model_Config extends Varien_Object
{
	const COLLECTOR_DOC_URL	  			= 'https://merchant.collectorbank.se/integration/';
	const ONLINE_GUI_URL	  			= 'https://merchant.collectorbank.se/';

	const SERVER_MODE_LIVE 				= 'LIVE';
	const SERVER_MODE_DEMO 				= 'DEMO';

	
	const LICENSE_URL         			= 'https://merchant.collectorbank.se/modules/platforms/magento/collectorbank/license';
	const DOCUMENTATION_URL     		= 'https://merchant.collectorbank.se/modules/platforms/magento/collectorbank#documentation';

	
	
	const CUSTOMER_TYPE_PERSON 			= 'person';
	const CUSTOMER_TYPE_ORGANIZATION	= 'organization';

	

	private $store = null;
	
	
	

	public function setStore($storeId)
	{
		$this->store = $storeId;
	}

	public function getStore()
	{
		if($this->store != null)
			return $this->store;

		if(Mage::app()->getStore()->getId() == 0)
			return Mage::app()->getRequest()->getParam('store', 0);

		return $this->store;
	}
	
	public function getActiveShppingMethods()
	{
		$methods = Mage::getSingleton('shipping/config')->getActiveCarriers();
				$options = array();

				foreach($methods as $_code => $_method)
				{
					if ($methods = $_method->getAllowedMethods()){
						foreach ($methods as $_mcode => $_mname){
							$code = $_code . '_' . $_mcode;
							$title = $_mname;

							if(!$title = Mage::getStoreConfig("carriers/$_code/title"))
								$title = $_code;

							$options[] = $code;
								//'label' => $title. ' (' . $_mcode.')'
							
						}
					}
			
				}

		return $options; // This array will have all the active shipping methods
	} 
	
	/**
	 *  Return config var
	 *
	 *  @param    string $key
	 *  @param    string $default value for non-existing key
	 *  @return   mixed
	 */
	public function getConfigData($key, $default = false)
	{
		
		if (!$this->hasData($key) || $this->store != null){
			$value = Mage::getStoreConfig('payment/collectorbank_invoice/'.$key, $this->getStore());
			if (is_null($value) || false === $value) {
	    		$value = $default;
			}
			$this->setData($key, $value);
		}
		
		return $this->getData($key);
	}
	
	public function getEnabled() 
	{
        return $this->getConfigData('active');
    }
	
	
	/**
	 * Get method title
	 *
	 * @return  string
	 */
	public function getTitle()
	{
		if(strlen($this->getConfigData('title')) > 0)
			return $this->getConfigData('title');

		return "Collector Bank Payment";
	}
	
	
	public function getBusinessUsername() 
	{		
        return Mage::getStoreConfig('gebork_collectorbank/settings_b2b/merchantid');
    }
	
	
	public function getBusinessSecretkey() 
	{
        return Mage::helper('core')->decrypt(Mage::getStoreConfig('gebork_collectorbank/settings_b2b/secretkey'));
    }
	
	public function getBusinessStoreId() 
	{		
        return Mage::getStoreConfig('gebork_collectorbank/settings_b2b/storeid');
    }
	
	
	public function getPrivateUsername() 
	{		
        return Mage::getStoreConfig('gebork_collectorbank/settings_b2c/merchantid');
    }
	
	public function getPrivateSecretkey() 
	{
        return Mage::helper('core')->decrypt(Mage::getStoreConfig('gebork_collectorbank/settings_b2c/secretkey'));
    }
	
	public function getPrivateStoreId() 
	{		
        return Mage::getStoreConfig('gebork_collectorbank/settings_b2c/storeid');
    }
	
	
	public function getInitializeUrl() 
	{		
        return 'https://checkout-api-uat.collector.se';
    }
	
	
	
	
	/**
	 * Get Collector Checkout mode (LIVE OR TEST)
	 *
	 * @return  bool
	 */
	public function isLive()
	{
		if($this->getConfigData('server') == self::SERVER_MODE_LIVE)
			return true;

		return false;
	}
	
	
	
	
	/**
	 * Get config for default Checkout
	 *
	 * @return bool
	 */
	public function showDefaultCheckout()
	{
		return $this->getConfigData('default_checkout');
	}
	
	
	/**
	 * Get config for gift messagebox
	 *
	 * @return bool
	 */
	public function showGiftmessage()
	{
		return $this->getConfigData('show_giftmessage');
	}
	
	
	
	/**
	 * Get config for newsletter
	 *
	 * @return bool
	 */
	public function showNewsletter()
	{
		return $this->getConfigData('show_newsletter');
	}
	
	
	/**
	 *  Use additional checkbox 
	 *
	 *  @return bool
	 */
	public function useAdditionalCheckbox()
	{
		return $this->getConfigData('additional_checkbox');
	}

	/**
	 *  Get additional checkbox link text
	 *
	 *  @return string
	 */
	public function getAdditionalCheckboxText()
	{
		return $this->getConfigData('additional_checkbox_text');
	}
	

	/**
	 *  Get additional checkbox required value
	 *
	 *  @return bool
	 */
	public function getAdditionalCheckboxRequired()
	{
		return (bool)$this->getConfigData('additional_checkbox_required');
	}
	
	/**
	 *  Get additional checkbox default value
	 *
	 *  @return bool
	 */
	public function getAdditionalCheckboxChecked()
	{
		return (bool)$this->getConfigData('additional_checkbox_checked');
	}

	
	
	
	
	
}