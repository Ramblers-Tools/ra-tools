<?php

/**
 * 21/09/26 CB restrict access to superUsers only
 * 21/11/25 CB removed "Save New" and "Save as Copy" buttons
 */

namespace Ramblers\Component\Ra_tools\Administrator\View\Apisite;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\User\CurrentUserInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a single Apisite.
 *
 * @since  2.1.0
 */
class HtmlView extends BaseHtmlView implements CurrentUserInterface {

    protected $state;
    protected $item;
    protected $form;
    protected $user;
    protected $toolsHelper;

    /**
     * Display the view
     *
     * @param   string  $tpl  Template name
     *
     * @return void
     *
     * @throws Exception
     */
    public function display($tpl = null) {
        $this->toolsHelper = new ToolsHelper;
        if (!$this->toolsHelper->isSuperuser()) {
            throw new \Exception('You do not have permission to update these records');
        }
        $this->state = $this->get('State');
        $this->item = $this->get('Item');
        $this->form = $this->get('Form');
        $this->user = $this->getCurrentUser();
        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }
        $this->addToolbar();

        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return void
     *
     * @throws Exception
     */
    protected function addToolbar() {
        Factory::getApplication()->input->set('hidemainmenu', true);

        $user = Factory::getApplication()->getIdentity();
        $isNew = ($this->item->id == 0);

        if (isset($this->item->checked_out)) {
            $checkedOut = !($this->item->checked_out == 0 || $this->item->checked_out == $user->get('id'));
        } else {
            $checkedOut = false;
        }

        $canDo = ContentHelper::getActions('com_ra_tools');

        ToolbarHelper::title(Text::_('API Site'), "generic");

        // If not checked out, can save the item.
        if (!$checkedOut && ($canDo->get('core.edit') || ($canDo->get('core.create')))) {
            ToolbarHelper::apply('apisite.apply', 'JTOOLBAR_APPLY');
            ToolbarHelper::save('apisite.save', 'JTOOLBAR_SAVE');
        }

        if (empty($this->item->id)) {
            ToolbarHelper::cancel('apisite.cancel', 'JTOOLBAR_CANCEL');
        } else {
            ToolbarHelper::cancel('apisite.cancel', 'JTOOLBAR_CLOSE');
        }
    }

}
