<?php

class Gebork_Ajax_AjaxController extends Mage_Core_Controller_Front_Action
{
	/* public function preDispatch()
	{
        parent::preDispatch();
		
        //if(!Mage::helper('ajax')->getEnabled()){
		if(!Mage::getModel('collectorbank/config')->getEnabled()) {
            $this->_redirectUrl(Mage::getUrl());
		}
    } */
	
	
	
    public function indexAction(){
        $this->_redirect('checkout/onepage', array('_secure'=>true));
    }


	public function headercartAction(){
		$this->loadLayout();
		$this->renderLayout();
	}

    public function headercartdeleteAction() {
        if ($this->getRequest()->getParam('btn_lnk')){
            $id = $this->getRequest()->getParam('id');
            if ($id) {
                try {
                    Mage::getSingleton('checkout/cart')->removeItem($id)
                      ->save();
                } catch (Exception $e) {
                    Mage::getSingleton('checkout/session')->addError($this->__('Cannot remove item'));
                }
            }

            $this->loadLayout();
            $newblock =  $this->getLayout()->getBlock('cart_sidebar')->toHtml();
            $this->getResponse()->setBody($newblock);

            $this->_initLayoutMessages('checkout/session');
            $this->renderLayout();
        } else {
            $backUrl = $this->_getRefererUrl();
            $this->getResponse()->setRedirect($backUrl);
        }
    }

    public function headercartupdateAction(){
        try {
            $cartData = array($_POST['item'] => array('qty' => $_POST['qty']));

            if (is_array($cartData)) {
                $filter = new Zend_Filter_LocalizedToNormalized(
                    array('locale' => Mage::app()->getLocale()->getLocaleCode())
                );
                foreach ($cartData as $index => $data) {
                    if (isset($data['qty'])) {
                        $cartData[$index]['qty'] = $filter->filter(trim($data['qty']));
                    }
                }
                $cart = $this->_getCart();
                if (! $cart->getCustomerSession()->getCustomer()->getId() && $cart->getQuote()->getCustomerId()) {
                    $cart->getQuote()->setCustomerId(null);
                }

                $cartData = $cart->suggestItemsQty($cartData);
                $cart->updateItems($cartData)->save();
                $this->_getSession()->setCartWasUpdated(true);

                $this->loadLayout();
                $this->renderLayout();
            }
        } catch (Mage_Core_Exception $e) {
            $this->_getSession()->addError(Mage::helper('core')->escapeHtml($e->getMessage()));
        } catch (Exception $e) {
            $this->_getSession()->addException($e, $this->__('Cannot update shopping cart.'));
            Mage::logException($e);
        }
    }

    public function _getCart(){
        return Mage::getSingleton('checkout/cart');
    }

    public function _getSession(){
        return Mage::getSingleton('checkout/session');
    }

    public function _getQuote(){
        return $this->_getCart()->getQuote();
    }
	
	
	public function qtyincreseAction(){
		try {
        	$response = array();
			$data = $this->getRequest()->getPost();
			$cartData = array($data['item_id'] => array('qty' => $data['item_qty']+$data['prevQty'])); 
						  
			
            if (is_array($cartData)) {
                $filter = new Zend_Filter_LocalizedToNormalized(
                    array('locale' => Mage::app()->getLocale()->getLocaleCode())
                );
				
                foreach ($cartData as $index => $data) {
                    if (isset($data['qty'])) {                        
                        $cartData[$index]['qty'] = trim($data['qty']);
                    }
                }				
                $cart = $this->_getCart();
                if (! $cart->getCustomerSession()->getCustomer()->getId() && $cart->getQuote()->getCustomerId()) {
                    $cart->getQuote()->setCustomerId(null);
                }
				
                $cartData = $cart->suggestItemsQty($cartData);
                $cart->updateItems($cartData)->save();				
				$this->loadLayout();
				
				if(Mage::getSingleton('core/design_package')->getPackageName()== "rwd") {
					$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
					$response['cart_content_ajax'] = $cart_list;
				
					$mini_cart = $this->getLayout()->getBlock('minicart_head')->toHtml();
					$response['min_cart'] = $mini_cart;
					$cart_sidebar = $this->getLayout()->getBlock('cart_sidebar')->toHtml();                      
					$response['cart_sidebar'] = $cart_sidebar;
					
					$collector = $this->getLayout()->getBlock('collectorbank_index')->toHtml();
					$response['collectorbank_index'] = $collector;
					
				} else {	  
     				$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
					$response['cart_content_ajax'] = $cart_list;
					$toplink = $this->getLayout()->getBlock('cartTop')->toHtml();                      
                    $response['header_cart'] = $toplink;
					$mini_cart = $this->getLayout()->getBlock('cart_sidebar')->toHtml();                      
                    $response['min_cart'] = $mini_cart;

				}
				$response['message'] = $this->__("Quantity increased successfully.");
            }
            
        } catch (Mage_Core_Exception $e) {           
			$response['status'] = 'ERROR';
			$response['message'] = $this->__($e->getMessage());
        } catch (Exception $e) {           
            $response['status'] = 'ERROR';
			$response['message'] = $this->__('Cannot update shopping cart.');
        }
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
		return;
	}
	
	
	
	public function qtydecreseAction(){
	 	try {
        	$response = array();          
			$data = $this->getRequest()->getPost();
			$preQty = $data['prevQty'];
			$minusQty = $data['item_qty'];
			$finalQty = $preQty - $minusQty;
			if($finalQty <= 0){
				$finalQty = 1;	
			}
            $cartData = array($data['item_id'] => array('qty' =>$finalQty)); 
					
            if (is_array($cartData)) {
                $filter = new Zend_Filter_LocalizedToNormalized(
                    array('locale' => Mage::app()->getLocale()->getLocaleCode())
                );
                foreach ($cartData as $index => $data) {
                    if (isset($data['qty'])) {
                        $cartData[$index]['qty'] = trim($data['qty']);
                    }
                }
			   
                $cart = $this->_getCart();
                if (! $cart->getCustomerSession()->getCustomer()->getId() && $cart->getQuote()->getCustomerId()) {
                    $cart->getQuote()->setCustomerId(null);
                }

                $cartData = $cart->suggestItemsQty($cartData);
                $cart->updateItems($cartData)->save();
			
				$this->loadLayout();
				
				if(Mage::getSingleton('core/design_package')->getPackageName()== "rwd"){
					$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
					$response['cart_content_ajax'] = $cart_list;
					
					$mini_cart = $this->getLayout()->getBlock('minicart_head')->toHtml();
					$response['min_cart'] = $mini_cart;
					$cart_sidebar = $this->getLayout()->getBlock('cart_sidebar')->toHtml();
					$response['cart_sidebar'] = $cart_sidebar;
				} else {
     				$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
					$response['cart_content_ajax'] = $cart_list;
					$toplink = $this->getLayout()->getBlock('cartTop')->toHtml();                      
                    $response['header_cart'] = $toplink;
					$mini_cart = $this->getLayout()->getBlock('cart_sidebar')->toHtml();                      
                    $response['min_cart'] = $mini_cart;
				}
				$response['message'] = $this->__("Quantity decreased successfully.");
            }
            
        } catch (Mage_Core_Exception $e) {           
			$response['status'] = 'ERROR';
			$response['message'] = $this->__($e->getMessage());
        } catch (Exception $e) {           
            $response['status'] = 'ERROR';
			$response['message'] = $this->__('Cannot update shopping cart.');
        }
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
		return;		
	}
	
	
	
	
	public function estimateUpdatePostAction()
	{
		try {
        	$response = array();
			$data = $this->getRequest()->getPost();
			
			$code = (string) $this->getRequest()->getParam('estimate_method');
			if (!empty($code)) {
				$this->_getQuote()->getShippingAddress()->setShippingMethod($code)/*->collectTotals()*/->save();
			}
			$cart = $this->_getCart();                
			$cart->save();
			
			$this->loadLayout();
			$session = Mage::getSingleton('checkout/session');
			$session->setData('is_shpping_changed',1); 
			$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
			$response['cart_content_ajax'] = $cart_list;
            
        } catch (Mage_Core_Exception $e) {           
			$response['status'] = 'ERROR';
			$response['message'] = $this->__($e->getMessage());
        } catch (Exception $e) {           
            $response['status'] = 'ERROR';
			$response['message'] = $this->__('Cannot update shopping cart.');
        }
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
		return;
	}
	
	public function loadiframeAction()
	{
		//echo "in herereere";die;
		$data = $this->getRequest()->getPost();
		//echo "<pre>";print_r($data);die;
		$this->loadLayout();	
		$session = Mage::getSingleton('checkout/session');
		$session->setData('type_data', $data);
		$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
		$response['cart_content_ajax'] = $cart_list;
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
		return;
	}
	
	public function subscribeAction()
	{
		//echo "in herereere";die;
		$data = $this->getRequest()->getPost();
		//echo "<pre>";print_r($data);die;
		
		$session = Mage::getSingleton('checkout/session');
		$session->setData('is_subscribed', $data['is_subscribed']);	
		$this->loadLayout();
		$this->renderLayout();
		/* $cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
		$response['cart_content_ajax'] = $cart_list;
		$this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response)); */
		return;
	}
	
	 /**
     * Add Gift Card to current quote
     *
     */
    public function giftcardaddAction()
    {
        $data = $this->getRequest()->getPost();
        if (isset($data['giftcard_code'])) {
            $code = $data['giftcard_code'];
            try {
                if (strlen($code) > Enterprise_GiftCardAccount_Helper_Data::GIFT_CARD_CODE_MAX_LENGTH) {
                    Mage::throwException(Mage::helper('enterprise_giftcardaccount')->__('Wrong gift card code.'));
                }
                Mage::getModel('enterprise_giftcardaccount/giftcardaccount')
                    ->loadByCode($code)
                    ->addToCart();
				
				//Mage::getSingleton('checkout/session')->addSuccess($this->__('Gift Card "%s" was added.', Mage::helper('core')->escapeHtml($code)));
				$cart = $this->_getCart();                
				$cart->save();
			
				$this->loadLayout();	
                
				
				$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
				$response['cart_content_ajax'] = $cart_list;
				
				
			} catch (Mage_Core_Exception $e) {       
				Mage::dispatchEvent('enterprise_giftcardaccount_add', array('status' => 'fail', 'code' => $code));			
				$response['status'] = 'ERROR';
				$response['message'] = $this->__($e->getMessage());
			} catch (Exception $e) {           
				$response['status'] = 'ERROR';
				$response['message'] = $this->__('Cannot apply gift card.');
			}
			
            
        }
		//echo "<pre>";print_r($response);die;
        $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
		return;
    }
	
	
	
	public function removegiftcardAction()
    {
        if ($code = $this->getRequest()->getParam('code')) {
            try {
                Mage::getModel('enterprise_giftcardaccount/giftcardaccount')
                    ->loadByCode($code)
                    ->removeFromCart();
               // Mage::getSingleton('checkout/session')->addSuccess($this->__('Gift Card "%s" was removed.', Mage::helper('core')->escapeHtml($code)));
           		$cart = $this->_getCart();                
				$cart->save();
			
				$this->loadLayout();	
                
				
				$cart_list = $this->getLayout()->getBlock('cart_content_ajax')->toHtml();
				$response['cart_content_ajax'] = $cart_list;
			
			} catch (Mage_Core_Exception $e) {			
				$response['status'] = 'ERROR';
				$response['message'] = $this->__($e->getMessage());
			} catch (Exception $e) {           
				$response['status'] = 'ERROR';
				$response['message'] = $this->__('Cannot remove gift card.');
			}
			
            $this->getResponse()->setBody(Mage::helper('core')->jsonEncode($response));
			return;
        } else {
            $this->_forward('noRoute');
        }
    }
}
?>