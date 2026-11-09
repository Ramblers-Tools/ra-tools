<?php

/**
 * 26/05/23 CB created
 * 27/05/23 CB show headings for CSV, clean phone number, show shape, meeting
 * 29/05/23 CB add exportGwen
 * 30/05/23 CB sort reports by group/date/title
 * 30/11/23 CB use Factory::getContainer()->get('DatabaseDriver');
 * 09/10/24 CB delete walksCsv
 * 20/04/25 CB change name to $toolsHelper, links from showGroup
 * 11/09/25 CB correct link to show walks
 * 18/09/25 CB show location
 * 09/11/26 CB remove redundant functions (all are still in ra-walks)
 */

namespace Ramblers\Component\Ra_tools\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Date\Date;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\FormController;
//use Joomla\CMS\Router\Route;
//use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\JsonHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;
use Joomla\Database\DatabaseInterface;

/**
 * Controller for a single area
 *
 * @since  1.6
 */
class AreaController extends FormController {

    private $show_message;
    private $walksfound = 0;
    private $walksupdated = 0;
    private $walkscreated = 0;

    function countWalks() {
        $app = Factory::getApplication();

        $callback = $app->getUserState('com_ra_wf.callback', 'walk_list');
        $code = $app->input->getCmd('code', 'NAT');

        $toolsHelper = new ToolsHelper;
        $objTable = new ToolsTable();

        echo "<h2>Total walks by Group";
        if (strlen($code) == 2) {
            echo ' for ' . $this->objHelper->lookupArea($code);
        }
        echo "</h2>";

        $objTable->add_header("Group, Count");
        $sql = "SELECT group_code, COUNT(id) as 'Num' ";
        $sql .= 'FROM #__ra_walks ';
        if (strlen($code) == 2) {
            $sql .= 'WHERE (group_code like "' . $code . '%") ';
        }
        $sql .= 'GROUP BY group_code ';
        $sql .= 'ORDER BY group_code ';
//        echo $sql;
        $rows = $toolsHelper->getRows($sql);
        $total_walks = 0;
        foreach ($rows as $row) {
            $objTable->add_item($row->group_code);
            $objTable->add_item($row->Num);
            $total_walks += $row->Num;
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo number_format($total_walks) . ' Walks<br>';
        // Depending on from where it was invoked, control will be passed back either to reports or to reports_area
        $target = "index.php?option=com_ra_tools&view=area&code=" . substr($code, 0, 2);
        echo $toolsHelper->backButton($target);
    }

    public function showArea() {
        $code = Factory::getApplication()->input->getCmd('code', 'NS');
        $toolsHelper = new ToolsHelper;
        $sql = "SELECT * FROM #__ra_areas  ";
        $sql .= "WHERE code = '" . $code . "'";
        $area = $toolsHelper->getItem($sql);
        $this->showLocal('Area', $area);

        echo $toolsHelper->backButton('index.php?option=com_ra_tools&view=area_list');
    }

    public function showGroup() {
        $code = Factory::getApplication()->input->getCmd('code', 'NS');
        $toolsHelper = new ToolsHelper;
        $sql = "SELECT * FROM #__ra_groups  ";
        $sql .= "WHERE code = '" . $code . "'";
//            echo $sql;
        $item = $toolsHelper->getItem($sql);
        $this->showLocal('Group', $item);
        echo $toolsHelper->backButton('index.php?option=com_ra_tools&view=group_list');
    }

    private function showLocal($type, $item) {
        $toolsHelper = new ToolsHelper;
        echo "<h2>$item->name</h2>";
        echo '<b>Code </b>' . $item->code . '<br>';
        echo '<b>Name </b>' . $item->name . '<br>';
        echo '<b>Details </b>' . $item->details . '<br>';
        echo '<b>Website </b>' . $toolsHelper->buildLink($item->website, $item->website, true) . '<br>';
        echo '<b>Head office site </b>' . $toolsHelper->buildLink($item->co_url, $item->co_url, true) . '<br>';
        echo '<b>Latitude</b> ' . $item->latitude . '<br>';
        echo '<b>Longitude </b> ' . $item->longitude . '<br>';
        echo '<b>Location </b> ' . $toolsHelper->showLocation($item->latitude, $item->longitude, 'Ggit status') . '<br>';

        $toolsHelper->getNearestOrganisations($item->code, 5, 'Y');
        $jsonHelper = new JsonHelper;

        echo '<b>Events in WalksManager</b> ';
        $count = $jsonHelper->getCountEvents($item->code);
        if ($count == 0) {
            echo '0';
        } else {
            $target = 'index.php?option=com_ra_tools&task=reports.showEvents&code=' . $item->code;
            echo $toolsHelper->buildLink($target, $count);
            echo $jsonHelper->showEventsButton($item->code);
        }
        echo '<br>';

        echo '<b>Walks in WalksManager</b> ';
        $count = $jsonHelper->getCountWalks($item->code);
        if ($count == 0) {
            echo '0';
        } else {
            $target = 'index.php?option=com_ra_tools&view=programme&group=' . $item->code;
            //           echo $toolsHelper->buildLink($target, $jsonHelper->getCountWalks($item->code));
            echo $toolsHelper->buildLink($target, $count, true);
            echo $jsonHelper->showWalksButton($item->code);
        }
        echo '<br>';
    }

}
