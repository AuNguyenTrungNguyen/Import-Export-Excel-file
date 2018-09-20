<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 7/13/2016
 * Time: 2:44 PM
 */
class Mkg_Importer_Block_Adminhtml_Product_Run extends Mage_Core_Block_Template
{
    public function __construct()
    {
        parent::__construct();
        // Used for AJAX loading
        $this->setSaveParametersInSession(true);
        $this->setUseAjax(true);
    }

    public function getDataImport()
    {
        return Mage::registry('data');
    }

    /*public function getRunButtonHtml()
    {
        $html = '';
        $html .= $this->getLayout()->createBlock('adminhtml/widget_button')->setType('button')
            ->setClass('save')->setLabel($this->__('Download Log File'))
            ->setOnClick('downloadLog(true)')
            ->toHtml();

        return $html;
    }*/
}
