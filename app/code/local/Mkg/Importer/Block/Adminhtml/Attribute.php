<?php
/**
 * Created by PhpStorm.
 * User: Nhut
 * Date: 5/24/16
 * Time: 8:50 AM
 */

class Mkg_Importer_Block_Adminhtml_Attribute extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
        $this->_controller = 'adminhtml_index';
        $this->_blockGroup = 'importer';
        $this->_headerText = Mage::helper('importer')->__('Exporter');
        $this->_addButtonLabel = Mage::helper('importer')->__('Exporter');
        parent::__construct();
    }
}
