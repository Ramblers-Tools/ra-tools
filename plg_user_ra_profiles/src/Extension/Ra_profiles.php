<?php

namespace Ramblers\Plugin\User\Ra_profiles\Extension;

defined('_JEXEC') or die;

use Joomla\CMS\Plugin\CMSPlugin;
use Ramblers\Component\Ra_tools\Site\Helpers\AppSignInStore;
use Ramblers\Component\Ra_tools\Site\Helpers\PersonHelper;
use Joomla\CMS\Log\Log;
use Joomla\Event\SubscriberInterface;

class Ra_profiles extends CMSPlugin implements SubscriberInterface
{
    public static function getSubscribedEvents(): array
    {
        return [
            'onUserBeforeSave' => 'onUserBeforeSave',
            'onUserAfterSave' => 'onUserAfterSave',
            'onUserAfterDelete' => 'onUserAfterDelete',
        ];
    }

    /** Password hashes before a save, by user id, to tell if the password changed. */
    private array $passwordsBefore = [];

    /** Notes the password before an existing user is saved (for signing the app out). */
    public function onUserBeforeSave(...$arguments): void
    {
        if (count($arguments) === 1 && is_object($arguments[0]) && method_exists($arguments[0], 'getUser')) {
            // Joomla 5 BeforeSaveEvent: getUser() is the user as it was before the save.
            $old = $arguments[0]->getUser();
        } else {
            $old = $arguments[0] ?? null;
        }
        $old = (array) $old;
        if (!empty($old['id'])) {
            $this->passwordsBefore[(int) $old['id']] = (string) ($old['password'] ?? '');
        }
    }

    /** A deleted user's Ramblers Tools app sign-ins stop working. */
    public function onUserAfterDelete(...$arguments): void
    {
        if (count($arguments) === 1 && is_object($arguments[0]) && method_exists($arguments[0], 'getUser')) {
            $user = $arguments[0]->getUser();
        } else {
            $user = $arguments[0] ?? null;
        }
        $userId = (int) (((array) $user)['id'] ?? 0);
        $this->signOutApp($userId, 'user deleted');
    }

    /** Signs the Ramblers Tools app out everywhere for this user. */
    private function signOutApp(int $userId, string $why): void
    {
        if ($userId < 1 || !class_exists(AppSignInStore::class)) {
            return;
        }
        try {
            $store = new AppSignInStore();
            if ($store->revoke($userId) > 0) {
                $store->log('AR', $userId, 'App signed out everywhere: ' . $why);
            }
        } catch (\Throwable $exception) {
            // The tables may not exist yet during an upgrade.
            Log::add('Unable to sign out the Ramblers Tools app for user ' . $userId . ': ' . $exception->getMessage(), Log::WARNING, 'ra_tools');
        }
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

        if (!$isNew && $success && $userId > 0) {
            $passwordBefore = $this->passwordsBefore[$userId] ?? null;
            unset($this->passwordsBefore[$userId]);
            $blocked = (int) (is_array($user) ? ($user['block'] ?? 0) : ($user->block ?? 0)) === 1;
            $passwordNow = (string) (is_array($user) ? ($user['password'] ?? '') : ($user->password ?? ''));
            if ($blocked) {
                $this->signOutApp($userId, 'user blocked');
            } elseif ($passwordBefore !== null && $passwordNow !== '' && !hash_equals($passwordBefore, $passwordNow)) {
                $this->signOutApp($userId, 'password changed');
            }
        }

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
