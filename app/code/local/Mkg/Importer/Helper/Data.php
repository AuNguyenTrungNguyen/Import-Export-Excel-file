<?php

class Mkg_Importer_Helper_Data extends Mage_Core_Helper_Abstract
{
    public function dirFiles($directry)
    {
        $filesall = array();
        $dir = dir($directry); //Open Directory
        while (false!== ($file = $dir->read())) { //Reads Directory
            $extension = substr($file, strrpos($file, '.')); // Gets the File Extension
            if ($extension == ".xlsx" || $extension == ".csv") { // Extensions Allowed
                $filesall[$file] = $file; // Store in Array
            }
        }
        $dir->close(); // Close Directory
        /*asort($filesall); // Sorts the Array*/
        return $filesall;
    }
}
