<?php

/**
 * Create the RA profile placeholder whenever Joomla creates a user.
 */

defined('_JEXEC') or die;

use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;
use Ramblers\Component\Ra_tools\Site\Helpers\PersonHelper;

class PlgUserRaProfile extends CMSPlugin {

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
    public function onUserAfterSave($user, $isNew, $success, $msg): void {
        if (!$isNew || !$success || !is_object($user) || (int) ($user->id ?? 0) < 1) {
            return;
        }

        try {
            (new PersonHelper())->ensurePlaceholderProfile((int) $user->id, (string) ($user->name ?? ''));
        } catch (\Throwable $exception) {
            Log::add(
                    'Unable to create RA profile placeholder for Joomla user ' . (int) $user->id
                    . ': ' . $exception->getMessage(),
                    Log::ERROR,
                    'ra_tools'
            );
        }
    }
}
