<?php

class Meanbee_VIPMembership_Model_Product_Type extends Mage_Catalog_Model_Product_Type_Virtual {
    const TYPE_VIP = 'vip';
    const XML_PATH_AUTHENTICATION = 'catalog/vip/authentication';

    protected function _prepareProduct(Varien_Object $buyRequest, $product, $processMode) {

        if ($this->_isStrictProcessMode($processMode)) {
            /** @var Meanbee_VIPMembership_Helper_Data $_helper */
            $_helper = Mage::helper('meanbee_vipmembership');
            $customer = Mage::getSingleton('customer/session')->getCustomer();

            // Check if the customer isn't logged in.
            if (!$customer->getId()) {
                return $_helper->__('You must be logged in to buy become a VIP member');
            }
        }
        return parent::_prepareProduct($buyRequest, $product, $processMode);
    }
}