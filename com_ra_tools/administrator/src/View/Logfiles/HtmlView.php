<?php

/**
 * 01/05/25 CB created from generated code
 * 09/11/26 CB prepare for Joomla 6 by removing references to SidebarHelper
 */

namespace Ramblers\Component\Ra_tools\Administrator\View\Logfiles;

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Language\Text;

/**
 * View class for a list of Logfiles.
 *
 * @since  2.0
 */
class HtmlView extends BaseHtmlView {

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
     * @since   2.0
     */
    protected function addToolbar() {
        $state = $this->get('State');
//        $this->canDo = ContentHelper::getActions('com_ra_tools');

        ToolbarHelper::title('Logfile records');

        $toolbar = Toolbar::getInstance('toolbar');

        // code from https://docs.joomla.org/J4.x:Joomla_4_Tips_and_Tricks:_Number_of_Records
        $toolbar->standardButton('nrecords')
                ->icon('fa fa-info-circle')
                ->text(number_format($this->pagination->total) . ' Records')
                ->task('')
                ->onclick('return false')
                ->listCheck(false);

        ToolbarHelper::cancel('logfiles.cancel', 'Return to Reports menu');
    }

    /**
     * Method to order fields
     *
     * @return void
     */
    protected function getSortFields() {
        return array(
            'a.`id`' => Text::_('JGRID_HEADING_ID'),
            'a.`sub_system`' => Text::_('COM_RA_TOOLS_LOGFILES_SUB_SYSTEM'),
            'a.`record_type`' => Text::_('COM_RA_TOOLS_LOGFILES_RECORD_TYPE'),
            'a.`log_date`' => Text::_('COM_RA_TOOLS_LOGFILES_LOG_DATE'),
            'a.`message`' => Text::_('COM_RA_TOOLS_LOGFILES_MESSAGE'),
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
