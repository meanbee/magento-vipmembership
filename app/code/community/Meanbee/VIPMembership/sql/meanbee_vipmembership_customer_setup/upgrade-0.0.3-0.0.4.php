<?php
$installer = $this;
/* @var $installer Mage_Customer_Model_Entity_Setup */

$installer->startSetup();

$eavConfig = Mage::getSingleton('eav/config');

$store = Mage::app()->getStore(Mage_Core_Model_App::ADMIN_STORE_ID);

$vipExpiry = $eavConfig->getAttribute('customer', 'vip_expiry');

if ($vipExpiry->getId()) {
    $vipExpiry
        ->setWebsite( ($store->getWebsite() ?: 0))
        ->setData('used_in_forms', array('adminhtml_customer'))
        ->save();
}

$vipOrderId = $eavConfig->getAttribute('customer', 'vip_order_id');

if ($vipOrderId->getId()) {
    $vipOrderId
        ->setWebsite( ($store->getWebsite() ?: 0))
        ->setData('used_in_forms', array('adminhtml_customer'))
        ->save();
}

$installer->endSetup();
