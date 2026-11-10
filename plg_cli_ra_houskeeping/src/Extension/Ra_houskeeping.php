<?php

namespace Ramblers\Plugin\Console\Ra_houskeeping\Extension;

\defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Ramblers\Plugin\Console\Ra_houskeeping\CliCommand\HousekeepingCommand;

final class Ra_houskeeping extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            \Joomla\Application\ApplicationEvents::BEFORE_EXECUTE => 'registerCommands',
        ];
    }

    public function registerCommands(): void
    {
        $this->getApplication()->addCommand(new HousekeepingCommand());
    }
}
