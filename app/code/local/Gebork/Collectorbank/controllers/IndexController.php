<?php
class Gebork_Collectorbank_IndexController extends Mage_Core_Controller_Front_Action{
	
	
	
	/* Redirection URL Action */	
	public function BsuccessAction() {
		
		$session = Mage::getSingleton('checkout/session');
		
		$logFileName = 'magentoorder.log';
		
		Mage::log('----------------- START ------------------------------- ', null, $logFileName);	
		
		$quote = $session->getQuote();
		$quoteId = $quote->getEntityId();
		
		
		$typeData = $session->getTypeData();
		
		$privateId = $session->getBusinessPrivateId();
		if($privateId){
			$orderData = Mage::getModel('collectorbank/api')->getOrderResponse();	
			//echo "<pre>business ";print_r($orderData);die;
			if(isset($orderData['error'])){
				$session->addError($orderData['error']['message']);
				$this->_redirect('checkout/cart');
				return;
			}
			$orderDetails = $orderData['data'];		
		
		
		if($orderDetails){		
			$email = $orderDetails['businessCustomer']['email'];
			$mobile = $orderDetails['businessCustomer']['mobilePhoneNumber'];
			$firstName = $orderDetails['businessCustomer']['deliveryAddress']['companyName'];
			$lastName = $orderDetails['businessCustomer']['referencePerson'];
			
			
			$store = Mage::app()->getStore();
			$website = Mage::app()->getWebsite();
			$customer = Mage::getModel('customer/customer')->setWebsiteId($website->getId())->loadByEmail($email);
				// if the customer is not already registered
				if (!$customer->getId()) {
					$customer = Mage::getModel('customer/customer');			
					$customer->setWebsiteId($website->getId())
							 ->setStore($store)
							 ->setFirstname($firstName)
							 ->setLastname($lastName)
							 ->setEmail($email);  
					try {
					   
						$password = $customer->generatePassword();         
						$customer->setPassword($password);        
						// set the customer as confirmed
						$customer->setForceConfirmed(true);        
						// save customer
						$customer->save();        
						$customer->setConfirmation(null);
						$customer->save();
						
						// set customer address
						$customerId = $customer->getId();        
						$customAddress = Mage::getModel('customer/address');            
						$customAddress->setData($billingAddress)
									  ->setCustomerId($customerId)
									  ->setIsDefaultBilling('1')
									  ->setIsDefaultShipping('1')
									  ->setSaveInAddressBook('1');
						
						// save customer address
						$customAddress->save();
						// send new account email to customer    
						
						$storeId = $customer->getSendemailStoreId();
						$customer->sendNewAccountEmail('registered', '', $storeId);
						
						Mage::log('Customer with email '.$email.' is successfully created.', null, $logFileName);
						
					} catch (Mage_Core_Exception $e) {						
						Mage::log('Cannot add customer for  '.$e->getMessage(), null, $logFileName);
					} catch (Exception $e) {
						Mage::log('Cannot add customer for  '.$email, null, $logFileName);
					} 
				}
				
			// Assign Customer To Sales Order Quote
			$quote->assignCustomer($customer);
			
			if($orderDetails['businessCustomer']['deliveryAddress']['country'] == 'Sverige'){	$scountry_id = "SE";	}
			if($orderDetails['businessCustomer']['invoiceAddress']['country'] == 'Sverige'){  $bcountry_id = "SE";	}
			
			$billingAddress = array(
				'customer_address_id' => '',
				'prefix' => '',
				'firstname' => $firstName,
				'middlename' => '',
				'lastname' => $lastName,
				'suffix' => '',
				'company' => $orderDetails['businessCustomer']['invoiceAddress']['companyName'], 
				'street' => array(
					 '0' => $orderDetails['businessCustomer']['invoiceAddress']['address'], // compulsory
					 '1' => $orderDetails['businessCustomer']['invoiceAddress']['address2'] // optional
				 ),
				'city' => $orderDetails['businessCustomer']['invoiceAddress']['city'],
				'country_id' => $scountry_id, // two letters country code
				'region' => '', // can be empty '' if no region
				'region_id' => '', // can be empty '' if no region_id
				'postcode' => $orderDetails['businessCustomer']['invoiceAddress']['postalCode'],
				'telephone' => $mobile,
				'fax' => '',
				'save_in_address_book' => 1
			);
		
			$shippingAddress = array(
				'customer_address_id' => '',
				'prefix' => '',
				'firstname' => $firstName,
				'middlename' => '',
				'lastname' => $lastName,
				'suffix' => '',
				'company' => $orderDetails['businessCustomer']['deliveryAddress']['companyName'], 
				'street' => array(
					 '0' => $orderDetails['businessCustomer']['deliveryAddress']['address'], // compulsory
					 '1' => $orderDetails['businessCustomer']['deliveryAddress']['address2'] // optional
				 ),
				'city' => $orderDetails['businessCustomer']['deliveryAddress']['city'],
				'country_id' => $scountry_id, // two letters country code
				'region' => '', // can be empty '' if no region
				'region_id' => '', // can be empty '' if no region_id
				'postcode' => $orderDetails['businessCustomer']['deliveryAddress']['postalCode'],
				'telephone' => $mobile,
				'fax' => '',
				'save_in_address_book' => 1
			);
		
		
			// Add billing address to quote
			$billingAddressData = $quote->getBillingAddress()->addData($billingAddress);
		 
			// Add shipping address to quote
			$shippingAddressData = $quote->getShippingAddress()->addData($shippingAddress);
			
			//check for selected shipping method
			$shippingMethod = $session->getSelectedShippingmethod();
			if(empty($shippingMethod)){
				$allShippingData = Mage::getModel('collectorbank/config')->getActiveShppingMethods();
				$orderItems = $orderDetails['order']['items'];
				foreach($orderItems as $oitem){
					//echo "<pre>";print_r($oitem);
					if(in_array($oitem['id'], $allShippingData)) {
						$shippingMethod = $oitem['id'];						
						break;
					}
				}
			}

			// Collect shipping rates on quote shipping address data
			$shippingAddressData->setCollectShippingRates(true)->collectShippingRates();

			// Set shipping and payment method on quote shipping address data
			$shippingAddressData->setShippingMethod($shippingMethod);			
			
			
			$paymentMethod = 'collectorbank_invoice';
			// Set shipping and payment method on quote shipping address data
			$shippingAddressData->setPaymentMethod($paymentMethod);			
			
			$colpayment_method = $orderDetails['purchase']['paymentMethod'];
			$colpayment_details = json_encode($orderDetails['purchase']);
			
			
			// Set payment method for the quote
			$quote->getPayment()->importData(array('method' => $paymentMethod,'coll_payment_method' => $colpayment_method,'coll_payment_details' => $colpayment_details));
			
			//die;
			try{
				$orderReservedId = $session->getReference();
				$quote->setResponse($orderDetails);
				$quote->setCollCustomerType($orderDetails['customerType']);
				$quote->setCollBusinessCustomer($orderDetails['businessCustomer']);
				$quote->setCollStatus($orderDetails['status']);
				$quote->setCollPurchaseIdentifier($orderDetails['purchase']['purchaseIdentifier']);
				$quote->setCollTotalAmount($orderDetails['order']['totalAmount']);
				if($orderDetails['reference'] == $orderReservedId){
					$quote->setReservedOrderId($orderReservedId);
				} else {
					$quote->setReservedOrderId($orderDetails['reference']);
				}
							
			 	// Collect totals of the quote
				$quote->collectTotals();
				$quote->save();
				
				$service = Mage::getModel('sales/service_quote', $quote);
				$service->submitAll();
				$incrementId = $service->getOrder()->getRealOrderId();
				
				if($session->getIsSubscribed() == 1){
					Mage::getModel('newsletter/subscriber')->subscribe($email);
				} 				
				
				$session->setLastQuoteId($quote->getId())
					->setLastSuccessQuoteId($quote->getId())
					->clearHelperData();
					
				Mage::getSingleton('checkout/session')->clear();
				Mage::getSingleton('checkout/cart')->truncate()->save();
				
				
				$session->unsBusinessPrivateId();
				$session->unsReference();
				
				
				
				 // Log order created message
				Mage::log('Order created with increment id: '.$incrementId, null, $logFileName);						
				$result['success'] = true;
				$result['error']   = false;
				
				$order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
				
				$this->loadLayout();
				$block = Mage::app()->getLayout()->getBlock('collectorbank_success');
				if ($block){//check if block actually exists					
						if ($order->getId()) {
							$orderId = $order->getId();
							$isVisible = !in_array($order->getState(),Mage::getSingleton('sales/order_config')->getInvisibleOnFrontStates());
							$block->setOrderId($incrementId);
							$block->setIsOrderVisible($isVisible);
							$block->setViewOrderId($block->getUrl('sales/order/view/', array('order_id' => $orderId)));
							$block->setViewOrderUrl($block->getUrl('sales/order/view/', array('order_id' => $orderId)));
							$block->setPrintUrl($block->getUrl('sales/order/print', array('order_id'=> $orderId)));
							$block->setCanPrintOrder($isVisible);
							$block->setCanViewOrder(Mage::getSingleton('customer/session')->isLoggedIn() && $isVisible);
						}
				}
				$this->renderLayout();			
				
				
			} catch (Mage_Core_Exception $e) {
					$result['success'] = false;
					$result['error'] = true;
					$result['error_messages'] = $e->getMessage();    
					Mage::log('Order creation is failed for invoice no '.$orderDetails['purchase']['purchaseIdentifier'] ."Error is --> ".Mage::helper('core')->jsonEncode($result), null, $logFileName);		
					$this->loadLayout();
					$block = Mage::app()->getLayout()->getBlock('collectorbank_success');
					if ($block){
						if($orderDetails['purchase']['purchaseIdentifier']){
							$block->setInvoiceNo($orderDetails['purchase']['purchaseIdentifier']);
						} else {
							$block->setCode(222);
						}
					}
					$this->renderLayout();					
			} 			
		} 
		
		} else {
			Mage::log('Order is already generated.', null, $logFileName);		
			$this->loadLayout();   
			$block = Mage::app()->getLayout()->getBlock('collectorbank_success');
			if ($block){
				$block->setCode(111);
			}
			$this->renderLayout();
		}

		Mage::log('----------------- END ------------------------------- ', null, $logFileName);		
	}
	
	
	
	/* Redirection URL Action */	
	public function SuccessAction() {
		
		//echo "in b2b";die;
		$session = Mage::getSingleton('checkout/session');
		
		$logFileName = 'magentoorder.log';
		
		Mage::log('----------------- START ------------------------------- ', null, $logFileName);	
		
		$quote = $session->getQuote();
		$quoteId = $quote->getEntityId();
		$privateId = $session->getPrivateId();
		
		
		
		if($privateId){
			$orderData = Mage::getModel('collectorbank/api')->getOrderResponse();	
			
			if(isset($orderData['error'])){
				$session->addError($orderData['error']['message']);
				$this->_redirect('checkout/cart');
				return;
			}
			$orderDetails = $orderData['data'];		
		
		
		if($orderDetails){		
			$email = $orderDetails['customer']['email'];
			$mobile = $orderDetails['customer']['mobilePhoneNumber'];
			$firstName = $orderDetails['customer']['deliveryAddress']['firstName'];
			$lastName = $orderDetails['customer']['deliveryAddress']['lastName'];
			
			
			$store = Mage::app()->getStore();
			$website = Mage::app()->getWebsite();
			$customer = Mage::getModel('customer/customer')->setWebsiteId($website->getId())->loadByEmail($email);
				// if the customer is not already registered
				if (!$customer->getId()) {
					$customer = Mage::getModel('customer/customer');			
					$customer->setWebsiteId($website->getId())
							 ->setStore($store)
							 ->setFirstname($firstName)
							 ->setLastname($lastName)
							 ->setEmail($email);  
					try {
					   
						$password = $customer->generatePassword();         
						$customer->setPassword($password);        
						// set the customer as confirmed
						$customer->setForceConfirmed(true);        
						// save customer
						$customer->save();        
						$customer->setConfirmation(null);
						$customer->save();
						
						// set customer address
						$customerId = $customer->getId();        
						$customAddress = Mage::getModel('customer/address');            
						$customAddress->setData($billingAddress)
									  ->setCustomerId($customerId)
									  ->setIsDefaultBilling('1')
									  ->setIsDefaultShipping('1')
									  ->setSaveInAddressBook('1');
						
						// save customer address
						$customAddress->save();
						// send new account email to customer    
						
						$storeId = $customer->getSendemailStoreId();
						$customer->sendNewAccountEmail('registered', '', $storeId);
						
						Mage::log('Customer with email '.$email.' is successfully created.', null, $logFileName);
						
					} catch (Mage_Core_Exception $e) {						
						Mage::log('Cannot add customer for  '.$e->getMessage(), null, $logFileName);
					} catch (Exception $e) {
						Mage::log('Cannot add customer for  '.$email, null, $logFileName);
					} 
				}
				
			// Assign Customer To Sales Order Quote
			$quote->assignCustomer($customer);
			
			if($orderDetails['customer']['deliveryAddress']['country'] == 'Sverige'){	$scountry_id = "SE";	}
			if($orderDetails['customer']['billingAddress']['country'] == 'Sverige'){  $bcountry_id = "SE";	}
			
			$billingAddress = array(
				'customer_address_id' => '',
				'prefix' => '',
				'firstname' => $firstName,
				'middlename' => '',
				'lastname' => $lastName,
				'suffix' => '',
				'company' => $orderDetails['customer']['billingAddress']['coAddress'], 
				'street' => array(
					 '0' => $orderDetails['customer']['billingAddress']['address'], // compulsory
					 '1' => $orderDetails['customer']['billingAddress']['address2'] // optional
				 ),
				'city' => $orderDetails['customer']['billingAddress']['city'],
				'country_id' => $scountry_id, // two letters country code
				'region' => '', // can be empty '' if no region
				'region_id' => '', // can be empty '' if no region_id
				'postcode' => $orderDetails['customer']['billingAddress']['postalCode'],
				'telephone' => $mobile,
				'fax' => '',
				'save_in_address_book' => 1
			);
		
			$shippingAddress = array(
				'customer_address_id' => '',
				'prefix' => '',
				'firstname' => $firstName,
				'middlename' => '',
				'lastname' => $lastName,
				'suffix' => '',
				'company' => $orderDetails['customer']['deliveryAddress']['coAddress'], 
				'street' => array(
					 '0' => $orderDetails['customer']['deliveryAddress']['address'], // compulsory
					 '1' => $orderDetails['customer']['deliveryAddress']['address2'] // optional
				 ),
				'city' => $orderDetails['customer']['deliveryAddress']['city'],
				'country_id' => $scountry_id, // two letters country code
				'region' => '', // can be empty '' if no region
				'region_id' => '', // can be empty '' if no region_id
				'postcode' => $orderDetails['customer']['deliveryAddress']['postalCode'],
				'telephone' => $mobile,
				'fax' => '',
				'save_in_address_book' => 1
			);
		
		
			// Add billing address to quote
			$billingAddressData = $quote->getBillingAddress()->addData($billingAddress);
		 
			// Add shipping address to quote
			$shippingAddressData = $quote->getShippingAddress()->addData($shippingAddress);
			
			//check for selected shipping method
			$shippingMethod = $session->getSelectedShippingmethod();
			if(empty($shippingMethod)){
				$allShippingData = Mage::getModel('collectorbank/config')->getActiveShppingMethods();
				$orderItems = $orderDetails['order']['items'];
				foreach($orderItems as $oitem){
					//echo "<pre>";print_r($oitem);
					if(in_array($oitem['id'], $allShippingData)) {
						$shippingMethod = $oitem['id'];						
						break;
					}
				}
			}

			// Collect shipping rates on quote shipping address data
			$shippingAddressData->setCollectShippingRates(true)->collectShippingRates();

			// Set shipping and payment method on quote shipping address data
			$shippingAddressData->setShippingMethod($shippingMethod);			
			
			//$paymentMethod = 'collectorpay';
			$paymentMethod = 'collectorbank_invoice';
			// Set shipping and payment method on quote shipping address data
			$shippingAddressData->setPaymentMethod($paymentMethod);			
			
			$colpayment_method = $orderDetails['purchase']['paymentMethod'];
			$colpayment_details = json_encode($orderDetails['purchase']);
			
			// Set payment method for the quote
			$quote->getPayment()->importData(array('method' => $paymentMethod,'coll_payment_method' => $colpayment_method,'coll_payment_details' => $colpayment_details));
			

			try{
				$orderReservedId = $session->getReference();
				$quote->setResponse($orderDetails);
				$quote->setCollCustomerType($orderDetails['customerType']);
				$quote->setCollBusinessCustomer($orderDetails['businessCustomer']);
				$quote->setCollStatus($orderDetails['status']);
				$quote->setCollPurchaseIdentifier($orderDetails['purchase']['purchaseIdentifier']);
				$quote->setCollTotalAmount($orderDetails['order']['totalAmount']);
				if($orderDetails['reference'] == $orderReservedId){
					$quote->setReservedOrderId($orderReservedId);
				} else {
					$quote->setReservedOrderId($orderDetails['reference']);
				}
							
			 	// Collect totals of the quote
				$quote->collectTotals();
				$quote->save();
				
				$service = Mage::getModel('sales/service_quote', $quote);
				$service->submitAll();
				$incrementId = $service->getOrder()->getRealOrderId();
				
				if($session->getIsSubscribed() == 1){
					Mage::getModel('newsletter/subscriber')->subscribe($email);
				} 				
				
				$session->setLastQuoteId($quote->getId())
					->setLastSuccessQuoteId($quote->getId())
					->clearHelperData();
					
				Mage::getSingleton('checkout/session')->clear();
				Mage::getSingleton('checkout/cart')->truncate()->save();
				
				$session->unsPrivateId();
				$session->unsReference();
				
				 // Log order created message
				Mage::log('Order created with increment id: '.$incrementId, null, $logFileName);						
				$result['success'] = true;
				$result['error']   = false;
				
				$order = Mage::getModel('sales/order')->loadByIncrementId($incrementId);
				
				$this->loadLayout();
				$block = Mage::app()->getLayout()->getBlock('collectorbank_success');
				if ($block){//check if block actually exists					
						if ($order->getId()) {
							$orderId = $order->getId();
							$isVisible = !in_array($order->getState(),Mage::getSingleton('sales/order_config')->getInvisibleOnFrontStates());
							$block->setOrderId($incrementId);
							$block->setIsOrderVisible($isVisible);
							$block->setViewOrderId($block->getUrl('sales/order/view/', array('order_id' => $orderId)));
							$block->setViewOrderUrl($block->getUrl('sales/order/view/', array('order_id' => $orderId)));
							$block->setPrintUrl($block->getUrl('sales/order/print', array('order_id'=> $orderId)));
							$block->setCanPrintOrder($isVisible);
							$block->setCanViewOrder(Mage::getSingleton('customer/session')->isLoggedIn() && $isVisible);
						}
				}
				$this->renderLayout();			
				
				
			} catch (Mage_Core_Exception $e) {
					$result['success'] = false;
					$result['error'] = true;
					$result['error_messages'] = $e->getMessage();    
					Mage::log('Order creation is failed for invoice no '.$orderDetails['purchase']['purchaseIdentifier'] ."Error is --> ".Mage::helper('core')->jsonEncode($result), null, $logFileName);		
					//Mage::app()->getResponse()->setBody(Mage::helper('core')->jsonEncode($result));
					$this->loadLayout();
					$block = Mage::app()->getLayout()->getBlock('collectorbank_success');
					if ($block){
						if($orderDetails['purchase']['purchaseIdentifier']){
							$block->setInvoiceNo($orderDetails['purchase']['purchaseIdentifier']);
						} else {
							$block->setCode(222);
						}
					}
					$this->renderLayout();					
			} 			
		} 
		
		} else {
			Mage::log('Order is already generated.', null, $logFileName);		
			$this->loadLayout();   
			$block = Mage::app()->getLayout()->getBlock('collectorbank_success');
			if ($block){
				$block->setCode(111);
			}
			$this->renderLayout();
		}

		Mage::log('----------------- END ------------------------------- ', null, $logFileName);		
	}
	
	/* Notification URL Action */
	public function NotificationAction(){
		$this->loadLayout();   
		$this->renderLayout();
	}

}