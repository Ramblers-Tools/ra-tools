<?php

/**
 * 13/10/25 CB optional start parameter for showTable,  resetHitCounters
 * 10/05/26 CB blockedUsers from mailman
 * 20/05/26 CB resetUsers from mailman
 * 27/06/26 CB change sort order in showLogfile
 * 03/09/26 CB change to showMenus
 * 06/09/26 CB use htmlspecialchars for message output
 */

namespace Ramblers\Component\Ra_tools\Administrator\Controller;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Ramblers\Component\Ra_tools\Site\Helpers\JsonHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\SchemaHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHtml;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

class ReportsController extends FormController {

    protected $criteria_sql;
    protected $back;
    protected $breadcrumbs;
    protected $db;
    protected $objApp;
    protected $toolsHelper;
    protected $prefix;
    protected $query;
    protected $scope;

    public function __construct() {
        parent::__construct();
        $this->db = Factory::getContainer()->get('DatabaseDriver');
        $this->toolsHelper = new ToolsHelper;
        $this->objApp = Factory::getApplication();
        $this->prefix = 'Reports: ';
        $this->back = 'administrator/index.php?option=com_ra_tools&view=reports';
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
        $this->breadcrumbs = $this->toolsHelper->buildLink('administrator/index.php', 'Home Dashboard');
        $this->breadcrumbs .= '>' . $this->toolsHelper->buildLink('administrator/index.php?option=com_ra_tools&view=dashboard', 'RA Dashboard');
        $this->breadcrumbs .= '>' . $this->toolsHelper->buildLink($this->back, 'System Reports');
    }

    public function areasLatitude() {
// display address of Areas, sorted by Latitude
        ToolBarHelper::title('Areas, sorted by Latitude');
        echo $this->breadcrumbs;
        $sql = 'SELECT latitude, longitude, code, name ';
        $sql .= "FROM #__ra_areas ";
        $sql .= 'ORDER BY latitude ';
        $objTable = new ToolsTable;
        $objTable->add_header("Latitude,Longitude,,Code,Name");
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $objTable->add_item($row->latitude);
            $objTable->add_item($row->longitude);
            $pin = $this->toolsHelper->showLocation($row->latitude, $row->longitude);
            $objTable->add_item($pin);
            $objTable->add_item($row->code);
            $objTable->add_item($row->name);
            $objTable->generate_line();
        }
        $objTable->generate_table();

        $sql = "SELECT COUNT(*) FROM #__ra_areas ";
        echo 'Number of Areas ' . $this->toolsHelper->getValue($sql) . '<br>';
        echo $this->toolsHelper->backButton($this->back);
    }

    public function areasLongitude() {
// display address of Areas, sorted by Longitude
        ToolBarHelper::title('Areas, sorted by Longitude');
        echo $this->breadcrumbs;
        $sql = 'SELECT longitude, latitude, code, name ';
        $sql .= "FROM #__ra_areas ";
        $sql .= 'ORDER BY longitude ';
        $this->toolsHelper->showQuery($sql);
        $sql = "SELECT COUNT(*) FROM #__ra_areas ";
        echo 'Number of Areas ' . $this->toolsHelper->getValue($sql) . '<br>';
        echo $this->toolsHelper->backButton($this->back);
    }


    public function blockedUsers() {
        ToolBarHelper::title($this->prefix . 'Blocked users');
        echo $this->breadcrumbs;
        $table = new ToolsTable();
        $table->add_header("Name,email,Lists,Audit,ID");

        $sql = "SELECT id, name as 'User', email  ";
        $sql .= 'FROM `#__users` ';
        $sql .= ' WHERE block=1';
        $sql .= ' ORDER BY id';
        $target = 'administrator/index.php?option=com_ra_mailman&task=system.purgeUser&id=';
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $table->add_item($row->User);
            $table->add_item($row->email);
            $count = $this->countLists($row->id);
            $table->add_item($count);
            $count = $this->countAudit($row->id);
            $table->add_item($count);
            if ($this->toolsHelper->isSuperuser()) {
                $table->add_item($this->toolsHelper->buildButton($target . $row->id, 'Purge', false, 'orange'));
            } else {
                $table->add_item($row->id);
            }
            $table->generate_line();
        }
        $table->generate_table();
        echo count($rows) . ' rows<br>';
        if ((count($rows) > 1) AND ($this->toolsHelper->isSuperuser())) {
            $target = 'administrator/index.php?option=com_ra_mailman&task=system.purgeAllUsers';
            echo $this->toolsHelper->buildButton($target, 'Purge All', false, 'red');
        }

        echo $this->toolsHelper->backButton($this->back);
    }
    private function breadcrumbsExtra($label, $report) {
// generates a link to be added to the standard breadcrumbs
        $target = 'administrator/index.php?option=com_ra_tools&task=reports.' . $report;
        return '>' . $this->toolsHelper->buildLink($target, $label);
    }

    public function contactsByCategory() {
        ToolBarHelper::title('Contacts by Category');
        echo $this->breadcrumbs;
        $sql = 'SELECT c.id, c.name, c.con_position, c.email_to, ';
        $sql .= 'u.username, u.email, ';
        $sql .= 'cat.title, c.state ';
        $sql .= 'FROM #__contact_details AS c ';
        $sql .= 'LEFT JOIN #__users AS u ON u.id =  c.user_id ';
        $sql .= 'INNER JOIN #__categories AS cat ON cat.id =  c.catid ';
        $sql .= "WHERE c.con_position IS NOT NULL ";
        $sql .= 'AND c.published=1 ';
        $sql .= 'AND cat.extension="com_contact" ';
        $sql .= 'AND cat.title="committee" ';
        $sql .= 'ORDER BY cat.title, c.name' . $order;

        $objTable = new ToolsTable;
        $objTable->add_header("Category,Name,Role,User name,email, Status");
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $objTable->add_item($row->title);
            $objTable->add_item($row->name);
            $objTable->add_item($row->con_position);
            $objTable->add_item($row->username);
            $objTable->add_item($row->email);
            $objTable->add_item($row->state);
            $objTable->generate_line();
        }
        $objTable->generate_table();

        echo $this->toolsHelper->backButton($this->back);
    }

        private function countLists($user_id) {
        $sql = 'SELECT COUNT(id) ';
        $sql .= 'FROM `#__ra_mail_subscriptions` ';
        $sql .= 'WHERE user_id=' . $user_id;
//        echo $sql . '<br>';
//        $count = $this->toolsHelper->getValue($sql);
        return $this->toolsHelper->getValue($sql);
    }
    
    public function countUsers() {
        ToolBarHelper::title($this->prefix . 'User count by Group');
        echo $this->breadcrumbs;
        $sql = "SELECT a.ra_group_code AS 'GroupCode', g.name, count(u.id) AS 'Number', ";
        $sql .= "MIN(w.walk_date) AS 'Earliest', ";
        $sql .= "MAX(w.walk_date) as 'Latest' ";
        $sql .= "FROM #__ra_profiles AS a ";
        $sql .= 'INNER JOIN #__users AS u ON u.id = a.id ';
        $sql .= 'LEFT JOIN #__ra_groups AS g ON g.code = a.ra_group_code ';
        $sql .= 'LEFT JOIN #__ra_walks AS w ON w.leader_user_id = a.id ';
        $sql .= 'GROUP BY a.ra_group_code ';
        $sql .= 'ORDER BY a.ra_group_code ';
        $rows = $this->toolsHelper->getRows($sql);
//      Show link that allows page to be printed
        $target = 'index.php?option=com_ra_tools&task=reports.countUsers';
        echo $this->toolsHelper->showPrint($target) . '<br>' . PHP_EOL;
        $objTable = new ToolsTable;
        $objTable->add_header("Code,Group,Count,Earliest walk,Latest walk");
        $target = 'administrator/index.php?option=com_ra_tools&task=reports.showUsersForGroup&group=';
        foreach ($rows as $row) {
            if ($row->GroupCode == '') {
                $objTable->add_item('');
            } else {
// URI cannot handle commas as part of the parameters
//$param = str_replace(',', '%5C%2C%20', $row->GroupCode);
                $param = str_replace(',', '_', $row->GroupCode);
                $objTable->add_item($this->toolsHelper->buildLink($target . $param, $row->GroupCode));
//$objTable->add_item($this->toolsHelper->buildLink($target . $row->GroupCode, $row->GroupCode));
            }
            $objTable->add_item($row->name);
            $objTable->add_item($row->Number);
            $objTable->add_item($row->Earliest);
            $objTable->add_item($row->Latest);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo $this->toolsHelper->backButton($this->back);
//        echo "<p>";
    }

    public function duplicateName() {
        ToolBarHelper::title('Users with Duplicate Name');
        echo $this->breadcrumbs;
        $sql = 'SELECT name, count(id) ';
        $sql .= 'FROM #__users GROUP BY name ';
        $sql .= 'HAVING COUNT(id) > 1 ';
        $sql .= 'ORDER BY name';
        $rows = $this->toolsHelper->getRows($sql);

        if (count($rows) == 0) {
            echo 'No duplicates found<br>';
        } else {
            $objTable = new ToolsTable;
            $objTable->add_header('id,Real name,Email,Preferred name,Group');
            foreach ($rows as $row) {

                $sql_user = 'SELECT u.id, u.name, u.email, p.preferred_name, p.home_group ';
                $sql_user .= 'FROM #__users AS u ';
                $sql_user .= 'LEFT JOIN #__ra_profiles AS p ON p.id = u.id ';
                $sql_user .= 'WHERE u.name="' . $row->name . '"';
//              echo "$sql_user<br>";
                $profiles = $this->toolsHelper->getRows($sql_user);
                foreach ($profiles as $profile) {
                    $objTable->add_item($profile->id);
                    $objTable->add_item($profile->name);
                    $objTable->add_item($profile->email);
                    $objTable->add_item($profile->preferred_name);
                    $objTable->add_item($profile->home_group);
                    $objTable->generate_line();
                }
            }
            $objTable->generate_table();
        }
// Check for duplicate email
        $sql = 'SELECT email, count(id) ';
        $sql .= 'FROM #__users GROUP BY email ';
        $sql .= 'HAVING COUNT(id) > 1 ';
        $sql .= 'ORDER BY email';
        $rows = $this->toolsHelper->getRows($sql);

        if (count($rows) > 0) {
            echo '<h2>Duplicae email</h2>';
            $objTable = new ToolsTable;
            $objTable->add_header('id,Real name,Email,Preferred name,Group');
            foreach ($rows as $row) {

                $sql_user = 'SELECT u.id, u.name, u.email, p.preferred_name, p.home_group ';
                $sql_user .= 'FROM #__users AS u ';
                $sql_user .= 'LEFT JOIN #__ra_profiles AS p ON p.id = u.id ';
                $sql_user .= 'WHERE u.email="' . $row->email . '"';
//              echo "$sql_user<br>";
                $profiles = $this->toolsHelper->getRows($sql_user);
                foreach ($profiles as $profile) {
                    $objTable->add_item($profile->id);
                    $objTable->add_item($profile->name);
                    $objTable->add_item($profile->email);
                    $objTable->add_item($profile->preferred_name);
                    $objTable->add_item($profile->home_group);
                    $objTable->generate_line();
                }
            }
            $objTable->generate_table();
        }
        echo $this->toolsHelper->backButton($this->back);
    }

    public function extractContacts() {
        ToolBarHelper::title($this->prefix . 'Contact details');
        echo $this->breadcrumbs . '<br>';
        $sql = 'SELECT cat.title, c.name, c.email_to, u.email ';
        $sql .= 'FROM `#__contact_details`  AS c ';
        $sql .= 'LEFT JOIN #__users AS u ON u.id =  c.user_id ';
        $sql .= 'INNER JOIN #__categories AS cat ON cat.id =  c.catid ';
        $sql .= 'ORDER BY cat.title, name';
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            echo $row->title . ',' . $row->name;
            echo ',' . $row->email, ',' . $row->email_to . '<br>';
        }
        echo $this->toolsHelper->backButton($this->back);
    }

    private function lookupPath($value) {
        if ($value == 'not specified') {
            return '';
        }
        if (is_dir(JPATH_ROOT . '/' . $value . '/')) {
            return 'Y';
        } else {
            return 'N';
        }
    }

    public function resetUsers() {
        ToolBarHelper::title($this->prefix . 'Users awaiting password reset');
        echo $this->breadcrumbs;
        $objTable = new ToolsTable();
        $objTable->add_header("Name,email,Preferred name,Group,Lists,ID");

        $sql = "SELECT u.id, u.name as 'User', u.email,  ";
        $sql .= 'p.home_group, p.preferred_name ';
        $sql .= 'FROM  `#__users` AS u ';
        $sql .= 'LEFT JOIN `#__ra_profiles` AS p on p.id = u.id ';
        $sql .= ' WHERE u.requireReset=1';
        $sql .= ' ORDER BY id';
//        $target = 'administrator/index.php?option=com_users&view=users';
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $objTable->add_item($row->User);
            $objTable->add_item($row->email);
            $objTable->add_item($row->preferred_name);
            $objTable->add_item($row->home_group);
            $count = $this->countLists($row->id);
            $objTable->add_item($count);
            $objTable->add_item($row->id);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo $this->toolsHelper->backButton($this->back);
    }

    private function setScopeCriteria() {
        switch ($this->scope) {
            case ($this->scope == 'D');
                $this->query->where('state<>1');
                break;
            case ($this->scope == 'F');
                $this->query->where('state=1');
                $this->query->where('datediff(walk_date, CURRENT_DATE) >= 0');
                break;
            case ($this->scope == 'H');
                $this->query->where('state=1');
                $this->query->where('datediff(walk_date, CURRENT_DATE) < 0');
        }
    }

    private function setSelectionCriteria($mode, $opt) {
        if ($mode == 'G') {
            $this->query->where("groups.code='" . $opt . "'");
        } else {
            if ($opt == 'NAT') {

            } else {
                $this->query->where("SUBSTR(groups.code,1,2)='" . $opt . "'");
            }
        }
    }

    public function showBespoke() {
        ToolBarHelper::title($this->prefix . 'Groups by value of bespoke');
        echo $this->breadcrumbs;
        $sql = 'SELECT bespoke, COUNT(id) AS cnt FROM `#__ra_groups` ';
        $sql .= 'GROUP BY bespoke ';
        $sql .= 'ORDER BY bespoke DESC';

        $objTable = new ToolsTable;
        $objTable->add_header("Value,Description,Count");
        $rows = $this->toolsHelper->getRows($sql);
        $target = 'administrator/index.php?option=com_ra_tools&task=reports.showBespokeDetail&bespoke=';
        $count = $this->toolsHelper->rows;
        $tot = 0;
        foreach ($rows as $row) {
            $link = $target . $row->bespoke;
            if ($row->bespoke == 2) {
                $description = 'Both copies';
            } else if ($row->bespoke == 1) {
                $description = 'Local copy only';
            } else {
                $description = 'Neither';
            }
            $objTable->add_item($this->toolsHelper->buildLink($target . $row->bespoke, $row->bespoke));
            $objTable->add_item($description);
            $objTable->add_item($row->cnt);
            $tot = $tot + $row->cnt;
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo "$tot Groups<br>";
        $sql = 'SELECT COUNT(id) AS cnt FROM `#__ra_groups` WHERE details=""';

        $tot = $this->toolsHelper->getValue($sql);
        if ($tot > 0) {
            echo 'Groups without a description<br>';
            $sql = 'SELECT code, name FROM `#__ra_groups` WHERE details=""';
            $rows = $this->toolsHelper->getRows($sql);
            foreach ($rows as $row) {
                echo $row->code . ' ' . $row->name . '<br>';
            }
            echo '<br>';
        }
        echo $this->toolsHelper->backButton($this->back);
    }

    public function showBespokeDetail() {
        $bespoke = $this->objApp->input->getCmd('bespoke', '1');

        ToolBarHelper::title($this->prefix . 'Groups where bespoke=' . $bespoke);
        echo $this->breadcrumbs;
        echo $this->breadcrumbsExtra('Groups by value of bespoke', 'showBespoke') . '<br>';
        $sql = 'SELECT code, details FROM `#__ra_groups` WHERE bespoke="' . $bespoke . '" ';
        $sql .= 'ORDER BY code';

        $objTable = new ToolsTable;
        $objTable->add_header("Code,Details");
        $rows = $this->toolsHelper->getRows($sql);
        $count = $this->toolsHelper->rows;
        foreach ($rows as $row) {
            $objTable->add_item($row->code);
            $objTable->add_item($row->details);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo "$count records<br>";
        $back = 'administrator/index.php?option=com_ra_tools&task=reports.showBespoke';
        echo $this->toolsHelper->backButton($back);
    }

    public function showClusters() {
        ToolBarHelper::title($this->prefix . 'Clusters and their contacts'); // `#__contact_details`
        echo $this->breadcrumbs;
        $sql = 'SELECT c.code, c.name, c.contact_id, con.state, ';
        $sql .= 'con.con_position, con.name AS `contact`, con.email_to ';
        $sql .= 'FROM `#__ra_clusters` AS c ';
        $sql .= 'LEFT JOIN `#__contact_details` AS con ON con.id =  c.contact_id ';
        $sql .= 'ORDER BY c.code';

        $objTable = new ToolsTable;
        $objTable->add_header("Code,Cluster,Contact ID,Contact name,email,Status");
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $objTable->add_item($row->code);
            $objTable->add_item($row->name); // con_position
            $objTable->add_item($row->contact_id);
            $objTable->add_item($row->contact);
            $objTable->add_item($row->email_to);
            $objTable->add_item($row->state);
            $objTable->generate_line();
        }
        $objTable->generate_table();

        echo $this->toolsHelper->backButton($this->back);
    }

    public function showColours() {
        echo '<link rel="stylesheet" type="text/css" href="/media/com_ra_tools/css/ramblers.css">';
        $c = array();

        $c[] = 'mustard';
        $c[] = 'orange';
        $c[] = 'red';
        $c[] = 'darkgreen';
        $c[] = 'lightgreen';
        $c[] = 'maroon';
        $c[] = 'mud';
        $c[] = 'grey';
        $c[] = 'teal';

        $c[] = 'sunset';
        $c[] = 'granite';
        $c[] = 'rosycheeks';
        $c[] = 'sunrise';
        $c[] = 'cloudy';
        $c[] = 'mintcakedark';
        $c[] = 'cancelled';
        $c[] = 'lightgrey';
        $c[] = 'midgrey';
        $target = 'index.php';
        ToolBarHelper::title('Examples of available colour styles');
        echo $this->breadcrumbs;
        foreach ($c as $colour) {
            echo '<h3>' . $colour . '</h3>';
            $class = ToolsHelper::lookupColourCode($colour, 'B');
            echo ' class="' . $class . '"';
            echo $this->toolsHelper->buildButton($target, 'Button', 0, $colour) . '<br>';

            $class = ToolsHelper::lookupColourCode($colour, 'T');
            echo ' class="' . $class . '"';
            $objTable = new ToolsTable();
            $title = ' class="' . $class . '" One,Two,Three,Four,Five';
            $objTable->add_header('One,Two,Three,Four,Five', $class);

            for ($i = 1;
                    $i < 6;
                    $i++) {
                $objTable->add_item($i);
            }
            $objTable->generate_line();
            $objTable->generate_table();
        }

        $saturation = [];
        $saturation[] = '0.2';
        $saturation[] = '0.4';
        $saturation[] = '0.6';
        $saturation[] = '0.8';
        $saturation[] = '1';

        $names = [];
        $colours = [];
        $names[] = 'mint cake';
        $colours[] = '156,200,171';
        $names[] = 'sunset';
        $colours[] = '240,128,80';
        $names[] = 'granite';
        $colours[] = '64,64,65';
        $names[] = 'rosy cheeks';
        $colours[] = '246,176,157';
        $names[] = 'sunrise';
        $colours[] = '249,177,4';

        $names[] = 'Pantone 0110';
        $colours[] = '215,169,0';
        $names[] = 'Pantone 0159';
        $colours[] = '199,91,18';
        $names[] = 'Pantone 0186';
        $colours[] = '198,12,48';
        $names[] = 'Pantone 0555';
        $colours[] = '32,108,0';

        $names[] = 'Pantone 0583';
        $colours[] = '168,180,0';
        $names[] = 'Pantone 1815';
        $colours[] = '120,35,39';
        $names[] = 'Pantone 4485';
        $colours[] = '91,73,31';
        $names[] = 'Pantone 5565';
        $colours[] = '139,165,156';
        $names[] = 'Pantone 7474';
        $colours[] = '0,122,135';

        echo '<h2>Standard colours (rgba)</h2>';
        echo 'This shows the full range of standard colours in varying levels of saturation.<br>';
        echo 'Firstly the colours from the 2022 redesign, followed by the previous colour palette.<br>';
        $objTable = new ToolsTable;
        $header = 'Colour';
        for ($s = 0;
                $s < 5;
                $s++) {
            $header .= ',' . $saturation[$s];
        }
        $objTable->add_header($header);

        for ($i = 0;
                $i < count($names);
                $i++) {

            $objTable->add_item($names[$i]);
//    echo $names[$i] . '<br>';
            for ($s = 0;
                    $s < 5;
                    $s++) {
                $rgba = '(' . $colours[$i] . ',' . $saturation[$s] . ')';
                $tag = '<div style="background-color: rgba' . $rgba . ';">';
//        echo substr($tag, 1);
                $objTable->add_item($tag . $rgba . '</div>');
            }
            $objTable->generate_line();
        }
        $objTable->generate_table();

        $target = "administrator/index.php?option=com_ra_tools&view=reports";
        echo $this->toolsHelper->backButton($target);
    }

    public function showEvents() {
// Because the report takes so long to run, drilldown opens a new window in the Site application
        $jsonHelper = new JsonHelper;
        $objHelper = new ToolsHelper;
        $objTable = new ToolsTable();
        $target = 'index.php?option=com_ra_tools&task=reports.showEvents&code=';

        echo '<h2>Events from WalksManager</h2>';
        echo $this->breadcrumbs;
        $objTable->add_header("Code,Name,Count");

        $sql = 'SELECT code, name FROM #__ra_areas ';
        $sql .= 'ORDER BY code ';
//       $sql .= 'LIMIT 3';
        $areas = $objHelper->getRows($sql);
        foreach ($areas as $area) {
            $count = $jsonHelper->getCountEvents($area->code);
            if ($count > 0) {
                $objTable->add_item($area->code);
                $objTable->add_item($area->name);
                $objTable->add_item($objHelper->buildLink($target . $area->code, $count, true));
                $objTable->generate_line();
            }
        }

        $objTable->generate_table();
        echo $objHelper->backButton($this->back);
    }

    function showExtensions() {
        ToolBarHelper::title($this->prefix . 'Extensions and Modules %ra%');
        echo $this->breadcrumbs;
        echo $this->toolsHelper->showExtensions();

        $back = "administrator/index.php?option=com_ra_tools&view=reports";
        echo $this->toolsHelper->backButton($back);
    }

    public function showFeed() {
        ToolBarHelper::title($this->prefix . 'Feed update for ' . $this->toolsHelper->lookupGroup($group_code));
        echo $this->breadcrumbs;
        $group_code = $this->objApp->input->getCmd('group_code', 'NS03');
        $this->scope = $this->objApp->input->getCmd('scope', '');
        $csv = substr($this->objApp->input->getCmd('csv', ''), 0, 1);

        $objTable = new ToolsTable();

        $objTable->add_header("Date,Message");
        $sql = "SELECT date_amended, field_value ";
        $sql .= "FROM #__ra_groups_audit AS audit ";
        $sql .= "INNER JOIN #__ra_groups `groups` ON `groups`.id = audit.object_id ";
        $sql .= "WHERE `groups`.code='" . $group_code . "' ";
        $sql .= 'ORDER BY date_amended DESC ';
//        echo $sql;
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $objTable->add_item($row->date_amended);
            $objTable->add_item($row->field_value);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        $back = "administrator/index.php?option=com_ra_tools&view=reports_group&group_code=" . $group_code . '&scope=' . $this->scope;
        echo $this->toolsHelper->backButton($back);
//        if ($csv == '') {
//            $target = "administrator/index.php?option=com_ra_tools&task=reports.showFeed&csv=feed&group_code=" . $group_code . '&scope=' . $this->scope;
//            echo $this->toolsHelper->buildLink($target, "Extract as CSV", False,  "btn btn-small button-new");
//        }
    }

    public function showFeedSummary() {
        $this->scope = $this->objApp->input->getCmd('scope', '');
        $csv = substr($this->objApp->input->getCmd('csv', ''), 0, 1);
        echo "<h2>Feed Summary</h2>";
        $objTable = new ToolsTable();
        $objTable->set_csv($csv);

        $objTable->add_header("Date,Message");
        $sql = "SELECT log_date, message ";
        $sql .= "FROM #__ra_logfile ";
        $sql .= "WHERE record_type='B9' AND ref=2 ";
        $sql .= 'ORDER BY log_date DESC ';
        $sql .= "Limit 28";
//        echo $sql;
        $rows = $this->toolsHelper->getRows($sql);
        foreach ($rows as $row) {
            $objTable->add_item($row->log_date);
            $objTable->add_item($row->message);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        $back = "administrator/index.php?option=com_ra_tools&view=reports_area&area=NAT&scope=" . $this->scope;
        echo $this->toolsHelper->backButton($back);
        if ($csv == '') {
            $target = "administrator/index.php?option=com_ra_tools&task=reports.showFeedSummary&csv=feedSummary";
            echo $this->toolsHelper->buildLink($target, "Extract as CSV", False, "btn btn-small button-new");
        }
    }

    public function showFeedSummaryArea() {
        $area = $this->objApp->input->getCmd('area_code', 'NS');
        $this->scope = $this->objApp->input->getCmd('scope', '');
        $current_group = '';
        $groups_count = 0;
        $groups_found = 0;
        $area_code = 'NS';
        echo "<h2>Feed update for " . $this->toolsHelper->lookupArea($area) . "</h2>";
        $sql = "SELECT code from #__ra_groups where code LIKE '" . $area . "%' ORDER BY code";
        $objTable = new ToolsTable();
        $objTable->add_header("Group,Date,Message");

        $groups = $this->toolsHelper->getRows($sql);
        $groups_count = $this->toolsHelper->rows;
        foreach ($groups as $group) {
            $sql = "SELECT `groups`.code, date_amended, field_value ";
            $sql .= "FROM #__ra_groups_audit AS audit ";
            $sql .= "INNER JOIN #__ra_groups `groups` ON `groups`.id = audit.object_id ";
            $sql .= "WHERE `groups`.code='" . $group->code . "' ";
            $sql .= 'ORDER BY date_amended DESC LIMIT 7';
//            echo $sql . '<br>';
            $rows = $this->toolsHelper->getRows($sql);
            foreach ($rows as $row) {
                if ($current_group == $row->code) {

                } else {
                    $groups_found++;
                    $current_group = $row->code;
                }
                $objTable->add_item($group->code);
                $objTable->add_item($row->date_amended);
                $objTable->add_item($row->field_value);
                $objTable->generate_line();
            }
        }

        $objTable->generate_table();
        echo $groups_found . " groups out of " . $groups_count;
        $back = "administrator/index.php?option=com_ra_tools&view=reports_area&area=" . $area . '&scope=' . $this->scope;
        echo $this->toolsHelper->backButton($back);
    }

    public function showIcons() {
// Created 08/08/25, not actually used
        echo 'info <i class="icon-info"></i><br>';
        echo 'refresh <i class="icon-refresh"></i><br>';
        echo 'envelope <i class="icon-envelope"></i><br>';
        echo 'repeat <i class="fa-repeat"></i><br>';
        echo 'calendar <i class="fa-solid fa-calendar-days"></i><br>';
        echo '<i class="fa-calendar-days"></i><br>';
    }

    public function showJoomlaUsersByGroup() {
        echo $this->breadcrumbs . '<br>';

        ToolBarHelper::title($this->prefix . 'Joomla users by group');
        $toolsHelper = new ToolsHelper;

        $sql = 'SELECT g.id, g.title, COUNT(u.id) as Num ';
        $sql .= 'FROM #__usergroups AS g ';
        $sql .= 'LEFT JOIN #__user_usergroup_map AS map ON map.group_id = g.id ';
        $sql .= 'INNER JOIN #__users AS u on u.id = map.user_id ';
        $sql .= 'GROUP BY g.id, g.title ';
        $sql .= 'ORDER BY g.id ';
        $rows = $this->toolsHelper->getRows($sql);
//      Show link that allows page to be printed
        $target = 'index.php?option=com_ra_tools&task=reports.showJoomlaUsersByGroup';
        echo $this->toolsHelper->showPrint($target) . '<br>' . PHP_EOL;
        $objTable = new ToolsTable;
        $objTable->add_header('Group_id,Title,Count');
        $target = 'administrator/index.php?option=com_ra_tools&task=reports.showJoomlaUsersForGroup&group=';
        foreach ($rows as $row) {
            $objTable->add_item($row->id);
            $objTable->add_item($row->title);
            $link = $target . $row->id . '&group_name=' . $row->title;
            $objTable->add_item($toolsHelper->buildLink($link, $row->Num, false));
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo $this->toolsHelper->backButton('administrator/index.php?option=com_ra_tools&view=reports');
    }

    public function showJoomlaUsersForGroup() {

        $group_id = $this->objApp->input->getInt('group', '');
        $group_name = $this->objApp->input->getCmd('group_name', '');
        ToolBarHelper::title($this->prefix . 'Joomla users for group ' . $group_name);
        echo $this->breadcrumbs . $this->breadcrumbsExtra('Show Joomla users by group', 'showJoomlaUsersByGroup') . '<br>';
        $toolsHelper = new ToolsHelper;
        $sql = 'SELECT u.id AS UserId, u.name, u.username, u.email ';
        $sql .= 'FROM #__users AS u ';
        $sql .= 'INNER JOIN #__user_usergroup_map AS map ON map.user_id = u.id ';
        $sql .= 'WHERE map.group_id=' . $group_id;
        $rows = $this->toolsHelper->getRows($sql);
//      Show link that allows page to be printed
        $target = 'index.php?option=com_ra_tools&task=reports.showJoomlaUsersForGroup&group=' . $group_id;
        echo $this->toolsHelper->showPrint($target) . '<br>' . PHP_EOL;
        $objTable = new ToolsTable;
        $objTable->add_header('id,Name,Username,Email');
        $target = 'administrator/index.php?option=com_ra_tools&task=reports.showUsersForGroup&group=';
        foreach ($rows as $row) {
            $objTable->add_item($row->UserId);
            $objTable->add_item($row->name);
            $objTable->add_item($row->username);
            $objTable->add_item($row->email);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo $this->toolsHelper->backButton('administrator/index.php?option=com_ra_tools&task=reports.showJoomlaUsersByGroup');
    }

    public function showLogfile() {
        echo $this->breadcrumbs;
        $offset = $this->objApp->input->getCmd('offset', '0');
        $option = $this->objApp->input->getCmd('option', 'com_ra_tools');
        $next_offset = $offset - 1;
        $previous_offset = $offset + 1;

        $date_difference = (int) $offset;
        $today = date_create(date("Y-m-d 00:00:00"));
        if ($date_difference === 0) {
            $target = $today;
        } else {
            if ($date_difference > 0) { // positive number
                $target = date_add($today, date_interval_create_from_date_string("-" . $date_difference . " days"));
            } else {
                $target = date_add($today, date_interval_create_from_date_string($date_difference . " days"));
            }
        }
        ToolBarHelper::title($this->prefix . 'Logfile records for ' . date_format($target, "D d M"));
//echo $option;
        $sql = "SELECT date_format(log_date, '%a %e-%m-%y') as Date, ";
        $sql .= "date_format(log_date, '%H:%i:%s.%u') as Time, ";
        $sql .= "sub_system, record_type, ";
        $sql .= "ref, ";
        $sql .= "message ";
        $sql .= "FROM #__ra_logfile ";
        $sql .= "WHERE log_date >='" . date_format($target, "Y/m/d H:i:s") . "' ";
        $sql .= "AND log_date <'" . date_format($target, "Y/m/d 23:59:59") . "' ";
        $sql .= "ORDER BY sub_system, log_date DESC, record_type DESC";
        $rows = $this->toolsHelper->getRows($sql);
        if (count($rows) == 0) {
            echo '<br>No logfile records for ' . date_format($target, "Y/m/d") . '<br>';
        } else {
            $objTable = new ToolsTable;
            $objTable->add_header('Date,Time,Sub system,Type,Ref,Message');
            foreach ($rows as $row) {
                $objTable->add_item($row->Date);
                $objTable->add_item($row->Time);
                $objTable->add_item($row->sub_system);
                $objTable->add_item($row->record_type);
                $objTable->add_item($row->ref);
                $objTable->add_item(htmlspecialchars($row->message));
                $objTable->generate_line();
            }
            $objTable->generate_table();
            echo "<h5>End of logfile records for " . date_format($target, "D d M") . "</h5>";
        }
        echo $this->toolsHelper->buildButton("administrator/index.php?option=com_ra_tools&task=reports.showLogfile&offset=" . $previous_offset, "Previous day", False, 'grey');
        if ($next_offset >= 0) {
            echo $this->toolsHelper->buildButton("administrator/index.php?option=com_ra_tools&task=reports.showLogfile&offset=" . $next_offset, "Next day", False, 'teal');
        }
        $target = "administrator/index.php?option=com_ra_tools&view=reports";
        echo $this->toolsHelper->backButton($target);
    }

    public function showLogfileByDay() {

    }

    public function showLogfileByMonth() {
        $field = 'log_date';
        $table = ' #__ra_logfile';
        $criteria = '';
        $title = 'Logfile records by month';
        $link = 'administrator/index.php?option=com_ra_tools&task=reports.showLogfileForMonth';
        $back = $this->back;
        echo $this->breadcrumbs;
        $this->toolsHelper->showMonthMatrix($field, $table, $criteria, $title, $link, $back);
    }

    public function showLogfileForDay() {
        $date = $this->objApp->input->getCmd('date', '');
        if ($date == '') {
            ToolBarHelper::title('date is blank' . $date);
            echo $this->toolsHelper->backButton($this->back);
        }
        ToolBarHelper::title('Logfile records for ' . $date);

        $sql = "SELECT date_format(log_date, '%a %e-%m-%y') as Date, ";
        $sql .= "date_format(log_date, '%H:%i:%s.%u') as Time, ";
        $sql .= "sub_system,record_type, ";
        $sql .= "ref, ";
        $sql .= "message ";
        $sql .= "FROM #__ra_logfile ";
        $sql .= "WHERE log_date >='" . $date . " 00:00:00' ";
        $sql .= "AND log_date <'" . $date . " 23:59:59' ";
        $sql .= "ORDER BY log_date DESC, record_type ";
//       echo $sql;
        if ($this->toolsHelper->showSql($sql)) {
            echo "<h5>End of logfile records for " . $date . "</h5>";
        } else {
            echo 'Error: ' . $this->toolsHelper->error . '<br>';
        }

        $back = 'administrator/index.php?option=com_ra_tools&task=reports.showLogfileForMonth';
        $yyyy = substr($date, 0, 4);
        $mm = substr($date, 5, 2);
        $back .= '&year=' . $yyyy . '&month=' . $mm;
        echo $this->toolsHelper->backButton($back);
    }

    public function showLogfileForMonth() {
        echo $this->breadcrumbs . $this->breadcrumbsExtra('Logfile by month', 'showLogfileByMonth') . '<br>';
        $yyyy = $this->objApp->input->getInt('year', '2025');
        $mm = $this->objApp->input->getInt('month', '5');
        $field = 'log_date';
        $table = ' #__ra_logfile';
        $criteria = '';
        $title = 'Logfile records for month ' . $mm . '/' . $yyyy;
        $link = 'administrator/index.php?option=com_ra_tools&task=reports.showLogfileForDay';
        $back = 'administrator/index.php?option=com_ra_tools&task=reports.showLogfileByMonth';
        $this->toolsHelper->showDayMatrix($field, $table, $yyyy, $mm, $criteria, $title, $link, $back);
    }

public function showMenus() {
        ToolBarHelper::title($this->prefix . 'Menu items');
        echo $this->breadcrumbs . '<br>';
//      Show link that allows page to be printed
        $target = 'administrator/index.php?option=com_ra_tools&task=reports.showMenus';
        echo $this->toolsHelper->showPrint($target) . '<br>' . PHP_EOL;
        $sql = 'SELECT  p.title AS "Parent", m.link, m.title, m.published, m.link, ';
        $sql .= "CASE WHEN p.menutype='main' THEN 'Admin' WHEN p.menutype='mainmenu' THEN 'Site' ELSE '' END AS 'Type', ";
        $sql .= 'm.note, m.id, m.parent_id ';
        $sql .= 'FROM `#__menu` AS m ';
        $sql .= 'INNER JOIN `#__menu` AS p ON p.id = m.parent_id ';
        $sql .= "WHERE m.link like 'index.php?option=com_ra%' ";
        $sql .= 'ORDER BY m.menutype, m.link, p.title, m.title';

        $rows = $this->toolsHelper->getRows($sql);
        $objTable = new ToolsTable();
$objTable->add_header('Location,Component,Parent,Title,Link,Note,Published,id');
        foreach ($rows as $row) {
            if ($row->Type == '') {
                $objTable->add_item('Sidebar');
            } else {
                $objTable->add_item($row->Type);
            }

            $component = substr($row->link, 17);
            $pointer = strpos($component, '&');
            if ($pointer == 0) {
                $view = '';
            } else {
                $view = substr($component, $pointer + 1);
                $component = substr($component, 0, $pointer);
            }
            if ($component == 'com_ra_tools') {
                $objTable->add_item('RA Tools');
            } elseif ($component == 'com_ra_tools') {
                $objTable->add_item('RA Events');
            } elseif ($component == 'com_ra_mailman') {
                $objTable->add_item('MailMan');
            } elseif ($component == 'com_ra_walks') {
                $objTable->add_item('RA Walks');
            } elseif ($component == 'com_ra_paths') {
                $objTable->add_item('RA paths');
            } elseif ($component == 'com_ra_wf') {
                $objTable->add_item('Walks Follow');
            } else {
                $objTable->add_item($component);
            }

            $objTable->add_item($row->Parent);
            $objTable->add_item($row->title);
            $objTable->add_item($view);
            $objTable->add_item($row->note);
            if ($row->published == '1') {
                $icon = 'publish';    // tick
            } else {
                $icon = 'delete';     // cross
            }
            $objTable->add_item('<span class="icon-' . $icon . '"></span>');
            $objTable->add_item($row->id);
            $objTable->generate_line();
        }
        $objTable->generate_table();
////////////////////////////////////////////////////////////////////////////////
        $sql = 'SELECT m.id, p.title AS "Parent", m.link, m.title, m.published, m.link, m.parent_id ';
        $sql .= 'FROM `#__menu` AS m ';
        $sql .= 'INNER JOIN `#__menu` AS p ON p.id = m.parent_id ';
        $sql .= 'WHERE p.title = "Menu_Item_Root" AND m.title = "Dashboard"';
        $this->toolsHelper->showQuery($sql);

        $sql = 'SELECT m.id FROM `#__menu` AS m INNER JOIN `#__menu` AS p ON p.id = m.parent_id WHERE p.title = "Menu_Item_Root" AND m.title = "Dashboard"';
        $id = $this->toolsHelper->getValue($sql);
        if ($id > 0) {
            $sql = 'UPDATE `#__menu` SET title = "RA Dashboard" WHERE id=' . $id;
            echo "sql = $sql<br>";
            echo "id = $id<br>";
            $this->toolsHelper->executeCommand($sql);
        }
////////////////////////////////////////////////////////////////////////////////
        $target = "administrator/index.php?option=com_ra_tools&view=reports";
        echo $this->toolsHelper->backButton($target);
    }


    public function showHitCounters() {
        if (!$this->toolsHelper->isSuperuser()) {
            Factory::getApplication()->enqueueMessage('Access only permitted for Superusers', 'warning');
            $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
            return;
        }

        ToolBarHelper::title($this->prefix . 'Top Article Hit Counters');
        echo $this->breadcrumbs . $this->breadcrumbsExtra('Article hit counters', 'showHitCounters') . '<br>';

        $sql = 'SELECT id, title, hits, state ';
        $sql .= 'FROM #__content ';
        $sql .= 'ORDER BY hits DESC, title ';
        $sql .= 'LIMIT 10';

        $rows = $this->toolsHelper->getRows($sql);

        if (count($rows) == 0) {
            echo 'No Articles found.<br>';
        } else {
            $objTable = new ToolsTable;
            $objTable->add_header('id,Article,Hits,Status');

            foreach ($rows as $row) {
                $edit = 'administrator/index.php?option=com_content&task=article.edit&id=' . $row->id;

                $objTable->add_item($row->id);
                $objTable->add_item($this->toolsHelper->buildLink($edit, $row->title));
                $objTable->add_item(number_format($row->hits));
                $objTable->add_item($row->state == 1 ? 'Published' : 'Unpublished');
                $objTable->generate_line();
            }

            $objTable->generate_table();
        }

        $target = 'administrator/index.php?option=com_ra_tools&task=system.resetHitCounters';
        echo $this->toolsHelper->buildButton($target, 'Reset all hit counters', false, 'sunrise');
        echo '&nbsp;';
        echo $this->toolsHelper->backButton($this->back);
    }

    public function showPaths() {
        ToolBarHelper::title('Reports: Paths ');
        /*
         * Should find any menu entries for routes, geofiles etc and check that they too exist
         */
//      Show link that allows page to be printed
        $target = 'index.php?option=com_ra_tools&task=reports.showPaths';
        echo $this->toolsHelper->showPrint($target) . '<br>' . PHP_EOL;
        echo '<table class="table table-striped">';
        echo ToolsHtml::addTableHeader(array("Component", "Description", "Value", "Found"));

        if (ComponentHelper::isEnabled('com_ra_tools', true)) {
            $params = ComponentHelper::getParams('com_ra_tools');

            $value = $params->get('document_library', 'not specified');
            echo ToolsHtml::addTableRow(array('com_ra_tools', 'Folder for document library', $value, $this->lookupPath($value)));

//            $value = $params->get('pdf_directory', 'not specified');
//            echo ToolsHtml::addTableRow(array('com_ra_tools', 'pdf_directory', $value, $this->lookupPath($value)));

            $value = $params->get('routes', 'not specified');
            echo ToolsHtml::addTableRow(array('com_ra_tools', 'Folder for storing GPX files', $value, $this->lookupPath($value)));
        }

        if (ComponentHelper::isEnabled('com_ra_walks', true)) {
            $params = ComponentHelper::getParams('com_ra_walks');
            $value = $params->get('walks_folder', 'not specified');
            echo ToolsHtml::addTableRow(array('com_ra_walks', 'walks_folder', $value, $this->lookupPath($value)));
        }
        if (ComponentHelper::isEnabled('com_ra_wf', true)) {

        }
        if (ComponentHelper::isEnabled('com_ra_wg', true)) {
            $value = $params->get('com_ra_wg', 'not specified');
            echo ToolsHtml::addTableRow(array('com_ra_wg', 'Folder for storing GPX files', $value, $this->lookupPath($value)));
        }

        echo "</table>" . PHP_EOL;
        if ((JDEBUG) AND ($this->toolsHelper->isSuperuser())) {
            $sql = 'SELECT extension_id from #__extensions WHERE element="com_ra_tools"';
//$extension_id = $toolsHelper->getValue($sql);
//$sql = 'SELECT version_id from #__schemas WHERE extension_id=' . $extension_id;
//echo 'Seeking  ' . $sql . '<br>';
//$version = $toolsHelper->getValue($sql) . '<br>';
//echo 'Version of database schema is ' . $version . PHP_EOL;

            echo 'Version of PHP is <b>' . phpversion() . '</b>, ini file is <b>' . php_ini_loaded_file() . '</b><br>' . PHP_EOL;
            echo '<br>';
            echo 'JPATH ROOT=' . JPATH_ROOT . '<br>';
            echo 'JPATH BASE=' . JPATH_BASE . '<br>';
            echo 'JPATH_LIBRARIES=' . JPATH_LIBRARIES . '<br>';
            echo 'JPATH COMPONENT=' . JPATH_COMPONENT . '<br>';
            echo 'JPATH JPATH_COMPONENT_ADMINISTRATOR=' . JPATH_COMPONENT_ADMINISTRATOR . '<br>';
            echo 'Templates folder=' . JPATH_THEMES . '<br>';
            $uri = Uri::getInstance();

//            echo 'Juri::base=' . Juri::base() . '<br>';
//    echo 'JPATH CACHE=' . JPATH_CACHE . '<br>';
            echo 'Current Url=' . $uri->toString() . '<br>';
            echo 'root(true)' . $uri::root(true) . '<br>';
            echo Factory::getApplication()->getMenu()->getActive()->route . '<br>'; // https://joomla.stackexchange.com/questions/32098/joomla-4-url-processing-routing
        }
        $target = "administrator/index.php?option=com_ra_tools&view=reports";
        echo $this->toolsHelper->backButton($target);
    }

    public function showRegistrations() {
        echo $this->breadcrumbs . '<br>';
        $field = 'registerDate';
        $table = ' #__users';
        $criteria = '';
        $title = 'User Registrations by month';
        $link = 'administrator/index.php?option=com_ra_tools&task=reports.showRegistrationsMonthly';
        $back = $this->back;
        $this->toolsHelper->showMonthMatrix($field, $table, $criteria, $title, $link, $back);
    }

    public function showRegistrationsMonthly() {
        $year = $this->objApp->input->getInt('year', '2024');
        $month = $this->objApp->input->getInt('month', '10');
        ToolBarHelper::title('Users registered ' . $month . '/' . $year);
        echo $this->breadcrumbs . $this->breadcrumbsExtra('Show Registrations', 'showRegistrations');
        $sql = 'SELECT registerDate, u.name, u.block, u.requireReset, lastvisitDate, ';
        $sql .= 'p.home_group, p.preferred_name ';
        $sql .= 'FROM `#__users` AS u ';
        $sql .= 'LEFT JOIN #__ra_profiles AS `p` ON `p`.id = u.`id` ';
        $sql .= 'WHERE YEAR(u.registerDate)="' . $year . '" AND MONTH(u.registerDate)="' . $month . '" ';
        $sql .= 'ORDER BY registerDate ';
//       echo $sql . '<br>';
        $rows = $this->toolsHelper->getRows($sql);
        $objTable = new ToolsTable;
        $objTable->add_header('Registered,Group,Name,Preferred name,Status,Reset?,Last login');
        $target = 'administrator/index.php?option=com_ra_tools&task=reports.showUsersForGroup&group=';
        foreach ($rows as $row) {
            $objTable->add_item($row->registerDate);
            $objTable->add_item($row->home_group);
            $objTable->add_item($row->name);
            $objTable->add_item($row->preferred_name);
            $objTable->add_item($row->block);
            $objTable->add_item($row->requireReset);
            $objTable->add_item($row->lastvisitDate);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        $target = "administrator/index.php?option=com_ra_tools&task=reports.showRegistrations";
        echo $this->toolsHelper->backButton($target);
    }

    public function showSchema() {
        $config = Factory::getConfig();
        $database = $config->get('db');

        ToolBarHelper::title('Ramblers Reports');
        $target = 'index.php?option=com_ra_tools&task=reports.showSchema';
        ToolBarHelper::title($this->prefix . "Database schema for " . $database);
        echo $this->breadcrumbs . '<br>';
        echo $this->toolsHelper->showPrint($target);

        $schemaHelper = new SchemaHelper;
        echo $schemaHelper->showSchema();
        $back = "administrator/index.php?option=com_ra_tools&view=reports";
        echo $this->toolsHelper->backButton($back);
    }

    public function showSummary() {
        $csv = substr($this->objApp->input->getCmd('csv', ''), 0, 1);
        $group_code = $this->objApp->input->getCmd('group_code', 'NS03');
        $scope = $this->objApp->input->getCmd('scope', 'F');
        echo "<h2>Walks history for " . $this->toolsHelper->lookupGroup($group_code) . "</h2>";
        $objTable = new ToolsTable();
        if ($csv === 'Y') {
            $objTable->set_csv('Summary');
        }
        $objTable->add_header("Month, Total walks,Joint walks,Guest walks,Total leaders,Total miles, Min miles,Max miles,Avg miles");
        $sql = "SELECT ym,num_walks,joint_walks,guest_walks, ";
        $sql .= "num_leaders,total_miles,min_miles,max_miles,avg_miles ";
        $sql .= "FROM #__ra_snapshot ";
        $sql .= "WHERE group_code='" . $group_code . "' ";
        $sql .= 'ORDER BY ym ';
//        echo $sql;
        $rows = $this->toolsHelper->getRows($sql);
        $total_miles = 0;
        $total_walks = 0;
        foreach ($rows as $row) {
            $total_miles += $row->total_miles;
            $total_walks += $row->num_walks;

            $objTable->add_item($row->ym);
            if ($row->num_walks == 0) {
                $objTable->add_item('');
            } else {
                $objTable->add_item($row->num_walks);
            }
            $objTable->add_item(number_format($row->joint_walks));
            $objTable->add_item($row->guest_walks);
            $objTable->add_item($row->num_leaders);
            $objTable->add_item($row->total_miles);
            $objTable->add_item($row->min_miles);
            $objTable->add_item($row->max_miles);
            $objTable->add_item($row->avg_miles);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo 'Total walks: ' . $total_walks . ', Total miles: ' . $total_miles . '<br>';

        $back = "administrator/index.php?option=com_ra_tools&view=reports_group&group_code=" . $group_code . '&scope=' . $scope;
        echo $this->toolsHelper->backButton($back);
        if (!$csv == 'Y') {
            $target = "administrator/index.php?option=com_ra_tools&task=reports.showSummary&csv=Y&group_code=" . $group_code;
            echo $this->toolsHelper->buildLink($target, "Extract as CSV", False, "btn btn-small button-new");
        }
    }

    function showTable($start=0, $limit=10) {
// display given number of records from the specified table
        $table = $this->objApp->input->getCmd('table', '');
        // Following two parameters can only be supplied manually
        $limit = $this->objApp->input->getInt('limit', '10');
        $start = $this->objApp->input->getInt('start', '0');

        $config = Factory::getConfig();
        $database = $config->get('db');
        $dbPrefix = $config->get('dbprefix');
        ToolBarHelper::title($this->prefix . "$limit records from $database $table");
        echo $this->breadcrumbs . $this->breadcrumbsExtra('Database schema', 'showSchema');
        $schemaHelper = new SchemaHelper;
        echo $schemaHelper->showTable($table, $limit, $start);

        $back = "administrator/index.php?option=com_ra_tools&task=reports.showSchema";
        echo $this->toolsHelper->backButton($back);
    }

    public function showTableSchema() {
        $table = $this->objApp->input->getCmd('table', '');
        $config = Factory::getConfig();
        $database = $config->get('db');
//        $dbPrefix = $config->get('dbprefix');
        $objTable = new ToolsTable();
        ToolBarHelper::title($this->prefix . 'Schema for ' . $database . ' ' . $table);
        echo $this->breadcrumbs . $this->breadcrumbsExtra('Database schema', 'showSchema') . '<br>';
        $target = 'index.php?option=com_ra_tools&task=reports.showTableSchema&table=' . $table;
        echo $this->toolsHelper->showPrint($target);
        $schemaHelper = new SchemaHelper;
        echo $schemaHelper->showTableSchema($table);
        $back = "administrator/index.php?option=com_ra_tools&task=reports.showSchema";
        echo $this->toolsHelper->backButton($back);
    }

    public function showUsersForGroup() {
        $group = $this->objApp->input->getCmd('group', '');
// In this parameter, commas will have been replaced by underscores
        $group_codes = str_replace('_', ',', $group);
        ToolBarHelper::title('Ramblers Reports');
        $sql = "SELECT u.name AS 'Name', u.email ";
        $sql .= "from #__ra_profiles AS a ";
        $sql .= 'INNER JOIN #__users AS u ON u.id = a.id ';
        $sql .= 'WHERE a.ra_group_code ' . $this->toolsHelper->buildGroups($group_codes);
        $rows = $this->toolsHelper->getRows($sql);
//      Show link that allows page to be printed
        $target = 'index.php?option=com_ra_tools&task=reports.showUsersForGroup&group=' . $group;
        echo '<h4>Users following ' . $group_codes . '</h4>';
        echo $this->toolsHelper->showPrint($target) . '<br>' . PHP_EOL;
        $objTable = new ToolsTable;
        $objTable->add_header("Name,Email");
        foreach ($rows as $row) {
            $objTable->add_item($row->Name);
            $objTable->add_item($row->email);
            $objTable->generate_line();
        }
        $objTable->generate_table();
        echo $this->toolsHelper->backButton('administrator/index.php?option=com_ra_tools&task=reports.countUsers');
//        echo "<p>";
    }

    public function test() {
        $schemaHelper = new SchemaHelper;
        echo $schemaHelper->test();
//echo $schemaHelper->showTableSchema($table);
    }

}
