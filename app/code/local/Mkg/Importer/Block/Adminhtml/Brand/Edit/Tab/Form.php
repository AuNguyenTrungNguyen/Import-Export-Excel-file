<?php

class Mkg_Importer_Block_Adminhtml_Brand_Edit_Tab_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();
        $this->setForm($form);
        $fieldset = $form->addFieldset('importer_form', array('legend'=>Mage::helper('importer')->__('Import Brand')));

        $fieldset->addField('filename', 'file', array(
            'label'     => Mage::helper('importer')->__('File'),
            'required'  => true,
            'name'      => 'filename',
            'accept'    => '.xlsx',
        ));
        return parent::_prepareForm();
    }
}
