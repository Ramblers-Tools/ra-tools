<?php

namespace Ramblers\Component\Ra_tools\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_tools\Site\Helpers\GpxFileHelper;

class GpxController extends BaseController
{
    public function save(): void
    {
        if (strtoupper($this->input->getMethod()) !== 'POST') {
            throw new \RuntimeException('POST request required.', 405);
        }
        $this->checkToken('post');
        $this->requireManagePermission();

        $filename = $this->input->post->getString('file', '');
        try {
            $savedFilename = GpxFileHelper::saveRoute(
                $filename,
                $this->input->post->getString('route_name', ''),
                $this->input->post->getString('description', ''),
                $this->input->post->getString('save_as', '')
            );
            $this->app->enqueueMessage('Saved ' . $savedFilename . '.', 'message');
            $url = $this->listUrl();
        } catch (\RuntimeException $exception) {
            $this->app->enqueueMessage($exception->getMessage(), 'error');
            $url = $this->routeUrl('gpx', $filename);
        }

        $this->setRedirect($url);
    }

    private function requireManagePermission(): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.edit', 'com_ra_tools') && !$user->authorise('core.create', 'com_ra_tools')) {
            throw new \RuntimeException('You are not authorised to edit GPX files.', 403);
        }
    }

    private function routeUrl(string $view, string $filename): string
    {
        $itemId = $this->input->getInt('Itemid', 0);
        $folder = $this->input->getString('folder', '');
        $folderParam = $folder !== '' ? '&folder=' . rawurlencode($folder) : '';
        return Route::_('index.php?option=com_ra_tools&view=' . $view . '&file=' . rawurlencode($filename)
            . $folderParam . '&Itemid=' . $itemId, false);
    }

    private function listUrl(): string
    {
        $itemId = $this->input->getInt('Itemid', 0);
        $folder = $this->input->getString('folder', '');
        $folderParam = $folder !== '' ? '&folder=' . rawurlencode($folder) : '';
        return Route::_('index.php?option=com_ra_tools&view=gpxs' . $folderParam . '&Itemid=' . $itemId, false);
    }
}
