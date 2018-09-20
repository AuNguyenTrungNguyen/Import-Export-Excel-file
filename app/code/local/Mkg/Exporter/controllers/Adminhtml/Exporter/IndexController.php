<?php
/**
 * Created by PhpStorm.
 * User: Nhut
 * Date: 5/24/16
 * Time: 8:49 AM
 */

include Mage::getBaseDir('lib') . DS . 'PHPExcel' . DS . 'PHPExcel.php';


class Mkg_Exporter_Adminhtml_Exporter_IndexController extends Mage_Adminhtml_Controller_action
{

    protected $_entityTypeId;

    public function preDispatch()
    {
        parent::preDispatch();
        $this->_entityTypeId = Mage::getModel('eav/entity')->setType(Mage_Catalog_Model_Product::ENTITY)->getTypeId();
    }

    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('catalog/exporter/export_attribute')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Items Manager'), Mage::helper('adminhtml')->__('Item Manager'));

        return $this;
    }

    public function indexAction()
    {
        $this->_initAction()
            ->renderLayout();
    }

    public function exportAction()
    {
        $data = $this->getRequest()->getPost();

        if (!isset($data['export_type'])) {
            $this->_redirect('*/*/edit');
            return;
        }

        $type = $data['export_type'];
        if ($type == 1) {
            $dataExport = $this->_getAllAttribute();
            $check = $this->_exportFile($dataExport, $type);

            if ($check === true) {
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('exporter')->__('Export all attributes is success!'));
            } else {
                Mage::getSingleton('adminhtml/session')->addError(Mage::helper('exporter')->__('Export file is error!'));
            }

        } elseif ($type == 2) {
            $dataExport = $this->_getAllAttributeInLastGroup();
            $check = $this->_exportFile($dataExport, $type);

            if ($check === true) {
                Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('exporter')->__('Export all attributes in last group is success!'));
            } else {
                Mage::getSingleton('adminhtml/session')->addError(Mage::helper('exporter')->__('Export file is error!'));
            }
        }

        $this->_redirect('*/*/edit');
    }

    public function editAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('exporter/items');

        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Item Manager'), Mage::helper('adminhtml')->__('Item Manager'));
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Item News'), Mage::helper('adminhtml')->__('Item News'));

        $this->getLayout()->getBlock('head')->setCanLoadExtJs(true);

        $this->_addContent($this->getLayout()->createBlock('exporter/adminhtml_attribute_edit'))
            ->_addLeft($this->getLayout()->createBlock('exporter/adminhtml_attribute_edit_tabs'));

        $this->renderLayout();
    }

    public function newAction()
    {
        $this->_forward('edit');
    }

    protected function _getEntityTypeId()
    {
        return Mage::getModel('catalog/product')->getResource()->getTypeId();
    }

    protected function _getAllAttribute()
    {
        $entityTypeId = $this->_getEntityTypeId();
        $dataExport = array();

        //GET ALL SETS
        $model = Mage::getResourceModel('eav/entity_attribute_set_collection');
        $model->setEntityTypeFilter($entityTypeId);

        foreach ($model as $attributeSet) {
            $nameSet = $attributeSet->getAttributeSetName();
            $idSet = $attributeSet->getAttributeSetID();

            //GET ALL GROUPS IN A SET
            $groups = Mage::getModel('eav/entity_attribute_group')
                ->getResourceCollection()
                ->setAttributeSetFilter($idSet)
                ->setSortOrder()
                ->load();

            foreach ($groups as $group) {
                $nameGroup = $group->getAttributeGroupName();
                $idGroup = $group->getId();
                $arrayGroup = array();

                //GET ALL ATTRIBUTE IN A GROUP
                $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
                    ->setAttributeGroupFilter($idGroup)
                    ->addVisibleFilter()
                    ->checkConfigurableProducts()
                    ->load();

                foreach ($attributes->getItems() as $attribute) {
                    $attributeCode = $attribute->getAttributeCode();
                    $isUserDefined = $attribute->getIsUserDefined();
                    $arrayGroup[] = array($nameSet, $nameGroup, $attributeCode);

                    if ($isUserDefined === "0") {
                        $arrayGroup = array();
                        break;
                    }
                }

                if (count($arrayGroup) > 0) {
                    foreach ($arrayGroup as $item) {
                        $dataExport[] = $item;
                    }
                }

            }
        }

        return $dataExport;
    }

    protected function _getAllAttributeInLastGroup()
    {
        $entityTypeId = $this->_getEntityTypeId();
        $dataExport = array();

        //GET ALL SETS
        $model = Mage::getResourceModel('eav/entity_attribute_set_collection');
        $model->setEntityTypeFilter($entityTypeId);

        foreach ($model as $attributeSet) {
            $nameSet = $attributeSet->getAttributeSetName();
            $idSet = $attributeSet->getAttributeSetID();

            //GET ALL GROUPS IN A SET
            $groups = Mage::getModel('eav/entity_attribute_group')
                ->getResourceCollection()
                ->setAttributeSetFilter($idSet)
                ->setSortOrder()
                ->load();

            $idLastGroup = 0;
            $nameLastGroup = '';
            $sortOrderHighest = 0;
            foreach ($groups as $group) {
                $nameGroup = $group->getAttributeGroupName();
                $idGroup = $group->getId();
                $sortOrder = $group->getSortOrder();

                if ($sortOrder > $sortOrderHighest) {
                    $idLastGroup = $idGroup;
                    $nameLastGroup = $nameGroup;
                }
            }

            //GET ALL ATTRIBUTE IN A GROUP
            $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
                ->setAttributeGroupFilter($idLastGroup)
                ->addVisibleFilter()
                ->checkConfigurableProducts()
                ->load();

            foreach ($attributes->getItems() as $attribute) {
                $attributeCode = $attribute->getAttributeCode();
                $dataExport[] = array($nameSet, $nameLastGroup, $attributeCode);
            }
        }
        return $dataExport;
    }

    protected function _exportFile($dataExport, $type)
    {

        $pathDir = Mage::getBaseDir('base') . DS . 'exporter';
        if (!file_exists($pathDir)) {
            if (!mkdir($pathDir)) {
                return false;
            }
        }

        $fileName = 'File_';
        if ($type === 1){
            $fileName = 'Export-all-attribute-set_';
        }elseif ($type === 2){
            $fileName = 'Export-last-group-set_';
        }

        $fileType = 'Excel2007';
        $timestamp = strtotime(date("Y/m/d h:i:sa"));
        $fileName = $fileName .  $timestamp . '.xlsx';
        $path = $pathDir . '\\' . $fileName;
        if (!file_exists($path)) {
            if (!fopen($path, 'w')) {
                return false;
            }
        }
        $objPHPExcel = PHPExcel_IOFactory::load($path);

        $objPHPExcel->setActiveSheetIndex(0)
            ->setCellValue('A1', "attribute_set_name")
            ->setCellValue('B1', "attribute_group_name")
            ->setCellValue('C1', "attribute_name");

        $i = 2;
        foreach ($dataExport as $value) {
            $objPHPExcel->setActiveSheetIndex(0)
                ->setCellValue("A$i", $value[0])
                ->setCellValue("B$i", $value[1])
                ->setCellValue("C$i", $value[2]);
            $i++;
        }

        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, $fileType);
        $objWriter->save($path);
        return true;
    }
}
