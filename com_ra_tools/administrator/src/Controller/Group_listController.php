<?php

/**
 * @version     3.3.14
 * @package     com_ra_tools
 * @author      charlie
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * 23/05/23 CB use db->quote for Areas/Groups
 * 06/06/23 CB correct back button for refresh groups
 * 14/06/23 CB re-write refreshGroups
 * 10/07/23 CB add function cancel
 * 17/07/23 CB remove diagnostics
 * 30/11/23 CB use Factory::getContainer()->get('DatabaseDriver');
 * 22/02/24 CB declare Joomla\Database\DatabaseInterface;
 * 21/10/24 CB set up maximum time of 10 minutes
 * 22/04/25 CB get API key from config
 * 18/08/25 CB get API key from ra_api_sites
 * 04/09/25 CB check before updating group details, set bespoke
 * 08/09/25 CB update area_id
 */

namespace Ramblers\Component\Ra_tools\Administrator\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\JsonHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Ra_tools list controller class.
 *
 * @since  1.6
 */
class Group_listController extends AdminController {

    protected $toolsHelper;

    public function __construct() {
        parent::__construct();
        $this->toolsHelper = new ToolsHelper;
        //      $this->app = Factory::getApplication();
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function cancel($key = null, $urlVar = null) {
        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
    }

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    The model name. Optional.
     * @param   string  $prefix  The class prefix. Optional.
     * @param   array   $config  The array of possible config values. Optional.
     *
     * @return  \Joomla\CMS\MVC\Model\BaseDatabaseModel
     *
     * @since   1.6
     */
    public function getModel($name = 'Group_list', $prefix = 'Administrator', $config = array('ignore_request' => true)) {
        return parent::getModel($name, $prefix, $config);
    }

    public function isBespoke($test) {
        if ($test == '') {
            return false;
        }
        if ((substr($test, 0, 10) == 'We are the') AND (substr($test, -66) == 'everyone in our local community to enjoy the pleasures of walking.')) {
            return false;
        } else {
            return true;
        }
    }

    public function refreshGroups() {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $this->updateGroups();
//      set up maximum time of 10 minutes
        $max = 10 * 60;
        set_time_limit($max);

        // set to 1 for debugging
        $debug = 1;
        // Read the Organisation feed, update tables #__ra_areas and #__ra_groups as required
        $group_count = 0;
        $group_insert = 0;
        $update_count = 0;

        $display = 0;
        $toolsHelper = new ToolsHelper;

        $api_key = $this->toolsHelper->lookupApiKey();
        if ($api_key == '') {
            $message = 'API key not found - please create a record in API sites';
            Factory::getApplication()->enqueueMessage($message, 'error');
        }
        $feedurl = 'https://walks-manager.ramblers.org.uk/api/volunteers/groups?api-key=' . $api_key;

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $feedurl);
        curl_setopt($ch, CURLOPT_HEADER, false); // do not include header in output
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // do not follow redirects
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true); // do not output result
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $max);  // allow xx seconds for timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, $max);  // allow xx seconds for timeout
//	curl_setopt($ch, CURLOPT_REFERER, JURI::base()); // say who wants the feed

        curl_setopt($ch, CURLOPT_REFERER, "com_ra_tools"); // say who wants the feed

        $data = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            print('Error code: ' . $error . "\n");
            print('Http return: ' . $httpCode . "\n");
            echo 'Access failed';
            return;
        }
        $group_list = json_decode($data);
        //       var_dump($group_list);
        // $group_list = $JsonHelper->getJson('group-event', 'groups=' . $group_code););


        $sql = "SELECT id,area_id,code,name,details,bespoke,website,co_url,latitude, longitude "
                . "FROM #__ra_groups WHERE code='";

        $record_count = 0;
        $count_bespoke = 0;
        $missing_description = 0;
        foreach ($group_list as $item) {
            $record_count++;
            $display = 0;

            if ($item->scope == 'G') {
//                echo "$record_count $item->group_code $item->name<br>";
                $area_id = $toolsHelper->getAreaCode($item->group_code);
                if ((int) $area_id == 0) {
                    echo "Area not found<br>";
                    $area_id = 1;
                }

                $group_count++;
                Factory::getContainer()->get('DatabaseDriver');
                $query = $db->getQuery(true);
                $query->set("area_id = " . $db->quote($area_id))
                        ->set("code = " . $db->quote($item->group_code))
                        ->set("name = " . $db->quote($item->name))
                        ->set("website = " . $db->quote($item->external_url))
                        ->set("co_url = " . $db->quote($item->url))
                        ->set("latitude = " . $db->quote((float) $item->latitude))
                        ->set("longitude = " . $db->quote((float) $item->longitude))
                ;
                $co_bespoke = $this->isBespoke($item->description);
                $sql_lookup = $sql . $item->group_code . "'";
                // Get the existing record
                $group = $toolsHelper->getItem($sql_lookup);
                $ra_bespoke = $this->isBespoke($group->details);
                if (is_null($group)) {
                    $query->set("details = " . $db->quote($item->description));
                    $query->insert('#__ra_groups');
                    $result = $db->setQuery($query)->execute();
                    $group_insert++;
                } else {
                    $update = 0;
                    if (($ra_bespoke) AND ($co_bespoke)) {
                        $query->set("bespoke = 2");
                        $update = 1;
                    } elseif (($ra_bespoke) OR ($co_bespoke)) {
                        $query->set("bespoke = 1");
                        $update = 1;
                        if ($co_bespoke) {
                            $query->set("details = " . $db->quote($item->description));
                        }
                    } else {
                        echo 'Setting details to ' . $db->quote($item->description) . '<br';
                        $query->set("details = " . $db->quote($item->description));
                        $update = 1;
                        $missing_description++;
                    }
                    // Matching record has been found
                    if (($group->code == 'CF51') OR ($group->details == '')) {
                        echo 'Group ' . $group->details . "<br>";
                        echo "co_bespoke $co_bespoke<br>";
                        echo "ra_bespoke $ra_bespoke<br>";
                        echo "update $update<br>";
                        echo $item->description . "<br>";
                        //                       echo 'Query is ' . $query . '<br>';
                        //                       die;
                    }
                    if ($group->area_id <> $area_id) {
                        echo 'Updating area_id for ' . $group->code . ' from ' . $group->area_id . ' to <b>' . $area_id . '</b><br>';
                        $update = 1;
                    }
                    if ($group->name <> $item->name) {
                        echo 'Updating name for ' . $group->code . ' from ' . $group->name . ' to <b>' . $item->name . '</b><br>';
                        $update = 1;
                    }
                    if (($co_bespoke) AND ($group->details <> $item->description)) {
                        echo 'Updating details for ' . $group->code . ' from ' . $group->details . ' to <b>' . $item->description . '</b><br>';
                        $update = 1;
                    }
                    if ($group->co_url <> $item->url) {
                        echo 'Updating co_url for ' . $group->code . ' from ' . $group->co_url . ' to <b>' . $item->url . '</b><br>';
                        $update = 1;
                    }
                    if ($group->website <> $item->external_url) {
                        echo 'Updating website for ' . $group->code . ' from ' . $group->website . ' to <b>' . $item->external_url . '</b><br>';
                        $update = 1;
                    }
                    if ($group->latitude <> $item->latitude) {
                        echo 'Updating latitude for ' . $group->code . ' from ' . $group->latitude . ' to <b>' . $item->latitude . '</b><br>';
                        $update = 1;
                    }
//                    if ($group->longitude <> $item->longitude) {
//                        echo 'Updating longitude for ' . $group->code . ' from ' . $group->longitude . ' to <b>' . $item->longitude . '</b><br>';
//                        $update = 1;
//                    }
                    if ($update) {
                        $update_count++;
                        $query->update('#__ra_groups')
                                ->where('id=' . $group->id);
//                        echo $query . '<br>';
                        $result = $db->setQuery($query)->execute();
                    }
                }
            }
//            if ($record_count > 10) {
//                break;
//            }
        }
        $message = $group_count . ' Groups,  ';
        if ($group_insert > 0) {
            $message .= $group_insert . ' records inserted ';
        }
        if ($update_count == 0) {
            $message .= 'No updates necessary';
        } else {
            $message .= $update_count . ' records updated';
        }
        echo '<br>' . $record_count . ' records read<br>';
        echo $count_bespoke . ' records found in local database with a bespoke description<br>';
        if ($missing_description > 0) {
            $message .= ', ' . $missing_description . ' records have a missing description ';
        }
        $sql = 'SELECT id, code FROM #__ra_areas';
        $rows = $toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $sql = 'UPDATE #__ra_groups SET area_id=' . $row->id;
            $sql .= ' WHERE code LIKE "' . $row->code . '%"';
            $toolsHelper->executeCommand($sql);
        }
        if ($debug) {
            echo $message . '<br>';
            $target = 'administrator/index.php?option=com_ra_tools&view=dashboard';
            echo $toolsHelper->backButton($target);
        } else {
            Factory::getApplication()->enqueueMessage($message, 'notice');
            $this->setRedirect(Route::_('index.php?option=com_ra_tools&view=group_list', false));
        }
    }

    public function setBespoke() {
        /*
         * value 0, neither
         * value 1 details have been customised
         * value 2 CO site has been customised
         */
        $toolsHelper = new ToolsHelper;
        $sql = 'SELECT * FROM #__ra_groups ORDER BY code';
//        $sql .= ' LIMIT 100';
        $count = 0;
        $rows = $toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            if ($this->isBespoke($row->details)) {
                echo "$row->code $row->details<br>";
                $sql_update = 'UPDATE #__ra_groups SET bespoke="1" WHERE code="';
                $count++;
            } else {
                $sql_update = 'UPDATE #__ra_groups SET bespoke="0" WHERE code="';
            }
            $toolsHelper->executeCommand($sql_update . $row->code . '"');
        }
        echo "$count Bespoke descriptions found<br>";
        $target = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        echo $toolsHelper->backButton($target);
    }

    public function test() {
        $test = 'As well as a regular programme of walks, publishes local walks guides, organises coach-connected walks, long distance walks in stages, and city walks.';
        echo $test . '<br>';
        $result = $this->isBespoke($test);
        if ($result) {
            echo 'Bespoke<br>';
        } else {
            echo 'not bespoke<br>';
        }
        $test = 'Bedfordshire\'s largest and most active walking group. Walks every Sunday and Wednesday, every second Tuesday and Thursday and most Fridays. Evening walks in the summer.';
        echo $test . '<br>';
        $result = $this->isBespoke($test);
        if ($result) {
            echo 'Bespoke<br>';
        } else {
            echo 'not bespoke<br>';
        }
    }

    private function updateGroups() {
        $sql = "DELETE FROM #__ra_groups WHERE code IN('DN11','LB11','LI12','LD06','WS03')";
        echo "$sql<br>";
        $this->toolsHelper->executeCommand($sql);
        $details = 'We are a friendly group based in East Staffordshire & we enjoy walking throughout the year in all sorts of places & conditions. It\'s a great way of meeting new friends & getting fit & healthy outdoors.';
        $code = 'NS01';
        $sql = "UPDATE #__ra_groups WHERE code='" . $code;
        $sql .= "' SET details='" . $details . "'";
        echo "$sql<br>";
        //       $this->toolsHelper->executeCommand($sql);
    }

}
