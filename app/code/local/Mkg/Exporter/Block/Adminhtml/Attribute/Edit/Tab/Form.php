<?php
/**
 * Created by PhpStorm.
 * User: Nhut
 * Date: 5/24/16
 * Time: 8:51 AM
 */

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
            'values'    => array(1 => 'Export All', 2 => 'Export Last Group'),
            'note'      => ' ',
        ));

        return parent::_prepareForm();
    }
}
