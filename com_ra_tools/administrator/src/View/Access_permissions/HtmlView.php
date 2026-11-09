<?php

/**
 * Administrator view listing user access permissions.
 * 02/11/26 CB Created
 */

namespace Ramblers\Component\Ra_tools\Administrator\View\Access_permissions;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class HtmlView extends BaseHtmlView {

    protected $app;
    protected $items;
    protected $ra_events_enabled = false;
    protected $ra_mailman_enabled = false;
    protected $ra_members_enabled = false;
    protected $ra_tools_enabled = false;
    protected $ra_treasurer_enabled = false;    
    protected $toolsHelper;
    protected $item_count = 0;
    protected $canDo;

    public function display($tpl = null) {
        $this->app = Factory::getApplication();
        $this->toolsHelper = new ToolsHelper;

        if (!$this->toolsHelper->isSuperuser()) {
            $this->app->enqueueMessage('Access denied.', 'error');
            $this->app->redirect(Route::_('index.php?option=com_ra_tools&view=dashboard', false));

            return;
        }
        if (ComponentHelper::isEnabled('com_ra_events', true)) {
            $this->ra_events_enabled = true;
        }
        if (ComponentHelper::isEnabled('com_ra_mailman', true)) {
            $this->ra_mailman_enabled = true;
        }
        if (ComponentHelper::isEnabled('com_ra_members', true)) {
            $this->ra_members_enabled = true;
        }
        if (ComponentHelper::isEnabled('com_ra_tools', true)) {
            $this->ra_tools_enabled = true;
        }
        if (ComponentHelper::isEnabled('com_ra_treasurer', true)) {
            $this->ra_treasurer_enabled = true;
        }
        $this->getItems();
        $this->addToolbar();

        return parent::display($tpl);
    }

    protected function addToolbar() {
        $this->app->input->set('hidemainmenu', true);
        $toolbar = Toolbar::getInstance('toolbar');

        ToolbarHelper::title(Text::_('Access permissions'));
        $this->canDo = ContentHelper::getActions('com_ra_tools');

        if ($this->canDo->get('core.create')) {
            $toolbar->addNew('access_permission.add');
        }

        $toolbar->standardButton('nrecords')
                ->icon('fa fa-info-circle')
                ->text(number_format($this->item_count) . ' Users')
                ->task('')
                ->onclick('return false')
                ->listCheck(false);

        ToolbarHelper::cancel('access_permission.cancelList', 'Return to Dashboard');
    }

    private function getItems(): void {
        $sql = 'SELECT u.id, p.preferred_name, ';
       
        if ($this->ra_events_enabled) {
            $sql .= 'MAX(CASE WHEN g.title = "com_ra_events" THEN 1 ELSE 0 END) AS access_ev, ';
        }
        if ($this->ra_mailman_enabled) {
            $sql .= 'MAX(CASE WHEN g.title = "com_ra_mailman" THEN 1 ELSE 0 END) AS access_mm, ';
        }
        if ($this->ra_members_enabled) {
            $sql .= 'MAX(CASE WHEN g.title = "com_ra_members" THEN 1 ELSE 0 END) AS access_me, ';
        }   
        if ($this->ra_treasurer_enabled) {
            $sql .= 'MAX(CASE WHEN g.title = "com_ra_treasurer" THEN 1 ELSE 0 END) AS access_tr, ';
        }
         $sql .= 'MAX(CASE WHEN g.title = "com_ra_tools" THEN 1 ELSE 0 END) AS access_to ';
        $sql .= 'FROM #__users AS u ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = u.id ';
        $sql .= 'INNER JOIN #__user_usergroup_map AS m ON m.user_id = u.id ';
        $sql .= 'INNER JOIN #__usergroups AS g ON g.id = m.group_id ';
        $sql .= 'WHERE g.title LIKE ("com_ra_%") ';
        $sql .= 'GROUP BY u.id, p.preferred_name ';
        $sql .= 'ORDER BY p.preferred_name';
        $this->items = $this->toolsHelper->getRows($sql);
        $this->item_count = is_array($this->items) ? count($this->items) : 0;
    }
}