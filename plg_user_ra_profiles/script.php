<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;
use Ramblers\Component\Ra_tools\Site\Helpers\PersonHelper;

// Joomla derives this name as Plg + group + element + InstallerScript.
// For the user/ra_profiles plugin the element underscore is significant.
class Plguserra_profilesInstallerScript {

    private const VERSION = '1.0.13';

    private function message(string $message): void {
        Factory::getApplication()->enqueueMessage($message, 'message');
    }

    private function getInstalledVersion(): ?string {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $type = 'plugin';
        $folder = 'user';
        $element = 'ra_profiles';
        $query = $db->getQuery(true)
                ->select($db->quoteName('manifest_cache'))
                ->from($db->quoteName('#__extensions'))
                ->where($db->quoteName('type') . ' = :type')
                ->where($db->quoteName('folder') . ' = :folder')
                ->where($db->quoteName('element') . ' = :element')
                ->bind(':type', $type, ParameterType::STRING)
                ->bind(':folder', $folder, ParameterType::STRING)
                ->bind(':element', $element, ParameterType::STRING);
        $db->setQuery($query);
        $cache = $db->loadResult();

        if (!$cache) {
            return null;
        }

        $manifest = json_decode((string) $cache, true);
        return is_array($manifest) && isset($manifest['version'])
                ? (string) $manifest['version'] : null;
    }

    public function preflight(string $type, InstallerAdapter $parent): bool {
        $this->message('Preflight RA Profile plugin (type=' . $type . ', target version '
                . self::VERSION . ').');

        if ($type !== 'install') {
            $this->message('Existing RA Profile plugin version: '
                    . ($this->getInstalledVersion() ?? 'not recorded') . '.');
        }

        return true;
    }

    public function install(InstallerAdapter $parent): bool {
        $this->message('Installing RA Profile plugin version ' . self::VERSION . '.');
        return true;
    }

    public function update(InstallerAdapter $parent): bool {
        $this->message('Updating RA Profile plugin to version ' . self::VERSION . '.');
        return true;
    }

    /**
     * Backfill one unpublished placeholder for every user that has none.
     *
     * The operation is idempotent and deliberately uses created_by = 0 to
     * identify system-created placeholders.
     */
    public function postflight(string $type, InstallerAdapter $parent): void {
        $this->message('Postflight RA Profile plugin (version ' . self::VERSION . ').');

        if ($type === 'uninstall') {
            return;
        }

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

            $this->message('Invoking RA Tools PersonHelper placeholder backfill.');

            if (!class_exists(PersonHelper::class)) {
                throw new \RuntimeException(
                        'RA Tools PersonHelper is not available. Update com_ra_tools before installing this plugin.'
                );
            }

            if (!method_exists(PersonHelper::class, 'createMissingPlaceholderProfiles')) {
                throw new \RuntimeException(
                        'The installed com_ra_tools version does not provide '
                        . 'PersonHelper::createMissingPlaceholderProfiles(). Update com_ra_tools first.'
                );
            }

            $count = (new PersonHelper())->createMissingPlaceholderProfiles();

            $this->message('RA Tools PersonHelper placeholder backfill returned ' . $count . ' record(s).');

            $this->message('RA profile placeholder backfill complete: created '
                    . $count . ' record(s).');
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

    public function uninstall(InstallerAdapter $parent): bool {
        $this->message('Uninstalling RA Profile plugin version '
                . ($this->getInstalledVersion() ?? 'not recorded') . '.');
        return true;
    }
}
