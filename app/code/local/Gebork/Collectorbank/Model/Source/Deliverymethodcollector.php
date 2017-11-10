<?php

class Gebork_Collectorbank_Model_Source_Deliverymethodcollector
{
    public function toOptionArray() {
      $helper = Mage::helper('collectorbank/invoiceservice');
        
      $arr = array();
      foreach ($helper->getDeliveryMethods('collector') as $k=>$v) {
          $arr[] = array('value'=>$k, 'label'=>$v);
      }
      return $arr;
    }
}
