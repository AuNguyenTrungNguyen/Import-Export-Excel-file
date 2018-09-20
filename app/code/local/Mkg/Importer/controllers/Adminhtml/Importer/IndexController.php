<?php
/**
 * Created by PhpStorm.
 * User: Nhut
 * Date: 5/24/16
 * Time: 8:49 AM
 */

include Mage::getBaseDir('lib') . DS . 'PHPExcel' . DS . 'PHPExcel.php';


class Mkg_Importer_Adminhtml_Importer_IndexController extends Mage_Adminhtml_Controller_action
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
            ->_setActiveMenu('catalog/importer/import_attribute')
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Items Manager'), Mage::helper('adminhtml')->__('Item Manager'));

        return $this;
    }

    public function indexAction()
    {
        $this->_initAction()
            ->renderLayout();
    }

    protected function logImport($where, $error)
    {
        $f = Mage::getBaseDir('var') . DS . 'importer' . DS . 'log' . DS . 'log.txt';
        $time = time();
        file_put_contents($f, $time . ':' . $where . '     ' . $error . PHP_EOL, FILE_APPEND);
    }

    public function logAction()
    {
        $f = Mage::getBaseDir('var') . DS . 'importer' . DS . 'log' . DS . 'log.txt';

        $this->getResponse()
            ->setHttpResponseCode(200)
            ->setHeader('Pragma', 'public', true)
            ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
            ->setHeader('Content-type', 'application/force-download')
            ->setHeader('Content-Length', filesize($f))
            ->setHeader('Content-Disposition', 'inline' . '; filename=' . basename($f));
        $this->getResponse()->clearBody();
        $this->getResponse()->sendHeaders();
        readfile($f);
        return;
    }

    public function downloadExcelFileAction()
    {
        $io = new Varien_Io_File();
        $fileName = $this->getRequest()->getParam('file');

        $f = Mage::getBaseDir('var') . DS . 'importer' . DS . 'fixed_import_files' . DS . $fileName;
        if ($io->fileExists($f)) {
            $this->getResponse()
                ->setHttpResponseCode(200)
                ->setHeader('Pragma', 'public', true)
                ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
                ->setHeader('Content-type', 'application/force-download')
                ->setHeader('Content-Length', filesize($f))
                ->setHeader('Content-Disposition', 'inline' . '; filename=' . $fileName);
            $this->getResponse()->clearBody();
            $this->getResponse()->sendHeaders();
            readfile($f);

            //Remove file after download
            $io->rm($f);

            return;
        } else {
            Mage::getSingleton('adminhtml/session')->addError("File doesn't exist or was downloaded.");
            $this->_redirect('*/*/edit');
        }
    }

    public function importAction()
    {
        if ($data = $this->getRequest()->getPost()) {
            if (!isset($data['import_type'])) {
                $this->_redirect('*/*/edit');
                return;
            }

            if (isset($_FILES['filename']['name']) && $_FILES['filename']['name'] != '') {
                $logFile = Mage::getBaseDir('var') . DS . 'importer' . DS . 'log' . DS . 'log.txt';

                try {
                    if (file_exists($logFile)) {
                        unlink($logFile);
                    }

                    $uploader = new Varien_File_Uploader('filename');
                    $uploader->setAllowedExtensions(array('xls', 'xlsx'));
                    $uploader->setAllowRenameFiles(false);
                    $uploader->setFilesDispersion(false);

                    $path = Mage::getBaseDir('var') . DS . 'importer';

                    if (!file_exists($path)) {
                        mkdir($path, 0777);
                    }

                    $path .= DS;

                    $uploader->save($path, strtolower($_FILES['filename']['name']));

                    $path .= $uploader->getUploadedFileName();

                    $type = $data['import_type'];
                    $i = 0;
                    if ($type == 1) {                             // Import attribute
                        $i = $this->_importAttribute($path);
                    } elseif ($type == 2) {                       // Import attribute set
                        $i = $this->_importAttributeSet($path);
                    }

                    unlink($path);

                    if ($type != 5) {
                        if ($i == -1) {
                            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('importer')->__('Some attributes is not exist!'));
                        } else if ($i == -2) {
                            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('importer')->__('Duplicated Attribute set(s) in file import!'));
                        } else if ($i == -3) {
                            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('importer')->__('Duplicated Group(s) in a Attribute set(s)!'));
                        } else if ($i == -4) {
                            Mage::getSingleton('adminhtml/session')->addError(Mage::helper('importer')->__('Duplicated Attribute(s) in Group(s)!'));
                        } else if ($i == 0) {
                            Mage::getSingleton('adminhtml/session')->addWarning(Mage::helper('importer')->__('Nothing changes'));
                        } else {
                            Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('importer')->__('File was imported: %s row(s) effected', $i));
                        }
                    } else {
                        Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('importer')->__('File was fixed: %s row(s) effected', $i));
                    }
                } catch (Exception $e) {
                    Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                }


                $url = Mage::helper("adminhtml")->getUrl("*/*/log");
                if (file_exists($logFile)) {
                    Mage::getSingleton('adminhtml/session')->addNotice(sprintf('<a href="%s" >Download Log file</a>', $url));
                }
            }
        } else {
            Mage::getSingleton('adminhtml/session')->addError('Submit data error');
        }
        $this->_redirect('*/*/edit');
    }

    public function editAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('importer/items');

        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Item Manager'), Mage::helper('adminhtml')->__('Item Manager'));
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Item News'), Mage::helper('adminhtml')->__('Item News'));

        $this->getLayout()->getBlock('head')->setCanLoadExtJs(true);

        $this->_addContent($this->getLayout()->createBlock('importer/adminhtml_attribute_edit'))
            ->_addLeft($this->getLayout()->createBlock('importer/adminhtml_attribute_edit_tabs'));

        $this->renderLayout();
    }

    public function newAction()
    {
        $this->_forward('edit');
    }

    private function _importAttribute($filePath)
    {
        if (!file_exists($filePath)) {
            Mage::getSingleton('adminhtml/session')->addError('Can not import this file.');
            $this->_redirect('*/*/edit');
            return;
        }
        try {
            $inputFileType = PHPExcel_IOFactory::identify($filePath);
            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($filePath);
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError('Can not read file or invalid file type');
            $this->_redirect('*/*/edit');
            return;
        }

        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        $keys = $sheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0];

        $count = 0;
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false, false, $keys);
            $rowData = $rowData[0];

            if (isset($rowData['apply_to'])) {
                $rowData['apply_to'] = explode(';', $rowData['apply_to']);
            }
            if (isset($rowData['frontend_label'])) {
                //mode update value multistore
                // import frontend_label data in another store, that existed with admin frontend_label value in database
                if (isset($rowData['update_bystore']) && $rowData['update_bystore']) {
                    //if is update, update Front end label by store code
//                    $tmp='';
//                        $frontendLabels= explode(';', $rowData['frontend_label']);
//                        foreach ($frontendLabels as $frontendLabel){
//                            $frontendLabelData = explode('|', $frontendLabel);
//                            $storeId= Mage::app()->getStore($frontendLabelData[0])->getId();
//                            if($storeId||$frontendLabelData[0]=="admin"){
//                                 $tmp[$storeId]= ($frontendLabelData[1]);
//                            }
//                        }
//                    $rowData['frontend_label']=$tmp;
                    $tmp = '';
                    $frontendLabels = explode('|', $rowData['frontend_label']);
                    $storeId = Mage::app()->getStore($rowData['store_code'])->getId();
                    $tmp[0] = trim($frontendLabels[0]);
                    if ($storeId) {
                        $tmp[$storeId] = trim($frontendLabels[1]);
                    }
                    $rowData['frontend_label'] = $tmp;
                } else {
                    $rowData['frontend_label'] = explode(';', $rowData['frontend_label']);
                }
            }
            if (isset($rowData['option'])) {
                //mode update value multistore
                // import option data in another store, that existed with admin option value in database
                if (isset($rowData['update_bystore']) && $rowData['update_bystore']) {
                    $i = 0;
                    foreach (explode(';', $rowData['option']) as $op) {
                        $arrOption = '';
                        $tmp = explode('|', $op);
                        $storeId = Mage::app()->getStore($rowData['store_code'])->getId();
                        $arrOption[0] = trim($tmp[0]);
                        if ($storeId) {
                            $arrOption[$storeId] = trim($tmp[1]);
                        }
                        $options['option_' . $i] = $arrOption;
                        $i++;
                    }
                    $rowData['option'] = array('value' => $options);
                } else {
                    $options = array();
                    $i = 0;
                    foreach (explode(';', $rowData['option']) as $op) {
                        $options['option_' . $i] = array($op);
                        $i++;
                    }
                    $rowData['option'] = array('value' => $options);
                }
            }

            if (isset($rowData['attribute_id'])) {
                unset($rowData['attribute_id']);
            }
            if (isset($rowData['entity_type_id'])) {
                unset($rowData['entity_type_id']);
            }

            if ($this->_saveAttribute($rowData)) {
                $count++;
            }
        }
        return $count;
    }

    private function _saveAttribute($data)
    {
        if ($data) {
            /* @var $model Mage_Catalog_Model_Entity_Attribute */
            $model = Mage::getModel('catalog/resource_eav_attribute');
            /* @var $helper Mage_Catalog_Helper_Product */
            $helper = Mage::helper('catalog/product');

            $id = null;
            if (isset($data['attribute_id'])) {
                $id = $data['attribute_id'];
            }

            //validate attribute_code
            if (isset($data['attribute_code'])) {
                $validatorAttrCode = new Zend_Validate_Regex(array('pattern' => '/^[a-z][a-z_0-9]{1,254}$/'));
                if (!$validatorAttrCode->isValid($data['attribute_code'])) {
                    return false;
                }
                //update attribute
                $update = isset($data['update']) && $data['update'];
                $updateByStore = isset($data['update_bystore']) && $data['update_bystore'];
                if ($update || $updateByStore) {
                    $existModel = Mage::getModel('eav/entity_attribute')->getCollection()->addFieldToFilter('attribute_code', array('eq' => $data['attribute_code']));
                    if (count($existModel) > 0 && $existModel->getFirstItem()->getId()) {
                        $id = $existModel->getFirstItem()->getId();
                        $data['entity_type_id'] = $this->_entityTypeId;
                        $data['attribute_id'] = $id;
                        unset($data['attribute_code']);
                        unset($data['update']);
                    }
                }
            }

            //Test - delete all attributes
//            $existModel = Mage::getModel('eav/entity_attribute')->getCollection()->addFieldToFilter('attribute_code', array('eq' => $data['attribute_code']));
//            $existModel = $existModel->getFirstItem();
//
//            $model->load($existModel->getId());
//
//            try {
//                $model->delete();
//            } catch (Exception $e){
//                return false;
//            }
//            return true;


            //validate frontend_input
            if (isset($data['frontend_input'])) {
                /** @var $validatorInputType Mage_Eav_Model_Adminhtml_System_Config_Source_Inputtype_Validator */
                $validatorInputType = Mage::getModel('eav/adminhtml_system_config_source_inputtype_validator');
                if (!$validatorInputType->isValid($data['frontend_input'])) {
                    return false;
                }
            }

            if ($id) {
                $model->load($id);

                if (!$model->getId()) {
                    return false;
                }

                // entity type check
                if ($model->getEntityTypeId() != $this->_entityTypeId) {
                    return false;
                }

                $data['attribute_code'] = $model->getAttributeCode();
                $data['is_user_defined'] = $model->getIsUserDefined();
                $data['frontend_input'] = $model->getFrontendInput();

                if (($update || $updateByStore) && isset($data['frontend_label'])) {
                    if ($data['frontend_label'][0] != $model->getStoreLabel(0) && $updateByStore) {
                        Mage::getSingleton('adminhtml/session')->addError($this->__('Frontend Label: %s is not exist', $data['frontend_label'][0]));
                        return false;
                    }
                    $allStores = Mage::app()->getStores();
                    $storeIds = array();
                    foreach ($data['frontend_label'] as $key => $label) {
                        array_push($storeIds, $key);
                    }
                    foreach ($allStores as $_eachStoreId => $val) {
                        $tmp = Mage::app()->getStore($_eachStoreId)->getId();
                        if (!in_array($tmp, $storeIds)) {
                            $data['frontend_label'][$tmp] = $model->getStoreLabel($tmp);
                        }
                    }
                }
                // processing option value
                if ($update && isset($data['option'])) {
                    if ($model->usesSource()) {
                        //get all admin value option
                        $options = $model->getSource()->getAllOptions();
                        // change array value option to 1-dimensional array (label)
                        $attrValueLabels = $this->getAttrOptionLabel($options);
                        // change array value option import to 1-dimensional array (label)
                        $adminValueOption = $this->getAdminValueOption($data['option']['value']);
                        $arrValue = array();
                        // foreach in array admin value option import to check data
                        // if  admin array value option import not exist in database, add  value optionadmin to update
                        foreach ($adminValueOption as $option) {
//                                $data['option']['delete'][$option['value']] = '1';
//                                $data['option']['value'][$option['value']] = array($model->getSource()->getOptionText($option['value']));
                            if (!in_array($option, $attrValueLabels)) {
                                $i = array_search($option, $adminValueOption);
                                $arrValue['option_' . $i] = $data['option']['value']['option_' . $i];
                            }
                        }
                        $data['option']['value'] = $arrValue;
                    }
                } elseif ($updateByStore && isset($data['option'])) {
                    if ($model->usesSource()) {
                        //get all admin value option
                        $options = $model->getSource()->getAllOptions();
                        // change array value option import to 1-dimensional array (label)
                        $adminValueOption = $this->getAdminValueOption($data['option']['value']);
                        $arrValue = array();
                        // foreach in array admin value option in database to check data
                        // if   array value option in database  existed  in array value option import, add  value optionadmin to update data another store
                        foreach ($options as $option) {
                            if ($option['value']) {
                                if (in_array($option['label'], $adminValueOption)) {
                                    $i = array_search($option['label'], $adminValueOption);
//                                        $data['option']['value'][$option['value']] = $data['option']['value']['option_'.$i] ;
                                    $arrValue[$option['value']] = $data['option']['value']['option_' . $i];
//                                        unset($data['option']['value']['option_'.$i]);
                                }
//                                    else {
//                                        $data['option']['delete'][$option['value']] = '1';
//                                        $data['option']['value'][$option['value']] = array($model->getSource()->getOptionText($option['value']));
//                                    }
                            }
                        }
                        $data['option']['value'] = $arrValue;
                    }
                }
            } else {
                /**
                 * @todo add to helper and specify all relations for properties
                 */
                $data['source_model'] = $helper->getAttributeSourceModelByInputType($data['frontend_input']);
                $data['backend_model'] = $helper->getAttributeBackendModelByInputType($data['frontend_input']);
            }
            if (!isset($data['is_configurable'])) {
                $data['is_configurable'] = 0;
            }
            if (!isset($data['is_filterable'])) {
                $data['is_filterable'] = 0;
            }
            if (!isset($data['is_filterable_in_search'])) {
                $data['is_filterable_in_search'] = 0;
            }
            if (is_null($model->getIsUserDefined()) || $model->getIsUserDefined() != 0) {
                $data['backend_type'] = $model->getBackendTypeByInput($data['frontend_input']);
            }
            if (!isset($data['apply_to'])) {
                $data['apply_to'] = array();
            }
            $defaultValueField = $model->getDefaultValueByInput($data['frontend_input']);
            if ($defaultValueField) {
                $data['default_value'] = $this->getRequest()->getParam($defaultValueField);
            }

            //filter
            $data = $this->_filterPostData($data);
            $model->addData($data);

            if (!$id) {
                $model->setEntityTypeId($this->_entityTypeId);
                $model->setIsUserDefined(1);
            }

            try {
                if ($model->getStoreId() == null) {
                    $model->setStoreId(0);
                }
                $model->save();
                return true;
            } catch (Exception $e) {
                return false;
            }
        }
        return false;
    }

    public function getAttrOptionLabel($arrData)
    {
        $result = array();
        foreach ($arrData as $data) {
            if ($data['label']) {
                array_push($result, $data['label']);
            }
        }
        return $result;
    }

    public function getAdminValueOption($arrData)
    {
        $result = array();
        foreach ($arrData as $data) {
            array_push($result, $data[0]);
        }
        return $result;
    }

    /**
     * Filter post data
     *
     * @param array $data
     * @return array
     */
    protected function _filterPostData($data)
    {
        if ($data) {
            /** @var $helperCatalog Mage_Catalog_Helper_Data */
            $helperCatalog = Mage::helper('catalog');
            //labels
            foreach ($data['frontend_label'] as & $value) {
                if ($value) {
                    $value = $helperCatalog->stripTags($value);
                }
            }

            if (!empty($data['option']) && !empty($data['option']['value']) && is_array($data['option']['value'])) {
                foreach ($data['option']['value'] as $key => $values) {
                    $data['option']['value'][$key] = array_map(array($helperCatalog, 'stripTags'), $values);
                }
            }
        }
        return $data;
    }

    protected function _importAttributeSet($filePath)
    {
        if (!file_exists($filePath)) {
            Mage::getSingleton('adminhtml/session')->addError('Can not import this file.');
            $this->_redirect('*/*/edit');
            return;
        }
        try {
            $inputFileType = PHPExcel_IOFactory::identify($filePath);
            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($filePath);
        } catch (Exception $e) {
            Mage::getSingleton('adminhtml/session')->addError('Can not read file or invalid file type');
            $this->_redirect('*/*/edit');
            return;
        }

        $errorExist = false; //Check attribute not exist
        $allFile = array(); //Temp save all row

        $sheet = $objPHPExcel->getSheet(0);
        $highestRow = $sheet->getHighestRow();
        $highestColumn = $sheet->getHighestColumn();

        $keys = $sheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0];
        for ($row = 2; $row <= $highestRow; $row++) {
            $rowData = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false, false, $keys);
            $rowData = $rowData[0];

            if (!$rowData['skeleton_set'] || !$rowData['attribute_set_name']) {
                break;
            }

            $groups = $rowData['groups'];
            if ($groups) {
                $groups = explode('&', $groups);
            }

            $rowData['groups'] = array();
            $rowData['attributes'] = array();

            $continue = false;

            $groupIdSubfix = 1;

            if (is_array($groups)) {
                foreach ($groups as $group) {
                    $item = explode('=', $group);
                    $groupId = 'group_' . $groupIdSubfix;
                    $rowData['groups'][] = array($groupId, $item[0], 10 * $groupIdSubfix);

                    $item = count($item) > 1 ? explode(';', $item[1]) : array();

                    foreach ($item as $att) {
                        $existModel = Mage::getModel('eav/entity_attribute')->getCollection()->addFieldToFilter('attribute_code', array('eq' => $att));

                        if (count($existModel) > 0 && $existModel->getFirstItem()->getId()) {
                            $existModel = $existModel->getFirstItem();
                            //$rowData['attributes'][] = array($existModel->getId(), $groupId, $row);
                            //UPDATE CODE ADD $att to rowData['attribute'] ($att is attribute_code)
                            $rowData['attributes'][] = array($existModel->getId(), $groupId, $row, $att);
                        } else {
                            $errorExist = true;
                            $this->logImport('Row ' . $row, sprintf('Failed: Attribute <%s> is not exist', $att));
                            $continue = true;
                            break;
                        }
                    }
                    if ($continue) {
                        break;
                    }

                    $groupIdSubfix++;
                }
            }

            if ($continue) {
                continue;
            }

            $allFile[] = $rowData;
        }

        $all = count($allFile);

        /*Check file import is correct
            -1 not exist attribute
            -2 exist attribute_set_name
            -3 exist groups
            -4 exist attribute
        */
        //Check duplicated attribute_set_name
        for ($i = 0; $i < $all; $i++) {
            $name = $allFile[$i]['attribute_set_name'];
            for ($j = $i + 1; $j < $all; $j++) {
                if ($name === $allFile[$j]['attribute_set_name']) {
                    return -2;
                }
            }
        }

        //Check duplicated groups or attribute
        //List all row, in a row get a group and compare all groups (attribute the same)
        for ($i = 0; $i < $all; $i++) {
            $group = $allFile[$i]['groups'];
            $length = count($group);
            if ($length > 1) {
                for ($j = 0; $j < $length; $j++) {
                    $groupJ = $group[$j][1];
                    for ($k = $j + 1; $k < $length; $k++) {
                        if ($groupJ === $group[$k][1]) {
                            return -3;
                        }
                    }
                }
            }

            $attribute = $allFile[$i]['attributes'];
            $length = count($attribute);
            if ($length > 1) {
                for ($j = 0; $j < $length; $j++) {
                    $attributeJ = $attribute[$j][0];
                    for ($k = $j + 1; $k < $length; $k++) {
                        if ($attributeJ === $attribute[$k][0]) {
                            return -4;
                        }
                    }
                }
            }
        }

        //Check attribute(s) not exist
        if ($errorExist) {
            return -1;
        }

        $update = 0; //count number row updated
        $count = 0; //count number row imported
        for ($i = 0; $i < $all; $i++) {
            $isUpdate = false; //Check row is update or not
            $checkSetExist = $this->_checkAttributeSetIsExist($allFile[$i]);
            if ($checkSetExist === false) {
                $result = $this->_saveNewAttributeSet($allFile[$i]);
                if ($result === true) {
                    $count++;
                } else {
                    $this->logImport('Row ' . $row, $result);
                }
            } else {
                $isUpdate = $this->_checkGroupIsExist($checkSetExist, $allFile[$i]);
            }
            if ($isUpdate === true) {
                $update++;
            }
        }

        if ($update > 0) {
            Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('importer')->__('File was update: %s row(s) effected', $update));
        }

        return $count;
    }

    protected function _buildAttributeSetData($setId, $data)
    {
        $newGroups = array();
        $newAttributes = array();
        if (!isset($data['attributes'])) {
            $data['attributes'] = array();
        } else {
            foreach ($data['attributes'] as $a) {
                $newAttributes[] = $a[0];
            }
        }
        if (!isset($data['groups'])) {
            $data['groups'] = array();
        } else {
            foreach ($data['groups'] as $g) {
                $newGroups[] = trim(strtolower($g[1]));
            }
        }

        if (!isset($data['not_attributes'])) {
            $data['not_attributes'] = array();
        }
        if (!isset($data['removeGroups'])) {
            $data['removeGroups'] = array();
        }


        /* @var $groups Mage_Eav_Model_Mysql4_Entity_Attribute_Group_Collection */
        $groups = Mage::getModel('eav/entity_attribute_group')
            ->getResourceCollection()
            ->setAttributeSetFilter($setId)
            ->setSortOrder()
            ->load();
        /* @var $node Mage_Eav_Model_Entity_Attribute_Group */
        foreach ($groups as $node) {
            if (in_array(strtolower($node->getAttributeGroupName()), $newGroups)) {
                return false;
            }

            $item = array();
            $item[] = $node->getAttributeGroupId();
            $item[] = $node->getAttributeGroupName();
            $item[] = $node->getSortOrder();

            $nodeChildren = Mage::getResourceModel('catalog/product_attribute_collection')
                ->setAttributeGroupFilter($node->getId())
                ->addVisibleFilter()
                ->checkConfigurableProducts()
                ->load();

            if ($nodeChildren->getSize() > 0) {
                foreach ($nodeChildren->getItems() as $child) {
                    /* @var $child Mage_Eav_Model_Entity_Attribute */

                    if (in_array($child->getId(), $newAttributes)) {
                        return false;
                    }

                    $attr = array();
                    $attr[] = $child->getAttributeId();
                    $attr[] = $node->getId();
                    $attr[] = $child->getSortOrder();

                    $data['attributes'][] = $attr;
                }
            }

            $data['groups'][] = $item;
        }

        return $data;
    }

    protected function _getEntityTypeId()
    {
        return Mage::getModel('catalog/product')->getResource()->getTypeId();
    }

    protected function _saveNewAttributeSet($data)
    {
        $entityTypeId = $this->_getEntityTypeId();

        /* @var $model Mage_Eav_Model_Entity_Attribute_Set */
        $model = Mage::getModel('eav/entity_attribute_set')
            ->setEntityTypeId($entityTypeId);

        /** @var $helper Mage_Adminhtml_Helper_Data */
        $helper = Mage::helper('adminhtml');

        try {
            if (!isset($data['skeleton_set'])) {
                $data['skeleton_set'] = 'default';
            }
            $sets = Mage::getModel('eav/entity_attribute_set')
                ->getResourceCollection()
                ->setEntityTypeFilter($entityTypeId)
                ->addFieldToFilter('attribute_set_name', array('eq' => $data['skeleton_set']))
                ->load();

            if (count($sets) == 0) {
                return 'base on att set is not exist';
            }

            $name = $helper->stripTags($data['attribute_set_name']);
            $model->setAttributeSetName(trim($name));
            $model->validate();
            $model->save();

            $model->initFromSkeleton($sets->getFirstItem()->getId());
            $model->save();

            $data = $this->_buildAttributeSetData($model->getId(), $data);
            if (!$data) {
                $model->delete();
                return 'Duplicated group or attribute (Att set was created without adding att)';
            }

            return $this->_saveAttributeSet($model->getId(), $data);
        } catch (Mage_Core_Exception $e) {
            return $e->getMessage();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    protected function _saveAttributeSet($attributeSetId, $data)
    {
        $entityTypeId = $this->_getEntityTypeId();

        /* @var $model Mage_Eav_Model_Entity_Attribute_Set */
        $model = Mage::getModel('eav/entity_attribute_set')
            ->setEntityTypeId($entityTypeId);

        /** @var $helper Mage_Adminhtml_Helper_Data */
        $helper = Mage::helper('adminhtml');

        try {
            if ($attributeSetId) {
                $model->load($attributeSetId);
            }
            if (!$model->getId()) {
                return 'attribute was not created';
            }
            $data['attribute_set_name'] = $helper->stripTags($data['attribute_set_name']);

            $model->organizeData($data);

            $model->validate();
            $model->save();
            return true;
        } catch (Mage_Core_Exception $e) {
            return $e->getMessage();
        } catch (Exception $e) {
            return $e->getMessage();
        }
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/importer');
    }

    /*
     * Check attribute exist in database.
     * PARAM:   $data: a row in list data
     * RETURN:  if attribute set exist return ID of Attribute Set or return false.
    */
    protected function _checkAttributeSetIsExist($data)
    {
        $entityTypeId = $this->_getEntityTypeId();
        $model = Mage::getResourceModel('eav/entity_attribute_set_collection');
        $model->setEntityTypeFilter($entityTypeId);
        foreach ($model as $attributeSet) {
            $name = $attributeSet->getAttributeSetName();
            if ($name === $data['attribute_set_name']) {
                return $attributeSet->getId();
            }
        }
        return false;
    }

    /*
     * CASE:    attribute set is exist: function check groups is exist or not in a attribute set and update data
     * PARAM:   $id: id of attribute set
                $data: a row in list data
     * RETURN:  true if update attribute set or false
    */
    protected function _checkGroupIsExist($id, $data)
    {
        $groups = Mage::getModel('eav/entity_attribute_group')
            ->getResourceCollection()
            ->setAttributeSetFilter($id)
            ->setSortOrder()
            ->load();

        $isUpdate = false;

        for ($i = 0; $i < count($data['groups']); $i++) {
            $check = false; //check group is exist
            foreach ($groups as $node) {
                if ($node->getAttributeGroupName() === $data['groups'][$i][1]) {
                    $check = true;
                    break;
                }
            }

            $codeGroup = $data['groups'][$i][0];
            $nameGroup = $data['groups'][$i][1];
            $attCodes = array();
            //filter all attribute for group and add to list
            for ($j = 0; $j < count($data['attributes']); $j++) {
                if ($data['attributes'][$j][1] === $codeGroup) {
                    $attCodes[] = $data['attributes'][$j][3];
                }
            }

            //group is exist
            if ($check === true) {

                $updateAttribute = $this->_addAttributeToGroup($id, $nameGroup, $attCodes);
                if ($updateAttribute === true) {
                    $isUpdate = true;
                }

            } else { // group is not exist

                $updateGroup = $this->_addNewGroup($id, $nameGroup, $attCodes);
                if ($updateGroup === true) {
                    $isUpdate = true;
                }
            }
        }

        return $isUpdate;
    }

    /*
    * CASE:    group is not exist: create group and add all attribute to group
    * PARAM:   $attributeSetId: id of attribute set
               $attributeGroupName: name of the group
               $attributeCodes: list attribute in group
    * RETURN:  true if update attribute to group or false
   */
    protected function _addNewGroup($attributeSetId, $attributeGroupName, $attributeCodes)
    {
        $exec = false;
        //Create groups
        $installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
        $entityTypeId = $installer->getEntityTypeId('catalog_product');
        $installer->addAttributeGroup($entityTypeId, $attributeSetId, $attributeGroupName);

        //Add attribute to group
        $attributeGroupId = $installer->getAttributeGroupId($entityTypeId, $attributeSetId, $attributeGroupName);

        for ($i = 0; $i < count($attributeCodes); $i++) {
            $installer->addAttributeToGroup($entityTypeId, $attributeSetId, $attributeGroupId, $attributeCodes[$i], null);
            $exec = true;
        }
        return $exec;
    }

    /*
    * CASE:    group is exist: check all attribute and add to group if attribute not exist in group
    * PARAM:   $attributeSetId: id of attribute set
               $attributeGroupName: name of the group
               $attributeCodes: list attribute in group
    * RETURN:  true if update attribute to group or false
   */
    protected function _addAttributeToGroup($attributeSetId, $attributeGroupName, $attributeCodes)
    {
        $exec = false;
        $groups = Mage::getModel('eav/entity_attribute_group')
            ->getResourceCollection()
            ->setAttributeSetFilter($attributeSetId)
            ->setSortOrder()
            ->load();

        $attributeExist = array();
        foreach ($groups as $group) {
            $attributes = Mage::getResourceModel('catalog/product_attribute_collection')
                ->setAttributeGroupFilter($group->getId())
                ->addVisibleFilter()
                ->checkConfigurableProducts()
                ->load();
            if ($attributes->getSize() > 0 && $group->getAttributeGroupName() === $attributeGroupName) {
                foreach ($attributes->getItems() as $attribute) {
                    $attributeExist[] = $attribute->getAttributeCode();
                }
            }
        }

        $installer = Mage::getResourceModel('catalog/setup', 'catalog_setup');
        $entityTypeId = $installer->getEntityTypeId('catalog_product');
        $attributeGroupId = $installer->getAttributeGroupId($entityTypeId, $attributeSetId, $attributeGroupName);

        for ($i = 0; $i < count($attributeCodes); $i++) {
            $isAdd = true;
            for ($j = 0; $j < count($attributeExist); $j++) {
                if ($attributeCodes[$i] === $attributeExist[$j]) {
                    $isAdd = false;
                    break;
                }
            }

            if ($isAdd === true) {
                $installer->addAttributeToGroup($entityTypeId, $attributeSetId, $attributeGroupId, $attributeCodes[$i], null);
                $exec = true;
            }
        }

        return $exec;
    }
}
