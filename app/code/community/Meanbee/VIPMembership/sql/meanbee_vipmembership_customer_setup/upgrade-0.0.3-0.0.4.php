<?php
$installer = $this;
/* @var $installer Mage_Customer_Model_Entity_Setup */

$installer->startSetup();

$sql = <<<EOQ

INSERT INTO `customer_form_attribute`
    SELECT 'adminhtml_customer',
           `attribute_id`
    FROM `eav_attribute`
    WHERE `entity_type_id` = {$installer->getEntityTypeId('customer')}
      AND `attribute_code` IN ('vip_expiry', 'vip_order_id');

EOQ;

$installer->run($sql);

$installer->endSetup();
