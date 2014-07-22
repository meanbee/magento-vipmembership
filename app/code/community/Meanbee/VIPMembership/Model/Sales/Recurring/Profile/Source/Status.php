<?php

class Meanbee_VIPMembership_Model_Sales_Recurring_Profile_Source_Status
{
    public function toOptionArray()
    {
        $statuses = Mage::getSingleton('sales/recurring_profile')->getAllStates();
        $options = array();
        $options[] = array(
            'value' => '',
            'label' => Mage::helper('adminhtml')->__('-- Please Select --')
        );
        foreach ($statuses as $code=>$label) {
            $options[] = array(
                'value' => $code,
                'label' => $label
            );
        }
        return $options;
    }
}
