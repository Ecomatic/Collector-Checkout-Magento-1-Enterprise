<?php

class Gebork_Collectorbank_Model_Collectorbank_Invoice extends Gebork_Collectorbank_Model_Collectorbank_Abstract
{
    protected $_code = 'collectorbank_invoice';

    public function authorize(Varien_Object $payment, $amount)
    {
		
        // Added validation of shipping and billing address
        if (!$this->validShippingAddress()) {
            Mage::helper('collectorbank')->logException(new Exception("Shipping and billing addresses don't match"));
            Mage::throwException(Mage::helper('collectorbank')->__('Billing address does not match shipping address'));

            return $this;
        }

        $order = $payment->getOrder();

        $originalIncrementId = $order->getOriginalIncrementId();
        $newIncrementId = $order->getIncrementId();

        if ($originalIncrementId != null and $originalIncrementId != $newIncrementId) {
            $this->replaceInvoice($payment, $amount);
        } 
				$session = Mage::getSingleton('checkout/session');
				$quote = $session->getQuote();
				$response = $quote->getResponse();
				
				
				
				$colpayment_method = $response['purchase']['paymentMethod'];
				$colpayment_details = json_encode($response['purchase']);
				$payment->setCollPaymentMethod($colpayment_method);
				$payment->setCollPaymentDetails($colpayment_details );
			
				
				 $result['invoice_status'] = $response['status'];
				 $result['invoice_no'] = $response['purchase']['purchaseIdentifier'];
				 $result['total_amount'] =  $response['order']['totalAmount'];
				

           
                $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_NO, isset($result['invoice_no']) ? $result['invoice_no'] : '');
                $payment->setAdditionalInformation(self::COLLECTOR_PAYMENT_REF, isset($result['payment_reference']) ? $result['payment_reference'] : '');
                $payment->setAdditionalInformation(self::COLLECTOR_LOWEST_AMOUNT_TO_PAY, isset($result['lowest_amount_to_pay']) ? $result['lowest_amount_to_pay'] : '');
                $payment->setAdditionalInformation(self::COLLECTOR_TOTAL_AMOUNT, isset($result['total_amount']) ? $result['total_amount'] : '');
                $payment->setAdditionalInformation(self::COLLECTOR_DUE_DATE, isset($result['due_date']) ? $result['due_date'] : '');
                $payment->setAdditionalInformation(self::COLLECTOR_AVAILABLE_RESERVATION_AMOUNT, isset($result['available_reservation_amount']) ? $result['available_reservation_amount'] : '');
                $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_STATUS, isset($result['invoice_status']) ? $result['invoice_status'] : '');
                $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE, $this->getInvoiceFee());
                $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_TAX, $this->getInvoiceFeeTax($order));
                $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_TAX_INVOICED, 0);
                $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICED, 0);
                $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_REFUNDED, 0);
                $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICE_NO, 0);
                $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_DESCRIPTION, Mage::helper('collectorbank')->__('Invoice fee'));

                $payment->save();

                $order = $payment->getOrder();
                if ($order->getState() == Mage_Sales_Model_Order::STATE_PENDING_PAYMENT) {
                    $comment = Mage::helper('collectorbank')->__('Collector authorization successful');
                    $order->setState(Mage_Sales_Model_Order::STATE_PROCESSING)
                        ->addStatusToHistory($this->getConfigData('order_status'), $comment)
                        ->setIsCustomerNotified(false)
                        ->save();
                }
           

        return $this;
    }

    public function capture(Varien_Object $payment, $amount)
    {
        $invoice = Mage::registry('current_invoice');
        if (!($invoice instanceof Mage_Sales_Model_Order_Invoice)) {
            Mage::throwException(Mage::helper('collectorbank')->__('Activating invoice failed'));
        }

        $isPartialInvoice = Mage::helper('collectorbank/invoiceservice')->isPartial($invoice);

        $transactionId = $payment->getAdditionalInformation(self::COLLECTOR_INVOICE_NO);

        if ($isPartialInvoice) {
            if ($this->hasCredit($payment)) {
                $result['error'] = true;
                $result['error_message'] = Mage::helper('collectorbank')->__('Orders with gift card, store credit or reward point can not be partial invoiced');
            } else {
                $additionalData = array('invoice' => $invoice);
                $partActivateInvoiceService = Mage::getModel('collectorbank/invoiceservice_partactivateinvoice')
                    ->setRequest($payment, $additionalData);
                $result = $partActivateInvoiceService->partActivateInvoice();

                if (!$result['error']) {
                    $payment->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_APPROVED);
                    if (!$payment->getParentTransactionId() || $transactionId != $payment->getParentTransactionId()) {
                        $payment->setTransactionId($transactionId);
                    }

                    Mage::getSingleton('adminhtml/session')->setData('collector_invoice_url', $result['invoice_url']);

                    $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_NO, isset($result['new_invoice_no']) ? $result['new_invoice_no'] : '');

                    //First invoice, add invoice fee
                    if (!$payment->getAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICED)) {
                        $invoiceFee = $payment->getAdditionalInformation(self::COLLECTOR_INVOICE_FEE);
                        $invoiceFeeTax = $payment->getAdditionalInformation(self::COLLECTOR_INVOICE_FEE_TAX);
                        $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICED, $invoiceFee);
                        $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_TAX_INVOICED, $invoiceFeeTax);
                        $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICE_NO, $transactionId);
                    }

                    $payment->save();
                }
            }
        } else {
            $activateInvoiceService = Mage::getModel('collectorbank/invoiceservice_activateinvoice')
                ->setRequest($payment);
            $result = $activateInvoiceService->activateInvoice();

            if (!$result['error']) {
                $payment->setStatus(Mage_Payment_Model_Method_Abstract::STATUS_APPROVED);
                if (!$payment->getParentTransactionId() || $transactionId != $payment->getParentTransactionId()) {
                    $payment->setTransactionId($transactionId);
                }

                Mage::getSingleton('adminhtml/session')->setData('collector_invoice_url', $result['invoice_url']);

                //First invoice, add invoice fee
                if (!$payment->getAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICED)) {
                    $invoiceFee = $payment->getAdditionalInformation(self::COLLECTOR_INVOICE_FEE);
                    $invoiceFeeTax = $payment->getAdditionalInformation(self::COLLECTOR_INVOICE_FEE_TAX);
                    $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICED, $invoiceFee);
                    $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_TAX_INVOICED, $invoiceFeeTax);
                    $payment->setAdditionalInformation(self::COLLECTOR_INVOICE_FEE_INVOICE_NO, $transactionId);
                }

                $payment->save();
            }
        }

        if ($result['error']) {
            Mage::throwException(Mage::helper('collectorbank')->__('Activating invoice failed: %s', $result['error_message']));
        }

        return $this;
    }

    public function getTitle()
    {
		return $this->getConfigData('title');
		
		
    }

    public function getInvoiceFee()
    {
        if ($this->isBusinessCustomer()) {
            return Mage::helper('collectorbank/invoiceservice')->getModuleConfig('invoice/invoice_fee_company');
        }

        return Mage::helper('collectorbank/invoiceservice')->getModuleConfig('invoice/invoice_fee');
    }

    public function getTaxClass()
    {
        if ($this->isBusinessCustomer()) {
            return Mage::helper('collectorbank/invoiceservice')->getModuleConfig('invoice/invoice_fee_company_tax_class');
        }

        return Mage::helper('collectorbank/invoiceservice')->getModuleConfig('invoice/invoice_fee_tax_class');
    }

    public function getInvoiceFeeTax($order)
    {
        $store = $order->getStore();
        $custTaxClassId = $order->getCustomerTaxClassId();

        $taxCalculationModel = Mage::getSingleton('tax/calculation');
        /* @var $taxCalculationModel Mage_Tax_Model_Calculation */
        $request = $taxCalculationModel->getRateRequest($order->getShippingAddress(), $order->getBillingAddress(), $custTaxClassId, $store);
        $shippingTaxClass = Mage::helper('collectorbank/invoiceservice')->getModuleConfig('invoice/invoice_fee_tax_class');

        $feeTax = 0;

        if ($shippingTaxClass) {
            if ($rate = $taxCalculationModel->getRate($request->setProductClassId($shippingTaxClass))) {
                $feeTax = ($this->getInvoiceFee() / ($rate + 100)) * $rate;
                $feeTax = $store->roundPrice($feeTax);
            }
        }

        return $feeTax;
    }

    public function getInvoiceFeeTaxPercent($order)
    {
        $feeAmount = $this->getInvoiceFee();
        $taxAmount = $this->getInvoiceFeeTax($order);

        $exTax = $feeAmount - $taxAmount;
        $taxPercent = ($taxAmount / $exTax) * 100;

        return $taxPercent;
    }

    public function getInvoiceType()
    {
        return Mage::helper('collectorbank')->getModuleConfig('invoice/invoice_method');
    }

    public function getDeliveryMethod()
    {
        if ($this->getInvoiceType() == 0) {
            return Mage::helper('collectorbank')->getModuleConfig('invoice/delivery_method_merchant');
        } elseif ($this->getInvoiceType() == 1) {
            return Mage::helper('collectorbank')->getModuleConfig('invoice/delivery_method_collector');
        } else {
            return;
        }
    }

    public function getInfoText($company = false)
    {
        if ($company) {
            $infoText = Mage::helper('collectorbank')->getModuleConfig('invoice/info_text_company');
        } else {
            $infoText = Mage::helper('collectorbank')->getModuleConfig('invoice/info_text');
        }

        if (empty($infoText)) {
            if ($company) {
                $infoText = Mage::helper('collectorbank')->__('Når du velger faktura får du varene levert før du gjør din betaling. Fakturering skjer i samarbeid med Collector credit AB. Mer informasjon finner du på www.collector.no.');
            } else {
                $infoText = Mage::helper('collectorbank')->__('Når du velger faktura får du varene tilsendt hjem før du betaler. Du kan deretter velge å betale hele beløpet med en gang eller dele opp betalingen i mindre deler. Du må være fylt 18 år for å handle med Collector. Mer informasjon finner du på www.collector.no.');
            }
        }

        return $infoText;
    }

    public function useCampaign()
    {
        return $this->getConfigData('use_campaign');
    }

    /**
     * Determines payment method availability.
     */
    public function isAvailable($quote = null)
    {
        if (!parent::isAvailable($quote)) {
            return false;
        }

        // Disable if business customer and module not configured for
        // business customers.
        $allowedCustomerTypes = Mage::helper('collectorbank')->getModuleConfig('invoice/customer_type');
        $customerType = Mage::helper('collectorbank/invoiceservice')->guessCustomerType($quote->getBillingAddress()) == 'company' ? 2 : 1;
        if ($customerType == 2 && $allowedCustomerTypes != 2) {
            return false;
        }

        return true;
    }

    /**
     * Detect if customer is a business customer.
     */
    public function isBusinessCustomer()
    {
        $paymentInfo = $this->getInfoInstance();
        $quote = $paymentInfo->getQuote();
        $order = $paymentInfo->getOrder();

        if (isset($order)) {
            $billingAddress = $order->getBillingAddress();
        } elseif (isset($quote)) {
            $billingAddress = $quote->getBillingAddress();
        }

        if ($billingAddress->getCompany()) {
            return true;
        }

        return false;
    }
}
