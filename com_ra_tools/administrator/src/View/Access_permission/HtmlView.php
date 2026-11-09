<?php

/**
 * single-user access-permission view.
 * 02/11/26 CB Created
 */

namespace Ramblers\Component\Ra_tools\Administrator\View\Access_permission;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class HtmlView extends BaseHtmlView {

    protected $canDo;
    protected $state;
    protected $item;
    protected $form;
    protected $toolsHelper;

    public function display($tpl = null) {
        $this->toolsHelper = new ToolsHelper;

        if (!$this->toolsHelper->isSuperuser()) {
            $app = Factory::getApplication();
            $app->enqueueMessage('Access denied.', 'error');
            $app->redirect(Route::_('index.php?option=com_ra_tools&view=dashboard', false));

            return;
        }

        $this->state = $this->get('State');
        $this->form = $this->get('Form');
        $this->item = (object) ['id' => 0];
        $this->canDo = ContentHelper::getActions('com_ra_tools');

        if (empty($this->form)) {
            throw new \RuntimeException('Unable to load access permission form definition.');
        }

        $this->addToolbar();

        parent::display($tpl);
    }

    protected function addToolbar() {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $user = Factory::getApplication()->getIdentity();
        $checkedOut = isset($this->item->checked_out)
                && !in_array((int) $this->item->checked_out, [0, (int) $user->get('id')], true);

        ToolbarHelper::title(Text::_('Access permission'), 'generic');

        if (!$checkedOut && ($this->canDo->get('core.edit') || $this->canDo->get('core.create'))) {
            ToolbarHelper::save('access_permission.save', 'JTOOLBAR_SAVE');
        }

        ToolbarHelper::cancel('access_permission.cancel', empty($this->item->id) ? 'JTOOLBAR_CANCEL' : 'JTOOLBAR_CLOSE');
    }
}