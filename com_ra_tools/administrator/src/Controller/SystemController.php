<?php

/**
 * @version     3.3.3
 * @package     com_ra_tools
 *
 * @copyright   Copyright (C) 2005 - 2019 Open Source Matters, Inc. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * 23/12/23 CB Created
 * 30/12/23 CB show Groups the user belongs to
 * 04/01/24 CB show Authorship of lists
 * 23/10/24 CB only show authorship of list for com_ra_mailman
 * 08/02/25 CB show access to mailman (not tools twice)
 * 22/04/25 CB list usergroups alphabetically
 * 03/05/25 use ToolsHelper->showAccess
 * 11/07/25 CB checkSchema
 * 22/07/25 CB emails/ref to INT
 * 08/08/25 CB mailman / ra_mail_lists / emails_outstanding
 * 19/04/26 CB resetHitCounters
 */

namespace Ramblers\Component\Ra_tools\Administrator\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\SchemaHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHtml;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;
use Ramblers\Component\Ra_tools\Site\Helpers\PersonHelper;

class SystemController extends FormController {

    protected $access_groups;
    protected $back;
    protected $changes_required;
    protected $components;
    protected $missing_group;
    protected $no;
    protected $app;
    protected $toolsHelper;
    protected $wrong_parent;
    protected $yes;

    public function __construct() {
        parent::__construct();
        $this->toolsHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
        $this->back = 'administrator/index.php?option=com_ra_tools&view=dashboard';

        $this->components[] = 'com_ra_tools';
        $this->components[] = 'com_ra_events';
        $this->components[] = 'com_ra_mailman';
        $this->components[] = 'com_ra_walks';
        $this->yes = '<img src="/media/com_ra_tools/tick.png" alt="OK" width="20" height="20">' . '<br>';
        $this->no = '<img src="/media/com_ra_tools/cross.png" alt="Fail" width="20" height="20">';
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function AccessWizard() {
        ToolBarHelper::title('Access Configuration Wizard');
        if (!$this->toolsHelper->isSuperuser()) {
            echo 'Access only permitted for Superusers';
            return;
        }
        $this->yes = '<img src="/media/com_ra_tools/tick.png" alt="OK" width="20" height="20">' . '<br>';
        $this->no = '<img src="/media/com_ra_tools/cross.png" alt="Fail" width="20" height="20">';
        echo 'This checks the current configuration of your Access setting.';
        $this->changes_required = 0;
        $this->missing_groups = 0;
        $this->wrong_parent = 0;

        $sql = 'SELECT rules FROM #__viewlevels ';
        $sql .= 'WHERE title="Special"';

        $item = $this->toolsHelper->getItem($sql);
        if (is_null($item)) {
            echo $sql . '<br>';
            echo 'View level Special not found.<br>';
            return;
        }
        $this->access_groups = ',' . substr($item->rules, 1, -1) . ',';
//        echo $this->access_groups . '<br>';

        foreach ($this->components as $component) {
            $this->checkComponentAccess($component);
        }
        // Check if error detected
        if ($this->changes_required == 0) {
            echo 'All settings have been set up as recommended.';
            echo $this->toolsHelper->backButton($this->back);
            return;
        }

        if ($this->missing_group > 0) {
            echo '<table><td>';
            echo 'To control update access to each component in the back end, it is recommended that you add ';
            if ($this->missing_groups == 1) {
                echo 'a group for this component.';
            } else {
                echo 'groups for these components.';
            }
            echo ' Then add the Users who need to be able to update the component.';
            echo '</td><td>';
            $target = 'administrator/index.php?option=com_installer&view=manage';
            echo $this->toolsHelper->standardButton('Go', $target);
            echo '</td></table>';
            echo '<br>';
        }

        if ($this->wrong_parent > 0) {
            echo '<table><td>';
            echo 'The ability to log on to the website back end is determined by membership of the access level "Special", It is recommended that you add ';
            if ($this->changes_required == 1) {
                echo 'this component';
            } else {
                echo 'these components';
            }
            echo ' to that access level.';
            echo 'Members in those groups will then be granted access to logon to the Administrative functions in the website back end.';
            echo '</td><td>';
            $target = 'administrator/index.php?option=com_installer&view=manage';
            echo $this->toolsHelper->standardButton('Go', $target);
            echo '</td></table>';
            echo '<br>';
        }
        echo 'It is recommended you make the changes suggested above.';
        echo '<br>';
        echo $this->toolsHelper->backButton($this->back);
    }

        /**
     * Reconcile Joomla users which have no RA profile by creating the
     * standard unpublished ZZ99 placeholder. Super User only.
     */
    public function backfillProfiles(): void {
        if (!$this->toolsHelper->isSuperuser()) {
            throw new \RuntimeException('Only a Super User may backfill RA profiles.', 403);
        }

        $count = (new PersonHelper())->createMissingPlaceholderProfiles();
        $this->app->enqueueMessage('Created ' . $count . ' RA profile placeholder(s).', 'message');
        $this->setRedirect('index.php?option=com_ra_tools&task=reports.checkDatabase&finding=users_without_profiles');
    }

    private function checkComponentAccess($component) {
        echo '<h3>' . $component . '</h3>';

        $sql = 'SELECT extension_id FROM #__extensions ';
        $sql .= 'WHERE element="' . $component . '"';
        $item = $this->toolsHelper->getItem($sql);
        if (is_null($item)) {
            echo 'Component ' . $component . ' is not installed.<br>';
            return;
        }
        echo 'Is component ' . $component . ' installed and published?  ';
        if (ComponentHelper::isEnabled($component, true)) {
            echo $this->yes;
        } else {
            echo $this->no;
            $this->changes_required++;
            $target = 'administrator/index.php?option=com_installer&view=manage';
            echo $this->toolsHelper->buildButton($target, 'Publish it now', false, 'red');
            echo '<br>';
            return;
        }

        echo 'Does Usergroup ' . $component . '  exist?  ';
        $sql = 'SELECT g.id, p.title ';
        $sql .= 'FROM #__usergroups as g ';
        $sql .= 'INNER JOIN #__usergroups as p on p.id = g.parent_id ';
        $sql .= 'WHERE g.title="' . $component . '"';
        $item = $this->toolsHelper->getItem($sql);
        if (is_null($item)) {
            $this->changes_required++;
            $this->missing_group++;
            echo $this->no;
            $target = 'administrator/index.php?option=com_users&view=group&layout=edit';
            echo $this->toolsHelper->buildButton($target, 'Create it now', false, 'red');
            $group_id = 0;
            return;
        } else {
            echo $this->yes;
            $group_id = $item->id;
        }

        echo 'Is Usergroup ' . $component . ' a child of Usergroup Public? ';
        if ($item->title == 'Public') {
            echo $this->yes;
        } else {
            echo $this->no . ' (' . $item->title . ')';
            $this->changes_required++;
            $this->wrong_parent++;
            $target = 'administrator/index.php?option=com_users&view=group&layout=edit&id=' . $group_id;
            echo $this->toolsHelper->buildButton($target, 'Fix it now', false, 'red');
            echo '<br>';
        }

        echo 'Is Access-level Special available to Usergroup ' . $component . '?';
        if (str_contains($this->access_groups, ',' . $group_id . ',')) {
//            echo $this->access_groups . ',' . $group_id . '<br>';
            echo $this->yes;
        } else {
            //           echo $this->access_groups . ',' . $group_id . '<br>';
            echo $this->no;
            $this->changes_required++;
            $target = 'administrator/index.php?option=com_users&view=level&layout=edit&id=3';
            echo $this->toolsHelper->buildButton($target, 'Fix it now', false, 'red');
            echo '<br>';
        }

// Find number of users in the user_group
        $sql = 'SELECT COUNT(*) ';
        $sql .= 'FROM #__user_usergroup_map WHERE group_id=' . $group_id;
//        echo $sql . '<br>';
        $count = $this->toolsHelper->getValue($sql);
        echo 'Number of users in user-group ' . $component . ' ' . $count;
        if ($count > 0) {
            $target = 'administrator/index.php?option=com_ra_tools&task=system.showUsers&group_id=' . $group_id;
            echo $this->toolsHelper->buildButton($target, 'Show', false, 'red');
        }
        echo '<br>';
    }

    public function checkSchema() {
        if (!$this->toolsHelper->isSuperuser()) {
            return;
        }
        $helper = New SchemaHelper;
        $helper->checkColumn('ra_logfile', 'sub_system', 'U', 'VARCHAR(10) NOT NULL; ');
        $helper->checkColumn('ra_logfile', 'sub_syatem', 'D', 'VARCHAR(10) NULL AFTER record_type; ');

        $helper->checkColumn('ra_mail_lists', 'emails_outstanding', 'A', 'INT DEFAULT "0" AFTER footer; ');
        $helper->checkColumn('ra_api_sites', 'sub_system', 'A', 'VARCHAR(10) NOT NULL AFTER id; ');

        $helper->checkColumn('ra_emails', 'sender_name', 'A', 'VARCHAR(100) NOT NULL AFTER date_sent; ');
        $helper->checkColumn('ra_emails', 'sender_email', 'A', 'TEXT AFTER sender_name; ');
        $helper->checkColumn('ra_emails', 'addressee_name', 'A', 'VARCHAR(100) NOT NULL AFTER sender_email; ');
        $helper->checkColumn('ra_emails', 'addressee_email', 'A', 'TEXT AFTER addressee_name; ');
        //       $sql = 'UPDATE #__ra_emails set ref = 0 where ref is null';
        //       $this->toolsHelper->executeCommand($sql);
        //       $sql = 'ALTER TABLE `#__ra_emails` CHANGE COLUMN `ref` `ref` INTEGER NOT NULL';
        //       $this->toolsHelper->executeCommand($sql);
        $helper->checkColumn('ra_emails', 'ref', 'A', 'INT NULL DEFAULT "0" AFTER record_type');
        //
        $helper->checkColumn('ra_mail_shots', 'record_type', 'A', 'VARCHAR(1) DEFAULT "M" AFTER id; ');
        $helper->checkColumn('ra_mail_shots', 'mail_list_id', 'U', 'INT NULL; ');
        $helper->checkColumn('ra_mail_shots', 'event_id', 'A', 'INT NULL AFTER mail_list_id; ');

        $helper->checkColumn('ra_groups', 'bespoke', 'A', 'VARCHAR(1) NOT NULL DEFAULT 0 AFTER `name`;');
        $helper->checkColumn('ra_groups', 'website', 'U', 'VARCHAR(250);');
        $helper->checkColumn('ra_groups', 'co_url', 'U', 'VARCHAR(250);');
        // `` ,

        $target = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        echo $this->toolsHelper->backButton($target);
    }

    public function createUsergroup($component) {
        $component = $this->app->input->getWord('component', '');
        $sql = 'INSERT INTO #__usergroup (name,parent_id) ';
        $sql .= '("' . $component . '","1")';
        $item = $this->toolsHelper->executeCommand($sql);
    }

    private function deleteFile($target) {
        echo "deleting file $target<br>";
    }

    private function deleteFolder($target) {
        echo "deleting folder $target<br>";
    }

    public function deleteProfilesWithoutUsers() {
        if (!$this->toolsHelper->isSuperuser()) {
            Factory::getApplication()->enqueueMessage('Access only permitted for Superusers', 'warning');
            $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
            return;
        }

        $sql = 'DELETE p FROM #__ra_profiles AS p ';
        $sql .= 'LEFT JOIN #__users AS u ON u.id = p.id ';
        $sql .= 'WHERE u.id IS NULL';
        $count = $this->toolsHelper->executeCommand($sql);
        if ($count !== false) {
            Factory::getApplication()->enqueueMessage('Deleted ' . number_format($count) . ' orphaned RA profile(s).', 'notice');
        } else {
            Factory::getApplication()->enqueueMessage('Unable to delete orphaned RA profiles.', 'error');
        }

        $this->setRedirect('index.php?option=com_ra_tools&task=reports.checkDatabase');
    }   

    public function deleteView($component = 'com_ra_events', $view = 'Myevents') {
// first character of View must be upper case
        $application[0] = 'administrator/';
        $application[1] = '';
        for ($i = 0; $i < 2; $i++) {
            $this->deleteFile($application[$i] . 'components/' . $component . '/src/Controller/' . $view . 'Controller.php');
            $this->deleteFile($application[$i] . 'components/' . $component . '/src/Model/' . $view . 'Model.php');
            $this->deleteFile($application[$i] . 'components/' . $component . '/src/table/' . $view . 'Table.php');
            $this->deleteFolder($application[$i] . 'components/' . $component . '/src/View/' . $view);
            $this->deleteFolder($application[$i] . 'components/' . $component . '/tmpl/' . strtolower($view));
        }
    }

    private function executeCommand($sql) {
        return $this->toolsHelper->executeCommand($sql);
    }

    public function resetHitCounters() {
        if (!$this->toolsHelper->isSuperuser()) {
            Factory::getApplication()->enqueueMessage('Access only permitted for Superusers', 'warning');
            $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
            return;
        }

        $sql = 'SELECT COUNT(*) FROM #__content WHERE hits > 0';
        $count = (int) $this->toolsHelper->getValue($sql);

        if ($this->toolsHelper->executeCommand('UPDATE #__content SET hits = 0')) {
            $message = 'Reset hit counters on ' . number_format($count) . ' article';
            if ($count !== 1) {
                $message .= 's';
            }
            $message .= '.';
            Factory::getApplication()->enqueueMessage($message, 'notice');
        } else {
            Factory::getApplication()->enqueueMessage('Unable to reset article hit counters.', 'error');
        }

        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
    }

    public function showAccess() {
// Invoked from dashboard
        ToolBarHelper::title('Show your access permissions');

        $user = Factory::getApplication()->getIdentity();
        $this->toolsHelper->showAccess($user->id);
        echo $this->toolsHelper->backButton($this->back);
    }

    private function showLists() {
        $user_id = Factory::getApplication()->getIdentity()->id;
        $sql = ' FROM #__ra_mail_lists AS l ';
        $sql .= 'INNER JOIN #__ra_mail_subscriptions AS s ON s.list_id = l.id ';
        $sql .= 'WHERE s.user_id=' . $user_id;
        $count = $this->toolsHelper->getValue('SELECT COUNT(*)' . $sql);
        if ($count > 0) {
            echo '<b>You are an Author for the following list';
            if ($count > 1) {
                echo 's';
            }
            echo ':</b><br>' . PHP_EOL;
            echo '<ul>' . PHP_EOL;
            $rows = $this->toolsHelper->getRows('SELECT l.name' . $sql);
            foreach ($rows as $row) {
                echo '<li>' . $row->name . '</li>' . PHP_EOL;
            }
            echo '</ul>' . PHP_EOL;
        }
    }

    private function showLiteral($value) {
        if ($value) {
            return '<img src="/media/com_ra_tools/tick.png" width="20" height="20">' . 'Permitted';
        } else {
            return '<img src="/media/com_ra_tools/cross.png" width="20" height="20">' . 'Denied';
        }
    }

    public function showUsers() {
        if (!$this->toolsHelper->isSuperuser()) {
            echo 'Access only permitted for Superusers';
            return;
        }
        $group_id = $this->app->input->getInt('group_id', '');
        $sql = 'SELECT title ';
        $sql .= 'FROM #__usergroups ';
        $sql .= 'WHERE id=' . $group_id;
        $title = $this->toolsHelper->getvalue($sql);
        ToolBarHelper::title('Users for group ' . $title);

        $sql = 'SELECT u.id AS UserId, u.name, u.username, u.email ';
        $sql .= 'FROM #__user_usergroup_map AS map ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = map.user_id ';
        $sql .= 'WHERE map.group_id=' . $group_id;
        $sql .= ' ORDER BY u.name';
        $rows = $this->toolsHelper->getRows($sql);
//      Show link that allows page to be printed
        $target = 'administrators/index.php?option=com_ra_tools&task=system.showUsers&group_id=' . $group_id;
        echo $this->toolsHelper->showPrint($target) . '<br>' . PHP_EOL;
        $objTable = new ToolsTable;
        $objTable->add_header('id,Name,Username,Email');
        foreach ($rows as $row) {
            $objTable->add_item($row->UserId);
            $objTable->add_item($row->name);
            $objTable->add_item($row->username);
            $objTable->add_item($row->email);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo $this->toolsHelper->backButton('administrator/index.php?option=com_ra_tools&task=system.AccessWizard');
    }

    public function test() {
        $event_id = 123;
        echo 'encoding token<br>';
        $token = $this->toolsHelper->encodeToken(0, 21, $event_id);

        if ($token === false) {
            echo 'encodeToken failed: ' . $this->toolsHelper->error . '<br>';
            return;
        }

        echo $token . '<br>';
        $data = $this->toolsHelper->decodeToken($token);

        if ($data === false) {
            echo 'decodeToken failed: ' . $this->toolsHelper->error . '<br>';
            return;
        }

        var_dump($data);
    }

    public function updateSites() {
        $sql = 'SELECT id FROM #__ra_api_sites ';
        $sql .= 'WHERE url="https://staffordshireramblers.org.uk"';
        $id = $this->getValue($sql);
        if (is_null($id)) {
            $sql = "INSERT INTO `#__ra_api_sites`
            (`sub_system`, `url`, `token`, `colour`, `state`, `created`, `created_by`) VALUES
('RA Tools', 'https://staffordshireramblers.org.uk', 'c2hhMjU2Ojk3OTo5ODQ4NGMzOTNhMGJmM2U5NWY3NzcyODViNTI2NzFkYzY2MmQwZTZmMzliMmNiMTlkNmUzNzI0MjNkNGUyOThk',
'rgba(133, 132, 191, 0.1)', 1, '2025-07-09 06:03:03', 1 );";
            $this->executeCommand($sql);
            echo 'Created API site for Staffs<br>';
        }
        $sql = 'SELECT id FROM #__ra_api_sites ';
        $sql .= 'WHERE url= "https://ramblers.org"';
        $id = $this->getValue($sql);
        if (is_null($id)) {
            $sql = "INSERT INTO `#__ra_api_sites`
            (`sub_system`, `url`, `token`, `colour`, `state`, `created`, `created_by`) VALUES
('RA Walks', 'https://ramblers.org', '742d93e8f409bf2b5aec6f64cf6f405e', '
rgba(133, 132, 191, 0.1)', 1, '2025-07-09 06:06:34', 1);";
            $this->executeCommand($sql);
            echo 'Created API site for CO<br>';
        }
    }

    private function getValue($sql) {
        return $this->toolsHelper->getValue($sql);
    }

        public function temp() {
        $body = '<table>';
        $body .= '<thead>';
        $body .= '<th>Date</th><th>Reason</th><th>Sender</th><th>Recipient</th><th>User</th>';
        $body .= '</thead>';
        $body .= '<tbody>';

        $details = '<tr>';
        $details .= '<td>' . 'D d/m/y H:i' . '</td>';
        $details .= '<td>' . 1 . '</td>';
        $details .= '<td>' . 2 . '</td>';
        $details .= '<td>' . 3 . '</td>';
        $details .= '<td>';
        $details .= 'No user found';
        $details .= '</td>';
        $details .= '</tr>';

        $body .= $details;
        $body .= '</tbody>';
        $body .= '</table>';
        echo $body;
    }

}
