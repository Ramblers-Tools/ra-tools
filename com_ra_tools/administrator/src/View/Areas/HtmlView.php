<?php

/**
 * 21/04/25 CB replace Ra_toolsHelper with ContentHelper
 * 09/11/26 CB prepare for Joomla 6 by removing references to SidebarHelper
 */

namespace Ramblers\Component\Ra_tools\Administrator\View\Areas;

// No direct access
defined('_JEXEC') or die;

use Joomla\CMS\Helper\ContentHelper;
use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\Component\Content\Administrator\Extension\ContentComponent;
use \Joomla\CMS\Form\Form;

/**
 * View class for a list of Areas.
 *
 * @since  3.0.0
 */
class HtmlView extends BaseHtmlView {

    protected $canDo;
    protected $items;
    protected $pagination;
    protected $state;

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
        $this->canDo = ContentHelper::getActions('com_ra_tools');
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
     * @return  void
     *
     * @since   3.0.0
     */
    protected function addToolbar() {
        $state = $this->get('State');

        ToolbarHelper::title(Text::_('Ramblers Areas'), "generic");

        $toolbar = Toolbar::getInstance('toolbar');

        // Check if the form exists before showing the add/edit buttons
        $formPath = JPATH_COMPONENT_ADMINISTRATOR . '/src/View/Areas';

        if (file_exists($formPath)) {
            if ($this->canDo->get('core.create')) {
                $toolbar->addNew('area.add');
            }
        }

        if ($this->canDo->get('core.edit.state')) {
            $dropdown = $toolbar->dropdownButton('status-group')
                    ->text('JTOOLBAR_CHANGE_STATUS')
                    ->toggleSplit(false)
                    ->icon('fas fa-ellipsis-h')
                    ->buttonClass('btn btn-action')
                    ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            if (isset($this->items[0]->state)) {

            } elseif (isset($this->items[0])) {
                // If this component does not use state then show a direct delete button as we can not trash
                $toolbar->delete('areas.delete')
                        ->text('JTOOLBAR_EMPTY_TRASH')
                        ->message('JGLOBAL_CONFIRM_DELETE')
                        ->listCheck(true);
            }


            if (isset($this->items[0]->checked_out)) {
                $childBar->checkin('areas.checkin')->listCheck(true);
            }
        }



        // Show trash and delete for components that uses the state field
        if (isset($this->items[0]->state)) {

            if ($this->state->get('filter.state') == ContentComponent::CONDITION_TRASHED && $this->canDo->get('core.delete')) {
                $toolbar->delete('areas.delete')
                        ->text('JTOOLBAR_EMPTY_TRASH')
                        ->message('JGLOBAL_CONFIRM_DELETE')
                        ->listCheck(true);
            }
        }
    }

    /**
     * Method to order fields
     *
     * @return void
     */
    protected function getSortFields() {
        return array(
            'a.`id`' => Text::_('JGRID_HEADING_ID'),
            'a.`nation_id`' => Text::_('COM_RA_TOOLS_AREAS_NATION_ID'),
            'a.`code`' => Text::_('COM_RA_TOOLS_AREAS_CODE'),
            'a.`name`' => Text::_('COM_RA_TOOLS_AREAS_NAME'),
            'a.`details`' => Text::_('COM_RA_TOOLS_AREAS_DETAILS'),
            'a.`website`' => Text::_('COM_RA_TOOLS_AREAS_WEBSITE'),
            'a.`co_url`' => Text::_('COM_RA_TOOLS_AREAS_CO_URL'),
            'a.`cluster`' => Text::_('COM_RA_TOOLS_AREAS_CLUSTER'),
            'a.`chair_id`' => Text::_('COM_RA_TOOLS_AREAS_CHAIR_ID'),
            'a.`latitude`' => Text::_('COM_RA_TOOLS_AREAS_LATITUDE'),
            'a.`longitude`' => Text::_('COM_RA_TOOLS_AREAS_LONGITUDE'),
            'a.`state`' => Text::_('JSTATUS'),
            'a.`created_by`' => Text::_('COM_RA_TOOLS_AREAS_CREATED_BY'),
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
