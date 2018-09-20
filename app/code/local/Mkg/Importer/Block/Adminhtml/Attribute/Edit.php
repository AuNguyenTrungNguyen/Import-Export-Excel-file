<?php
/**
 * Created by PhpStorm.
 * User: Nhut
 * Date: 5/24/16
 * Time: 8:51 AM
 */

class Mkg_Importer_Block_Adminhtml_Attribute_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_blockGroup = 'importer';
        $this->_controller = 'adminhtml_attribute';

        $this->_updateButton('save', 'label', Mage::helper('importer')->__('Start import'));

        $this->_addButton('log', array(
            'label'   => 'Download Log',
            'onclick' => "setLocation('{$this->getUrl('*/*/log')}')",
            'class'   => 'add'
        ));

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
            
            $('import_type').on('change', function(){
            	if($('import_type').value == 5)
            		$('note_import_type').down().update('Note: Fix Product Import File from 4 car attributes (brand, model, type, year) to 1 car attribute (car_model)');
            	else $('note_import_type').down().update('');
            });
        ";
    }

    public function getHeaderText()
    {
        if (Mage::registry('importer_data') && Mage::registry('importer_data')->getId()) {
            return Mage::helper('importer')->__("Edit Item '%s'", $this->htmlEscape(Mage::registry('importer_data')->getTitle()));
        } else {
            return Mage::helper('importer')->__('Add Item');
        }
    }
}
