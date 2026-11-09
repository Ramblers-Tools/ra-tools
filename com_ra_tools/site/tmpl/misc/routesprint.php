<?php

/**
 * @version     4.1.6
 * @package     com_ra_tools
 * @copyright   Copyright (C) 2021. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie <webmaster@bigley.me.uk> - https://www.stokeandnewcastleramblers.org.uk
 * 11/04/25 CB Created
 * 25/03/25 CB change number of columns to 2
 * 08/04/25 CB sort names, omit folders
 * 28/04/26 CB omit hidden files and temp file
 * 09/11/26 CB Updates for Joomla 6 compatibility
 */
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use \Joomla\Uri\Uri;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

//$app = \Joomla\CMS\Factory::getApplication();
//var_dump($this->menu_params);
//echo "<br>";
//$title = $this->params->get('page_title', '');
if (is_null($this->menu_params)) {
    $folder = "/images";
    $display_type = "RJsonwalksStdFulldetails";
    $title = "Settings not found";
    $download = "None";
} else {
    $display_type = $this->menu_params->get('display_type');
    $target_folder = $this->menu_params->get('target_folder', 'images');
    $this->working_folder = 'images/' . $target_folder;
    if (JDEBUG) {
        echo "target folder is $target_folder<br>";
        echo "working folder is $this->working_folder<br>";
    }
}
// Validate input parameters

if ($target_folder == '-1') {
    Factory::getApplication()->enqueueMessage('No folder has been selected', 'error');
    return;
}
if (!file_exists($this->working_folder)) {
    $text = "Folder does not exist: " . $this->working_folder . ". Unable to list contents";
    // Add a message to the message queue
    Factory::getApplication()->enqueueMessage($text, 'error');
    return;
}

echo '<h2>' . $this->app->get('sitename') . '</h2>';

echo "Routes being displayed from $this->working_folder<br>";
$handle = opendir($this->working_folder);
if ($handle) {
    while (false !== ($entry = readdir($handle))) {
        if ($entry != "." && $entry != "..") {
            if ((is_dir($working_folder . '/' . $entry)) || (substr($entry, 0, 1) == '.') || (substr($entry, 0, 7) == '0000gpx')) {

            } else {
                $names[] = $entry;
            }
        }
    }
    closedir($handle);
}


if ($names) {
//  natcasesort($names);
    sort($names, SORT_NATURAL);
} else {
    echo 'No files in <b>' . $this->working_folder . $entry . '</b><br>';
    return;
}

$num_columns = 2;
/* * *************************************************************************
 * This common code is shared between
 *   com_ra_tools/site/tmpl/upload/default.php AND
 *   com_ra_tools/site/tmpl/misc/folderlist.php
 * it displays the array of $names in the required number of columns,
 * and assumes the $names[] and $num_columns have been defined
 */
$average_count = intdiv(count($names), $num_columns);
if ($average_count == (count($names) / $num_columns)) {
// all columns have the same number of items
    $max_rows = $average_count;
} else {
    $max_rows = $average_count + 1;
}
//echo 'max=' . $max_rows . ', av= ' . $average_count . ', int ' . (count($names) / $num_columns) . '<br>';
$header = '';
for ($col = 0; ( $col + 2) <= $num_columns; $col++) {
    $header .= ',';
}
$max_pointer = count($names);
//echo 'cols=' . $num_columns . '<br>';
$objTable = new ToolsTable();
$objTable->add_header($header);

for ($row = 0; ($row + 1) <= $max_rows; $row++) {
    for ($col = 0; ( $col + 1) <= $num_columns; $col++) {
        $i = ($col * $max_rows) + $row;
        if ($i < $max_pointer) {
            $value = $names[$i];
            if (substr($value, 0, 3) == 'Sub') {
                $details = $value;
            } else {
                $name = $value;
                if (substr($this->working_folder, -1) == '/') {
                    $target = $this->working_folder . $name;
                } else {
                    $target = $this->working_folder . '/' . $name;
                }
                $details = $this->objHelper->buildLink($target, $value, true);
                if ($this->canDo->get('core.delete')) {
//                  echo 'delete' . $target_delete;
                    $details .= $this->toolsHelper->buildLink($target_delete . $value, '<i class="icon-trash" ></i>');
                }
            }
        } else {
            $details = '';
        }
        $objTable->add_item($details);
    }
    $objTable->generate_line(); //echo '</td>';
}
$objTable->generate_table();
/* * ***********************************************************************
 * end of common code
 */
$back = 'index.php?option=com_ra_tools&view=misc&layout=routes&Itemid=' . $this->menu_id;
echo $this->toolsHelper->backButton($back);
?>


