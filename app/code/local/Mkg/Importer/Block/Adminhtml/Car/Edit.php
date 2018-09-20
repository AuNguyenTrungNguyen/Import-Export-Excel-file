<?php
/**
 * Created by PhpStorm.
 * User: Nhut
 * Date: 5/24/16
 * Time: 8:51 AM
 */

class Mkg_Importer_Block_Adminhtml_Car_Edit extends Mage_Adminhtml_Block_Widget_Form_Container
{
    public function __construct()
    {
        parent::__construct();

        $this->_objectId = 'id';
        $this->_blockGroup = 'importer';
        $this->_controller = 'adminhtml_car';

        $this->_updateButton('save', 'label', Mage::helper('importer')->__('Start import'));

        $this->_addButton('log', array(
            'label'   => 'Download Log',
            'onclick' => "setLocation('{$this->getUrl('*/*/log')}')",
            'class'   => 'add'
        ));

        $this->_removeButton('back');
        $this->_removeButton('reset');
    }

    public function getHeaderText()
    {
        return Mage::helper('importer')->__('Import Car Data');
    }
}
