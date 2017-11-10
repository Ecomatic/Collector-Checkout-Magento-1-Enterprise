<?php

class Gebork_Collectorbank_Model_Source_Country
{
    public function toOptionArray() {
        $countries = array(
            //array('value' => 'NO', 'label' => Mage::helper('collectorbank')->__('Norway')),
            array('value' => 'SE', 'label' => Mage::helper('collectorbank')->__('Sweden')),
        );

        return $countries;
    }
}