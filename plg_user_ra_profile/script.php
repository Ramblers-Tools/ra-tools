<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;

class PlgUserRaProfileInstallerScript {

    /**
     * Backfill one unpublished placeholder for every user that has none.
     *
     * The operation is idempotent and deliberately uses created_by = 0 to
     * identify system-created placeholders.
     */
    public function postflight(string $type, InstallerAdapter $parent): void {
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        try {
            $tables = $db->getTableList();
            $profileTable = strtolower($db->replacePrefix('#__ra_profiles'));

            if (!in_array($profileTable, array_map('strtolower', $tables), true)) {
                Factory::getApplication()->enqueueMessage(
                        'RA profile placeholders were not backfilled because #__ra_profiles does not exist yet.',
                        'warning'
                );
                return;
            }

            $query = $db->getQuery(true)
                    ->insert($db->quoteName('#__ra_profiles'))
                    ->columns($db->quoteName(['id', 'home_group', 'preferred_name', 'state', 'created_by']))
                    ->select(
                            'u.id, ' . $db->quote('ZZ99') . ', u.name, 0, 0'
                            . ' FROM ' . $db->quoteName('#__users') . ' AS u'
                            . ' LEFT JOIN ' . $db->quoteName('#__ra_profiles') . ' AS p ON p.id = u.id'
                            . ' WHERE p.id IS NULL'
                    );

            $db->setQuery($query)->execute();
            $count = (int) $db->getAffectedRows();

            if ($count > 0) {
                Factory::getApplication()->enqueueMessage(
                        'Created ' . $count . ' RA profile placeholder(s).',
                        'message'
                );
            }
        } catch (\Throwable $exception) {
            Log::add(
                    'RA profile placeholder backfill failed: ' . $exception->getMessage(),
                    Log::ERROR,
                    'ra_tools'
            );
            Factory::getApplication()->enqueueMessage(
                    'RA profile placeholder backfill failed: ' . $exception->getMessage(),
                    'warning'
            );
        }
    }
}
