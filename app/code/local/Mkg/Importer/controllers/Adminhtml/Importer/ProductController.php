<?php
/**
 * Created by PhpStorm.
 * User: user
 * Date: 7/6/2016
 * Time: 9:44 AM
 */
include Mage::getBaseDir('lib') . DS . 'PHPExcel' . DS . 'PHPExcel.php';

/**
 * Class Mkg_Importer_Adminhtml_Importer_ProductController
 */
class Mkg_Importer_Adminhtml_Importer_ProductController extends Mage_Adminhtml_Controller_action
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

    public function editAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('importer/items');

        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Item Manager'), Mage::helper('adminhtml')->__('Item Manager'));
        $this->_addBreadcrumb(Mage::helper('adminhtml')->__('Item News'), Mage::helper('adminhtml')->__('Item News'));

        $this->getLayout()->getBlock('head')->setCanLoadExtJs(true);
        /*$logFile = Mage::getBaseDir('var') . DS . 'importer' . DS . 'log' . DS . 'log.txt';*/

        $this->_addContent($this->getLayout()->createBlock('importer/adminhtml_product_edit'))
          ->_addLeft($this->getLayout()->createBlock('importer/adminhtml_product_edit_tabs'));

        $this->renderLayout();
    }

    public function newAction()
    {
        $this->_forward('edit');
    }


    public function logAction()
    {
        $f = Mage::getBaseDir('var') . DS . 'importer' . DS . 'log' . DS . 'product_log.txt';

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

    private function _getFile($filename)
    {
        return Mage::getBaseDir('var') . DS . 'importer' . DS . $filename;
    }

    public function uploadAction()
    {
        if ($data = $this->getRequest()->getPost()) {
            if (isset($_FILES['filename']['name']) && $_FILES['filename']['name'] != '') {
                try {
                    $uploader = new Varien_File_Uploader('filename');
                    $uploader->setAllowedExtensions(array('xls', 'xlsx', 'csv'));
                    $uploader->setAllowRenameFiles(true);
                    $uploader->setFilesDispersion(false);

                    $path = Mage::getBaseDir('var') . DS . 'importer' . DS . 'import';

                    if (!file_exists($path)) {
                        mkdir($path, 0777);
                    }
                    $path .= DS;
                    $filename = $_FILES['filename']['name'];
                    $name = pathinfo($filename, PATHINFO_FILENAME);
                    $ext = pathinfo($filename, PATHINFO_EXTENSION);
                    $uploader->save($path, date("Y-m-d_h-i-sa") . strtolower($name) . "." . $ext);
                    $path .= $uploader->getUploadedFileName();

                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('importer')->__('File was upload'));
                } catch (Exception $e) {
                    Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                }
            }
        } else {
            Mage::getSingleton('adminhtml/session')->addError('Submit data error');
        }
        $this->_redirect('*/*/edit');
    }

    protected function runAction()
    {
        $this->loadLayout();
        if ($filename = Mage::app()->getRequest()->getParam('file')) {
            $logFile = Mage::getBaseDir('var') . DS . 'importer' . DS . 'log' . DS . 'product_log.txt';
            if (file_exists($logFile)) {
                unlink($logFile);
            }

            $file = $this->_getFile("import" . DS . $filename);
            if (!file_exists($file)) {
                print_r($this->__('Can not import this file.'));
                return;
            }

            try {
                $inputFileType = PHPExcel_IOFactory::identify($file);
                $objReader = PHPExcel_IOFactory::createReader($inputFileType);
                $objPHPExcel = $objReader->load($file);
            } catch (Exception $e) {
                Mage::getSingleton('adminhtml/session')->addError($this->__('Can not read or invalid file type'));
                return;
            }

            $sheet = $objPHPExcel->getSheet(0);
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestDataColumn();

            $keys = $sheet->rangeToArray('A1:' . $highestColumn . '1', null, true, false)[0];

            $numSuccess = 0;

            $response = array();
            for ($row = 2; $row <= $highestRow; $row++) {
                $rowData = $sheet->rangeToArray('A' . $row . ':' . $highestColumn . $row, null, true, false, false, $keys);
                $rowData[0]['image-replace'] = Mage::app()->getRequest()->getParam('image-replace');
                $dataImport = $rowData[0];
                $result = $this->importProductAction($dataImport);
                if (strpos($result, $this->__('Import success')) >= 0) {
                    $numSuccess++;
                }
                $response[] = $this->__('Row') . ' ' . $row . ': ' . $result;
            }
            $response[] = $this->__('Imported') . ': ' . $numSuccess . '/' . ($highestRow - 1);
            Mage::register('response', $response);
        }

        $this->renderLayout();
    }

    private function _getImagePath($image)
    {
        return Mage::getBaseDir('media') . DS . 'importer' . DS . $image;
    }

    protected function _getEntityTypeId()
    {
        return Mage::getModel('catalog/product')->getResource()->getTypeId();
    }

    protected function getStatus($label)
    {
        switch ($label) {
            case 'Enabled':
                return 1;
                break;
            case 'Disabled':
                return 2;
                break;
            default:
                return 1;
                break;
        }
    }

    protected function getWebsite($arrWebsite)
    {
        $websiteModel = Mage::getResourceModel('core/website_collection');
        $websiteArray = explode(' , ', $arrWebsite);
        return $websiteModel
          ->addFieldToFilter('code', array('in' => $websiteArray))
          ->getAllIds();
    }

    protected function getVisibility($visibility)
    {
        switch ($visibility) {
            case 'None':
                return 1;
                break;
            case 'Catalog':
                return 2;
                break;
            case 'Search':
                return 3;
                break;
            case 'Catalog, Search':
                return 4;
                break;
            default:
                return 4;
                break;
        }
    }

    protected function getAttributeSetId($attSet)
    {
        $entityTypeId = Mage::getModel('eav/entity')
          ->setType('catalog_product')
          ->getTypeId();
        return Mage::getModel('eav/entity_attribute_set')
          ->getCollection()
          ->setEntityTypeFilter($entityTypeId)
          ->addFieldToFilter('attribute_set_name', $attSet)
          ->getFirstItem()
          ->getAttributeSetId();
    }


    protected function getConfigAttribute($superAttributeCode)
    {
        $attributeModel = Mage::getResourceModel('eav/entity_attribute');
        $arrAttribute = explode(' , ', $superAttributeCode);
        $superAttribute = array();
        $i = 0;
        foreach ($arrAttribute as $value) {
            $superAttribute['id'][$i] = $attributeModel
              ->getIdByCode('catalog_product', $value);
            $superAttribute['code'][$i] = $value;
            $i++;
        }
        return $superAttribute;
    }

    protected function setImageGallery($product)
    {
        $image = '';
        $thumbnail = '';
        $smallImage = '';
        if (isset($product['image'])) {
            $image = $product['image'];
        }
        if (isset($product['thumbnail'])) {
            $thumbnail = $product['thumbnail'];
        }
        if (isset($product['small_image'])) {
            $smallImage = $product['small_image'];
        }

        if (isset($product['gallery'])) {
            $mediaGalleryData = explode(';', $product['gallery']);
            foreach ($mediaGalleryData as $mediaItem) {
                $mediaItemArray = explode(' , ', $mediaItem);
                $mediaType = array();
                if ($mediaItemArray[0] == $image) {
                    $mediaType[] = 'image';
                }
                if ($mediaItemArray[0] == $thumbnail) {
                    $mediaType[] = 'thumbnail';
                }
                if ($mediaItemArray[0] == $smallImage) {
                    $mediaType[] = 'small_image';
                }
                $product->addImageToMediaGallery($this->_getImagePath($mediaItemArray[0]), $mediaType, false, false, $mediaItemArray[1]);
            }
        }
    }

    protected function setConfigData($configData)
    {
        if (isset($configData)) {
            if (!isset($configData['store_id'])) {
                $configData['store_id'] = Mage_Catalog_Model_Abstract::DEFAULT_STORE_ID;
            }
            if (isset($configData['status'])) {
                $configData['status'] = $this->getStatus($configData['status']);
            }
            if (isset($configData['websites'])) {
                $configData['website_ids'] = $this->getWebsite($configData['websites']);
            }
            if (isset($configData['visibility'])) {
                $configData['visibility'] = $this->getVisibility($configData['visibility']);
            }

            if (isset($configData['category_ids'])) {
                $configData['category_ids'] = explode(' , ', $configData['category_ids']);
            }
            if (isset($configData['price'])) {
                $configData['price'] = sprintf("%0.2f", $configData['price']);
            }
        }
        return $configData;
    }

    private function _getAssociatedProduct($productId, $attConfig, $store, &$mess, $products = null)
    {
        $productModel = Mage::getResourceModel('catalog/product');
        $oldSuperProduct = Mage::getModel('catalog/product_type_configurable')
          ->getChildrenIds($productId);
        $sameOptArr = array();
        if ($productId) {
            //echeck same product in old product
            foreach ($oldSuperProduct[0] as $oldId) {
                $optIds = array();
                foreach ($attConfig as $attCode) {
                    $optValue = $productModel->getAttributeRawValue($oldId, $attCode, $store);
                    if ($optValue) {
                        $optIds[] = $optValue;
                    }
                }
                if (count($optIds) == count($attConfig)) {
                    array_push($sameOptArr, implode(',', $optIds));
                }
            }
        }


        $superProductsSkus = array();
        if (isset($products)) {
            $superProductsSkus = explode(' , ', $products);
        }
        $configurableProductsData = array();
        foreach ($superProductsSkus as $sku) {
            $id = $productModel->getIdBySku($sku);

            // Check simple product have belonged another config product.
            $parentId = Mage::getModel('catalog/product_type_configurable')->getParentIdsByChild($id);
            if (!empty($parentId) && $productId && !in_array($productId, $parentId)) {
                $mess[] = $this->__('can not save %s in super_products_sku because it have belonged another config product', $sku);
                continue;
            }

            $optIds = array();
            foreach ($attConfig as $attCode) {
                $optValue = $productModel->getAttributeRawValue($id, $attCode, $store);
                if ($optValue) {
                    $optIds[] = $optValue;
                }
            }
            if ($id && !in_array($id, $oldSuperProduct[0])
              && !empty($optIds) && (count($optIds) == count($attConfig))
              && !in_array(implode(',', $optIds), $sameOptArr)
            ) {
                $configurableProductsData[$id] = array();
                array_push($sameOptArr, implode(',', $optIds));
            } else {
                $mess[] = $this->__('can not save %s in super_products_sku (check attribute)', $sku);
            }
        }
        return $configurableProductsData;
    }

    protected function logImport($where, $error)
    {
        $f = Mage::getBaseDir('var') . DS . 'importer' . DS . 'log' . DS . 'product_log.txt';
        $time = time();
        file_put_contents($f, $time . ':' . $where . '     ' . $error . PHP_EOL, FILE_APPEND);
    }

    protected function getConfigurableAttributes($productId)
    {
        $product = Mage::getModel('catalog/product')->load($productId);
        $attributes = $product->load($productId)->getTypeInstance(true)->
        getConfigurableAttributesAsArray($product);
        $atts = array();
        foreach ($attributes as $att) {
            $atts[] = $att['attribute_code'];
        }
        return $atts;
    }

    protected function importProductAction($data)
    {
        //validate data
        $product = Mage::getModel('catalog/product');
        $message = array();
        if ($data) {
            /* $importData = $this->setConfigData($data);*/
            /* set data of import file to $product*/
            $product->setData($this->setConfigData($data));
            $productId = $product->getIdBySku($product->getSku());

            //validate sku and product type
            if (!isset($product['sku'])) {
                $message[] = $this->__('invalid sku');
            }
            if (!isset($product['type_id']) && !$productId) {
                $message[] = $this->__('invalid product type');
            }
            if (!isset($product['attribute_set'])) {
                if (!$productId) {
                    $message[] = $this->__('invalid attribute set');
                } else {
                    $sets = Mage::getModel('catalog/product')->load($productId)['attribute_set_id'];
                }
            } else /* if(isset($product['attribute_set']))*/ {
                $sets = $this->getAttributeSetId($product['attribute_set']);
                if (count($sets) < 1) {
                    $message[] = $this->__('attribute set is not exist');
                }
            }

            try {
                if (empty($message) && isset($sets)) {
                    //set Attribute select and multiselect
                    $attAlongSet = Mage::getModel('eav/entity_attribute')->getCollection()
                      ->setAttributeSetFilter($sets);
                    $attAlongSet
                      ->addFieldtofilter('frontend_input', array('select', 'multiselect', 'date'));
                    $attResource = Mage::getModel('catalog/product')->getResource();
                    foreach ($attAlongSet as $att_item) {
                        $attCode = $att_item->getAttributeCode();
                        $attType = $att_item->getFrontendInput();
                        $attSource = $attResource->getAttribute($attCode)->getSource();
                        if (isset($product[$attCode]) && $attCode != 'status' && $attCode != 'visibility') {
                            if ($attType == 'date') {
                                $valuesId = date('Y-m-d', strtotime($product[$attCode]));
                            } elseif ($attType === 'multiselect') {
                                $valuesText = array_unique(explode(' , ', $product[$attCode]));
                                $valuesId = array_filter(array_map(array($attSource, 'getOptionId'), $valuesText));
                            } else {
                                $valuesText = $product[$attCode];
                                $valuesId = $attSource->getOptionId($valuesText);
                            }
                            $product->setData($attCode, $valuesId);
                        }
                    }
                    //set product type
                    $productType = Mage::getModel('catalog/product')->load($productId)->getTypeID();

                    if (!isset($product['type_id']) || ($productId && isset($product['type_id']) && ($productType !== $product['type_id']))) {
                        $product['type_id'] = $productType;
                    } else {
                        $productType = $product['type_id'];
                    }

                    if ($productType == 'configurable') {
                        //new product
                        if (!$productId) {
                            if (isset($sets)) {
                                $product->setAttributeSetId($sets);
                            }
                            /*set attribute for config product*/
                            if (!isset($product['super_attribute_code'])) {
                                return $this->__('Invalid super attribute');
                            }
                            $attributesConfig = $this->getConfigAttribute($product['super_attribute_code']);
                            $attribute_ids = $attributesConfig['id'];
                            $attribute_codes = $attributesConfig['code'];
                            $attributes = array();
                            foreach ($attAlongSet as $attribute) {
                                $attributes[] = $attribute['attribute_id'];
                            }
                            //   check super attribute can apply
                            foreach ($attribute_ids as $attribute_id) {
                                $attribute = Mage::getModel('eav/entity_attribute')->load($attribute_id);
                                $applyTo = $attribute->getApplyTo();
                                if ($attribute->getIsGlobal() != Mage_Catalog_Model_Resource_Eav_Attribute::SCOPE_GLOBAL
                                    || !$attribute->getIsConfigurable()
                                    || (count($applyTo) > 0 && !in_array($productType, $applyTo))) {
                                    return $this->__('Supper attributes does not applicable');
                                }
                            }
                            if (count(array_diff($attribute_ids, $attributes)) !== 0) {
                                return $this->__('Attributes does not exist');
                            }
                            $attributes_array = $product->getTypeInstance()
                              ->setUsedProductAttributeIds($attribute_ids)
                              ->getConfigurableAttributesAsArray();
                            // Add it back to the configurable product..
                            $product->setCanSaveConfigurableAttributes(true);
                            $product->setConfigurableAttributesData($attributes_array);
                        } else {
                            $attribute_codes = $this->getConfigurableAttributes($productId);
                        }

                        //set simple product associated
                        if (isset($product['super_products_sku'])) {
                            $products = $product['super_products_sku'];
                            $product->setConfigurableProductsData($this->_getAssociatedProduct($productId, $attribute_codes, $product['store_id'], $message, $products));
                        }
                    } elseif ($productType == "simple" && !$productId) {
                        $product->setAttributeSetId($sets);
                    }

                    /**
                     * Create Permanent Redirect for old URL key
                     */
                    if ($productId && isset($product['url_key_create_redirect'])) {
                        $product->setData('save_rewrites_history', (bool)$product['url_key_create_redirect']);
                    }

                    /**
                     * Use URL key default
                     */
                    if ($productId && isset($product['url_key']) && $product['url_key'] === '***') {
                        $product->setData('url_key', false);
                    }

                    //delete old image
                    if ($product['image-replace'] && $productId) {
                        $mediaApi = Mage::getModel("catalog/product_attribute_media_api");
                        $mediaItems = $mediaApi->items($productId);
                        if (count($mediaItems) > 0) {
                            $io = new Varien_Io_File();
                            foreach ($mediaItems as $mediaItem) {
                                $_imgFile = Mage::getBaseDir('media') . DS . 'catalog' . DS . 'product' . DS . $mediaItem['file'];
                                if ($io->fileExists($_imgFile)) {
                                    $io->rm($_imgFile);
                                }

                                $mediaApi->remove($productId, $mediaItem['file']);
                            }
                        }
                    }

                    //add image
                    $this->setImageGallery($product);

                    //stock data
                    $stockItem = Mage::getModel('cataloginventory/stock_item')
                      ->loadByProduct($product->loadByAttribute('sku', $product->getSku()));
                    $stockData = array(
                      'manage_stock'            => isset($product['manage_stock']) ? $product['manage_stock'] : $stockItem['manage_stock'],
                      'is_in_stock'             => isset($product['is_in_stock']) ? $product['is_in_stock'] : $stockItem['is_in_stock'],
                      'use_config_manage_stock'
                                                => isset($product['enable_qty_increments']) ? $product['enable_qty_increments'] : $stockItem['enable_qty_increments'],
                      'qty'                     => isset($product['qty']) ? $product['qty'] : $stockItem['qty'],
                      'min_sale_qty'            => isset($product['min_sale_qty']) ? $product['min_sale_qty'] : $stockItem['min_sale_qty'],
                      'max_sale_qty'            => isset($product['max_sale_qty']) ? $product['max_sale_qty'] : $stockItem['max_sale_qty'],
                      'use_config_max_sale_qty' => isset($product['use_config_max_sale_qty']) ? $product['use_config_max_sale_qty'] : $stockItem['use_config_max_sale_qty'],
                      'use_config_min_sale_qty' => isset($product['use_config_min_sale_qty']) ? $product['use_config_min_sale_qty'] : $stockItem['use_config_min_sale_qty'],
                    );
                    $product->setStockData($stockData);
                    $stockItem->delete();

                    //import relate product
                    if (isset($product['relate_products_sku'])) {
                        $relate_sku = array_unique(explode(' , ', $product['relate_products_sku']));
                        $relateSku = array();
                        foreach ($relate_sku as $relateItem) {
                            if ($product->getIdBySku($relateItem)) {
                                $relateSku[$product->getIdBySku($relateItem)] = array();
                            }
                        }
                        $product->setRelatedLinkData($relateSku);
                    }


                    //up sells product
                    if (isset($product['up_sells_products_sku'])) {
                        $up_sell_sku = array_unique(explode(' , ', $product['up_sells_products_sku']));
                        $upSellSku = array();
                        foreach ($up_sell_sku as $upSellItem) {
                            if ($product->getIdBySku($upSellItem)) {
                                $upSellSku[$product->getIdBySku($upSellItem)] = array();
                            }
                        }
                        $product->setUpSellLinkData($upSellSku);
                    }

                    //cross sell product
                    if ($product['cross_sells_products_sku']) {
                        $cross_sell_sku = array_unique(explode(' , ', $product['cross_sells_products_sku']));
                        $crossSellSku = array();
                        foreach ($cross_sell_sku as $crossSellItem) {
                            if ($product->getIdBySku($crossSellItem)) {
                                $crossSellSku[$product->getIdBySku($crossSellItem)] = array();
                            }
                        }
                        $product->setCrossSellLinkData($crossSellSku);
                    }
                    if ($product['category_ids']) {
                        $category_ids = array_unique($product['category_ids']);
                        $product->setCategoryIds($category_ids);
                    }
                    if ($product['tier_price']) {
                        $tierPrices = (explode(',', $product['tier_price']));
                        if (count($tierPrices)) {
                            $tierPriceArr = '';
                            foreach ($tierPrices as $tierPrice) {
                                $data = explode('|', $tierPrice);
                                $tierPriceArr[] = array(
                                  'website_id' => Mage::app()->getWebsite($data[0])->getId(),
                                  'cust_group' => ($data[1] == "ALL GROUPS") ? Mage_Customer_Model_Group::CUST_GROUP_ALL : Mage::app()->getModel('customer/group')->load($data[1], 'customer_group_code'),
                                  'price_qty'  => $data[2],
                                  'price'      => $data[3],
                                  'delete'     => '',
                                );
                            }
                            $table = Mage::getSingleton('core/resource')->getTableName('catalog/product') . '_tier_price';
                            Mage::getSingleton('core/resource')->getConnection('core_write')->query("DELETE FROM $table WHERE entity_id = $productId");
                            $product->setTierPrice($tierPriceArr);
                        }
                    }

                    if (!isset($product['created_at'])) {
                        $product['created_at'] = Mage::getModel('catalog/product')->load($productId)->getCreatedAt();
                    }

                    $product->save();
                    $message[] = $this->__("Import success");
                } else {
                    $this->logImport($this->__('Row') . ' ' . $product['row'], implode(', ', $message));
                }
            } catch (Exception $e) {
                return $e->getMessage();
            }
        }
        return implode(', ', $message);
    }

    public function downloadExportFileAction()
    {
        $fileName = $this->getRequest()->getParam('exportfile');
        $f = Mage::getBaseDir('var') . DS . 'importer' . DS . 'export' . DS . $fileName;

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

    protected function exportAction()
    {
        $this->loadLayout();
        $response = array();
        $sku = Mage::app()->getRequest()->getParam('sku');
        $stt = Mage::app()->getRequest()->getParam('stt');
        $att_set = Mage::app()->getRequest()->getParam('atts');
        $product_type = Mage::app()->getRequest()->getParam('prot');
        $visibility = Mage::app()->getRequest()->getParam('vis');
        $productModel = Mage::getModel('catalog/product');
        $productCollection = $productModel->getCollection();
        if (isset($sku)) {
            $productCollection->addFieldToFilter('sku', array('eq' => $sku));
            array_push($response, "Sku : " . $sku);
        }
        if (isset($stt)) {
            $productCollection->addFieldToFilter('status', array('eq' => $stt));
            array_push($response, "Status : " . $stt);
        }
        if (isset($att_set)) {
            $productCollection->addFieldToFilter('attribute_set_id', array('eq' => $att_set));
            array_push($response, "Attribute set : " . $att_set);
        }
        if (isset($product_type)) {
            $productCollection->addFieldToFilter('type_id', array('eq' => $product_type));
            array_push($response, "Product type : " . $product_type);
        }
        if (isset($visibility)) {
            $productCollection->addFieldToFilter('visibility', array('eq' => $visibility));
            array_push($response, "Visibility : " . $visibility);
        }

        $data = array();
        $attributeList = Mage::getModel('catalog/product_attribute_api')->items($att_set);
        array_push(
            $attributeList,
            array('code' => 'image', 'type' => ' text'),
            array('code' => 'small_image', 'type' => ' text'),
            array('code' => 'thumbnail', 'type' => ' text'),
            array('code' => 'relate_products_sku', 'type' => ' text'),
            array('code' => 'up_sells_products_sku', 'type' => ' text'),
            array('code' => 'cross_sells_products_sku', 'type' => ' text'),
            array('code' => 'super_attribute_code', 'type' => ' text'),
            array('code' => 'super_products_sku', 'type' => ' text'),
            array('code' => 'manage_stock', 'type' => ' text'),
            array('code' => 'use_config_manage_stock', 'type' => ' text'),
            array('code' => 'enable_qty_increments', 'type' => ' text'),
            array('code' => 'use_config_enable_qty_increments', 'type' => ' text'),
            array('code' => 'is_in_stock', 'type' => ' text'),
            array('code' => 'qty', 'type' => ' text'),
            array('code' => 'websites', 'type' => ' text'),
            array('code' => 'category_ids', 'type' => ' text'),
            array('code' => 'store_id', 'type' => ' text'),
            array('code' => 'type_id', 'type' => ' text')
        );

        unset($attributeList[array_search('tier_price', array_column($attributeList, 'code'))]);
        unset($attributeList[array_search('group_price', array_column($attributeList, 'code'))]);

        $csvHeader = array();
        foreach ($attributeList as $att) {
            $csvHeader[$att['code']] = $att['code'];
        }
        $directory = $this->_getFile("export");
        if (!file_exists($directory)) {
            mkdir($directory, 0777, true);
        }
        $linkFile = $directory . DS . 'export' . date("Y-m-d_h-i-sa") . '.csv';
        $fopen = fopen($linkFile, 'w');
        fputcsv($fopen, $csvHeader, ",");

        $count = 0;
        foreach ($productCollection as $product) {
            $productData = $productModel->load($product->getId())->getData();
            foreach ($attributeList as $att_item) {
                if (isset($att_item['type']) && isset($productData[$att_item['code']])) {
                    if ($att_item['type'] == 'multiselect' || $att_item['type'] == 'select') {
                        $attOpt = $product->getResource()->getAttribute($att_item['code'])->getSource()
                          ->getOptionText($productData[$att_item['code']]);
                        $data[$att_item['code']] = implode(' , ', (array)$attOpt);
                    } else {
                        $data[$att_item['code']] = $productData[$att_item['code']];
                    }
                } else {
                    $data[$att_item['code']] = '';
                }
            }
            //set website, category and store
            $websiteData = $product->getWebsiteIds();
            $websiteCodes = array();
            foreach ($websiteData as $web_value) {
                $websiteCodes[] = Mage::app()->getWebsite($web_value)->getCode();
            }
            $data['websites'] = implode(' , ', $websiteCodes);
            //set store and category
            $data['category_ids'] = implode(' , ', $product->getCategoryIds());
            $data['store_id'] = implode(' , ', $product->getStoreIds());

            //set stock
            $stockData = Mage::getModel('cataloginventory/stock_item')
              ->loadByProduct($product)->getData();

            $data['manage_stock'] = $stockData['manage_stock'];
            $data['use_config_manage_stock'] = $stockData['use_config_manage_stock'];
            $data['enable_qty_increments'] = $stockData['enable_qty_increments'];
            $data['use_config_enable_qty_increments'] = $stockData['use_config_enable_qty_increments'];
            $data['is_in_stock'] = $stockData['is_in_stock'];
            $data['qty'] = $stockData['qty'];
            //relate product
            $relateProduct = Mage::getResourceModel('catalog/product')->getProductsSku($product->getRelatedProductIds());
            $relateSkus = array();
            foreach ($relateProduct as $relateItem) {
                $relateSkus[] = $relateItem['sku'];
            }
            $data['relate_products_sku'] = implode(' , ', $relateSkus);

            $upsellProduct = Mage::getResourceModel('catalog/product')->getProductsSku($product->getUpSellProductIds());
            $upsellSkus = array();
            foreach ($upsellProduct as $upsellItem) {
                $upsellSkus[] = $upsellItem['sku'];
            }
            $data['up_sells_products_sku'] = implode(' , ', $upsellSkus);

            //get cross sell product
            $crosssellProduct = Mage::getResourceModel('catalog/product')->getProductsSku($product->getCrossSellProductIds());
            $crosssellSkus = array();
            foreach ($crosssellProduct as $crosssellItem) {
                $crosssellSkus[] = $crosssellItem['sku'];
            }
            $data['cross_sells_products_sku'] = implode(' , ', $crosssellSkus);
            //get data for config product
            if ($product->getTypeId() == 'configurable') {
                $superAttribute = $product->getTypeInstance(true)->getConfigurableAttributesAsArray($product);
                $superAtt = array();
                foreach ($superAttribute as $itemAtt) {
                    $superAtt[] = $itemAtt['attribute_code'];
                }
                $data['super_attribute_code'] = implode(' , ', $superAtt);

                //associate product
                $associateProduct = Mage::getModel('catalog/product_type_configurable')->getUsedProducts(null, $product);
                $associateSku = array();
                foreach ($associateProduct as $assItem) {
                    $associateSku[] = $assItem['sku'];
                }
                $data['super_products_sku'] = implode(' , ', $associateSku);
            }
            fputcsv($fopen, $data, ",");
            $count++;
        }
        fclose($fopen);
        $response[] = "Export successfully " . $count . "/" . $productCollection->getSize();

        /*download file export*/
        $url = Mage::helper("adminhtml")->getUrl("*/*/downloadExportFile", array('exportfile' => basename($linkFile)));
        $response[] = "<a href=$url >Download Export file</a>";

        Mage::register('response', $response);
        $this->renderLayout();
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/importer');
    }
}
