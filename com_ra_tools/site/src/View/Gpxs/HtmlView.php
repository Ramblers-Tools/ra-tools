<?php

namespace Ramblers\Component\Ra_tools\Site\View\Gpxs;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\View\HtmlView as BaseHtmlView;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

class HtmlView extends BaseHtmlView
{
    protected $items = [];
    protected $pagination;
    protected $folder = '';
    protected $params;
    protected $canManage = false;
    protected $canDelete = false;
    protected $toolsHelper;

    public function display($tpl = null)
    {
        $app = Factory::getApplication();
        $this->document->getWebAssetManager()
            ->useScript('keepalive')
            ->useScript('form.validate');
        $menu = $app->getMenu()->getActive();
        $this->params = $menu ? $menu->getParams() : $app->getParams('com_ra_tools');
        $this->items = $this->get('Items');
        $this->pagination = $this->get('Pagination');
        $this->folder = $this->get('Folder');
        $this->toolsHelper = new ToolsHelper();
        $actions = $this->toolsHelper->getActions('com_ra_tools');
        $this->canManage = $actions->get('core.create') || $actions->get('core.edit');
        $this->canDelete = $actions->get('core.delete');

        if (count($errors = $this->get('Errors'))) {
            throw new \RuntimeException(implode("\n", $errors));
        }

        $this->document->setTitle($this->canManage ? 'Edit GPX routes' : 'Show GPX routes');
        return parent::display($tpl);
    }
}
