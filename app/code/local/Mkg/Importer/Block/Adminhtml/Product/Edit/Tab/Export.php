<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 7/19/2016
 * Time: 5:54 PM
 */
class Mkg_Importer_Block_Adminhtml_Product_Edit_Tab_Export extends Mage_Adminhtml_Block_Widget_Form
{
    public function __construct()
    {
        parent::__construct();

        $this->setTemplate('importer/export.phtml');
    }

    protected function getAttributeSet()
    {
        $attributeSetArr = array();
        $attributeSetArr[0] = 'Any Attribute Set';
        $attributeSetCollection = Mage::getResourceModel('eav/entity_attribute_set_collection');
        $attributeSetCollection->setEntityTypeFilter('4'); // 4 is Catalog Product Entity Type ID
        foreach ($attributeSetCollection as $id => $attributeSet) {
            $name = $attributeSet->getAttributeSetName();
            $attributeSetId = $attributeSet->getId();
            $attributeSetArr[$attributeSetId] = $name;
        }
        return $attributeSetArr;
    }
}
