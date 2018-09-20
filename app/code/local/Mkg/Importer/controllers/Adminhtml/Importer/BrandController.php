<?php
/**
 * Created by PhpStorm.
 * User: thevinh
 * Date: 4/21/17
 * Time: 9:01 AM
 */

include Mage::getBaseDir('lib') . DS . 'PHPExcel' . DS . 'PHPExcel.php';


class Mkg_Importer_Adminhtml_Importer_BrandController extends Mage_Adminhtml_Controller_Action
{
    
    protected $_logFilename = 'brand.txt';
    
    /**
     * Download log file.
     *
     */
    public function logAction()
    {
        $f = $this->_getLogFile();
        
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
    
    /**
     * Return current log file (w/ full path), set by $this->_logFilename
     * @return string
     */
    protected function _getLogFile()
    {
        return Mage::getBaseDir('var') . DS . 'importer' . DS . 'log' . DS . $this->_logFilename;
    }
    
    public function newAction()
    {
        $this->loadLayout();
        $this->_setActiveMenu('catalog/importer');
        $this->_title('Import Brand');
        
        $this->getLayout()->getBlock('head')->setCanLoadExtJs(true);
        
        $this->_addContent($this->getLayout()->createBlock('importer/adminhtml_brand_edit'))
             ->_addLeft($this->getLayout()->createBlock('importer/adminhtml_brand_edit_tabs'));
        
        $this->renderLayout();
    }
    
    public function importAction()
    {
        if ($data = $this->getRequest()->getPost()) {
            if (isset($_FILES['filename']['name']) && $_FILES['filename']['name'] != '') {
                $logFile = $this->_getLogFile();
                
                $io = new Varien_Io_File();
                
                try {
                    if ($io->fileExists($logFile)) {
                        unlink($logFile);
                    }
                    
                    $uploader = new Varien_File_Uploader('filename');
                    $uploader->setAllowedExtensions(array('xls', 'xlsx'));
                    $uploader->setAllowRenameFiles(false);
                    $uploader->setFilesDispersion(false);
                    
                    $path = Mage::getBaseDir('var') . DS . 'importer';
                    
                    if (!$io->fileExists($path)) {
                        $io->mkdir($path, 0777);
                    }
                    
                    $path .= DS;
                    
                    $uploader->save($path, strtolower($_FILES['filename']['name']));
                    
                    $path .= $uploader->getUploadedFileName();
                    
                    // Import car (module My Garage)
                    $i = $this->_importBrand($path);
                    if (is_string($i)) {
                        throw new Exception($i);
                    }
                    
                    unlink($path);
                    
                    Mage::getSingleton('adminhtml/session')->addSuccess(Mage::helper('importer')->__(
                        'File was imported: %s row(s) effected',
                        $i
                    ));
                } catch (Exception $e) {
                    Mage::getSingleton('adminhtml/session')->addError($e->getMessage());
                    $this->logImport('Row ' . 0, sprintf('Failed: Brand Import Error: ' . $e->getMessage()));
                }
                
                $url = Mage::helper("adminhtml")->getUrl("*/*/log");
                if ($io->fileExists($logFile)) {
                    Mage::getSingleton('adminhtml/session')->addNotice(sprintf(
                        '<a href="%s" >Download Log file</a>',
                        $url
                    ));
                }
            }
        } else {
            Mage::getSingleton('adminhtml/session')->addError('Submit data error');
        }
        $this->_redirect('*/*/new');
    }
    
    /**
     * Import brand data. Form is same with product import form (column order is not important):
     *
     * Data must be filled in row #1
     *
     * @param $filePath string File Path
     *
     * @return int|string Return number of row imported | Error string
     */
    protected function _importBrand($filePath)
    {
        $startTime = microtime(true);
        $io = new Varien_Io_File();
        $mediaDir = Mage::getBaseDir('media') . DS;
        $mediaImportSrcDir = $mediaDir . 'importer' . DS;
        
        $count = 0;
        try {
            //Check file exists
            if (!$io->fileExists($filePath)) {
                throw new Exception($this->__('File doesn\'t exist, or was not successfully uploaded.'));
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
            
            // 0. Go to each row
            for ($row = 2; $row <= $highestRow; $row++) {
                // Get our current row
                $rowData = $sheet->rangeToArray("A{$row}:" . $highestColumn . $row, null, true, true, true, $header);
                $rowData = $rowData[$row];
                
                // There must be at least store_id and id fields, if not, show exception
                if (!isset($rowData['identifier']) || !isset($rowData['store_id'])) {
                    throw new Exception($this->__('Please ensure that the uploaded form has at least store_id and identifier columns.'));
                }
                
                // 1. Get the brand model
                $model = Mage::getModel('brand/entity')->setStoreId($rowData['store_id']);
                $model->load($rowData['identifier'], 'identifier');
                
                // 2a. If there is an ID --> Update brands (multi store value or update batch brands)
                if ($model->getId()) {
                    // Set scope for the brand updating
//                    $model;
                    
                    // For each of store and website fields,
                    // if there is no data, or (there is data but data == ***), set use default.
                    // otherwise, set multi store values
                    foreach ($this->_getSWFields() as $field) {
                        if (!isset($rowData[$field]) || (isset($rowData[$field]) && $rowData[$field] == '***')) {
                            $model->setData($field . '_default', '1');
                            $model->unsetData($field);
                        } else {
                            if ($field == 'logo' || $field == 'banner') {
                                // Copy images (logo, banner)
                                // Check if file to be copied doesn't exist and has been successfully copied
                                if ($rowData[$field] = $this->_copyFile(
                                    $mediaImportSrcDir . $rowData[$field],
                                    $mediaDir . $rowData[$field]
                                )
                                ) {
                                    if ($rowData['store_id'] == '0' || $model->getData($field . '_in_stores')) {
                                        Mage::helper('brand')->deleteImageFile($model->getData($field));
                                    }
                                    $model->setData($field, substr(strrchr($rowData[$field], "/"), 1));
                                }
                            } else {
                                $model->setData($field, $rowData[$field]);
                            }
                        }
                    }
                    
                    // Update video (only store 0)
                    if ($rowData['store_id'] == '0' && isset($rowData['videos'])) {
                        $model->setData('videos', $rowData['videos']);
                    }
                    if ($rowData['store_id'] == '0' && isset($rowData['meta_keyword'])) {
                        $model->setData('meta_keyword', $rowData['meta_keyword']);
                    }
                    if ($rowData['store_id'] == '0' && isset($rowData['meta_description'])) {
                        $model->setData('meta_description', $rowData['meta_description']);
                    }
                    
                    $model->save();
                    
                    /*            Custom links import process               */
                    // Get data (content, url and target)
                    $brandCustomLinksContentArr = isset($rowData['custom_links_content']) ? explode(
                        ' , ',
                        $rowData['custom_links_content']
                    ) : null;
                    $brandCustomLinksUrlArr = isset($rowData['custom_links_url']) ? explode(
                        ' , ',
                        $rowData['custom_links_url']
                    ) : null;
                    $brandCustomLinksTargetArr = isset($rowData['custom_links_target']) ? explode(
                        ' , ',
                        $rowData['custom_links_target']
                    ) : null;
                    
                    // If this is update store 0 value
                    if ($rowData['store_id'] == '0') {
                        // Remove all current bannerslider/custom links and then add new from file
                        foreach ($model->getCustomlinks() as $cl) {
                            $cl->delete();
                        }
                        
                        if (isset($brandCustomLinksContentArr) && count($brandCustomLinksContentArr)) {
                            foreach ($brandCustomLinksContentArr as $it => $link) {
                                if (!empty($link)) {
                                    $cl = Mage::getModel('brand/link');
                                    // Clear spaces
                                    $link = preg_replace(
                                        '/( )$/',
                                        '',
                                        preg_replace('/^\s/', '', preg_replace('/(\s\s+|\n+)/s', ' ', $link))
                                    );
                                    $cl->setData('inner_html', $link);
        
                                    if (isset($brandCustomLinksUrlArr[$it])) {
                                        $cl->setData('href', $brandCustomLinksUrlArr[$it]);
                                    }
        
                                    if (isset($brandCustomLinksTargetArr[$it])) {
                                        $cl->setData('target', $brandCustomLinksTargetArr[$it]);
                                    }
        
                                    $cl->setStoreId($rowData['store_id']);
                                    $cl->setBrandId($model->getId());
                                    $cl->setSortOrder($it);
                                    $cl->save();
                                }
                            }
                        }
                    } else {
                        $it = 0;
                        // For each of current brand's custom links,
                        // if there is no any data, or (there is data but data == ***), set use default
                        // Otherwise, update link
                        foreach ($model->getCustomlinks() as $k => $cl) {
                            if (!isset($brandCustomLinksContentArr[$it]) || (isset($brandCustomLinksContentArr[$it]) && $brandCustomLinksContentArr[$it] == '***')) {
                                $cl->setData('inner_html_default', true);
                                $cl->setData('href_default', true);
                                $cl->setData('target_default', true);
                                $cl->setStoreId($rowData['store_id']);
                                $cl->save();
                            } else {
                                $link = $brandCustomLinksContentArr[$it];
                                if (!empty($link)) {
                                    // Clear spaces
                                    $link = preg_replace(
                                        '/( )$/',
                                        '',
                                        preg_replace('/^\s/', '', preg_replace('/(\s\s+|\n+)/s', ' ', $link))
                                    );
                                    $cl->setData('inner_html', $link);
                
                                    if (isset($brandCustomLinksUrlArr[$it])) {
                                        $cl->setData('href', $brandCustomLinksUrlArr[$it]);
                                    }
                
                                    if (isset($brandCustomLinksTargetArr[$it])) {
                                        $cl->setData('target', $brandCustomLinksTargetArr[$it]);
                                    }
                
                                    $cl->setStoreId($rowData['store_id']);
                                    $cl->save();
                                }
                            }
                            $it++;
                        }
                    } //end else store = 0 (update default store value or HK store value)
                        
                    
                    // Brand bannerslider import process
                    $brandBannersliderImgArr = isset($rowData['bannerslider_image']) ? explode(
                        ' , ',
                        $rowData['bannerslider_image']
                    ) : null;
                    $brandBannersliderUrlArr = isset($rowData['bannerslider_url']) ? explode(
                        ' , ',
                        $rowData['bannerslider_url']
                    ) : null;
                    $brandBannersliderTargetArr = isset($rowData['bannerslider_target']) ? explode(
                        ' , ',
                        $rowData['bannerslider_target']
                    ) : null;
    
                    if ($rowData['store_id'] == '0') {
                        foreach ($model->getBannerslider() as $bs) {
                            $bs->delete();
                        }
    
                        if (isset($brandBannersliderImgArr) && count($brandBannersliderImgArr)) {
                            foreach ($brandBannersliderImgArr as $it => $img) {
                                if (!empty($img)) {
                                    $bs = Mage::getModel('brand/image');
                
                                    // Clear space before and after string
                                    $img = preg_replace(
                                        '/( )$/',
                                        '',
                                        preg_replace('/^\s/', '', preg_replace('/(\s\s+|\n+)/s', ' ', $img))
                                    );
                
                                    if ($img = $this->_copyFile($mediaImportSrcDir . $img, $mediaDir . $img)) {
                                        $bs->setData('file_name', substr(strrchr($img, "/"), 1));
                                        $bs->setData('url', $brandBannersliderUrlArr[$it]);
                                        $bs->setData('target', $brandBannersliderTargetArr[$it]);
                    
                                        $bs->setStoreId($rowData['store_id']);
                                        $bs->setBrandId($model->getId());
                                        $bs->setSortOrder($it);
                                        $bs->save();
                                    }
                                }
                            }
                        }
                    } else {
                        $it = 0;
                        foreach ($model->getBannerslider() as $k => $bs) {
                            if (!isset($brandBannersliderImgArr[$it]) ||
                                (isset($brandBannersliderImgArr[$it]) && $brandBannersliderImgArr[$it] == '***')
                            ) {
                                // if there is bannerslider data like file_name_in_store
                                // --> this has had already another images in store config
                                // --> update img and del the old one.
                                if ($bs->getData('file_name_in_store')) {
                                    Mage::helper('brand')->deleteImageFile($bs->getData('file_name'));
                                }
                                $bs->setData('file_name_default', true);
                                $bs->setData('url_default', true);
                                $bs->setData('target_default', true);
                                $bs->setStoreId($rowData['store_id']);
                                $bs->save();
                            } else {
                                $img = $brandBannersliderImgArr[$it];
                                if (!empty($img)) {
                                    // Clear space before and after string
                                    $img = preg_replace(
                                        '/( )$/',
                                        '',
                                        preg_replace('/^\s/', '', preg_replace('/(\s\s+|\n+)/s', ' ', $img))
                                    );
                
                                    if ($img = $this->_copyFile($mediaImportSrcDir . $img, $mediaDir . $img)) {
                                        $bs->setStoreId($rowData['store_id']);
                                        $bs->load($bs->getId());
                    
                                        // if there is bannerslider data like file_name_in_store
                                        // --> this has had already another images in store config
                                        // --> update img and del the old one.
                                        if ($bs->getData('file_name_in_store')) {
                                            Mage::helper('brand')->deleteImageFile($bs->getData('file_name'));
                                        }
                    
                                        $bs->setData('file_name', substr(strrchr($img, "/"), 1));
                    
                                        if (isset($brandBannersliderUrlArr[$it]) && $brandBannersliderUrlArr[$it] != '') {
                                            $bs->setData('url', $brandBannersliderUrlArr[$it]);
                                        } else {
                                            $bs->setData('url', '#');
                                        }
                                        if (isset($brandBannersliderTargetArr[$it]) && $brandBannersliderTargetArr[$it] != '') {
                                            $bs->setData('target', $brandBannersliderTargetArr[$it]);
                                        } else {
                                            $bs->setData('target', '#');
                                        }
                                        
                                        $bs->save();
                                    }
                                }
                            }
                            $it++;
                        }
                    } //end else store = 0 (update default store value or HK store value)
                } else { // 2b. ID not found (not 404 lol) --> insert brands
                    // Check if its not enough field in the file -> throw exception
                    foreach ($this->_getRequiredField() as $field) {
                        if (!array_search($field, $header)) {
                            throw new Exception($this->__('Please ensure that the uploaded form has required fields.'));
                        }
                    }
                    
                    // Fill data into model
                    $model->setStoreId(0)
                          ->setData('name', $rowData['name'])
                          ->setData('identifier', $rowData['identifier'])
                          ->setData('about', $rowData['about']);
                        
                    $model->setData('priority', isset($rowData['priority']) ? $rowData['priority'] : 0);
                    $model->setData('videos', isset($rowData['videos']) ? $rowData['videos'] : '');
                    $model->setData('additional_info', isset($rowData['additional_info']) ? $rowData['additional_info'] : '');
                    $model->setData('slogan', isset($rowData['slogan']) ? $rowData['slogan'] : '');
                    $model->setData('meta_keyword', isset($rowData['meta_keyword']) ? $rowData['meta_keyword'] : '');
                    $model->setData('meta_description', isset($rowData['meta_description']) ? $rowData['meta_description'] : '');
                    $model->setData('status', isset($rowData['status']) ? $rowData['status'] : '1');
                    
                    // Copy images (logo, banner)
                    // Check if file to be copied doesn't exist and has been successfully copied
                    if ($rowData['logo'] = $this->_copyFile(
                        $mediaImportSrcDir . $rowData['logo'],
                        $mediaDir . $rowData['logo']
                    )
                    ) {
                        $model->setData('logo', substr(strrchr($rowData['logo'], "/"), 1));
                    }
                    
                    if ($rowData['banner'] = $this->_copyFile(
                        $mediaImportSrcDir . $rowData['banner'],
                        $mediaDir . $rowData['banner']
                    )
                    ) {
                        $model->setData('banner', substr(strrchr($rowData['banner'], "/"), 1));
                    }
                    
                    $model->save();
                    
                    // Brand bannerslider import process
                    if (isset($rowData['bannerslider_image'])
                    ) {
                        $brandBannersliderImgArr = explode(' , ', $rowData['bannerslider_image']);
                        $brandBannersliderUrlArr = explode(' , ', $rowData['bannerslider_url']);
                        $brandBannersliderTargetArr = explode(' , ', $rowData['bannerslider_target']);
                    
                        foreach ($brandBannersliderImgArr as $k => $img) {
                            if (!empty($img)) {
                                // Clear space before and after string
                                $img = preg_replace(
                                    '/( )$/',
                                    '',
                                    preg_replace('/^\s/', '', preg_replace('/(\s\s+|\n+)/s', ' ', $img))
                                );
                                
                                if ($img = $this->_copyFile($mediaImportSrcDir . $img, $mediaDir . $img)) {
                                    $brandBannersliderModel = Mage::getModel('brand/image');
                                    
                                    $brandBannersliderModel
                                        ->setData('file_name', substr(strrchr($img, "/"), 1))
                                        ->setBrandId($model->getId())
                                        ->setData('sort_order', $k);
                                    
                                    if (isset($brandBannersliderUrlArr[$k]) && $brandBannersliderUrlArr[$k] != '') {
                                        $brandBannersliderModel->setData('url', $brandBannersliderUrlArr[$k]);
                                    } else {
                                        $brandBannersliderModel->setData('url', '#');
                                    }
                                    if (isset($brandBannersliderTargetArr[$k])) {
                                        $brandBannersliderModel->setData('target', $brandBannersliderTargetArr[$k]);
                                    } else {
                                        $brandBannersliderModel->setData('target', '_blank');
                                    }
                                    
                                    $brandBannersliderModel->save();
                                }
                            }
                        }
                    }
    
                    // Brand custom links import process
                    if (isset($rowData['custom_links_content']) &&
                        isset($rowData['custom_links_url']) &&
                        isset($rowData['custom_links_target'])
                    ) {
                        $brandCustomLinksContentArr = explode(' , ', $rowData['custom_links_content']);
                        $brandCustomLinksUrlArr = explode(' , ', $rowData['custom_links_url']);
                        $brandCustomLinksTargetArr = explode(' , ', $rowData['custom_links_target']);
    
                        foreach ($brandCustomLinksContentArr as $k => $link) {
                            if (!empty($link)) {
                                // Clear spaces
                                $link = preg_replace(
                                    '/( )$/',
                                    '',
                                    preg_replace('/^\s/', '', preg_replace('/(\s\s+|\n+)/s', ' ', $link))
                                );
                                $brandCustomLinksModel = Mage::getModel('brand/link');
            
                                $brandCustomLinksModel
                                    ->setData('inner_html', $link)
                                    ->setData('brand_id', $model->getId())
                                    ->setData('sort_order', $k);
            
                                if (isset($brandCustomLinksUrlArr[$k])) {
                                    $brandCustomLinksModel->setData('href', $brandCustomLinksUrlArr[$k]);
                                }
            
                                if (isset($brandCustomLinksTargetArr[$k])) {
                                    $brandCustomLinksModel->setData('target', $brandCustomLinksTargetArr[$k]);
                                }
            
                                $brandCustomLinksModel->save();
                            }
                        }
                    }
                }
                
                $count++;
            }
        } catch (Exception $e) {
            return $this->__('Line #') . ($count + 1) . ' ' . $e->getMessage();
        }
        
        $resultTime = microtime(true) - $startTime;
        Mage::getSingleton('adminhtml/session')->addSuccess($count . " brand(s) imported successfully in " . gmdate(
            'H:i:s',
            $resultTime
        ));
        
        return $count;
    }
    
    /**
     * Get all store & website customability fields
     *
     */
    private function _getSWFields()
    {
        return array(
            'name',
            'priority',
            'logo',
            'banner',
            'slogan',
            'status',
            'about',
            'additional_info',
        );
    }
    
    /**
     * Copy file & keep both if existed
     * This is a bit hard to read (because it was really hard to write), was not able to comment each line.
     * In general, this fn will copy file from /media/importer/brand to /media/brand
     * But will keep file if there have been already another file with the same name.
     * E.g:
     * [Existing file]    --> [New file]
     * test.jpg        --> test_1.jpg
     * test_1.jpg    --> test_2.jpg
     * test_2.jpg        --> test_3.jpg
     * test_9ab.jpg    --> test_9ab_1.jpgw
     *
     * @param $src  string Source file name to copy
     * @param $dest string Destination file name to copy
     *
     * @return bool|string Return a destination filename if success, else return false.
     */
    protected function _copyFile($src, $dest)
    {
        if (is_dir($src) || is_dir($dest)) {
            return false;
        }
        
        $io = new Varien_Io_File();
        
        // Fix destination path (brand/001.philips.jpg --> brand/0/0/001.philips.jpg)
        $dest = Mage::getBaseDir('media') . DS . 'brand' . DS  .
            strtolower(substr(strrchr($dest, '/'), 1, 1) . DS . substr(strrchr($dest, '/'), 2, 1) . DS . substr(strrchr($dest, '/'), 1));
    
        //Check and mkdir
        if (!$io->fileExists($io->dirname($dest), false)) {
            $io->mkdir($io->dirname($dest), '2755', true);
        }
        
        if ($io->fileExists($dest)) {
            $ext = strrchr($dest, '.');
            $fileNamePart = substr($dest, 0, strpos($dest, strrchr($dest, '.')));
            $fileOrder = substr(strrchr($fileNamePart, '_'), 1);
            
            if (!is_numeric($fileOrder)) {
                $dest = $fileNamePart . '_1' . $ext;
            }
            
            while ($io->fileExists($dest)) {
                $fileNamePart = substr($dest, 0, strpos($dest, strrchr($dest, '.')));
                $fileOrder = substr(strrchr($fileNamePart, '_'), 1);
                $fileNamePart1 = substr($fileNamePart, 0, strrpos($fileNamePart, $fileOrder) - 1);
                $dest = $fileNamePart1 . '_' . ((int)$fileOrder + 1) . $ext;
            }
        }
        
        if ($io->cp($src, $dest)) {
            return $dest;
        } else {
            return false;
        }
    }
    
    /**
     * Write log to file
     *
     * @param $where string
     * @param $error string Error content
     */
    protected function logImport($where, $error)
    {
        $f = $this->_getLogFile();
        $time = time();
        file_put_contents($f, $time . ':' . $where . '     ' . $error . PHP_EOL, FILE_APPEND);
    }
    
    protected function _initAction()
    {
        $this->loadLayout()
             ->_setActiveMenu('catalog/importer')
             ->_addBreadcrumb(
                 Mage::helper('adminhtml')->__('Items Manager'),
                 Mage::helper('adminhtml')->__('Item Manager')
             );
        
        return $this;
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
    
    private function _getAllFields()
    {
        return array(
            'name',
            'identifier',
            'store_id',
            'priority',
            'logo',
            'banner',
            'slogan',
            'status',
            'about',
            'additional_info',
            'meta_keyword',
            'meta_description',
            'videos',
            'bannerslider_image',
            'bannerslider_url',
            'bannerslider_target',
            'custom_links_content',
            'custom_links_url',
            'custom_links_target',
        );
    }
    
    private function _getRequiredField()
    {
        return array(
            'store_id',
            'name',
            'identifier',
            'logo',
            'banner',
            'status',
            'about',
        );
    }
}
