<?php

class Meanbee_VIPMembership_Block_Customer_Account_Dashboard_Vip extends Mage_Customer_Block_Account_Dashboard {
    public function isPrimeCustomer() {
        $customerId = $this->getCustomer()->getId();
        return Mage::helper('meanbee_vipmembership')->getCustomerVIPProfile($customerId) ? true : false;
    }
}