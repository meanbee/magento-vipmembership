<?php
$installer = $this;
/* @var $installer Mage_Customer_Model_Entity_Setup */

$installer->startSetup();

$installer->addAttribute(
    'customer',
    'vip_expiry',
    array(
        'group'                => 'Default',
        'type'                 => 'datetime',
        'label'                => 'VIP Expiry Date',
        'input'                => 'date',
        'global'               => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'required'             => 0,
        'default'              => 0,
        'visible_on_front'     => 1,
        'used_for_price_rules' => 0,
        'adminhtml_only'       => 1,
    )
);

$installer->endSetup();