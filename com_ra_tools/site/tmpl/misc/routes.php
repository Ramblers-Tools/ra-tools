<?php

/**
 * @version     5.1.1
 * @package     com_ra_tools
 * @copyright   Copyright (C) 2021. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie <webmaster@bigley.me.uk> - https://www.stokeandnewcastleramblers.org.uk
 * 10/04/22 tweaked
 * 11/06/22 CB Show route library to logged in members
 * 20/11/23 CB strip leading slash from name of gpx file if necessary
 * 04/12/23 CB take lat/long from ra_groups for home group
 * 22/01/24 CB check folder exists
 * 19/09/24 CB make selection field a folderlist (not fixedfolderlist)
 * 23/09/24 CB use recursive folderlist instead of folder / sub folder
 * 29/09/24 CB replace JPATH_SITE by JPATH_ROOT
 * 10/10/24 CB when defining working_folder, don't prefix with JPATH_ROOT
 * 11/04/25 CB add routesprint
 * 13/04/25 CB delete warning about Lat/Long out of range
 * 09/11/26 CB Updates for Joomla 6 compatibility
 */
// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use \Joomla\Uri\Uri;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

$home_group = $this->params->get('default_group', 'NS03');
$sql = 'SELECT latitude, longitude FROM ';
if (strlen($home_group == 2)) {
    $sql .= '#__ra_areas';
} else {
    $sql .= '#__ra_groups';
}
$sql .= ' WHERE code="' . $home_group . '"';

$item = $this->toolsHelper->getItem($sql);
$latitude = $item->latitude;
$longitude = (float) $item->longitude;

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
    $intro = $this->menu_params->get('page_intro');
    $download = $this->menu_params->get('download');
    $target_folder = $this->menu_params->get('target_folder', 'images');
    $show_folder = $this->menu_params->get('show_folder', 'Y');
    $show_file = $this->menu_params->get('show_file', 'Y');

    $working_folder = ToolsHelper::addSlash('/images/' . $target_folder);
    $working_folder = 'images/' . $target_folder;
    if (JDEBUG) {
        echo "display type $display_type<br>";
        echo "target folder is $target_folder<br>";
        echo "working folder is $working_folder<br>";
    }
    // if only showing a single file, $gpx will be the name of the file to be displayed
    $gpx = $this->menu_params->get('gpx');

    if (substr($gpx, 0, 1) == '/') {
        $gpx = substr($gpx, 1);
    }
//    echo "gpx $gpx<br>";
}
// Validate input parameters


if (($display_type == 'L') or ($display_type == 'S')) {
    if ($target_folder == '-1') {
        Factory::getApplication()->enqueueMessage('No folder has been selected', 'error');
        return;
    }
    if (!file_exists($working_folder)) {
        $text = 'Folder ' . $working_folder . ' does not exist';
        // Add a message to the message queue
        Factory::getApplication()->enqueueMessage($text, 'error');
        return;
    }
}
if ($display_type == 'S') {
    if ($gpx == '') {
        Factory::getApplication()->enqueueMessage('Filename is blank ', 'error');
    }
    $target_file = JPATH_ROOT . '/' . $working_folder . '/' . $gpx;
    //   echo "target file is $target_file<br>";

    $path_parts = pathinfo($target_file);
    if (strtolower($path_parts['extension']) != "gpx") {
        $app = Factory::getApplication();
        $app->enqueueMessage('GPX: Route file is not a gpx file: ' . $target, 'error');
        echo "<p><b>UGPX: Route file is not a gpx file: $target_file</b></p>";
        return;
    }
    if (!file_exists($target_file)) {
        $text = 'File ' . $target_file . ' does not exist';
        // Add a message to the message queue
        Factory::getApplication()->enqueueMessage($text, 'error');
        return;
    }
    $gpx_target = $working_folder . '/' . $gpx;
}
echo '<h2>' . $this->params->get('page_title') . '</h2>';

if (!$intro == "") {
    echo $intro . '<br>';
}
if ($display_type == 'P') {
    $object = new RLeafletMapdraw();
    $object->setCenter($latitude, $longitude, 10); // lat, long, zoom
    $object->display();
} else {
    if ($display_type == 'L') {
        if (!file_exists($working_folder)) {
            $text = "Folder does not exist: " . $working_folder . ". Unable to list contents";
// Add a message to the message queue
            Factory::getApplication()->enqueueMessage($text, 'error');
            return;
        }
        $map = new RLeafletGpxMaplist();
        $map->addDownloadLink = $download; // "None" no download link, "Users" link if registered user; "Public" link for public
        $map->folder = $working_folder;
        $map->displayTitle = False;
        $map->display();
        if ($show_folder == 'Y') {
            echo "Routes being displayed from $working_folder<br>";
        }
        if (!$this->canDo->get('core.create')) {
            $target = 'index.php?option=com_ra_tools&view=misc&layout=routes_clean&Itemid=' . $this->menu_id;
            echo $this->toolsHelper->buildButton($target, 'Clean waypoints', False, 'red');
        }
        $target = 'index.php?option=com_ra_tools&view=misc&layout=routesprint&Itemid=' . $this->menu_id;
        $target .= '&tmpl=component';
        echo $this->toolsHelper->buildButton($target, 'Print file list', False, 'mintcake');
    } else {  // display_type = S
        $map = new RLeafletGpxMap();  // standard software to read json feed and decode file
        $map->linecolour = '#782327'; // optionally set the route's line colour
        $map->addDownloadLink = $download; //  "None" no download link, "Users" link if registered user; "Public" link for public
//        $map->folder = $path;
        $map->displayPath($gpx_target);
        if ($show_file == 'Y') {
            echo 'Route is ' . $gpx_target;
        }
    }
    if ($this->user->id == 0) {
        if ($download == 'Users') {
            echo 'Login or Register to download gpx files<br>';
        }
    }
}
?>
