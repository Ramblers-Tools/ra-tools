<?php

namespace Ramblers\Component\Ra_tools\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\BaseDatabaseModel;
use Ramblers\Component\Ra_tools\Site\Helpers\GpxFileHelper;

class GpxModel extends BaseDatabaseModel
{
    public function getItem(): array
    {
        $filename = Factory::getApplication()->getInput()->getString('file', '');
        return GpxFileHelper::readRoute($filename);
    }

    public function getFolder(): string
    {
        return GpxFileHelper::configuredFolder();
    }
}
