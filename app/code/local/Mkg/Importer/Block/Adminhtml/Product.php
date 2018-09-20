<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 7/6/2016
 * Time: 9:53 AM
 */

class Mkg_Importer_Block_Adminhtml_Product extends Mage_Adminhtml_Block_Widget_Grid_Container
{

    public function __construct()
    {
        $this->_controller = 'adminhtml_product';
        $this->_blockGroup = 'importer';
        $this->_headerText = Mage::helper('importer')->__('Exporter');
        $this->_addButtonLabel = Mage::helper('importer')->__('Exporter');
        parent::__construct();
    }
}
