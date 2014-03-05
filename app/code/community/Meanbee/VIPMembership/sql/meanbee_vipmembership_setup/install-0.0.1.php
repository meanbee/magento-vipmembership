<?php
/** @var $installer Mage_Catalog_Model_Resource_Setup */
$installer = $this;

$installer->startSetup();

/* Configure attributes for new product type: VIP */
$attributes = array(
    'price',
    'special_price',
    'special_from_date',
    'special_to_date',
    'minimal_price',
    'tax_class_id',
    'is_recurring',
    'recurring_profile'
);
foreach ($attributes as $attributeCode) {
    $applyTo = explode(
        ',',
        $installer->getAttribute(
            Mage_Catalog_Model_Product::ENTITY,
           $attributeCode,
            'apply_to'
        )
    );

    if (!in_array(Meanbee_VIPMembership_Model_Product_Type::TYPE_VIP, $applyTo)) {
        $applyTo[] = Meanbee_VIPMembership_Model_Product_Type::TYPE_VIP;
        $installer->updateAttribute(
            Mage_Catalog_Model_Product::ENTITY,
            $attributeCode,
            'apply_to',
            join(',', $applyTo)
        );
    }
}
/* END: Configure attributes for new product type: VIP */


/* Customer VIP Group & Attributes */
$installer->addAttributeGroup('catalog_product', 1, 'VIP Membership');

$installer->addAttribute(
    'catalog_product',
    'vip_length',
    array(
        'group'    => 'VIP Membership',
        'type'     => 'int',
        'label'    => 'VIP Length',
        'input'    => 'text',
        'required' => 0,
        'default'  => 0,
        'global'   => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'apply_to' => Meanbee_VIPMembership_Model_Product_Type::TYPE_VIP,
    )
);

$installer->addAttribute(
    'catalog_product',
    'vip_length_unit',
    array(
        'group'  => 'VIP Membership',
        'type'   => 'varchar',
        'label'  => 'VIP Length Unit',
        'input'  => 'select',
        'required' => 0,
        'default'  => 0,
        'global'   => Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_STORE,
        'apply_to' => Meanbee_VIPMembership_Model_Product_Type::TYPE_VIP,
        'option' => array (
            'value' =>
            array(
                'days'=> array('Days'),
                'weeks'=> array('Weeks'),
                'months'=> array('Months'),
                'years'=> array('Years'),
            )
        ),
    )
);
/* END: Customer VIP Group & Attributes */

$installer->endSetup();