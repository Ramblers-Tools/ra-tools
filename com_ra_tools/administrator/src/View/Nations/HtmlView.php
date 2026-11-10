<?php

/**
 * 21/04/25 CB replace Ra_toolsHelper with ContentHelper
 * 09/11/26 CB prepare for Joomla 6 by removing references to SidebarHelper
 */

namespace Ramblers\Component\Ra_tools\Administrator\View\Nations;

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
 * View class for a list of Nations.
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

        ToolbarHelper::title(Text::_('COM_RA_TOOLS_TITLE_NATIONS'), "generic");

        $toolbar = Toolbar::getInstance('toolbar');

// Check if the form exists before showing the add/edit buttons
        $formPath = JPATH_COMPONENT_ADMINISTRATOR . '/src/View/Nations';

        if (file_exists($formPath)) {
            if ($this->canDo->get('core.create')) {
                $toolbar->addNew('nation.add');
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

            if (isset($this->items[0])) {
// If this component does not use state then show a direct delete button as we can not trash
                $toolbar->delete('nations.delete')
                        ->text('JTOOLBAR_EMPTY_TRASH')
                        ->message('JGLOBAL_CONFIRM_DELETE')
                        ->listCheck(true);
            }

            if (isset($this->items[0]->checked_out)) {
                $childBar->checkin('nations.checkin')->listCheck(true);
            }

            if (isset($this->items[0]->state)) {
                $childBar->trash('nations.trash')->listCheck(true);
            }
// code from https://docs.joomla.org/J4.x:Joomla_4_Tips_and_Tricks:_Number_of_Records
            $toolbar->standardButton('nrecords')
                    ->icon('fa fa-info-circle')
                    ->text(number_format($this->pagination->total) . ' Records')
                    ->task('')
                    ->onclick('return false')
                    ->listCheck(false);
        }

        ToolbarHelper::cancel('area_list.cancel', 'Return to Dashboard');
    }

    /*

      // Show trash and delete for components that uses the state field
      if (isset($this->items[0]->state)) {

      if ($this->state->get('filter.state') == ContentComponent::CONDITION_TRASHED && $this->canDo->get('core.delete')) {
      $toolbar->delete('nations.delete')
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
            'a.`code`' => Text::_('COM_RA_TOOLS_NATIONS_CODE'),
            'a.`name`' => Text::_('COM_RA_TOOLS_NATIONS_NAME'),
            'a.`attachment`' => Text::_('COM_RA_TOOLS_NATIONS_ATTACHMENT'),
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
