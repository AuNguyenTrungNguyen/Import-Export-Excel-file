<?php

include Mage::getBaseDir('lib') . DS . 'PHPExcel' . DS . 'PHPExcel.php';


class Mkg_Importer_Adminhtml_Importer_FixController extends Mage_Adminhtml_Controller_action
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
            ->_addBreadcrumb(Mage::helper('adminhtml')->__('Items Manager'), Mage::helper('adminhtml')->__('Item Manager'));

        return $this;
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
        
        $io->cd(Mage::getBaseDir('var') . DS . 'importer' . DS . 'fixed_import_files');
        if ($io->fileExists($fileName)) {
            $this->getResponse()
                 ->setHttpResponseCode(200)
                 ->setHeader('Pragma', 'public', true)
                 ->setHeader('Cache-Control', 'must-revalidate, post-check=0, pre-check=0', true)
                 ->setHeader('Content-type', 'application/force-download')
                 ->setHeader('Content-Length', filesize($io->pwd() . DS . $fileName))
                 ->setHeader('Content-Disposition', 'inline' . '; filename=' . $fileName);
            $this->getResponse()->clearBody();
            $this->getResponse()->sendHeaders();
            readfile($io->pwd() . DS . $fileName);
            
            //Remove file after download
            $io->rm($fileName);
        } else {
            Mage::getSingleton('adminhtml/session')->addError("File doesn't exist or was downloaded.");
            $this->_redirect('*/*/new');
        }
        $io->__destruct();
        return;
    }

    public function importAction()
    {
        if ($data = $this->getRequest()->getPost()) {
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
                    
                    $i = $this->_fixFileImport($path);
                    if (is_string($i)) {
                        throw new Exception($i);
                    }

                    unlink($path);

                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('importer')->__('File was fixed: %s row(s) effected', $i));
                } catch (Exception $e) {
                    Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                }


                $url =  Mage::helper("adminhtml")->getUrl("*/*/log");
                if (file_exists($logFile)) {
                    Mage::getSingleton('adminhtml/session')->addNotice(sprintf('<a href="%s" >Download Log file</a>', $url));
                }
            }
        } else {
            Mage::getSingleton('adminhtml/session')->addError('Submit data error');
        }
        $this->_redirect('*/*/new');
    }

    public function newAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('catalog/importer');

        $this->getLayout()->getBlock('head')->setCanLoadExtJs(true);

        $this->_addContent($this->getLayout()->createBlock('importer/adminhtml_fix_edit'))
            ->_addLeft($this->getLayout()->createBlock('importer/adminhtml_fix_edit_tabs'));

        $this->renderLayout();
    }

    /**
     * Fix product import files
     * - from format Brand | Model | Type | Year
     * - to format car_model
     * Others attributes remain the same.
     *
     * Support 2 formats:
     * Each sku per row (types and years could be multiple valued)
     * Or: Sku can be duplicated in several rows (only years can be multiple valued)
     *
     * @param $filePath
     *
     * @return int|string return number of row processed or
     */
    protected function _fixFileImport($filePath)
    {
        $startRow = 1;
        $io = new Varien_Io_File();
        
        $startTime = microtime(true);
        try {
            //Check file exists
            if (!$io->fileExists($filePath)) {
                throw new Exception($this->__('Can not import this file.'));
            }
            
            //Read excel file
            $inputFileType = PHPExcel_IOFactory::identify($filePath);
            $objReader = PHPExcel_IOFactory::createReader($inputFileType);
            $objPHPExcel = $objReader->load($filePath);
                        
            $sheet = $objPHPExcel->getSheet(0);
            $highestRow = $sheet->getHighestRow();
            $highestColumn = $sheet->getHighestColumn();
            
            //Get headers of sheet
            $header = $sheet->rangeToArray("A{$startRow}:{$highestColumn}{$startRow}", null, true, true, true)[$startRow];
            
            //Looking for brand
            $brandCol = array_search('brand_of_vehicle', $header);
            //Model
            $modelCol = array_search('model_of_vehicle', $header);
            //Type
            $typeCol = array_search('type_of_vehicle', $header);
            //Year
            $yearCol = array_search('year_of_vehicle', $header);
            //Sku
            $skuCol = array_search('sku', $header);
            
            //Check if there is no car attributes column (wrong form format)
            if (!($brandCol && $modelCol && $typeCol && $yearCol && $skuCol)) {
                throw new Exception($this->__('There is no column containing car attributes in uploaded excel file.'));
            }
            
            //Insert new col TODO: check max column
            $codeCol = max($brandCol, $modelCol, $typeCol, $yearCol);
            ++$codeCol;
            
            $sheet->insertNewColumnBefore($codeCol);
            $sheet->setCellValue($codeCol . $startRow, Mkg_Garage_Model_Car::ATTRIBUTE_CODE);
            
            $processedCount = 0;
            $totalRow = 0;
            $skuArr = array();
            
            //Duyet tung dong
            for ($row = $startRow+1; $row <= $highestRow; $row++) {
                $rowData = $sheet->rangeToArray(
                    "A{$row}:" . $highestColumn . $row,
                    null,
                    true,
                    true,
                    true,
                    array($brandCol => 'brand_of_vehicle',
                                                      $typeCol  => 'type_of_vehicle',
                                                      $modelCol => 'model_of_vehicle',
                                                      $yearCol  => 'year_of_vehicle',
                                                      $skuCol  => 'sku',
                                                )
                );
                $rowData = $rowData[$row];
                
                $codes = array();
//              $brand = $rowData['brand_of_vehicle'];
//              $model = $rowData['model_of_vehicle'];
                
                // Multiple values processing
                $years = explode(',', $rowData['year_of_vehicle']);
                $types = explode(',', $rowData['type_of_vehicle']);
                $brands = explode(',', $rowData['brand_of_vehicle']);
                $models = explode(',', $rowData['model_of_vehicle']);
                
                $skuArr[$row] = $rowData['sku'];
                
                if (!empty($brands)) {
                    foreach ($brands as $brand) {
                        $brand = mb_ereg_replace('^( )*', '', $brand);
                        $brand = mb_ereg_replace('( )*$', '', $brand);
                        
                        foreach ($models as $model) {
                            $model = mb_ereg_replace('^( )*', '', $model);
                            $model = mb_ereg_replace('( )*$', '', $model);
                            
                            foreach ($types as $type) {
                                $type = mb_ereg_replace('^( )*', '', $type);
                                $type = mb_ereg_replace('( )*$', '', $type);
                                
                                foreach ($years as $year) {
                                    $year = mb_ereg_replace('^( )*', '', $year);
                                    $year = mb_ereg_replace('( )*$', '', $year);
                                    
                                    $carCollection = Mage::getModel('garage/car')->getCollection();
                                    $carCollection
                                        ->addFieldToFilter('make', array('eq' => $brand))
                                        ->addFieldToFilter('model', array('eq' => $model))
                                        ->addFieldToFilter('type', array('eq' => $type))
                                        ->addFieldToFilter('year', array('eq' => $year));
                                    $code = $carCollection->getFirstItem()->getCode();
                                    
                                    if ($code != '') {
                                        $codes[] = $code;
                                    }
                                }
                            }
                        }
                    }
                    if (count($codes)) {
                        $processedCount++;
                        $sheet->setCellValue($codeCol . $row, implode(',', $codes));
                    }
                    
                    //If 2nd sku appears, and it's the same with the previous one
                    if ($skuArr[$row-1] == $skuArr[$row]) {
                        //Get this row car_model attribute
                        $finalCM = array();
                        $finalCM = array_merge(explode(',', $sheet->getCell($codeCol.($row-1))->getValue()), $finalCM);
                        $finalCM = array_merge(explode(',', $sheet->getCell($codeCol.$row)->getValue()), $finalCM);
//                      $finalCM[] = $sheet->getCell($codeCol.($row-1))->getValue();
//                      $finalCM[] = $sheet->getCell($codeCol.$row)->getValue();
                        $finalCM = array_unique($finalCM);
                        //Set previous car_model with this row value too
                        $sheet->setCellValue($codeCol.($row-1), implode(',', $finalCM));
                        //Then delete this row
                        $sheet->removeRow($row);
                        $totalRow--;
                        $row--;
                    }
                    $totalRow++;
                }
            }
            
            //Delete those 4 attr
            $sheet->removeColumn($this->_getColumnIndexByName($sheet, 'brand_of_vehicle', $startRow));
            $sheet->removeColumn($this->_getColumnIndexByName($sheet, 'type_of_vehicle', $startRow));
            $sheet->removeColumn($this->_getColumnIndexByName($sheet, 'model_of_vehicle', $startRow));
            $sheet->removeColumn($this->_getColumnIndexByName($sheet, 'year_of_vehicle', $startRow));
            
            //Check file output exists, if not, mkdir it
            if (!$io->fileExists(Mage::getBaseDir('var') . DS . 'importer' . DS . 'fixed_import_files')) {
                $io->mkdir(Mage::getBaseDir('var') . DS . 'importer' . DS . 'fixed_import_files');
            }
            //Output File Path: Magento_Dir/var/importer/fixed_import_files/fixed_file_name.xlsx
            $outputFilePath = Mage::getBaseDir('var') . DS . 'importer' . DS . 'fixed_import_files' . DS . 'fixed_' . basename($filePath);
            
            $writer = PHPExcel_IOFactory::createWriter($objPHPExcel, $inputFileType);
            $writer->save($outputFilePath);
        } catch (Exception $e) {
            if (isset($row)) {
                $this->logImport('Row ' . $row, sprintf('Failed: Import File Process Error: ' . $e->getMessage()));
            }
            $this->_redirect('*/*/new');
            return $e->getMessage();
        }
        
        $resultTime = microtime(true) - $startTime;
        Mage::getSingleton('adminhtml/session')->addSuccess($processedCount . " row(s) / total of {$totalRow} row(s) processed successfully in " . gmdate('H:i:s', $resultTime));
        
        $url =  Mage::helper("adminhtml")->getUrl("*/*/downloadExcelFile", array('file' => basename($outputFilePath)));
        Mage::getSingleton('adminhtml/session')->addNotice(sprintf('<a href="%s" >Download Output file</a>', $url));
        
        return $processedCount;
    }
    
    protected function _getColumnIndexByName($sheet, $name, $startRow = 1)
    {
        $highestColumn = $sheet->getHighestColumn();
        
        //Get headers of sheet
        $header = $sheet->rangeToArray("A{$startRow}:{$highestColumn}{$startRow}", null, true, true, true)[$startRow];
        
        //Looking for tha name
        return array_search($name, $header);
    }

    protected function _isAllowed()
    {
        return Mage::getSingleton('admin/session')->isAllowed('catalog/importer');
    }
}
