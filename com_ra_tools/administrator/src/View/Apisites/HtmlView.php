<?php

/**
 * @version    3.3.10
 * @package    com_ra_tools
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 30/06/25 CB delete Options button
 * 21/08/25 CB remove trash, create delete button
 */

namespace Ramblers\Component\Ra_tools\Administrator\View\Apisites;

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Ramblers\Component\Ra_tools\Administrator\Helper\Ra_eventsHelper;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Language\Text;
use Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\Form\Form;
use \Joomla\CMS\HTML\Helpers\Sidebar;
use \Joomla\CMS\User\CurrentUserInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a list of Apisites.
 *
 * @since  2.1.0
 */
class HtmlView extends BaseHtmlView {

    protected $items;
    protected $pagination;
    protected $state;
    protected $toolsHelper;
    protected $user;

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
        $this->state = $this->get('State');
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->filterForm = $this->get('FilterForm');
        $this->activeFilters = $this->get('ActiveFilters');
        $this->user = $this->getCurrentUser();
        $this->toolsHelper = new ToolsHelper;
        
        // Check for errors.
        if (count($errors = $this->get('Errors'))) {
            throw new \Exception(implode("\n", $errors));
        }

        $this->addToolbar();

        $this->sidebar = Sidebar::render();
        parent::display($tpl);
    }

    /**
     * Add the page title and toolbar.
     *
     * @return  void
     *
     * @since   2.1.0
     */
    protected function addToolbar() {
        $state = $this->get('State');
        $canDo = ContentHelper::getActions('com_ra_tools');

        ToolbarHelper::title(Text::_('API sites'), "generic");

        $toolbar = Toolbar::getInstance('toolbar');

        // Check if the form exists before showing the add/edit buttons
        $formPath = JPATH_COMPONENT_ADMINISTRATOR . '/src/View/Apisites';

        if (file_exists($formPath)) {
            if ($canDo->get('core.create')) {
                $toolbar->addNew('apisite.add');
            }
        }

        if ($canDo->get('core.edit.state')) {
            $dropdown = $toolbar->dropdownButton('status-group')
                    ->text('JTOOLBAR_CHANGE_STATUS')
                    ->toggleSplit(false)
                    ->icon('fas fa-ellipsis-h')
                    ->buttonClass('btn btn-action')
                    ->listCheck(true);
//
//            $childBar = $dropdown->getChildToolbar();
//            if (isset($this->items[0]->state)) {
//                $childBar->trash('apisites.trash')->listCheck(true);
//            }


            if ($canDo->get('core.delete')) {
                $toolbar->delete('apisites.delete')
                        ->text('Delete')
                        ->message('JGLOBAL_CONFIRM_DELETE')
                        ->listCheck(true);
            }
            $toolbar->standardButton('nrecords')
                    ->icon('fa fa-info-circle')
                    ->text(number_format($this->pagination->total) . ' Records')
                    ->task('')
                    ->onclick('return false')
                    ->listCheck(false);

            ToolbarHelper::cancel('apisites.cancel', 'Return to Dashboard');
        }



        // Show trash and delete for components that uses the state field
        if (isset($this->items[0]->state)) {

//            if ($this->state->get('filter.state') == ContentComponent::CONDITION_TRASHED && $canDo->get('core.delete')) {
//                $toolbar->delete('apisites.delete')
//                        ->text('JTOOLBAR_EMPTY_TRASH')
//                        ->message('JGLOBAL_CONFIRM_DELETE')
//                        ->listCheck(true);
//            }
        }

        // Set sidebar action
        Sidebar::setAction('index.php?option=com_ra_tools&view=apisites');
    }

    /**
     * Method to order fields
     *
     * @return void
     */
    protected function getSortFields() {
        return array(
            'a.`id`' => Text::_('JGRID_HEADING_ID'),
            'a.`state`' => Text::_('JSTATUS'),
            'a.`url`' => Text::_('COM_RA_EVENTS_APISITES_URL'),
            'a.`colour`' => Text::_('COM_RA_EVENTS_APISITES_COLOUR'),
            'a.`token`' => Text::_('COM_RA_EVENTS_APISITES_TOKEN'),
        );
    }

    /**
     * Check if state is set
     *
     * @param   mixed  $state  State
     *
     * @return bool
     */
    public function getState($state) {
        return isset($this->state->{$state}) ? $this->state->{$state} : false;
    }

}
