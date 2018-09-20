<?php
/**
 * Created by PhpStorm.
 * User: Nhut
 * Date: 5/24/16
 * Time: 8:49 AM
 */

include Mage::getBaseDir('lib') . DS . 'PHPExcel' . DS . 'PHPExcel.php';


class Mkg_Importer_Adminhtml_Importer_CarController extends Mage_Adminhtml_Controller_action
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
            ->_setActiveMenu('catalog/importer')
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

                    // Import car (module My Garage)
                    $i = $this->_importCar($path);
                    if (is_string($i)) {
                        throw new Exception($i);
                    }
                    
                    unlink($path);

                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('importer')->__('File was imported: %s row(s) effected', $i));
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
        $this->_title('Import Car');

        $this->getLayout()->getBlock('head')->setCanLoadExtJs(true);

        $this->_addContent($this->getLayout()->createBlock('importer/adminhtml_car_edit'))
            ->_addLeft($this->getLayout()->createBlock('importer/adminhtml_car_edit_tabs'));

        $this->renderLayout();
    }

    /**
     * Import car data. Form as following format (column order is not important):
     * brand_of_vehicle, model_of_vehicle, type_of_vehicle, year_of_vehicle, vehicle_code
     * Data must be filled in row #1 and in any column.
     *
     * @param $filePath string File Path
     *
     * @return int|string Return number of row imported | Error string
     */
    protected function _importCar($filePath)
    {
        $io = new Varien_Io_File();
        
        $startTime = microtime(true);
        $count = 0;
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
            $header = $sheet->rangeToArray('A1:' . $highestColumn . '1', null, true, true, true)[1];
            
            //Looking for brand
            $brandCol = array_search('brand_of_vehicle', $header);
            //Model
            $modelCol = array_search('model_of_vehicle', $header);
            //Type
            $typeCol = array_search('type_of_vehicle', $header);
            //Year
            $yearCol = array_search('year_of_vehicle', $header);
            //Car code
            $codeCol = array_search('vehicle_code', $header);
            
            
            if (!($brandCol && $modelCol && $typeCol && $yearCol && $codeCol)) {
                throw new Exception($this->__('Please check that form format must have these columns: brand_of_vehicle, 
				model_of_vehicle, type_of_vehicle, year_of_vehicle and vehicle_code'));
            }
            
            //Duyet tung dong
            for ($row = 2; $row <= $highestRow; $row++) {
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
                                                      $codeCol => 'vehicle_code',
                                                )
                );
                $rowData = $rowData[$row];
                
                $model = Mage::getModel('garage/car');
                $model->setId(null);
                $model->setYear(preg_replace('/( )$/', '', preg_replace('/^\s/', '', preg_replace('/(\s\s+|\n+)/s', ' ', $rowData['year_of_vehicle']))));
                $model->setMake(preg_replace('/( )$/', '', preg_replace('/^\s/', '', preg_replace('/(\s\s+|\n+)/s', ' ', $rowData['brand_of_vehicle']))));
                $model->setModel(preg_replace('/( )$/', '', preg_replace('/^\s/', '', preg_replace('/(\s\s+|\n+)/s', ' ', $rowData['model_of_vehicle']))));
                $model->setType(preg_replace('/( )$/', '', preg_replace('/^\s/', '', preg_replace('/(\s\s+|\n+)/s', ' ', $rowData['type_of_vehicle']))));
                $model->setCode(preg_replace('/( )$/', '', preg_replace('/^\s/', '', preg_replace('/(\s\s+|\n+)/s', ' ', $rowData['vehicle_code']))));
                $model->setImage(false);
                $model->setDescription('');
                $model->setStatus(Mkg_Garage_Model_Car::STATUS_ENABLED);
                $model->save();
                
                if ($model->getId()) {
                    $count++;
                }
            }
        } catch (Exception $e) {
            $this->logImport('Row ' . $row, sprintf('Failed: Car Import Error: ' . $e->getMessage()));
            $this->_redirect('*/*/new');
            return $e->getMessage();
        }
        
        $resultTime = microtime(true) - $startTime;
        Mage::getSingleton('adminhtml/session')->addSuccess($count . " car(s) imported successfully in " . gmdate('H:i:s', $resultTime));
        return $count;
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
