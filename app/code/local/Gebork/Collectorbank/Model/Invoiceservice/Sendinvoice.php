<?php
class Gebork_Collectorbank_Model_Invoiceservice_Sendinvoice extends Gebork_Collectorbank_Model_Invoiceservice_Abstract
{
    protected $request;

    public function _construct() {
        parent::_construct();
    }

    public function setRequest($payment, $additionalData = false) {
        $invoice = $additionalData['invoice'];
        $newEmail = $additionalData['new_email'];
        $request = array(
            'StoreId' => $this->helper->getModuleConfig('settings/store_id_private') ? $this->helper->getModuleConfig('settings/store_id_private') : null,
            'CorrelationId' => $payment->getOrder()->getId(),
            'CountryCode' => $this->helper->getCountryCode($payment->getOrder()->getStoreId()),
            'InvoiceNo' => $invoice->getTransactionId(),
            'InvoiceDeliveryMethod' => 2,
            'Email' => $newEmail ? $newEmail : $invoice->getOrder()->getCustomerEmail(),
        );
        if($this->helper->guessCustomerType($payment->getOrder()->getBillingAddress()) == "company") {
            $request['StoreId'] = $this->helper->getModuleConfig('settings/store_id_company');
        }
        // We don't want to send StoreId at all if it hasn't been set.
        if (!isset($request['StoreId']) || !$request['StoreId']) {
            unset($request['StoreId']);
        }
        
        $this->setStoreId($payment->getOrder()->getStoreId());

        $this->request = $request;

        return $this;
    }

    public function getRequest() {
        return $this->request;
    }

    public function resend() {
        $request = $this->getRequest();

        $request = array('SendInvoiceRequest' => $request);

        try {
            $client = $this->createSoapClient(1);
            $response = $client->__soapCall('SendInvoice', $request);
            return $this->prepareResponse($response);
        }
        catch (Exception $e) {
            $this->exceptionHandler($e, $client);
            return array('error' => true, 'message' => $e->getMessage());
        }
    }
}
