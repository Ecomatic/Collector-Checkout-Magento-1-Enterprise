<?php

class Gebork_Collectorbank_Helper_Data extends Mage_Core_Helper_Abstract
{
	public function getPaymentConfig($path, $method, $store_id = null)
    {
        return Mage::getStoreConfig('payment/'.$method.'/'.$path, $store_id);
    }

    public function getModuleConfig($path, $store_id = null)
    {
		
		return Mage::getStoreConfig('gebork_collectorbank/'.$path, $store_id);
    }

    public function getCountryCode($storeId = null)
    {
        return Mage::getStoreConfig('general/country/default', $storeId);
    }

    public function log($msg)
    {
        $pattern = '/(<ns1\:Password>.+?)+(<\/ns1\:Password>)/i';
        $msg = preg_replace($pattern, '<ns1:Password>********</ns1:Password>', $msg);

        $pattern = '/(<ns1\:RegNo>.+?)+(<\/ns1\:RegNo>)/i';
        $msg = preg_replace($pattern, '<ns1:RegNo>***********</ns1:RegNo>', $msg);

        Mage::log($msg, Zend_Log::DEBUG, 'gebork_collector_debug.log');
    }

    public function logException($e)
    {
        Mage::log("\n".$e->__toString(), Zend_Log::ERR, 'gebork_collector_exception.log');
    }

    public function getLogos()
    {
        return array(
            'black.png' => 'Black',
        );
    }

    public function cutStringAt($string, $limit, $endingDots = true)
    {
        if ($endingDots) {
            $dots = '...';
            $limit = $limit - strlen($dots);
        }
        if (strlen($string) > $limit) {
            $string = substr($string, 0, $limit);
            $stringRev = strrev($string);
            $pos = strpos($stringRev, ' ');
            $stringRev = substr($stringRev, $pos);
            $string = strrev($stringRev).$dots;
        }

        return $string;
    }

    public function isFirstInvoice($order, $invoice)
    {
        $orderInvoice = $order->getInvoiceCollection()->getFirstItem();
        if ($orderInvoice->getId() and $orderInvoice->getId() == $invoice->getId()) {
            return true;
        }

        return false;
    }

} 