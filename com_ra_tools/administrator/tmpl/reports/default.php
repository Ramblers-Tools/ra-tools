<?php
/**
 * @version     3.3.14
 * @package     com_ra_tools
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie <webmaster@bigley.me.uk> - https://www.stokeandnewcastleramblers.org.uk
 * 01/12/22 CB created from com ramblers
 * 07/12/22 CB analyse Joomla users by their allocated security group
 * 12/12/22 CB showPaths
 * 19/12/22 CB add WF reports from site reports
 * 06/02/23 CB mailman report
 * 23/06/23 CB remove mailman reports again
 * 06/09/23 CB showLogfile
 * 22/01/24 CB contactsByCategory
 * 21/04/25 CB Show Events from WalksManager, User by Registration date
 * 24/04/25 CB showLogfileByDate
 * 01/05/25 CB show logfiles view
 * 18/05/25 CB duplicateName
 * 08/07/25 CB breadcrumbs
 * 15/07/25 CB show emails
 * 21/07/25 CB refer to Home Dashboard in breadcrumbs
 * 14/04/26 CB restructure formatting
 * 18/09/26 CB delete areasLatitude
 */
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\Toolbar;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

$toolsHelper = new ToolsHelper;
ToolBarHelper::title('System reports');

// Import CSS
$this->wa = $this->document->getWebAssetManager();
$this->wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
$this->wa->registerAndUseStyle('dashboard', 'com_ra_tools/dashboard.css');

$breadcrumbs = $toolsHelper->buildLink('administrator/index.php', 'Home Dashboard');
$breadcrumbs .= '>' . $toolsHelper->buildLink('administrator/index.php?option=com_ra_tools&view=dashboard', 'RA Dashboard');
echo $breadcrumbs;
echo '<h2>System reports</h2>';
$reports = [
    'Check database for integrity' => 'administrator/index.php?option=com_ra_tools&task=reports.checkDatabase',    
    'Top article hit counters' => 'administrator/index.php?option=com_ra_tools&task=reports.showHitCounters',
    'Contact By Category' => 'administrator/index.php?option=com_ra_tools&task=reports.contactsByCategory',
    'Count users by Registration date' => 'administrator/index.php?option=com_ra_tools&task=reports.showRegistrations',
    'Show Joomla User by Group' => 'administrator/index.php?option=com_ra_tools&task=reports.showJoomlaUsersByGroup',
    'Show Blocked Users' => 'administrator/index.php?option=com_ra_tools&task=reports.blockedUsers',
//    'Extract contacts' => 'administrator/index.php?option=com_ra_tools&task=reports.extractContacts',
 
    'Users with duplicate name' => 'administrator/index.php?option=com_ra_tools&task=reports.duplicateName',
    'Users requiring a password reset' => 'administrator/index.php?option=com_ra_tools&task=reports.resetUsers',  
    
    'List emails' => 'administrator/index.php?option=com_ra_tools&view=emails',
    'Extensions and versions' => 'administrator/index.php?option=com_ra_tools&task=reports.showExtensions',
    'Schema' => 'administrator/index.php?option=com_ra_tools&task=reports.showSchema',
    'Groups by bespoke description' => 'administrator/index.php?option=com_ra_tools&task=reports.showBespoke',
];

if (ComponentHelper::isEnabled('com_ra_mailman', true) OR (ComponentHelper::isEnabled('com_ra_walks', true))) {
    $reports['Show Logfile by month'] = 'administrator/index.php?option=com_ra_tools&task=reports.showLogfileByMonth';
    $reports['Search all Logfile records'] = 'administrator/index.php?option=com_ra_tools&view=logfiles';
    $reports['Show recent Logfile records'] = 'administrator/index.php?option=com_ra_tools&task=reports.showLogfile';
}

if (ComponentHelper::isEnabled('com_ra_sg', true)) {
    $reports['Summarise Self Guided walks'] = 'administrator/index.php?option=com_ra_tools&task=reports.summariseGuided';
}

$reports['Colours'] = 'administrator/index.php?option=com_ra_tools&task=reports.showColours';
$reports['Show Events from JSON feed, by Area'] = 'administrator/index.php?option=com_ra_tools&task=reports.showEvents';
$reports['Show Ramblers menus'] = 'administrator/index.php?option=com_ra_tools&task=reports.showMenus';
//echo __file__ . '<br>';
//var_dump($this->params);
?>

<form action="<?php echo Route::_('index.php?option=com_ra_tools&view=reports'); ?>" method="post" name="reportsForm" id="reportsForm">
    <div id="j-main-container" class="span10">
        <div class="clearfix"> </div>

        <div class="dashboard-grid">
            <div class="dashboard-block">
                <div class="block-header">
                    <h2>System reports</h2>
                </div>

                <div class="block-content">
                    <?php
                    echo '<ul>';
                    foreach ($reports as $caption => $task) {
                        echo '<li>' . $toolsHelper->buildLink($task, $caption) . '</li>';
                    }
                    echo '</ul>';
                    ?>
                </div>
            </div>
        </div>
        <?php
        $target = 'administrator/index.php?option=com_ra_tools&view=dashboard';
        echo $toolsHelper->backButton($target);
        ?>
        <input type="hidden" name="task" value="" />
        <?php echo JHtml::_('form.token'); ?>
    </div>
</form>
<?php
echo "<!-- End of code from ' . __file . ' -->" . PHP_EOL;
