<?php

/**
 * @version     5.1.1
 * @package     com_ra_tools
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie <webmaster@bigley.me.uk> - https://www.stokeandnewcastleramblers.org.uk
 * 03/01/22 use prefix ra, not cb
 * 29/09/24 CB replace JPATH_SITE by JPATH_ROOT
 */
use Ramblers\Component\Ra_tools\Site\Helpers;

// No direct access

defined('_JEXEC') or die;
echo "start of code from " . __FILE__ . PHP_EOL;
echo '<h2>THIS IS DEFAULT VIEW</h2>';
echo "constant JPATH BASE= " . JPATH_BASE . "<br>";

echo "<h4>" . JPATH_ROOT . "</h4>";
$helper = JPATH_ROOT . '/components/com_ra_tools/src/Helpers/ToolsHelper.php';
if (file_exists($helper)) {
    require_once $helper;
//    echo "Found " . $helper . '<br>';
} else {
    echo "Cant find " . $helper . '<br>';
}
$table = JPATH_ROOT . '/components/com_ra_tools/src/Helpers/ToolsTable.php';
if (file_exists($table)) {
    require_once $table;
    echo "Found " . $table . '<br>';
} else {
    echo "Cant find " . $table . '<br>';
}
//JLoader::register('ToolsHelper', $helper);

//$objHelper = new ToolsHelper;
echo $this->toolsHelper->showQuery('Select * from #__mywalks');

//$objTable = new Table;

echo '<h2>Areas</h2>';
$sql = "SELECT code, name,website,co_url ";
$sql .= "FROM #__ra_areas ";
$sql .= "ORDER BY code LIMIT 20";
echo $sql;
$rows = $this->toolsHelper->getRows($sql);
//$objTable = new Table();
//$objTable->add_header('Code,Name,website,CO site');
foreach ($rows as $row) {
//    $objTable->add_item($row->code);
//    $objTable->add_item($row->name);
//    $objTable->add_item($row->website);
//    $objTable->add_item($row->co_website);
//    $objTable->generate_line();
    echo $row->code . '<br>';
}
//$objTable->generate_table();
$back = "index.php?option=com_ra_tools&task=reports.showUserGroups";
//echo $objHelper->backButton($back);

//echo $objHelper->showQuery('Select * from #__mywalks');

