<?php

/*
 * Installation script
 * 18/10/26 CB Created from com_ra_mailman script
 *             Deleted schema updates
 * 
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class Pkg_Ra_toolsInstallerScript {

    private $minimumJoomlaVersion = '4.2';
    private $minimumPHPVersion = JOOMLA_MINIMUM_PHP;
    private $minimumToolsVersion = '4.0.0';
    private $reconfigure_message;
    private $current_version;
    private $version_required;
    private $dbPrefix;
    private $db;
    private $versionCache = [];
    private $dbVersionCache = [];

    private function message(string $message, string $type = 'message'): void {
        Factory::getApplication()->enqueueMessage($message, $type);
    }

    function buildButton($url, $text, $newWindow = 0, $colour = '') {
        if ($colour == '') {
            $colour = 'sunrise';
        }
        $class = 'link-button ' . $colour;
        $q = chr(34);
        $out = "<a class=" . $q . $class . $q;
        $out .= " href=" . $q . $url . $q;
        $out .= " target =" . $q . "_self" . $q;
        $out .= ">";
        $out .= $text;
        $out .= "</a>";
        return $out;
    }

    function checkColumn($table, $column, $mode, $details = '') {
//  $mode = A: add the field, using data supplied in $details
//  $mode = U: update the field (keeping name the same), using $details
//  $mode = D: delete the field
//  Safe to re-run: checks information_schema first

        $count = $this->checkColumnExists($table, $column);
        $table_name = $this->dbPrefix . $table;
        if (($mode == 'A') AND ($count == 1)
                OR ($mode == 'D') AND ($count == 0)) {
            return true;
        }
        if (($mode == 'U') AND ($count == 0)) {
            return $this->fail('Installer could not update missing field ' . $table_name . '.' . $column . '.');
        }

        $sql = 'ALTER TABLE ' . $table_name . ' ';
        if ($mode == 'A') {
            $sql .= 'ADD ' . $column . ' ';
            $sql .= $details;
        } elseif ($mode == 'D') {
            $sql .= 'DROP ' . $column;
        } elseif ($mode == 'U') {
            $sql .= 'CHANGE ' . $column . ' ' . $column . ' ';
            $sql .= $details;
        }
        $this->message($sql);
        try {
            $this->executeCommand($sql);
        } catch (\RuntimeException $exception) {
            return $this->fail('Installer failed to alter database table ' . $table_name . ': ' . $exception->getMessage());
        }

        $this->message('Success for ' . $table_name . '.' . $column);
        return true;
    }

    private function checkColumnExists($table, $column) {
        $config = Factory::getApplication()->getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $this->dbPrefix . $table . "' ";
        $sql .= "AND COLUMN_NAME='" . $column . "'";

        return (int) $this->getValue($sql);
    }

    private function deleteFile($target) {
        $file = JPATH_ROOT . $target;
        if (file_exists($file)) {
            File::delete($file);
            if (file_exists($file)) {
                $this->message('File ' . $file . ' found, but unable to delete', 'warning');
            } else {
                $this->message('File ' . $file . ' deleted');
            }
        } else {
            $this->message('Unable to delete ' . $file . ': file not found');
        }
    }

    private function deleteFolder($target) {
        $folder = JPATH_ROOT . $target;
        if (file_exists($folder)) {
            $deleted = Folder::delete($folder);
            if (!$deleted || file_exists($folder)) {
                $this->message('Folder ' . $folder . ' found, but unable to delete', 'warning');
            } else {
                $this->message('Folder ' . $folder . ' deleted');
            }
        } else {
            $this->message('Unable to delete ' . $folder . ': folder not found');
        }
    }

    public function deleteView($view, $application = '') {
// first character of View must be upper case
        $component = 'com_ra_tools';
        $this->message('Deleting ' . ($application == '' ? 'Site' : $application) . ' files for view ' . $view);

        $this->deleteFile($application . '/components/' . $component . '/forms/filter_' . strtolower($view) . '.xml');
        $this->deleteFile($application . '/components/' . $component . '/src/Controller/' . $view . 'Controller.php');
        $this->deleteFile($application . '/components/' . $component . '/src/Model/' . $view . 'Model.php');
        $this->deleteFile($application . '/components/' . $component . '/src/table/' . $view . 'Table.php');
        $this->deleteFolder($application . '/components/' . $component . '/src/View/' . $view);
        $this->deleteFolder($application . '/components/' . $component . '/tmpl/' . strtolower($view));
    }

    private function getDatabase(): DatabaseInterface {
        if ($this->db === null) {
            $this->db = Factory::getContainer()->get(DatabaseInterface::class);
        }

        return $this->db;
    }

    private function executeCommand($sql) {
        $db = $this->getDatabase();
        $db->setQuery($sql);
        return $db->execute();
    }

    private function fail(string $message): bool {
        $this->message($message, 'error');
        Log::add($message, Log::ERROR, 'jerror');

        return false;
    }

    public function getDbVersion($component = 'com_ra_tools') {
        if (array_key_exists($component, $this->dbVersionCache)) {
            return $this->dbVersionCache[$component];
        }

        $sql = 'SELECT s.version_id ';
        $sql .= 'FROM #__extensions as e ';
        $sql .= 'LEFT JOIN #__schemas AS s ON s.extension_id = e.extension_id ';
        $sql .= 'WHERE e.element="' . $component . '"';
        $this->dbVersionCache[$component] = $this->getValue($sql);

        return $this->dbVersionCache[$component];
    }

    public function getVersion($component = 'com_ra_tools') {
        if (array_key_exists($component, $this->versionCache)) {
            return $this->versionCache[$component];
        }

        // This returns the version as displayed by System / Manage extensions
        $sql = 'SELECT manifest_cache ';
        $sql .= 'FROM  #__extensions  ';
        $sql .= 'WHERE element="' . $component . '"';
        $data = json_decode((string) $this->getValue($sql));

        $this->versionCache[$component] = (is_object($data) && isset($data->version)) ? (string) $data->version : null;

        return $this->versionCache[$component];
    }

    private function getValue($sql) {
        $db = $this->getDatabase();
        $db->setQuery($sql);
        return $db->loadResult();
    }

    public function install($parent): bool {
        $this->message('Installing RA Tools (pkg_ra_tools)');

        return true;
    }

    public function red($text) {
        $this->message($text, 'warning');
    }

    public function uninstall($parent): bool {
        $componentVersion = $this->getVersion('com_ra_tools') ?? 'not recorded';
        $componentDbVersion = $this->getDbVersion('com_ra_tools') ?: 'not recorded';
        $this->message('Uninstalling RA Tools package (pkg_ra_tools); '
            . 'component com_ra_tools version ' . $componentVersion
            . ', component database version ' . $componentDbVersion);

        return true;
    }

    public function update($parent): bool {
        $this->message('Updating RA Tools (pkg_ra_tools)');

        return true;
    }

    public function postflight($type, $parent) {
        $this->message('Postflight RA Tools (pkg_ra_tools)');

        if ($type == 'uninstall') {
            return true;
        }

        // Runs on every install/update and is safe to re-run. The 5.1.2 update SQL also
        // adds contact_id, but this covers sites where that file did not run.
        if ($this->checkColumn('ra_mail_shots', 'contact_id', 'A', 'INT NULL AFTER attachment; ')) {
            // Older versions stored the contact's email in reply_to; carry it across so
            // existing mailshots keep the contact that was chosen
            if ($this->checkColumnExists('ra_mail_shots', 'reply_to')) {
                try {
                    $this->executeCommand('UPDATE `#__ra_mail_shots` AS s '
                            . 'INNER JOIN `#__users` AS u ON u.email = s.reply_to '
                            . "SET s.contact_id = u.id WHERE s.contact_id IS NULL AND s.reply_to <> ''");
                } catch (\RuntimeException $exception) {
                    $this->message('Unable to copy reply_to into contact_id: ' . $exception->getMessage(), 'warning');
                }
            }
        } else {
            $this->message('Mailshot Contact override will not be saved until ra_mail_shots.contact_id is added', 'warning');
        }

        $componentVersion = $this->getVersion('com_ra_tools') ?? 'not recorded';
        $this->message('com_ra_tools is now at ' . $componentVersion);
        if ($this->reconfigure_message == true) {
            $this->red('Please review and update the configuration settings for com_ra_tools.');
        }

        $this->activateAdminTemplate();

        $this->message('<b>Useful links</b><br>'
                . $this->buildButton('index.php?option=com_ra_tools&view=dashboard', 'Dashboard', false, 'granite') . '<br>'
                . $this->buildButton('index.php?option=com_config&view=component&component=com_ra_tools', 'Configure'));
        return true;
    }

    public function preflight($type, $parent): bool {
        $this->message('Preflight RA Tools (type=' . $type . ')');
        if ($type == 'uninstall') {
            return $this->restoreAtumAdminTemplate();
        }
        if (!empty($this->minimumPHPVersion) && version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
            return $this->fail(Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion));
        }
        if (!empty($this->minimumJoomlaVersion) && version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
            return $this->fail(Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion));
        }
        if ($type == 'install') {
            return true;
        }

        $this->current_version = $this->getVersion('com_ra_tools');
        $currentDbVersion = $this->getDbVersion('com_ra_tools') ?: 'not recorded';

        if ($this->current_version === null) {
            return $this->fail('Unable to determine the currently installed com_ra_tools version.');
        }

        $this->message('com_ra_tools already present, version ' . $this->current_version
            . ', database version ' . $currentDbVersion);

//        $this->version_required = '5.0.16';
//        if (version_compare($this->current_version, $this->version_required, 'ge')) {
//            $this->message('Current version is ' . $this->current_version . ', no additional processing required');
//            return true;
//        } else {
//            $this->message('Version is currently ' . $this->current_version . ', '
//                    . 'Requires version >= ' . $this->version_required, 'warning');
//        }

        return true;
    }


    /**
     * Make the RA Tools admin template the active one.
     *
     * Joomla's TemplateAdapter only inserts a style row on a fresh install, and always
     * with home='0', so the template is never active straight after being installed.
     * This mirrors StyleModel::setHome(). The model itself is no use from an installer:
     * it runs a core.edit.state authorisation check that throws on a CLI install.
     */
    private function activateAdminTemplate(string $template = 'ra_tools_admin'): void {
        try {
            $db = $this->getDatabase();

            // An administrator can duplicate styles in com_templates, so take the lowest id.
            $query = $db->getQuery(true)
                    ->select($db->quoteName(['id', 'home', 'title']))
                    ->from($db->quoteName('#__template_styles'))
                    ->where($db->quoteName('client_id') . ' = 1')
                    ->where($db->quoteName('template') . ' = :template')
                    ->bind(':template', $template, ParameterType::STRING)
                    ->order($db->quoteName('id') . ' ASC');
            $db->setQuery($query, 0, 1);
            $style = $db->loadObject();

            if ($style === null) {
                $this->message('The RA Tools admin template is not installed, so the admin template has been left unchanged.', 'warning');
                return;
            }

            // Joomla stores the untranslated language key as the title, because the
            // installer runs before lib_joomla.ini is loaded.
            if ($style->title !== 'RA Tools Admin') {
                $title = 'RA Tools Admin';
                $query = $db->getQuery(true)
                        ->update($db->quoteName('#__template_styles'))
                        ->set($db->quoteName('title') . ' = :title')
                        ->where($db->quoteName('id') . ' = :id')
                        ->bind(':title', $title, ParameterType::STRING)
                        ->bind(':id', $style->id, ParameterType::INTEGER);
                $db->setQuery($query)->execute();
            }

            if ($style->home === '1') {
                $this->message('Admin template is already RA Tools Admin.');
                return;
            }

            $query = $db->getQuery(true)
                    ->select($db->quoteName('template'))
                    ->from($db->quoteName('#__template_styles'))
                    ->where($db->quoteName('client_id') . ' = 1')
                    ->where($db->quoteName('home') . ' = ' . $db->quote('1'));
            $db->setQuery($query, 0, 1);
            $previous = $db->loadResult();

            // Scope by client_id, or this clears the site template's home as well.
            $query = $db->getQuery(true)
                    ->update($db->quoteName('#__template_styles'))
                    ->set($db->quoteName('home') . ' = ' . $db->quote('0'))
                    ->where($db->quoteName('client_id') . ' = 1')
                    ->where($db->quoteName('home') . ' = ' . $db->quote('1'));
            $db->setQuery($query)->execute();

            $query = $db->getQuery(true)
                    ->update($db->quoteName('#__template_styles'))
                    ->set($db->quoteName('home') . ' = ' . $db->quote('1'))
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':id', $style->id, ParameterType::INTEGER);
            $db->setQuery($query)->execute();

            $this->message('Admin template switched from ' . ($previous ?: 'none') . ' to RA Tools Admin.');
            $this->message('Anyone who picked their own administrator template in their user profile will keep seeing that one.', 'warning');
        } catch (\Exception $exception) {
            // Cosmetic. Never let a template switch abort an otherwise good install.
            $this->message('Unable to activate the RA Tools admin template: ' . $exception->getMessage(), 'warning');
        }
    }

    /**
     * Hand the admin template back to Atum before the package children are removed.
     *
     * Joomla refuses to uninstall a template whose style is home='1', in
     * TemplateAdapter::setupUninstall(). This has to run in preflight rather than
     * uninstall(): an exception thrown from uninstall() is swallowed by the installer,
     * which then deletes the package manifest and its #__extensions row regardless,
     * leaving an active template and no package left to remove it.
     */
    private function restoreAtumAdminTemplate(): bool {
        try {
            $db = $this->getDatabase();
            $template = 'ra_tools_admin';

            $query = $db->getQuery(true)
                    ->select('COUNT(*)')
                    ->from($db->quoteName('#__template_styles'))
                    ->where($db->quoteName('client_id') . ' = 1')
                    ->where($db->quoteName('home') . ' = ' . $db->quote('1'))
                    ->where($db->quoteName('template') . ' = :template')
                    ->bind(':template', $template, ParameterType::STRING);
            $db->setQuery($query);

            if ((int) $db->loadResult() === 0) {
                return true;   // Not the active template, nothing to undo.
            }

            $atum = 'atum';
            $query = $db->getQuery(true)
                    ->select($db->quoteName('id'))
                    ->from($db->quoteName('#__template_styles'))
                    ->where($db->quoteName('client_id') . ' = 1')
                    ->where($db->quoteName('template') . ' = :atum')
                    ->bind(':atum', $atum, ParameterType::STRING)
                    ->order($db->quoteName('id') . ' ASC');
            $db->setQuery($query, 0, 1);
            $atumId = (int) $db->loadResult();

            if ($atumId === 0) {
                return $this->fail('The RA Tools admin template is active and there is no Atum style to switch back to. '
                        . 'Choose another template under System > Administrator Templates: Styles, then uninstall again.');
            }

            $query = $db->getQuery(true)
                    ->update($db->quoteName('#__template_styles'))
                    ->set($db->quoteName('home') . ' = ' . $db->quote('0'))
                    ->where($db->quoteName('client_id') . ' = 1')
                    ->where($db->quoteName('home') . ' = ' . $db->quote('1'));
            $db->setQuery($query)->execute();

            $query = $db->getQuery(true)
                    ->update($db->quoteName('#__template_styles'))
                    ->set($db->quoteName('home') . ' = ' . $db->quote('1'))
                    ->where($db->quoteName('id') . ' = :id')
                    ->bind(':id', $atumId, ParameterType::INTEGER);
            $db->setQuery($query)->execute();

            $this->message('Admin template handed back to Atum so the RA Tools template can be removed.');
            return true;
        } catch (\Exception $exception) {
            return $this->fail('Unable to restore the Atum admin template before uninstalling: ' . $exception->getMessage());
        }
    }

}
