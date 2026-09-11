<?php

/*
 * Installation script
 * 09/07/25 CB recreated from event script
 * 31/07/25 CB deleteView
 * 15/09/25 CB version 3.4.0 - add title to ra_api_sites
 * 17/10/25 CB message if unable to delete file/folder
 * 03/11/25 CB use built in function to delete files and folders (3.4.5)
 * 21/01/26 CB 3.5.1 correct ra_emails addressee_email
 * 17/06/26 CB api_sites: sub_system -> varchar(12)
 * 19/07/26 CB api_sites: sub_system -> varchar(20) (12 truncated existing data on upgrade)
 * 30/08/26 CB consolidate installed component version lookup
 */

\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use Joomla\CMS\Filesystem\Folder;
use Joomla\CMS\Installer\InstallerAdapter;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Log\Log;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Joomla\Database\ParameterType;

class Com_Ra_toolsInstallerScript {

    private const VERSION_REQUIRING_NO_PROCESSING = '3.7.4';

    private $component;
    private $current_version;
    protected $deleteFiles = array();
    protected $deleteFolders = array();
    private $minimumJoomlaVersion = '4.0';
    private $minimumPHPVersion = JOOMLA_MINIMUM_PHP;
    private $reconfigure_message = false;

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
//  $mode = A: add the field
//  $mode = U: update the field (keeping name the same)
//  $mode = D: delete the field

        $count = $this->checkColumnExists($table, $column);
        $table_name = $this->dbPrefix . $table;
        echo 'mode=' . $mode . ': Seeking ' . $table_name . '/' . $column . ', count=' . $count . "<br>";
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
        $config = JFactory::getConfig();
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

        $config = JFactory::getConfig();
        $database = $config->get('db');
        $this->dbPrefix = $config->get('dbprefix');

        $table_name = $this->dbPrefix . $table;
        $sql = 'SELECT COUNT(COLUMN_NAME) ';
        $sql .= "FROM information_schema.COLUMNS ";
        $sql .= "WHERE TABLE_SCHEMA='" . $database . "' AND TABLE_NAME ='" . $table_name . "' ";
//        echo "$sql<br>";

        $count = $this->getValue($sql);
        echo 'Seeking ' . $table_name . ', count=' . $count . "<br>";
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

    private function createTables() {
// table ra_ event_states

        $details = '(`id` int NOT NULL ,
            `seq` INT NOT NULL,
            `title` varchar(20) NOT NULL,
            PRIMARY KEY (`id`)
            ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci; ';
        $this->checkTable('ra_event_states', $details);

        $sql = 'SELECT COUNT(id) FROM #__ra_event_states';
        $count = $this->getValue($sql);
        if ($count == 0) {
            $sql = "INSERT INTO #__ra_event_states (seq,id,title) VALUES(1,0,'Provisional')";
            $this->executeCommand($sql);
            $sql = "INSERT INTO #__ra_event_states (seq,id,title) VALUES(2,1,'Confirmed')";
            $this->executeCommand($sql);
            $sql = "INSERT INTO #__ra_event_states (seq,id,title) VALUES(3,-2, 'Cancelled')";
            $this->executeCommand($sql);
        }

// ra_event_types
        $details = '(
            `id` int(11) UNSIGNED  NOT NULL AUTO_INCREMENT,
            `description` varchar(20) NOT NULL,
            `ordering` INT NOT NULL DEFAULT 0,
            `state` TINYINT(1) NOT NULL DEFAULT 1,
            PRIMARY KEY (`id`)
        ) ENGINE=InnoDB COLLATE=utf8mb4_unicode_ci;';
        $this->checkTable('ra_event_types', $details);

        $sql = 'SELECT COUNT(id) FROM #__ra_event_types';
        echo $sql;
        $count = $this->getValue($sql);
        echo 'Number of records ' . $count . '<br>';
    }

    public function deleteView($component = 'com_ra_events', $view = 'Myevents') {
// first character of View must be upper case
        $application[0] = 'administrator/';
        $application[1] = '';
        for ($i = 0; $i < 2; $i++) {
            $this->deleteFiles[] = $application[$i] . 'components/' . $component . '/forms/filter_' . strtolower($view) . 'xml';
            $this->deleteFiles[] = $application[$i] . 'components/' . $component . '/forms/filter_' . strtolower($view) . 'xml';
            $this->deleteFiles[] = $application[$i] . 'components/' . $component . '/src/Controller/' . $view . 'Controller.php';
            $this->deleteFiles[] = $application[$i] . 'components/' . $component . '/src/Model/' . $view . 'Model.php';
            $this->deleteFiles[] = $application[$i] . 'components/' . $component . '/src/table/' . $view . 'Table.php';
            $this->deleteFolders[] = $application[$i] . 'components/' . $component . '/src/View/' . $view;
            $this->deleteFolders[] = $application[$i] . 'components/' . $component . '/tmpl/' . strtolower($view);
        }
    }

    private function executeCommand($sql) {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        return $db->execute();
    }

    private function fail(string $message): bool {
        Factory::getApplication()->enqueueMessage($message, 'error');
        Log::add($message, Log::ERROR, 'jerror');

        return false;
    }

    /**
     * Return the installed manifest version for a component.
     */
    private function getInstalledComponentVersion(string $component = 'com_ra_tools'): ?string {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $extensionType = 'component';

        $query->select($db->quoteName('e.manifest_cache'))
                ->from($db->quoteName('#__extensions', 'e'))
                ->where($db->quoteName('e.element') . ' = :component')
                ->where($db->quoteName('e.type') . ' = :extensionType')
                ->bind(':component', $component, ParameterType::STRING)
                ->bind(':extensionType', $extensionType, ParameterType::STRING);

        $db->setQuery($query);
        $manifestCache = $db->loadResult();

        if ($manifestCache === null) {
            return null;
        }

        try {
            $manifest = json_decode((string) $manifestCache, true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new \RuntimeException(
                    'Installer could not decode version information for ' . $component . '.',
                    0,
                    $exception
            );
        }

        if (!is_array($manifest)) {
            throw new \RuntimeException('Installer found invalid version information for ' . $component . '.');
        }

        $installedVersion = $manifest['version'] ?? null;

        return is_scalar($installedVersion) ? (string) $installedVersion : null;
    }

    private function getValue($sql) {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        return $db->loadResult();
    }

    public function install($parent): bool {
        $this->message('Installing RA Tools (com_ra_tools).');
        if (ComponentHelper::isEnabled('com_ra_tools', true)) {
            try {
                $installedVersion = $this->getInstalledComponentVersion();
            } catch (\RuntimeException $exception) {
                return $this->fail($exception->getMessage());
            }

            $this->message('com_ra_tools found, version ' . ($installedVersion ?? 'not recorded') . '.');
        }
        return true;
    }

    private function message(string $message): void {
        Factory::getApplication()->enqueueMessage($message, 'message');
    }

    public function postflight($type, $parent) {
        $this->message('Postflight RA Tools (com_ra_tools).');

        if ($type == 'uninstall') {
            return true;
        }
        try {
            $installedVersion = $this->getInstalledComponentVersion();
        } catch (\RuntimeException $exception) {
            return $this->fail($exception->getMessage());
        }

        $this->message('com_ra_tools is now at ' . ($installedVersion ?? 'not recorded') . '.');
//        $this->removeFiles();
        if ($this->reconfigure_message == true) {
            $this->message('Version was originally ' . $this->current_version
                    . '; version ' . self::VERSION_REQUIRING_NO_PROCESSING
                    . ' or later is required to skip upgrade processing.');
            $this->red('Please review and update the configuration settings for com_ra_tools.');
        }
        $this->message('<b>Useful links</b><br>'
                . $this->buildButton('index.php?option=com_ra_tools&view=dashboard', 'RA Dashboard') . '<br>'
                . $this->buildButton('index.php?option=com_config&view=component&component=com_ra_tools', 'Configure'));
        return true;
    }

    public function preflight($type, $parent): bool {
        $this->message('Preflight RA Tools (type=' . $type . ').');
        if ($type == 'uninstall') {
            return true;
        }

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

        if (ComponentHelper::isEnabled('com_ra_tools', true)) {
            try {
                $this->current_version = $this->getInstalledComponentVersion();
            } catch (\RuntimeException $exception) {
                return $this->fail($exception->getMessage());
            }

            if ($this->current_version === null) {
                return $this->fail('Installer could not find readable version information for com_ra_tools.');
            }

            $this->message('com_ra_tools already present, version=' . $this->current_version . '.');
        }
        if ($type == 'install') {
            return true;
        }
        if ($this->current_version === null) {
            return $this->fail('Installer could not find the existing com_ra_tools installation.');
        }

        $this->reconfigure_message = false;
        $this->deleteFiles[] = 'components/com_ra_tools/tmpl/emailform/default.xml';
        if (version_compare($this->current_version, self::VERSION_REQUIRING_NO_PROCESSING, 'ge')) {
            $this->message('Current version is ' . $this->current_version
                    . '; no additional processing required.');
        } else {
            $this->message('Version was originally ' . $this->current_version
                    . '; version ' . self::VERSION_REQUIRING_NO_PROCESSING
                    . ' or later is required to skip upgrade processing.');
            if (version_compare($this->current_version, '3.5.2', 'le')) {
                $this->checkColumn('ra_emails', 'addressee_email', 'U', 'TEXT; ');
                $this->checkColumn('ra_clusters', 'website', 'A', 'VARCHAR(100) NOT NULL AFTER area_list; ');
            }
            if (version_compare($this->current_version, '3.4.2', 'le')) {
                $this->checkColumn('ra_emails', 'ref', 'A', 'INT DEFAULT "0" AFTER `record_type`;');
                $this->checkColumn('ra_emails', 'sender_name', 'A', 'VARCHAR(100) NOT NULL DEFAULT "0" AFTER date_sent; ');
                $this->checkColumn('ra_emails', 'sender_email', 'A', 'VARCHAR(100) NOT NULL DEFAULT "0" AFTER date_sent; ');
                $this->checkColumn('ra_emails', 'addressee_name', 'A', 'VARCHAR(100) NOT NULL DEFAULT "0" AFTER sender_email; ');
                $this->checkColumn('ra_emails', 'addressee_email', 'A', 'VARCHAR(100) NOT NULL DEFAULT "0" AFTER addressee_name; ');
            }
            if (version_compare($this->current_version, '3.4.1', 'le')) {
                $this->updateAreas();
            }
            if (version_compare($this->current_version, '3.4.0', 'le')) {
                $this->checkColumn('ra_api_sites', 'title', 'A', 'VARCHAR(100) NULL AFTER sub_system; ');
            }
            if (version_compare($this->current_version, '3.3.13', 'le')) {
                $this->checkColumn('ra_groups', 'bespoke', 'A', 'VARCHAR(1) NOT NULL DEFAULT "0" AFTER name; ');
                $this->checkColumn('ra_groups', 'latitude', 'U', 'DECIMAL(14,12) NOT NULL;');
                $this->checkColumn('ra_groups', 'longitude', 'U', 'DECIMAL(14,12) NOT NULL;');
            }
            if (version_compare($this->current_version, '3.3.11', 'le')) {
                $this->deleteView('com_ra_events', 'Apisites');
                $this->deleteView('com_ra_events', 'Apisite');
            }
            if (version_compare($this->current_version, '3.3.10', 'le')) {
                $this->checkColumn('ra_logfile', 'sub_system', 'U', 'VARCHAR(12) NOT NULL;');
                $this->checkColumn('ra_logfile', 'sub_syatem', 'D', '');
            }
            if (version_compare($this->current_version, '3.3.7', 'le')) {
                $this->updateSites();
                $this->reconfigure_message = true;
            }


            if (version_compare($this->current_version, '3.3.1', 'le')) {
                $this->reconfigure_message = true;
                $this->checkColumn('ra_api_sites', 'sub_system', 'A', 'VARCHAR(10) NOT NULL AFTER id; ');
                $this->checkColumn('ra_emails', 'ref', 'A', 'VARCHAR(2) NOT NULL AFTER date_sent; ');
                $this->checkColumn('ra_emails', 'sender_name', 'A', 'VARCHAR(100) NOT NULL AFTER date_sent; ');
                $this->checkColumn('ra_emails', 'sender_email', 'A', 'VARCHAR(100) NOT NULL AFTER sender_name; ');
                $this->checkColumn('ra_emails', 'addressee_name', 'A', 'VARCHAR(100) NOT NULL AFTER sender_email; ');
                $this->checkColumn('ra_emails', 'addressee_email', 'A', 'TEXT AFTER addressee_name; ');
                //       $this->checkColumn('ra_emails', 'addressee_email', 'U', 'TEXT AFTER addressee_name; ');
                if (version_compare($this->current_version, '2.1.2', 'ge')) {
                    $details = '`id` int(11) UNSIGNED NOT NULL AUTO_INCREMENT,
                        `sub_system` VARCHAR(12) NOT NULL ,
                        `url` VARCHAR(100) NOT NULL ,
                        `token` VARCHAR(255) NOT NULL ,
                        `colour` VARCHAR(22) NOT NULL ,
                        `state` TINYINT(1) NULL  DEFAULT 1,
                        `ordering` INT NULL DEFAULT 0,
                        `checked_out` INT(11) UNSIGNED,
                        `checked_out_time` DATETIME NULL DEFAULT NULL ,
                        `created` DATETIME NULL DEFAULT NULL ,
                        `created_by` INT(11)  NULL DEFAULT 0,
                        `modified` DATETIME NULL  DEFAULT NULL ,
                        `modified_by` INT(11) NULL  DEFAULT 0,
                        PRIMARY KEY (`id`)
                        ) DEFAULT COLLATE=utf8mb4_unicode_ci; ';
                    $this->checkTable('ra_api_sites', $details);
                }
            }
            if (version_compare($this->current_version, '3.7.4', 'le')) {
                $this->checkColumn('ra_api_sites', 'sub_system', 'U', 'VARCHAR(20) NOT NULL;');
            }
        }

        return true;
    }

    public function red($text) {
        Factory::getApplication()->enqueueMessage($text, 'warning');
    }

    public function uninstall($parent): bool {
        try {
            $installedVersion = $this->getInstalledComponentVersion();
        } catch (\RuntimeException $exception) {
            Log::add($exception->getMessage(), Log::WARNING, 'jerror');
            $installedVersion = null;
        }

        echo '<p>Uninstalling RA Tools (com_ra_tools) version=' . ($installedVersion ?? 'not recorded') . '<br>';
        return true;
    }

    public function update($parent): bool {
        echo '<p>Updating RA Tools (com_ra_tools)</p>';
        return true;
    }

    public function updateAreas() {
        // new field in ra_events
        $this->checkColumn('ra_areas', 'bespoke', 'A', 'INT DEFAULT "0" AFTER details; ');
        $sql = 'UPDATE #__ra_areas SET bespoke=0';
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Our footpaths and bridleways span a varied countryside taking in farmland, canals, rivers, fine villages and woodland. Visit our website for more information – www.nottsarearamblers.org.uk' , bespoke=1 WHERE code ='NE'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Shropshire is a diverse county, offering walks in landscapes ranging from the rugged hills of the south to the special landscape of the Meres and Mosses in the north.' , bespoke=1 WHERE code = 'SS'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Supporting the work of the ten groups in the Buckinghamshire, Milton Keynes and West Middlesey Area.' , bespoke=1 WHERE code = 'BU'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'The Area consists of 5 Groups, each aiming to promote walking, arrange locally led walks and protect our rights of way. We also organise campaigning events, rallies and promotional events.' , bespoke=1 WHERE code = 'NP'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'The Herefordshire area helps to look after the paths and green spaces throughout the county. We are a leading voice on walking matters in the county.' , bespoke=1 WHERE code = 'HW'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'The Highlands is one of Europe\'s most unspoilt scenic regions, a rugged landscape of imposing mountains, sheltered fertile glens and scattered offshore islands offering unsurpassed walking for all.' , bespoke=1 WHERE code = 'SC'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'The Wiltshire Ramblers currently total some 1,550 members divided into 6 groups, one of which is our young persons group for 20 to 40 year olds. Our groups work towards the charitable aims of Ramblers in different ways, but one thing they all do is ...' , bespoke=1 WHERE code = 'WE'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'There are eleven Groups in the Lothian and Borders Ramblers Area, each arranges a walks programme suitable for people with different levels of eyperience and fitness.  ' , bespoke=1 WHERE code = 'LB'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'This Area covers most of the former County of Avon.' , bespoke=1 WHERE code = 'AV'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'This Area organises a regular programme of walks for families.' , bespoke=1 WHERE code = 'SO'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Unlike many other Ramblers Areas, Manchester and High Peak itself organises a full led walks programme covering Greater Manchester and beyond, with all walks making use of public transport connections.' , bespoke=1 WHERE code = 'MR'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Visit the Surrey Area website for details of our 17 walking groups covering the county of Surrey and the London boroughs of Croydon, Kingston, Merton, Richmond and Sutton.' , bespoke=1 WHERE code = 'SR'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Walking in Devon you will enjoy both Dartmoor and Eymoor National Parks; (over 630 miles long), The Dartmoor Way, The Two Moors Way, The Tarka Trail and much more' , bespoke=1 WHERE code = 'DN'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'We have 9 Berkshire Area groups: 7 traditional groups covering the county, a 20s & 30s group and a flexi group – something for everybody!' , bespoke=1 WHERE code = 'BK'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'We organise walks to suit all abilities, using car, coach and public transport. <b>When dogs are allowed on our walks they are usually on a lead. Contact the leader if this concerns you.</b>' , bespoke=1 WHERE code = 'MC'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'We support the work of the groups in our Area, including seven geographic and two age-related groups. We work to protect and enhance the Rights of Way in Hertfordshire, Barnet, Enfield and Haringey.' , bespoke=1 WHERE code = 'HF'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'We work with our Groups protecting/enhancing footpaths & countryside; and creating opportunities to enjoy the outdoors through led walks. ' , bespoke=1 WHERE code = 'SW'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Welcome to our beautiful county in The Heart Of England!' , bespoke=1 WHERE code = 'WK'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Welcome to our Cornwall Ramblers page!  We cover a large area with diverse walking, from coastal, to countryside, eyploring industrial mining areas and discovering parish paths and some hidden gems...' , bespoke=1 WHERE code = 'CL'";
        $this->executeCommand($sql);
        $sql = "UPDATE #__ra_areas SET details = 'Welcome to the Inner London Area Ramblers. Our area has 10 walking groups, three London-wide, and seven based in two or three boroughs, stretching from Heathrow to Thamesmead.' , bespoke=1 WHERE code = 'IL'";
        $this->executeCommand($sql);

        $sql = 'DELETE FROM #__ra_groups WHERE details="Ramblers Web Editor Group"';
        $this->executeCommand($sql);
    }

    public function updateSites() {
        $sql = 'SELECT id FROM #__ra_api_sites ';
        $sql .= 'WHERE url="https://staffordshireramblers.org"';
        $id = $this->getValue($sql);
        if (is_null($id)) {
            $sql = "INSERT INTO `#__ra_api_sites`
            (`sub_system`, `url`, `token`, `colour`, `state`, `created`, `created_by`) VALUES
('RA Tools', 'https://staffordshireramblers.org', 'c2hhMjU2Ojk3OTo5ODQ4NGMzOTNhMGJmM2U5NWY3NzcyODViNTI2NzFkYzY2MmQwZTZmMzliMmNiMTlkNmUzNzI0MjNkNGUyOThk',
'rgba(133, 132, 191, 0.1)', 1, '2025-07-09 06:03:03', 1 );";
            $this->executeCommand($sql);
            echo 'Created API site for Staffs<br>';
        }
        $sql = 'SELECT id FROM #__ra_api_sites ';
        $sql .= 'WHERE url= "https://ramblers.org.uk"';
        $id = $this->getValue($sql);
        if (is_null($id)) {
            $sql = "INSERT INTO `#__ra_api_sites`
            (`sub_system`, `url`, `token`, `colour`, `state`, `created`, `created_by`) VALUES
('RA Walks', 'https://ramblers.org.uk', '742d93e8f409bf2b5aec6f64cf6f405e',
'rgba(133, 132, 191, 0.1)', 1, '2025-07-09 06:06:34', 1);";
            $this->executeCommand($sql);
            echo 'Created API site for CO<br>';
        }
    }

}
