<?php

include Mage::getBaseDir('lib') . DS . 'PHPExcel' . DS . 'PHPExcel.php';

class Mkg_Exporter_Adminhtml_Exporter_IndexController extends Mage_Adminhtml_Controller_action
{

    protected function _initAction()
    {
        $this->loadLayout()
            ->_setActiveMenu('catalog/exporter')
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
            $this->_exportFile($dataExport, $type);

        } elseif ($type == 2) {
            $dataExport = $this->_getAllAttributeInLastGroup();
            $this->_exportFile($dataExport, $type);
        }
        $this->_redirect('*/*/edit');
    }

    public function editAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('catalog/exporter');

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

    /*
     * Get all attributes in group(s) user created. (Condition: DO NOT HAVE ANY attribute system in group)
     * PARAM:
     * RETURN:  array with each line format {attribute set name, attribute group name, attribute code}
    */
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

    /*
     * Get all attributes in last groups.
     * PARAM:
     * RETURN:  array with each line format {attribute set name, attribute group name, attribute code}
    */
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

    /*
     * Export data to excel file.
     * PARAM:   $dataExport: array data to export
     *          $type: type is user export
     * RETURN:  void
    */
    protected function _exportFile($dataExport, $type)
    {
        $fileName = 'File_';
        if ($type === "1") {
            $fileName = 'Export-all-attribute-set_';
        } elseif ($type === "2") {
            $fileName = 'Export-last-group-set_';
        }

        $fileType = 'Excel5';
        $timestamp = strtotime(date("Y/m/d h:i:sa"));
        $fileName = $fileName . $timestamp . '.xls';

        $objPHPExcel = new PHPExcel();
        $objPHPExcel->setActiveSheetIndex(0);

        $objPHPExcel->getActiveSheet()
            ->setCellValue('A1', 'attribute_set_name')
            ->setCellValue('B1', 'attribute_group_name')
            ->setCellValue('C1', 'attribute_name');

        $i = 2;
        foreach ($dataExport as $value) {
            $objPHPExcel->getActiveSheet()
                ->setCellValue("A$i", $value[0])
                ->setCellValue("B$i", $value[1])
                ->setCellValue("C$i", $value[2]);
            $i++;
        }

        header('Content-Disposition: attachment;filename="' . $fileName . '"');
        $objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, $fileType);
        if (isset($objWriter)) {
            $objWriter->save('php://output');
        }
    }
}
