<?php

class Meanbee_VIPMembership_Model_Observer {

    /**
     * Fires when the customer hits the checkout/onepage/success page.
     * Checks to update the customer to potentially be upgraded to VIP.
     * @event checkout_onepage_controller_success_action
     * @param $observer
     */
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

    /**
     * Event is fired after order status has changed.
     * @event sales_order_status_history_save_after
     * @param $observer
     */
    public function salesOrderStatusHistorySaveAfter($observer) {
        /** @var Meanbee_VIPMembership_Helper_Data $_helper */
        $_helper = Mage::helper('meanbee_vipmembership');
        if (!$_helper->isVIPMembershipEnabled()) {
            return;
        }
        /** @var Mage_Sales_Model_Order_Status_History $orderHistory */
        $orderHistory = $observer->getStatusHistory();
        $order = $orderHistory->getOrder();
        // Not relying on the customer session since this observer can be fired via the admin
        $customer = Mage::getModel('customer/customer')->load($order->getCustomerId());
        $this->_vipProductPurchased(array($order->getId()), $customer);
    }

    /**
     * Checks the status of the order against the system configuration value
     * @param $order Mage_Sales_Model_Order
     * @return bool
     */
    protected function _customerOrderCanBecomeVip($order) {
        return Mage::helper('meanbee_vipmembership')->isOrderStatusAllowed($order->getStatus());
    }

    /**
     * Checks the status of the recurring profile against the system configuration value
     * @param Mage_Sales_Model_Recurring_Profile $recurringProfile
     * @return mixed
     */
    protected function _customerRecurringProfileCanBecomeVip($recurringProfile) {
        return Mage::helper('meanbee_vipmembership')->isRecurringProfileStatusAllowed($recurringProfile->getState());
    }

    /**
     * Checks orders for the VIP Membership product type, then checks the order status before upgrading the customer
     * @param array $orderIds
     * @param mixed $customer
     */
    protected function _vipProductPurchased($orderIds, $customer = null) {
        /** @var Meanbee_VIPMembership_Helper_Data $_helper */
        $_helper = Mage::helper('meanbee_vipmembership');

        if ($customer == null) {
            $customer = Mage::getSingleton('customer/session')->getCustomer();
        }

        $orders = Mage::getModel('sales/order')->getCollection()->addFieldToFilter('entity_id', array('in' => $orderIds));
        foreach ($orders as $order) {
            if (!$this->_customerOrderCanBecomeVip($order)) {
                continue;
            }

            // If customer has already been upgraded due to this order
            if ($order->getId() == $customer->getVipOrderId()) {
                continue;
            }

            $items = array_filter($order->getAllItems(), function($v) { return $v->getProductType() == Meanbee_VIPMembership_Model_Product_Type::TYPE_VIP; });
            if (count($items)) {
                /** @var Mage_Sales_Model_Order_Item $item */
                $item = array_pop($items);
                $product = $item->getProduct();

                // Get start date for calculating membership expiry.  Extend from current expiry if member exists
                $startDate = time();
                if ($customer->getId() && $_helper->isCustomerVIP($customer->getId())) {
                    $startDate = strtotime($customer->getVipExpiry());
                }

                // Calculate membership end date from start date and membership length
                $length = sprintf("+ %s %s", $product->getVipLength(), $product->getAttributeText('vip_length_unit'));
                $expiryDate = strtotime($length, $startDate);

                if ($this->_customerOrderCanBecomeVip($order)) {
                    $this->_upgradeCustomerToVIP($expiryDate, $order->getId(), $customer);
                }
            }
        }
    }

    /**
     * Checks all recurring profiles for the VIP Product Type, checks the profile status, then upgrades customer.
     */
    protected function _recurringProfileVip() {
        $profileIds = Mage::getSingleton('checkout/session')->getLastRecurringProfileIds();
        // If no recurring profile ids exist, exit.
        if (!count($profileIds)) {
            return;
        }

        /** @var Meanbee_VIPMembership_Helper_Data $_helper */
        $_helper = Mage::helper('meanbee_vipmembership');

        $customer = Mage::getSingleton('customer/session')->getCustomer();

        $profiles = Mage::getModel('sales/recurring_profile')->getCollection()->addFieldToFilter('profile_id', array('in' => $profileIds))->load();
        foreach ($profiles as $profile) {
            $orderItemInfo = unserialize($profile->getOrderItemInfo());
            if ($orderItemInfo['product_type'] == Meanbee_VIPMembership_Model_Product_Type::TYPE_VIP) {

                // Get start date for calculating membership expiry.  Extend from current expiry if member exists
                $startDate = time();
                if ($customer->getId() && $_helper->isCustomerVIP($customer->getId())) {
                    $startDate = strtotime($customer->getVipExpiry());
                }

                // Calculate membership end date from start date and membership length
                $length = sprintf("+ %s %s", $profile->getPeriodFrequency(), $profile->getPeriodUnit());
                $expiryDate = strtotime($length, $startDate);

                if ($this->_customerRecurringProfileCanBecomeVip($profile)) {
                    $this->_upgradeCustomerToVIP($expiryDate, $profile->getId(), $customer);
                }
            }
        }
    }

    /**
     * Upgrades customer to VIP
     * @param string $expiryDate
     * @param int $orderId
     * @param mixed  $customer
     * @throws Exception
     */
    protected function _upgradeCustomerToVIP($expiryDate, $orderId, $customer) {
        /** @var Meanbee_VIPMembership_Helper_Data $_helper */
        $_helper = Mage::helper('meanbee_vipmembership');

        if ($customer->getId()) {
            $customer->setGroupId($_helper->getCustomerGroupId());
            $customer->setVipExpiry($expiryDate);
            $customer->setVipOrderId($orderId);
            $customer->save();
        }
    }

    /**
     * This is the cron which runs every night to check VIP Membership statuses
     * @throws Mage_Core_Exception
     */
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
        if ($customers->count()) {
            foreach ($customers as $customer) {
                if (($expires = $customer->getVipExpiry())) {
                    $expires = Mage::getModel('core/date')->date(null, $expires);
                    $today = Mage::getModel('core/date')->date();
                    if ($today >= $expires) {
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
        if ($profiles->count()) {
            foreach ($profiles as $profile) {
                $orderItemInfo = unserialize($profile->getOrderItemInfo());
                if ($orderItemInfo['product_type'] == Meanbee_VIPMembership_Model_Product_Type::TYPE_VIP
                    && $profile->getState() != Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE) {

                    $cancelCustomers[] = $profile->getCustomerId();

                } elseif ($orderItemInfo['product_type'] == Meanbee_VIPMembership_Model_Product_Type::TYPE_VIP ) {
                    $vipCustomers[] = $profile->getCustomerId();
                    $period = '+' . $profile->getPeriodFrequency() . ' ' . $profile->getPeriodUnit();
                    $vipCustomersExpiry[$profile->getCustomerId()] = $_helper->getProfileExpiryDate($profile->getStartDatetime(), $period);
                }
            }
        }
        if (count($cancelCustomers)) {
            $this->_bulkChangeCustomerGroup($cancelCustomers, Mage::getStoreConfig('customer/create_account/default_group'));
        }
        if (count($vipCustomers)) {
            $this->_bulkChangeCustomerGroup($vipCustomers, $_helper->getCustomerGroupId(), $vipCustomersExpiry);
        }
    }

    /**
     * Updates an array of customers to a specified group, and optionally sets the customers vip_expiry attribute.
     * @param array $customerIDs
     * @param int $group
     * @param null $customerVipExpiry
     * @throws Exception
     */
    protected function _bulkChangeCustomerGroup($customerIDs, $group, $customerVipExpiry = null) {
        $customers = Mage::getModel('customer/customer')->getCollection()->addFieldToFilter('entity_id', array('in' => $customerIDs));
        foreach ($customers as $customer) {
            $customer->setGroupId($group);
            if ($customerVipExpiry[$customer->getId()]) {
                $customer->setVipExpiry($customerVipExpiry[$customer->getId()]);
            }
            $customer->save();
        }
    }
}
