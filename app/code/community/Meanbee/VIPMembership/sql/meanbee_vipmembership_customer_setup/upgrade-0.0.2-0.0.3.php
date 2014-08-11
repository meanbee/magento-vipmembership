<?php
$installer = $this;
/* @var $installer Mage_Customer_Model_Entity_Setup */

$installer->startSetup();

$installer->addAttribute(
    'customer',
    'vip_order_id',
    array(
        'group'                => 'Default',
        'type'                 => 'varchar',
        'label'                => 'Last VIP Order',
        'input'                => 'text',
        'global'               => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'required'             => 0,
        'default'              => 0,
        'visible_on_front'     => 1,
        'used_for_price_rules' => 0,
        'adminhtml_only'       => 1,
    )
);

$installer->endSetup();