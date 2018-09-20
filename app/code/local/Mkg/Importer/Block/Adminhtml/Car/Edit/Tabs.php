<?php
/**
 * Created by PhpStorm.
 * User: Nhut
 * Date: 5/24/16
 * Time: 8:51 AM
 */

class Mkg_Importer_Block_Adminhtml_Car_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('attribute_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('importer')->__('Exporter'));
    }

    protected function _beforeToHtml()
    {
        $this->addTab('form_section', array(
            'label'     => Mage::helper('importer')->__('Import Data'),
            'title'     => Mage::helper('importer')->__('Import Data'),
            'content'   => $this->getLayout()->createBlock('importer/adminhtml_car_edit_tab_form')->toHtml(),
        ));

        return parent::_beforeToHtml();
    }
}
