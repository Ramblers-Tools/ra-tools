<?php

/**
 * @version     3.5.3
 * @package     com_ra_tools
 * @copyright   Copyright (C) 2021. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie <webmaster@bigley.me.uk> - https://www.stokeandnewcastleramblers.org.uk
 * 24/10/24 CB change default group_type to list
 * 16/12/24 CB show_criteria
 * 19/01/26 CB Changes to implement new radius selection
 * 20/01/26 CB show radius distance as miles
 * 21/02/26 CB correct selection of group from menu
 * 10/08/26 CB use search radius from component params, not menu params
 */

namespace Ramblers\Component\Ra_tools\Site\View\Programme_day;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class HtmlView extends BaseHtmlView {

    protected $day;
    protected $dayswitcher;
    protected $display_type;
    protected $filter_type;
    protected $group;
    protected $group_type;
    protected $intro;
    protected $limit;
    protected $lookahead_weeks;
    protected $radius;
    protected $restrict_walks;
    protected $show_cancelled;
    protected $show_criteria;
    protected $menu_id;
    protected $toolsHelper;
    protected $user;

    public function display($tpl = null) {
        $this->user = Factory::getApplication()->getIdentity();
        $app = Factory::getApplication();
        $context = 'com_ra_tools.programme_day.';
        // Load the component params
        $params = ComponentHelper::getParams('com_ra_tools');
//        var_dump($params);
//        echo '<br>end of params from component helper<br>';
        $this->toolsHelper = new ToolsHelper;
        $this->menu_id = $app->input->getCmd('Itemid', '');

        // day will be blank if called from a menu
        $this->day = $app->input->getWord('day', '');
        if ($this->day == '') {
//            echo 'Day not specified, getting from menu<br>';
            // invoked from menu
            $menu = $app->getMenu()->getActive();
            if (is_null($menu)) {
                echo 'Menu params are null<br>';
            } else {
                $menu_params = $menu->getParams();
//              var_dump($menu_params);
            }
            $this->day = $menu_params->get('day', '');
            $this->intro = $menu_params->get('intro');
            $this->filter_type = $menu_params->get('filter_type', 'group');
            if ($this->filter_type == 'radius') {
                // Use the default group from the component params
                $item = $this->toolsHelper->getItem('SELECT latitude, longitude from #__ra_groups where code="' . $this->group . '"');
                $this->radius = $params->get('radius', '25');
                $this->group = $params->get('default_group', '');
            } else {
                $this->group_type = $menu_params->get('group_type', 'list');
                if (($this->group_type == "single")) {
                    $this->group = $params->get('default_group');
                } elseif ($this->group_type == "list") {
                    $this->group = $params->get('group_list');
                } else {
                    $this->group = $params->get('code');
                }
            }
            $this->display_type = $menu_params->get('display_type', 'simple');
            $this->dayswitcher = $menu_params->get('dayswitcher', '1');
            $this->show_cancelled = $menu_params->get('show_cancelled', '0');
            $this->restrict_walks = $menu_params->get('restrict_walks');
            $this->limit = (int) $menu_params->get('limit');
            $this->lookahead_weeks = (int) $menu_params->get('lookahead_weeks');
            $this->show_criteria = $menu_params->get('show_criteria', '2'); // default to Always
            Factory::getApplication()->setUserState($context . 'intro', $this->intro);
            $app->setUserState($context . 'filter_type', $this->filter_type);
            $app->setUserState($context . 'group', $this->group);
            $app->setUserState($context . 'group_type', $this->group_type);
            $app->setUserState($context . 'radius', $this->radius);
            $app->setUserState($context . 'display_type', $this->display_type);
            $app->setUserState($context . 'dayswitcher', $this->dayswitcher);
            $app->setUserState($context . 'extra_filter', $this->extra_filter);
            $app->setUserState($context . 'show_cancelled', $this->show_cancelled);
            $app->setUserState($context . 'restrict_walks', $this->restrict_walks);
            $app->setUserState($context . 'limit', $this->limit);
            $app->setUserState($context . 'lookahead_weeks', $this->lookahead_weeks);
            $app->setUserState($context . 'show_criteria', $this->show_criteria);
        } else {
            // invoked by self, get details from the user state
            $this->intro = $app->getUserState($context . 'intro');
            $this->filter_type = $app->getUserState($context . 'filter_type');
            $this->group = $app->getUserState($context . 'group');
            $this->group_type = $app->getUserState($context . 'group_type');
            $this->radius = $app->getUserState($context . 'radius');
            $this->display_type = $app->getUserState($context . 'display_type');
            $this->dayswitcher = $app->getUserState($context . 'dayswitcher');
            $this->show_cancelled = $app->getUserState($context . 'show_cancelled');
            $this->restrict_walks = $app->getUserState($context . 'restrict_walks');
            $this->limit = $app->getUserState($context . 'limit');
            $this->lookahead_weeks = $app->getUserState($context . 'lookahead_weeks');
            $this->show_criteria = $app->getUserState($context . 'show_criteria');
            if (($this->group_type == "single")) {
                $this->group = $params->get('default_group');
            } elseif ($this->group_type == "list") {
                $this->group = $params->get('group_list');
            }
        }
        if (JDEBUG) {
            echo 'filter_type=' . $this->filter_type . "<br>";
            echo 'group=' . $this->group . "<br>";
            echo 'group_type=' . $this->group_type . "<br>";
            echo 'display_type=' . $this->display_type . "<br>";
            echo "Day=" . $this->day . ", radius=" . $this->radius . "<br>";
        }
        echo "<h2>" . $this->day . " walks</h2>";
        $wa = $this->document->getWebAssetManager();
        $wa->useScript('keepalive')
                ->useScript('form.validate');
        return parent::display($tpl);
    }

}
