<?php

/**
 * 25/10/26 CB Created
 */

namespace Ramblers\Component\Ra_tools\Administrator\View\Ra_users;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\View\GenericDataException;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Joomla\CMS\Factory;

/**
 * View class for the user selection modal.
 */
class HtmlView extends BaseHtmlView {

    protected $items;
    protected $pagination;
    protected $state;
    public $groupSelectionValid;

    public function display($tpl = null) {
        if (!Factory::getApplication()->getIdentity()->authorise('core.manage', 'com_users')) {
            throw new \RuntimeException('You are not authorised to select users.', 403);
        }

        $model = $this->getModel();
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->state = $this->get('State');
        $this->groupSelectionValid = $model->hasValidGroupSelection();
        if (count($errors = $this->get('Errors'))) {
            throw new GenericDataException(implode("\n", $errors), 500);
        }

        return parent::display($tpl);
    }
}
