<?php
/**
 * Created by PhpStorm.
 * User: Nhut
 * Date: 5/24/16
 * Time: 8:50 AM
 */

class Mkg_Exporter_Block_Adminhtml_Attribute extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
        $this->_controller = 'adminhtml_index';
        $this->_blockGroup = 'exporter';
        $this->_headerText = Mage::helper('exporter')->__('Exporter');
        $this->_addButtonLabel = Mage::helper('exporter')->__('Exporter');
        parent::__construct();
    }
}