<?php

/**
 * Shows the contents of a given file
 *
 * @version     5.1.1
 * @package     com_ra_tools
 * @copyright   Copyright (C) 2021. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie <webmaster@bigley.me.uk> - https://www.stokeandnewcastleramblers.org.uk
 * 30/11/22 CB Created from com ramblers
 * 13/12/23 CB Use subfolder within com_ra_tools
 * 12/04/24 CB correct error message
 * 29/09/24 CB replace JPATH_SITE by JPATH_ROOT
 * 22/12/24 CB specify folder as a recursive folder
 * 25/10/25 CB allow pdf file (should do a redirect)
 * 27/08/26 CB correct objHelper to toolsHelper
 */
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

$objTable = new ToolsTable;
$error = '';

//  Find any introduction for the page
$intro = $this->menu_params->get('page_intro');
//  Find any page footer
$page_footer = $this->menu_params->get('page_footer', '');
$folder = $this->menu_params->get('folder', '');
$working_folder = ToolsHelper::addSlash('images/' . $folder);
$file = $this->menu_params->get('file', '');

$show_file = $this->menu_params->get('show_file', 'N');
$download = $this->menu_params->get('download', 'N');

if ($file == '') {
    Factory::getApplication()->enqueueMessage('Filename is blank ', 'error');
    return;
}
$target = $working_folder . $file;
if (!file_exists($target)) {
    $text = 'File ' . $target . ' does not exist';
    // Add a message to the message queue
    Factory::getApplication()->enqueueMessage($text, 'error');
    return;
}

echo '<h2>Displaying file ' . $file . '</h2>';
if ($intro != '') {
    echo $intro . "<br>";
}

$file_extension = strtolower(pathinfo($target, PATHINFO_EXTENSION));
$allowed_extensions = array('csv', 'pdf', 'html', 'htm', 'txt');
if (!in_array($file_extension, $allowed_extensions)) {
    $error .= 'Extension of ' . $file_extension . ' not permitted (must be csv,pdf,txt,htm or html) ';
}

if ($error == '') {
    if ($file_extension == 'csv') {
        $objTable->show_csv($target);
    } elseif ($file_extension == 'txt') {
        echo file_get_contents($target) . '<br>';
    } elseif ($file_extension == 'pdf') {
        echo ToolsHelper::buildLink('images/' . $folder . '/' . $file, 'Show file', true) . '<br>';
    } else {
        $data_file = new SplFileObject($target);

//        Loop until we reach the end of the file.
        while (!$data_file->eof()) {
            // Echo one line from the file.
            echo $data_file->fgets();
        }
// Unset the file to call __destruct(), closing the file handle.
        $data_file = null;
    }
    if ($show_file == 'Y') {
        echo 'File is ' . $target;
    }

    if ($download == 'Y') {
        echo $this->toolsHelper->buildLink($folder . '/' . $file, 'Download', False, 'link-button button-p0186');
    }
    echo '<br>';
} else {
    echo $error . '<br>';
}
if ($page_footer != '') {
    echo $page_footer . "<br>";
}

