<?php

class Mkg_Exporter_Block_Adminhtml_Attribute_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('attribute_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('exporter')->__('Exporter'));
    }

    protected function _beforeToHtml()
    {
        $this->addTab('form_section', array(
            'label'     => Mage::helper('exporter')->__('Export Data'),
            'title'     => Mage::helper('exporter')->__('Export Data'),
            'content'   => $this->getLayout()->createBlock('exporter/adminhtml_attribute_edit_tab_form')->toHtml(),
        ));

        return parent::_beforeToHtml();
    }
}
