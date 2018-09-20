<?php
/**
 * Created by PhpStorm.
 * User: Nhut
 * Date: 5/24/16
 * Time: 8:51 AM
 */

class Mkg_Importer_Block_Adminhtml_Product_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_blockGroup = 'importer';
        $this->_controller = 'adminhtml_product';

        $this->_updateButton('save', 'label', Mage::helper('importer')->__('Upload File'));
        Mage_Adminhtml_Block_Widget_Container::addButton('download_log', array(
            'label' => Mage::helper('importer')->__('Download Log File'),
            'onclick' => 'setLocation(\'' . $this->getUrl('*/*/log', null, null) . '\');'
        ), 0, 100, 'header', 'header');

        $this->_removeButton('back');
        $this->_removeButton('reset');

        $this->_formScripts[] = "
            function toggleEditor() {
                if (tinyMCE.getInstanceById('importer_content') == null) {
                    tinyMCE.execCommand('mceAddControl', false, 'importer_content');
                } else {
                    tinyMCE.execCommand('mceRemoveControl', false, 'importer_content');
                }
            }

            function saveAndContinueEdit(){
                editForm.submit($('edit_form').action+'back/edit/');
            }

        ";
    }

    public function getHeaderText()
    {
        /*if( Mage::registry('importer_data') && Mage::registry('importer_data')->getId() ) {
            return Mage::helper('importer')->__("Edit Item '%s'", $this->htmlEscape(Mage::registry('importer_data')->getTitle()));
        } else {
            return Mage::helper('importer')->__('Add Item');
        }*/
    }
}
