<?php

/**
 * Create the RA profile placeholder whenever Joomla creates a user.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Event\SubscriberInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\PersonHelper;

class PlgUserRaProfiles extends CMSPlugin implements SubscriberInterface {

    /**
     * Subscribe explicitly so the plugin works with Joomla's current event
     * dispatcher as well as the legacy plugin dispatcher.
     */
    public static function getSubscribedEvents(): array {
        return ['onUserAfterSave' => 'onUserAfterSave'];
    }

    /**
     * Create an unpublished ZZ99 profile for a newly-created Joomla user.
     *
     * @param   object  $user     Saved Joomla user.
     * @param   bool    $isNew    Whether this is a new user.
     * @param   bool    $success  Whether Joomla saved the user successfully.
     * @param   string  $msg      Joomla save message.
     *
     * @return  void
     */
    public function onUserAfterSave(...$arguments): void {
        Log::add('RA Profiles diagnostic: legacy onUserAfterSave invoked.', Log::INFO, 'ra_tools');
        if (isset($this->app) && is_object($this->app)
                && method_exists($this->app, 'enqueueMessage')) {
            $this->app->enqueueMessage('RA Profiles diagnostic: user-save handler invoked.', 'notice');
        }

        // Joomla 4/5/6 may pass an event object; older dispatchers pass the
        // four legacy arguments directly.
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

            // Do not hide a failed automatic repair from an administrator.
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
