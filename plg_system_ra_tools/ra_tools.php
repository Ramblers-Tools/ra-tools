<?php
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Plugin\CMSPlugin;

/** Removes component-owned records after Joomla removes a user. */
class PlgSystemRaTools extends CMSPlugin
{
    /** @param mixed $event Joomla 5/6 AfterDeleteEvent, or legacy user array. */
    public function onUserAfterDelete($event): void
    {
        if (is_object($event) && method_exists($event, 'getDeletingResult')) {
            if (!$event->getDeletingResult()) {
                return;
            }
            $properties = $event->getUser();
        } elseif (is_array($event)) {
            $properties = $event;
        } else {
            return;
        }

        $userId = (int) ($properties['id'] ?? 0);
        if ($userId <= 0) {
            return;
        }

        $db = Factory::getContainer()->get('Joomla\\Database\\DatabaseInterface');
        $tables = ['#__ra_profiles'];
        if (ComponentHelper::isEnabled('com_ra_events', true)) {
            $tables[] = '#__ra_bookings';
        }
        if (ComponentHelper::isEnabled('com_ra_mailman', true)) {
            $tables[] = '#__ra_mail_recipients';
            try {
                $subscriptionIds = $db->setQuery(
                    $db->getQuery(true)->select('id')->from($db->quoteName('#__ra_mail_subscriptions'))
                        ->where($db->quoteName('user_id') . ' = ' . $userId)
                )->loadColumn();
                if ($subscriptionIds) {
                    $db->setQuery(
                        $db->getQuery(true)->delete($db->quoteName('#__ra_mail_subscriptions_audit'))
                            ->where($db->quoteName('object_id') . ' IN (' . implode(',', array_map('intval', $subscriptionIds)) . ')')
                    )->execute();
                }
            } catch (Throwable $exception) {
                Log::add('RA Tools could not remove subscription audit records for deleted user ' . $userId . ': ' . $exception->getMessage(), Log::WARNING, 'com_ra_tools');
            }
            $tables[] = '#__ra_mail_subscriptions';
        }

        foreach ($tables as $table) {
            try {
                $column = $table === '#__ra_profiles' ? 'id' : 'user_id';
                $query = $db->getQuery(true)
                    ->delete($db->quoteName($table))
                    ->where($db->quoteName($column) . ' = ' . $userId);
                $db->setQuery($query)->execute();
            } catch (Throwable $exception) {
                Log::add(
                    'RA Tools could not remove dependent records for deleted user ' . $userId
                    . ' from ' . $table . ': ' . $exception->getMessage(),
                    Log::WARNING,
                    'com_ra_tools'
                );
            }
        }
    }
}
