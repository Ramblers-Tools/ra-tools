<?php

namespace Ramblers\Component\Ra_tools\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Joomla\CMS\Pagination\Pagination;
use Ramblers\Component\Ra_tools\Site\Helpers\GpxFileHelper;

class GpxsModel extends BaseDatabaseModel
{
    private const PAGE_SIZE = 15;

    protected $pagination;

    public function getItems(): array
    {
        $pagination = $this->getPagination();
        $items = GpxFileHelper::listRoutes($pagination->limitstart, self::PAGE_SIZE);

        foreach ($items as &$item) {
            $item += ['start_latitude' => '', 'start_longitude' => ''];
        }
        unset($item);

        return $items;
    }

    public function getPagination(): Pagination
    {
        if (!$this->pagination) {
            $app = \Joomla\CMS\Factory::getApplication();
            $start = $app->getInput()->get('limitstart', 0, 'uint');
            $this->pagination = new Pagination(GpxFileHelper::countRoutes(), $start, self::PAGE_SIZE);
        }

        return $this->pagination;
    }

    public function getFolder(): string
    {
        return GpxFileHelper::configuredFolder();
    }
}
