<?php
$installer = $this;
/* @var $installer Mage_Customer_Model_Entity_Setup */

$installer->startSetup();
$installer->run("

ALTER TABLE `{$installer->getTable('sales/quote_payment')}` ADD `coll_payment_method` VARCHAR( 255 )  NULL ;
ALTER TABLE `{$installer->getTable('sales/quote_payment')}` ADD `coll_payment_details` TEXT  NULL ;

ALTER TABLE `{$installer->getTable('sales/order_payment')}` ADD `coll_payment_method` VARCHAR( 255 )  NULL ;
ALTER TABLE `{$installer->getTable('sales/order_payment')}` ADD `coll_payment_details` TEXT  NULL ;

");
$installer->endSetup();
