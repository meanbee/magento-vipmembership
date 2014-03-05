<?php

class Meanbee_VIPMembership_Model_Observer {

    public function checkoutOnepageControllerSuccessAction($observer) {
        /** @var Meanbee_VIPMembership_Helper_Data $_helper */
        $_helper = Mage::helper('meanbee_vipmembership');
        if (!$_helper->isVIPMembershipEnabled()) {
            return;
        }
        /* Check for vip product type */
        $this->_vipProductPurchased($observer->getOrderIds());
        /* Check recurring profiles for membership */
        $this->_recurringProfileVip();

    }

    protected function _vipProductPurchased($orderIds) {
        $orders = Mage::getModel('sales/order')->getCollection()->addFieldToFilter('entity_id', array('in' => $orderIds));
        foreach($orders as $order) {
            $items = array_filter($order->getAllItems(), function($v) { return $v->getProductType() == Meanbee_VIPMembership_Model_Product_Type::TYPE_VIP; });
            if(count($items)) {
                /** @var Mage_Sales_Model_Order_Item $item */
                $item = array_pop($items);
                $product = $item->getProduct();
                $expiryDate = strtotime('+' . $product->getVipLength() . ' ' . $product->getAttributeText('vip_length_unit'));
                $this->_upgradeCustomerToVIP($expiryDate);
            }
        }
    }

    protected function _recurringProfileVip() {
        $profileIds = Mage::getSingleton('checkout/session')->getLastRecurringProfileIds();
        // If no recurring profile ids exist, exit.
        if(!count($profileIds)) {
            return false;
        }

        $profiles = Mage::getModel('sales/recurring_profile')->getCollection()->addFieldToFilter('profile_id', array('in' => $profileIds))->load();
        foreach ($profiles as $profile) {
            $orderItemInfo = unserialize($profile->getOrderItemInfo());
            if ($orderItemInfo['product_type'] == Meanbee_VIPMembership_Model_Product_Type::TYPE_VIP) {
                $expiryDate = strtotime('+' . $profile->getPeriodFrequency() . ' ' . $profile->getPeriodUnit());
                $this->_upgradeCustomerToVIP($expiryDate);
                return true;
            }
        }
    }

    protected function _upgradeCustomerToVIP($expiryDate) {
        /** @var Meanbee_VIPMembership_Helper_Data $_helper */
        $_helper = Mage::helper('meanbee_vipmembership');
        $customer = Mage::getSingleton('customer/session')->getCustomer();
        $customer->setGroupId($_helper->getCustomerGroupId());
        $customer->setVipExpiry($expiryDate);
        $customer->save();
    }

    /* CRON */
    public function checkVIPMemberships() {
        /** @var Meanbee_VIPMembership_Helper_Data $_helper */
        $_helper = Mage::helper('meanbee_vipmembership');
        $cancelCustomers = array();
        $vipCustomers = array();
        $vipCustomersExpiry = array();
        // Check non-recurring types:
        $customers = Mage::getModel('customer/customer')->getCollection()
            ->addFieldToFilter('group_id', array('eq' => Meanbee_VIPMembership_Helper_Data::getCustomerGroupId()))
            ->addAttributeToSelect('vip_expiry');
        if($customers->count()) {
            foreach ($customers as $customer) {
                if(($expires = $customer->getVipExpiry())) {
                    $expires = Mage::getModel('core/date')->date(null, $expires);
                    $today = Mage::getModel('core/date')->date();
                    if($today >= $expires) {
                        $cancelCustomers[] = $customer->getId();
                        $customer->unsetData('vip_expiry');
                    } else {
                        $vipCustomers[] = $customer->getId();
                    }
                }
            }
        }

        // Check recurring profiles:
        $profiles = Mage::getModel('sales/recurring_profile')->getCollection()->load();
        if($profiles->count()) {
            foreach ($profiles as $profile) {
                $orderItemInfo = unserialize($profile->getOrderItemInfo());
                if ($orderItemInfo['product_type'] == Meanbee_VIPMembership_Model_Product_Type::TYPE_VIP
                    && $profile->getState() != Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE) {

                    $cancelCustomers[] = $profile->getCustomerId();

                } elseif($orderItemInfo['product_type'] == Meanbee_VIPMembership_Model_Product_Type::TYPE_VIP ) {
                    $vipCustomers[] = $profile->getCustomerId();
                    $period = '+' . $profile->getPeriodFrequency() . ' ' . $profile->getPeriodUnit();
                    $vipCustomersExpiry[$profile->getCustomerId()] = $_helper->getProfileExpiryDate($profile->getStartDatetime(), $period);
                }
            }
        }
        if(count($cancelCustomers)) {
            $this->_bulkChangeCustomerGroup($cancelCustomers, Mage::getStoreConfig('customer/create_account/default_group'));
        }
        if(count($vipCustomers)) {
            $this->_bulkChangeCustomerGroup($vipCustomers, $_helper->getCustomerGroupId(), $vipCustomersExpiry);
        }
    }

    protected function _bulkChangeCustomerGroup($customerIDs, $group, $customerVipExpiry = null) {
        $customers = Mage::getModel('customer/customer')->getCollection()->addFieldToFilter('entity_id', array('in' => $customerIDs));
        Mage::log('bulk change customer groups: '.$customers->count(), null, 'ashsmith_cron.log', true);
        foreach ($customers as $customer) {
            $customer->setGroupId($group);
            if($customerVipExpiry[$customer->getId()]) {
                $customer->setVipExpiry($customerVipExpiry[$customer->getId()]);
            }
            $customer->save();
        }
    }
}
