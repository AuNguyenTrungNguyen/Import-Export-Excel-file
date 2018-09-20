<?php
/**
 * Created by PhpStorm.
 * User: Nhut
 * Date: 5/24/16
 * Time: 8:51 AM
 */

class Mkg_Importer_Block_Adminhtml_Product_Edit_Tabs extends Mage_Adminhtml_Block_Widget_Tabs
{
    public function __construct()
    {
        parent::__construct();
        $this->setId('product_tabs');
        $this->setDestElementId('edit_form');
        $this->setTitle(Mage::helper('importer')->__('Product Exporter'));
    }

    protected function _beforeToHtml()
    {
        $this->addTab('form_section', array(
            'label'     => Mage::helper('importer')->__('Upload File'),
            'title'     => Mage::helper('importer')->__('Upload File'),
            'content'   => $this->getLayout()->createBlock('importer/adminhtml_product_edit_tab_form')->toHtml(),
        ));

        $this->addTab('import_section', array(
            'label'     => Mage::helper('importer')->__('Import Data'),
            'title'     => Mage::helper('importer')->__('Import Data'),
            'content'   => $this->getLayout()->createBlock('importer/adminhtml_product_edit_tab_import')->toHtml(),
        ));

        $this->addTab('export_section', array(
            'label'     => Mage::helper('importer')->__('Export Data'),
            'title'     => Mage::helper('importer')->__('Export Data'),
            'content'   => $this->getLayout()->createBlock('importer/adminhtml_product_edit_tab_export')->toHtml(),
        ));

        return parent::_beforeToHtml();
    }
}
