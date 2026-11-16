<?php

namespace Ramblers\Component\Ra_tools\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_tools\Site\Helpers\GpxFileHelper;

class GpxsController extends BaseController
{
    public function upload(): void
    {
        if (strtoupper($this->input->getMethod()) !== 'POST') {
            throw new \RuntimeException('POST request required.', 405);
        }
        $this->checkToken('post');
        $this->requireManagePermission();

        try {
            $upload = $this->input->files->get('gpx_file', [], 'array');
            $filename = GpxFileHelper::upload(is_array($upload) ? $upload : []);
            $this->app->enqueueMessage('Uploaded ' . $filename . '.', 'message');
        } catch (\RuntimeException $exception) {
            $this->app->enqueueMessage($exception->getMessage(), 'error');
        }

        $this->setRedirect($this->listUrl());
    }

    private function requireManagePermission(): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.create', 'com_ra_tools') && !$user->authorise('core.edit', 'com_ra_tools')) {
            throw new \RuntimeException('You are not authorised to upload GPX files.', 403);
        }
    }

    private function listUrl(): string
    {
        $itemId = $this->input->getInt('Itemid', 0);
        return Route::_('index.php?option=com_ra_tools&view=gpxs&Itemid=' . $itemId, false);
    }
}
