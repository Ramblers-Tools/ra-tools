<?php

namespace Ramblers\Component\Ra_tools\Site\View\Gpx;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class HtmlView extends BaseHtmlView
{
    protected $item;
    protected $folder = '';
    protected $params;
    protected $canManage = false;

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $menu = $app->getMenu()->getActive();
        $this->params = $menu ? $menu->getParams() : $app->getParams('com_ra_tools');
        $this->item = $this->get('Item');
        $this->folder = $this->get('Folder');
        $actions = ToolsHelper::getActions('com_ra_tools');
        $this->canManage = $actions->get('core.edit') || $actions->get('core.create');

        if (count($errors = $this->get('Errors'))) {
            throw new \RuntimeException(implode("\n", $errors));
        }

        $this->document->setTitle($this->canManage ? 'Edit GPX route' : 'Show GPX route');
        return parent::display($tpl);
    }
}
