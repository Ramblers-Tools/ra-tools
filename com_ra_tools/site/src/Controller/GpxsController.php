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
            foreach ($this->normaliseUploads(is_array($upload) ? $upload : []) as $file) {
                try {
                    $filename = GpxFileHelper::upload($file);
                    $this->app->enqueueMessage('Uploaded ' . $filename . '.', 'message');
                } catch (\RuntimeException $exception) {
                    $name = trim((string) ($file['name'] ?? ''));
                    $prefix = $name !== '' ? $name . ': ' : '';
                    $this->app->enqueueMessage($prefix . $exception->getMessage(), 'error');
                }
            }
        } catch (\RuntimeException $exception) {
            $this->app->enqueueMessage($exception->getMessage(), 'error');
        }

        $this->setRedirect($this->listUrl());
    }

    public function delete(): void
    {
        if (strtoupper($this->input->getMethod()) !== 'POST') {
            throw new \RuntimeException('POST request required.', 405);
        }
        $this->checkToken('post');
        $this->requireDeletePermission();

        try {
            $filename = GpxFileHelper::deleteRoute($this->input->post->getString('file', ''));
            $this->app->enqueueMessage('Deleted ' . $filename . '.', 'message');
        } catch (\RuntimeException $exception) {
            $this->app->enqueueMessage($exception->getMessage(), 'error');
        }

        $this->setRedirect($this->listUrl());
    }

    private function normaliseUploads(array $upload): array
    {
        // Joomla's Files input pivots PHP's $_FILES structure into a list of file records.
        if (isset($upload[0]) && is_array($upload[0]) && array_key_exists('name', $upload[0])) {
            return array_values($upload);
        }

        // Also accept the original, unpivoted $_FILES shape.
        $names = $upload['name'] ?? [];
        if (!is_array($names)) {
            $names = [$names];
        }

        $files = [];
        foreach (array_keys($names) as $key) {
            $file = [];
            foreach (['name', 'type', 'tmp_name', 'error', 'size'] as $field) {
                $values = $upload[$field] ?? [];
                $file[$field] = is_array($values) ? ($values[$key] ?? null) : $values;
            }
            $files[] = $file;
        }

        if ($files === []) {
            throw new \RuntimeException('Select at least one GPX file to upload.');
        }

        return $files;
    }

    private function requireManagePermission(): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.create', 'com_ra_tools') && !$user->authorise('core.edit', 'com_ra_tools')) {
            throw new \RuntimeException('You are not authorised to upload GPX files.', 403);
        }
    }

    private function requireDeletePermission(): void
    {
        $user = Factory::getApplication()->getIdentity();
        if (!$user->authorise('core.delete', 'com_ra_tools')) {
            throw new \RuntimeException('You are not authorised to delete GPX files.', 403);
        }
    }

    private function listUrl(): string
    {
        $itemId = $this->input->getInt('Itemid', 0);
        $folder = $this->input->getString('folder', '');
        $folderParam = $folder !== '' ? '&folder=' . rawurlencode($folder) : '';
        return Route::_('index.php?option=com_ra_tools&view=gpxs&Itemid=' . $itemId . $folderParam, false);
    }
}
