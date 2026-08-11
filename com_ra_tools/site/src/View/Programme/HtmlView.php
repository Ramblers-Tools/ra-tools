<?php

/*
 * @version     3.5.3
 * 02/09/23 CB optionally restrict by lookahead_weeks
 * 20/11/23 CB allow display of specified group or area
 * 24/10/24 CB correct lookupArea
 * 16/12/24 CB show_criteria
 * 19/01/26 CB Changes to implement new radius selection
 * 02/02/26 CB Always show criteria if not from menu
 * 10/08/26 CB use search radius from component params, not menu params
 */

namespace Ramblers\Component\Ra_tools\Site\View\Programme;

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class HtmlView extends BaseHtmlView {

    protected $centre_point;
    protected $show_cancelled = 0;
    protected $display_type;
    protected $filter_type;
    protected $group;
    protected $intro;
    protected $limit = 0;
    protected $lookahead_weeks = 0;
    protected $show_criteria;
    public $toolsHelper;
    protected $params;
    protected $menu_params;
    protected $radius;
    protected $restrict_walks = 0;
    protected $title;
    protected $user;

    public function display($tpl = null) {
        // Load the component params
        $params = ComponentHelper::getParams('com_ra_tools');
        $app = Factory::getApplication();
        $this->user = Factory::getApplication()->getIdentity();
        $this->toolsHelper = new ToolsHelper;
        $menu = $app->getMenu()->getActive();
        if (is_null($menu)) {

        } else {
            $menu_params = $menu->getParams();
        }

        $this->group = Factory::getApplication()->input->getCmd('group', '');
        if ($this->group == '') {
            // we have been called from a menu
            $this->intro = $menu_params->get('intro');
            $this->filter_type = $menu_params->get('filter_type', 'group');
            if ($this->filter_type == 'radius') {
                $this->radius = $params->get('radius', '25');
                $this->group = $params->get('default_group', '');
            } else {
                $group_type = $menu_params->get('group_type', 'single');
                if (($group_type == "single")) {
                    $this->group = $params->get('default_group');
                    echo 'single group: ' . $this->group . '<br>';
                } elseif ($group_type == "list") {
                    $this->group = $params->get('group_list');
                } else {
                    $this->group = $menu_params->get('code');
                    echo 'Getting specified ' . $this->group . '<br>';
                }
            }
            $this->display_type = $menu_params->get('display_type', 'simple');
            $this->show_cancelled = $menu_params->get('show_cancelled', '0');
            $this->restrict_walks = $menu_params->get('restrict_walks', '0');
            if ($this->restrict_walks == 1) {
                $this->limit = $menu_params->get('limit', '100');
            } elseif ($this->restrict_walks == 2) {
                $this->lookahead_weeks = $menu_params->get('lookahead_weeks', '12');
            }
            $this->show_criteria = $menu_params->get('show_criteria', '2'); // default to Always
        } else {
            $this->group_type = 'group';
            $this->show_criteria = '2';
            // get the defaults from the component parameters
            $this->intro = $params->get('intro');
            $this->filter_type = 'group';
            $this->display_type = $params->get('display_type', 'simple');
            $this->limit = (int) $params->get('limit');

            $title = 'Walks for ';
            if (strlen($this->group) == 2) {
                // Area
                $title .= $this->toolsHelper->lookupArea($this->group);
                $this->group = $this->toolsHelper->expandArea($this->group);
            } else {
                $title .= $this->toolsHelper->lookupGroup($this->group);
            }
            $layout = Factory::getApplication()->input->getCmd('layout', '');
            if ($layout == 'radius') {
                $this->centre_point = $this->group;
                $this->radius = 30;
            }
        }
//        var_dump($this->params);

        $wa = $this->document->getWebAssetManager();
        $wa->useScript('keepalive')
                ->useScript('form.validate');

        echo "<h2>" . $title . "</h2>";
        return parent::display($tpl);
    }

}
