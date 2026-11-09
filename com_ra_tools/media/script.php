<?php

/**
 * Installation script
 * 01/08/23 CB Create from MailMan script
 * 07/08/23 copy checkColumn and checkTable from version 3
 * 21/08/23 CB copy walksprinted.php to Ramblers/jsonwalks/std
 * 21/08/23 CB correct location of walksprinted
 * 09/10/23 CB update clusters
 * 16/10/23 CB Clusters
 * 13/11/23 CB don't use ToolsHelper->executeCommand
 * 20/11/23 CB start deletion of obsolete praogramme_area view
 * 20/11/23 CB actually tried to delete obsolete View
 * 04/12/23 CB update database access
 * 04/01/23 CB copy framework.inc.php
 * 04/09/24 CB remove messages about Programme_area
 * 10/09/24 CB new getVersions, new deleteFiles (from version 5.0.3)
 * 19/10/24 CB checkGroups
 * 21/12/24 CB delete ra_feedback_summary
 * 23/02/25 CB use SchemaHelper - WILL ONLY BE AVAILABLE in  POSTFLIGHT
 * 17/03/25 CB delete code for copying scripts to folder cli
 *             add field sub_system to ra_logfile
 * 13/04/25 CB link to Dashboard
 * 29/04/25 CB getDbVersion
 * 09/11/26 CB Updates for Joomla 6 compatibility
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
// use Joomla\Filesystem\File;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\SchemaHelper;

class Com_Ra_toolsInstallerScript {

    private $component;
    private $original_version;
    private $minimumJoomlaVersion = '4.0';
    private $minimumPHPVersion = JOOMLA_MINIMUM_PHP;

    function buildLink($url, $text, $newWindow = 0, $class = "") {
        // N.B. cannot be used from batch programs, because Uri::root() is not available
// copied from ToolsHelper 10/04/25
        $q = chr(34);
        $out = PHP_EOL . "<a ";
//        echo "BuildLink: url = $url, substr=" . substr($url, 0, 4) . ", text=$text, root=" . Uri::root() . "<br>";
        if (!$class == "") {
            $out .= "class=" . $q . $class . $q;
        }
        $out .= " href=" . $q;
        if (substr($url, 0, 4) == "http") {

        } else {
            $out .= Uri::root();    // this seems to be derived from configuration.php/ live_site in the website root
        }
        $out .= $url . $q;
        if ($newWindow) {
            $out .= " target =" . $q . "_blank" . $q;
        } else {
            $out .= " target =" . $q . "_self" . $q;
        }
        $out .= ">";
        if ($text == "")
            $out .= $url;
        else
            $out .= $text;
        $out .= "</a>" . PHP_EOL;
//        echo "BuildLink: output= $out";
        return $out;
    }

    function checkColumn($table, $column, $mode, $details = '') {
//  $mode = A: add the field, using data suppied in $details
//  $mode = U: update the field (keeping name the same), using $details
//  $mode = D: delete the field

        $count = $this->checkColumnExists($table, $column);
        $table_name = $this->dbPrefix . $table;
        //       echo 'mode=' . $mode . ': Seeking ' . $table_name . '/' . $column . ', count=' . $count . "<br>";
        if (($mode == 'A') AND ($count == 1)
                OR ($mode == 'D') AND ($count == 0)) {
            return true;
        }
        if (($mode == 'U') AND ($count == 0)) {
            echo 'Field ' . $column . ' not found in ' . $table_name . '<br>';
            return false;
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
        echo "$sql<br>";
        $response = $this->executeCommand($sql);
        if ($response) {
            echo 'Success';
        } else {
            echo 'Failure';
        }
        echo ' for ' . $table_name . '<br>';
        return $count;
    }

    private function checkColumnExists($table, $column) {
        $config = \Joomla\CMS\Factory::getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $this->dbPrefix . $table . "' ";
        $sql .= "AND COLUMN_NAME='" . $column . "'";
//    echo "$sql<br>";

        return $this->getValue($sql);
    }

    function checkTable($table, $details, $details2 = '') {

        $config = \Joomla\CMS\Factory::getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $table_name . "' ";
//        echo "$sql<br>";

        $count = $this->getValue($sql);
        echo 'Seeking ' . $table_name . ', count = ' . $count . "<br>";
        if ($count > 0) {
            return $count;
        }
        $sql = 'CREATE TABLE ' . $table_name . ' ' . $details;
        echo "$sql<br>";
        $response = $this->executeCommand($sql);
        if ($response) {
            echo 'Table created OK<br>';
        } else {
            echo 'Failure<br>';
            return false;
        }
        if ($details2 != '') {
            $sql = 'ALTER TABLE ' . $table_name . ' ' . $details2;
            $response = $this->executeCommand($sql);
            if ($response) {
                echo 'Table altered OK<br>';
            } else {
                echo 'Failure<br>';
                return false;
            }
        }
    }

    function check403() {
        $details = '(`code` VARCHAR(3) NOT NULL, '
                . '`name` VARCHAR(20) NOT NULL, '
                . '`contact_id` INT NULL, '
                . '`areas` VARCHAR(256) NULL, '
                . ' PRIMARY KEY(`code`)'
                . ') ENGINE = InnoDB DEFAULT CHARSET = utf8mb4 DEFAULT COLLATE = utf8mb4_unicode_ci;
                ';
        $details2 = 'INSERT INTO `#__ra_clusters`(code, name,areas) values ';
        $details2 .= "('ME','Midlands and East',''),";
        $details2 .= "('N','North and North West',''),";
        $details2 .= "('SE','South East',''),";
        $details2 .= "('SSW','South and South West','')";
        $this->checkTable('ra_clusters', $details, '');
        $this->checkColumn('ra_areas', 'cluster', 'A', "ADD cluster VARCHAR(3) NOT NULL DEFAULT '' AFTER co_url; ");
        $this->updateClusters();
    }

    private function deleteFile($target) {
        // Not needed, could use a built in function (if details were known!)
        if (file_exists(JPATH_SITE . $target)) {
            File::delete(JPATH_SITE . $target);
            echo "$target deleted<br>";
        }
    }

    private function deleteFolder($target) {
        // 08/10/24 CB does not seem to work!
        if (file_exists(JPATH_SITE . $target)) {
            Folder::delete(JPATH_SITE . $target);
            echo JPATH_SITE . "$target deleted<br>";
        } else {
            echo "$target not found<br>";
        }
    }

    private function executeCommand($sql) {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        return $db->execute();
    }

    public function getDbVersion($component = 'com_ra_tools') {
        $sql = 'SELECT s.version_id ';
        $sql .= 'FROM #__extensions as e ';
        $sql .= 'LEFT JOIN #__schemas AS s ON s.extension_id = e.extension_id ';
        $sql .= 'WHERE e.element="' . $component . '"';
        return $this->getValue($sql);
    }

    private function getValue($sql) {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        return $db->loadResult();
    }

    public function getVersion($component = 'com_ra_tools') {
        // This retuns the version as display by System / Manage extensions
        $sql = 'SELECT manifest_cache ';
        $sql .= 'FROM  #__extensions  ';
        $sql .= 'WHERE element="' . $component . '"';
        $data = json_decode($this->getValue($sql));
        return $data->version;
    }

    public function install($parent): bool {
        echo '<p>Installing RA Tools (com_ra_tools) ' . '</p>';
        if (ComponentHelper::isEnabled('com_ra_tools', true)) {
            $this->original_version = $this->getVersion();
            echo '<p>com_ra_tools found, version ' . $this->original_version;
            echo ', database version ' . $this->getDbVersion() . '</p>';
        }
        return true;
    }

    public function uninstall($parent): bool {
        echo '<p>Uninstalling RA Tools (com_ra_tools)</p>';

        return true;
    }

    public function update($parent): bool {
        echo '<p>Updating RA Tools (com_ra_tools)</p>';

// You can have the backend jump directly to the newly updated component configuration page
// $parent->getParent()->setRedirectURL('index.php?option=com_ra_tools');
        return true;
    }

    public function preflight($type, $parent): bool {
        echo '<p>Preflight RA Tools (type = ' . $type . ')</p>';
        if (ComponentHelper::isEnabled('com_ra_tools', true)) {
            $this->original_version = $this->getVersion();
            echo '<p>com_ra_tools found, version ' . $this->original_version;
            echo ', database version ' . $this->getDbVersion() . '</p>';
        }
        if ($type !== 'uninstall') {
            if (!empty($this->minimumPHPVersion) && version_compare(PHP_VERSION, $this->minimumPHPVersion, '<')) {
                Log::add(
                        Text::sprintf('JLIB_INSTALLER_MINIMUM_PHP', $this->minimumPHPVersion),
                        Log::WARNING,
                        'jerror'
                );
                return false;
            }

            if (!empty($this->minimumJoomlaVersion) && version_compare(JVERSION, $this->minimumJoomlaVersion, '<')) {
                Log::add(
                        Text::sprintf('JLIB_INSTALLER_MINIMUM_JOOMLA', $this->minimumJoomlaVersion),
                        Log::WARNING,
                        'jerror'
                );
                return false;
            }
        }
        return true;
    }

    public function postflight($type, $parent) {
        '<p>Postflight RA Tools (com_ra_tools)</p>';

        if ($type == 'uninstall') {
            return 1;
        }
        $this->current_version = $this->getVersion();
        echo '<p>com_ra_tools is now at version ' . $this->current_version;
        echo ', database version ' . $this->getDbVersion() . '</p>';

        $version_required = '3.2.0';

        if (version_compare($this->original_version, $version_required, 'ge')) {
            echo 'Previous version was ' . $this->original_version . ', no additional processing required</p>';
            return true;
        } else {
            echo '<p>Version was originally ' . $this->original_version . ', ';
            echo 'Requires version >= ' . $version_required . '</p>';
        }

        $this->deleteFile('/components/com_ra_tools/src/View/Programme_area');
        $this->deleteFile('/components/com_ra_tools/tmpl/programme_area');
        $this->deleteFile('/components/com_ra_tools/tmpl/walk/default.xml');
        $this->deleteFile('/components/com_ra_tools/tmpl/walkform/default.xml');
        $this->deleteFile('/components/com_ra_tools/tmpl/walks/default.xml');

//        if (1) {
        $this->checkColumn('ra_logfile', 'sub_system', 'A', "VARCHAR(4) NULL DEFAULT '' AFTER log_date; ");
//        $this->checkColumn('ra_mail_shots', 'author_id', 'D');
//            $this->check403();
//        }
        echo '<br>';

        echo $this->buildLink('administrator/index.php?option=com_ra_tools&view=dashboard', 'RA Dashboard');
        return true;
    }

    private function updateClusters() {
        $sql = 'UPDATE `#__ra_areas` SET cluster = "SC" WHERE nation_id=2';
        $response = $this->executeCommand($sql);
        if ($response) {
            echo 'Updated Scotland';
        } else {
            echo 'Scotland Failure';
        }

        $sql = 'UPDATE `#__ra_areas` SET cluster = "WA" WHERE nation_id=3';
        $response = $this->executeCommand($sql);
        if ($response) {
            echo 'Updated Wales';
        } else {
            echo 'Wales Failure';
        }

        $sql = 'UPDATE `#__ra_areas` SET cluster = "ME" WHERE code in ("BF","LI","NP","NR","NE","SS","NS","WO","CH","DE")';
        $response = $this->executeCommand($sql);
        if ($response) {
            echo 'Updated ME';
        } else {
            echo 'ME Failure';
        }

        $sql = 'UPDATE `#__ra_areas` SET cluster = "SE" WHERE code in ("BU","CB","ES","WX","KT","IL","IW","NO","OX","SK","SR","SX")';
        $response = $this->executeCommand($sql);
        if ($response) {
            echo 'Updated SE';
        } else {
            echo 'SE Failure';
        }

        $sql = 'UPDATE `#__ra_areas` SET cluster = "SSW" WHERE code in ("AV","BK","CL","DN","DT","GR","IW","OX","SO","WE")';
        $response = $this->executeCommand($sql);
        if ($response) {
            echo 'Updated SSW';
        } else {
            echo 'SSW Failure';
        }
    }

}
