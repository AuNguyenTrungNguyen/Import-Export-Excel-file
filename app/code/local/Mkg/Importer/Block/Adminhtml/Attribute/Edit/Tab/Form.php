<?php
/**
 * Created by PhpStorm.
 * User: Nhut
 * Date: 5/24/16
 * Time: 8:51 AM
 */

class Mkg_Importer_Block_Adminhtml_Attribute_Edit_Tab_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();
        $this->setForm($form);
        $fieldset = $form->addFieldset('importer_form', array('legend'=>Mage::helper('importer')->__('Import Data')));


        $fieldset->addField('import_type', 'select', array(
            'label'     => Mage::helper('importer')->__('Import Type'),
            'required'  => true,
            'name'      => 'import_type',
            'values'    => array(1 => 'Attribute', 2 => 'Attribute Set'),
            'note'      => ' ',
        ));

        $fieldset->addField('filename', 'file', array(
            'label'     => Mage::helper('importer')->__('File'),
            'required'  => true,
            'name'      => 'filename',
            'accept'    => '.csv',
        ));
        return parent::_prepareForm();
    }
}
