<?php

namespace Ramblers\Plugin\User\Ra_profiles\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Ramblers\Component\Ra_tools\Site\Helpers\PersonHelper;
use Joomla\CMS\Log\Log;
use Joomla\Event\SubscriberInterface;

class Ra_profiles extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return ['onUserAfterSave' => 'onUserAfterSave'];
    }

    public function onUserAfterSave(...$arguments): void
    {
        Log::add('RA Profiles diagnostic: namespaced onUserAfterSave invoked.', Log::INFO, 'ra_tools');
        if (isset($this->app) && is_object($this->app)
                && method_exists($this->app, 'enqueueMessage')) {
            $this->app->enqueueMessage('RA Profiles diagnostic: user-save handler invoked.', 'notice');
        }

        if (count($arguments) === 1 && is_object($arguments[0])
                && method_exists($arguments[0], 'getUser')) {
            $event = $arguments[0];
            $user = $event->getUser();
            $isNew = $event->getIsNew();
            $success = $event->getSavingResult();
        } else {
            [$user, $isNew, $success] = array_pad($arguments, 3, null);
        }

        $userId = is_array($user) ? (int) ($user['id'] ?? 0) : (int) ($user->id ?? 0);
        $name = is_array($user) ? (string) ($user['name'] ?? '') : (string) ($user->name ?? '');

        if (!$isNew || !$success || $userId < 1) {
            return;
        }

        try {
            (new PersonHelper())->ensurePlaceholderProfile($userId, $name);
            if (isset($this->app) && is_object($this->app)
                    && method_exists($this->app, 'enqueueMessage')) {
                $this->app->enqueueMessage(
                        'RA profile placeholder created for Joomla user ' . $userId . '.',
                        'message'
                );
            }
        } catch (\Throwable $exception) {
            Log::add(
                    'Unable to create RA profile placeholder for Joomla user ' . $userId
                    . ': ' . $exception->getMessage(),
                    Log::ERROR,
                    'ra_tools'
            );
            if (isset($this->app) && is_object($this->app)
                    && method_exists($this->app, 'enqueueMessage')) {
                $this->app->enqueueMessage(
                        'Unable to create the RA profile placeholder for Joomla user '
                        . $userId . ': ' . $exception->getMessage(),
                        'error'
                );
            }
        }
    }
}
