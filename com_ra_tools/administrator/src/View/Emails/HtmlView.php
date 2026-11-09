<?php

/**
 * 16/07/25 CB regenerated
 * 20/07/25 CB Publish / Unpublish / Delete
 * 09/11/26 CB prepare for Joomla 6 by removing references to SidebarHelper
 */

namespace Ramblers\Component\Ra_tools\Administrator\View\Emails;

// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use \Joomla\CMS\Toolbar\Toolbar;
use \Joomla\CMS\Toolbar\ToolbarHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\Component\Content\Administrator\Extension\ContentComponent;
use \Joomla\CMS\Form\Form;
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * View class for a list of Emails.
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
        $this->toolsHelper = new Toolshelper;
        $breadcrumbs = $this->toolsHelper->buildLink('administrator/index.php', 'Home Dashboard');
        $breadcrumbs .= '>' . $this->toolsHelper->buildLink('administrator/index.php?option=com_ra_tools&view=dashboard', 'RA Dashboard');
        $breadcrumbs .= '>' . $this->toolsHelper->buildLink('administrator/index.php?option=com_ra_tools&view=reports', 'System Reports');
        echo $breadcrumbs;
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
        $canDo = ToolsHelper::getActions();

        ToolbarHelper::title(Text::_('Emails'), "generic");

        $toolbar = Toolbar::getInstance('toolbar');

        if ($canDo->get('core.edit.state')) {
            $dropdown = $toolbar->dropdownButton('status-group')
                    ->text('JTOOLBAR_CHANGE_STATUS')
                    ->toggleSplit(false)
                    ->icon('fas fa-ellipsis-h')
                    ->buttonClass('btn btn-action')
                    ->listCheck(true);

            $childBar = $dropdown->getChildToolbar();

            if (isset($this->items[0]->state)) {
                $childBar->publish('emails.publish')->listCheck(true);
                $childBar->unpublish('emails.unpublish')->listCheck(true);
            }

            if (isset($this->items[0]->checked_out)) {
                $childBar->checkin('emails.checkin')->listCheck(true);
            }
            if ($canDo->get('core.delete')) {
                $toolbar->delete('emails.delete')
                        ->text('Delete')
                        ->message('JGLOBAL_CONFIRM_DELETE')
                        ->listCheck(true);
            }
//            if (isset($this->items[0]->state)) {
//                $childBar->trash('emails.trash')->listCheck(true);
//            }
            $toolbar->standardButton('nrecords')
                    ->icon('fa fa-info-circle')
                    ->text(number_format($this->pagination->total) . ' Records')
                    ->task('')
                    ->onclick('return false')
                    ->listCheck(false);
        }
        ToolbarHelper::cancel('emails.cancel', 'Return to Dashboard');
    }

    /**
     * Method to order fields
     *
     * @return void
     */
    protected function getSortFields() {
        return array(
            'a.`id`' => Text::_('JGRID_HEADING_ID'),
            'a.`sub_system`' => Text::_('COM_RA_TOOLS_EMAILS_SUB_SYSTEM'),
            'a.`record_type`' => Text::_('COM_RA_TOOLS_EMAILS_RECORD_TYPE'),
            'a.`date_sent`' => Text::_('COM_RA_TOOLS_EMAILS_DATE_SENT'),
            'a.`addressee`' => Text::_('COM_RA_TOOLS_EMAILS_ADDRESSEE'),
            'a.`title`' => Text::_('COM_RA_TOOLS_EMAILS_TITLE'),
            'a.`body`' => Text::_('COM_RA_TOOLS_EMAILS_BODY'),
            'a.`attachments`' => Text::_('COM_RA_TOOLS_EMAILS_ATTACHMENTS'),
            'a.`sender`' => Text::_('COM_RA_TOOLS_EMAILS_SENDER'),
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
