<?php

class Mkg_Exporter_Block_Adminhtml_Attribute_Edit_Tab_Form extends Mage_Adminhtml_Block_Widget_Form
{
    protected function _prepareForm()
    {
        $form = new Varien_Data_Form();
        $this->setForm($form);
        $fieldset = $form->addFieldset('exporter_form', array('legend'=>Mage::helper('exporter')->__('Export Data')));


        $fieldset->addField('export_type', 'select', array(
            'label'     => Mage::helper('exporter')->__('Export Type'),
            'required'  => true,
            'name'      => 'export_type',
            'values'    => array(1 => 'Export all group user created', 2 => 'Export last group'),
            'note'      => ' ',
        ));

        return parent::_prepareForm();
    }
}
