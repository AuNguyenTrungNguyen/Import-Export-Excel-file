<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 7/6/2016
 * Time: 11:24 AM
 */
class Mkg_Importer_Block_Adminhtml_Product_Edit_Tab_Import extends Mage_Adminhtml_Block_Widget_Form
{
    public function __construct()
    {
        parent::__construct();
        $this->setTemplate('importer/import.phtml');
    }

    public function getImportedFiles()
    {
        $listFile = Mage::helper("importer")->dirFiles(Mage::getBaseDir().DS.'var'.DS.'importer'.DS.'import');
        return $listFile;
    }

    public function getRunButtonHtml()
    {
        $html = '';
        $html .= $this->getLayout()->createBlock('adminhtml/widget_button')->setType('button')
            ->setClass('save')->setLabel($this->__('Run Profile in Popup'))
            ->setOnClick('runProfile(true)')
            ->toHtml();

        return $html;
    }
}
