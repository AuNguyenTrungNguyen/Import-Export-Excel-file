<?php
/**
 * Created by PhpStorm.
 * User: Nhut
 * Date: 5/24/16
 * Time: 8:51 AM
 */

class Mkg_Importer_Block_Adminhtml_Fix_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('fix_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('importer')->__('Exporter'));
    }

    protected function _beforeToHtml()
    {
        $this->addTab('form_section', array(
            'label'     => Mage::helper('importer')->__('Fix Import File'),
            'title'     => Mage::helper('importer')->__('Fix Import File'),
            'content'   => $this->getLayout()->createBlock('importer/adminhtml_fix_edit_tab_form')->toHtml(),
        ));

        return parent::_beforeToHtml();
    }
}
