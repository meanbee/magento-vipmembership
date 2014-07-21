<?php
class Meanbee_VIPMembership_Helper_Data extends Mage_Core_Helper_Abstract {

    const MEMBERSHIP_ORDER_STATUS_PATH = 'vipmembership_config/vipmembership_general/order_status';
    const MEMBERSHIP_CUSTOMER_GROUP_PATH = 'vipmembership_config/vipmembership_general/customer_group';
    const MEMBERSHIP_ENABLED_PATH = 'vipmembership_config/vipmembership_general/is_enabled';
    const MEMBERSHIP_LOGGING_PATH = 'vipmembership_config/vipmembership_general/log_enabled';

    /**
     * @return mixed
     */
    public function getCustomerGroupId() {
        return Mage::getStoreConfig(self::MEMBERSHIP_CUSTOMER_GROUP_PATH);
    }

    public function isVIPMembershipEnabled() {
        return Mage::getStoreConfig(self::MEMBERSHIP_ENABLED_PATH);
    }

    /**
     * @param $customerId
     * @return string
     */
    public function getCustomerVIPExpiry($customerId) {
        $profile = $this->getCustomerVIPProfile($customerId);
        $interval = $profile->getPeriodFrequency() . ' ' . $profile->getPeriodUnit();
        $newDate = strtotime($interval, strtotime($profile->getStartDatetime()));
        return Mage::getModel('core/date')->date(null, $newDate);
    }

    /**
     * @param $customerId
     * @return Mage_Eav_Model_Entity_Collection_Abstract
     */
    public function getCustomerActiveRecurringProfiles($customerId) {
        return Mage::getModel('sales/recurring_profile')
            ->getCollection()
            ->addFieldToFilter('state', array('eq' => Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE))
            ->addFieldToFilter('customer_id', array('eq' => $customerId));
    }

    /**
     * @param $customerId
     * @return bool
     */
    public function isCustomerVIP($customerId) {
        $customer = Mage::getModel('customer/customer')->load($customerId);
        if($customer->getGroupId() == $this->getCustomerGroupId()) {
            return true;
        }
        return false;
    }


    /**
     * @param $customerId
     * @return bool|Mage_Sales_Model_Recurring_Profile
     */
    public function getCustomerVIPProfile($customerId) {
        /** @var Mage_Sales_Model_Resource_Recurring_Profile_Collection $profiles */
        $profiles = $this->getCustomerActiveRecurringProfiles($customerId);
        if(count($profiles)) {
            foreach($profiles as $profile) {
                /** @var Mage_Sales_Model_Recurring_Profile $profile */
                $orderItem = unserialize($profile->getOrderItemInfo());
                if($orderItem['product_type'] == Meanbee_VIPMembership_Model_Product_Type::TYPE_VIP) {
                    return $profile;
                }
            }
        }
        return false;
    }

    public function getProfileExpiryDate($start, $period) {
        return Mage::getModel('core/date')->date(null, strtotime($period, strtotime($start)));
    }

    /**
     * Check if the order status matches the defined order status in system config
     * @param string $status
     * @return bool
     */
    public function isOrderStatusAllowed($status) {
        return $status == Mage::getStoreConfig(self::MEMBERSHIP_ORDER_STATUS_PATH);
    }

    /**
     * Check if the recurring profile is active.
     * @param $status
     * @return bool
     */
    public function isRecurringProfileStatusAllowed($status) {
        return $status == Mage_Sales_Model_Recurring_Profile::STATE_ACTIVE;
    }
}
