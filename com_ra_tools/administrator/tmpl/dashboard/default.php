<?php
/**
 * @version     3.7.4
 * @package     com_ra_tools
 * @copyright   Copyleft (C) 2021
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie <webmaster@bigley.me.uk> - https://www.stokeandnewcastleramblers.org.uk

 * 12/03/25 CB Add Events / Bookings
 * 16/03/25 CB add Events / Users
 * 25/03/25 CB add events / dataload
 * 06/04/25 CB Events / Eventtypes
 * 13/04/25 CB list Users
 * 21/04/25 CB use JsonHelper to show feed
 * 01/05/25 CB check canDo->create for showing WalksRefresh
 * 14/06/25 CB add option for Events . apisites
 * 25/08/25 show title from View
 * 28/08/25 CB use apisites from com_ra_tools, not com_ra_events
 * 09/09/25 CB remove diagnostics for events menu
 * 14/11/25 CB Mailman recipients
 * 02/02/26 CB add Clusters
 * 04/02/26 CB Add RA Develop section
 * 11/02/26 CB Restructure with grid layout and permission-based blocks - removed duplicates
 * 04/05/26 CB add block for Members
 * 07/06/26 CB show email deliveries
 * 06/07/26 CB add link to Standard Articles
 * 20/07/26 add RA SSO block
 * 20/07/26 add RA Delivery block, move List Email exceptions out of System Tools
 */
// No direct access
\defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\JsonHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

$jsonHelper = new JsonHelper;
$toolsHelper = new ToolsHelper;

$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
$wa->registerAndUseStyle('dashboard', 'com_ra_tools/dashboard.css');

$component = ComponentHelper::getComponent('com_ra_tools');
$canDo = ContentHelper::getActions('com_ra_tools');

// Build blocks array - permission checks at construction time
$blocks = [];

// ============= SYSTEM TOOLS BLOCK  =============
if ($toolsHelper->isSuperuser()) { // (SUPERUSER ONLY)
    $sysToolsItems[] = ['label' => 'Standard Articles', 'url' => 'index.php?option=com_ra_tools&view=standardarticles'];
    $sysToolsItems[] = ['label' => 'API sites', 'url' => 'index.php?option=com_ra_tools&view=apisites'];
    $sysToolsItems[] = ['label' => 'Access Configuration Wizard', 'url' => 'index.php?option=com_ra_tools&task=system.AccessWizard'];
    if ((ComponentHelper::isEnabled('com_ra_events', true)) 
        || (ComponentHelper::isEnabled('com_ra_mailman', true))) {
        $sysToolsItems[] = ['label' => 'List Users', 'url' => 'index.php?option=com_ra_tools&view=users'];
    }
    if ((ComponentHelper::isEnabled('com_ra_mailman', true))) {
        $sysToolsItems[] = ['label' => 'Process MailMan Renewals', 'url' => 'index.php?option=com_ra_mailman&task=system.checkRenewals'];    
    }
}
$sysToolsItems[] =  ['label' => 'Show your access permissions', 'url' => 'index.php?option=com_ra_tools&task=system.showAccess'];
$sysToolsItems[] = ['label' => 'System Reports', 'url' => 'index.php?option=com_ra_tools&view=reports'];




if ($canDo->get('core.admin')) {
    $versions = $toolsHelper->getVersions('com_ra_tools');
    $sysToolsItems[] = ['label' => 'Configure com_ra_tools (v' . $versions->component . ')', 'url' => 'index.php?option=com_config&view=component&component=com_ra_tools'];
    $sysToolsItems[] = ['label' => 'DB version: ' . $versions->db_version, 'url' => '#', 'disabled' => true];
}

$blocks[] = [
    'title' => 'System Tools',
    'items' => $sysToolsItems
];


// ========== MAIL MANAGER BLOCK ==========
if (ComponentHelper::isEnabled('com_ra_mailman', true)) {
    $mailmanCanDo = ContentHelper::getActions('com_ra_mailman');

    $mailmanItems = [
        ['label' => 'Mailing lists', 'url' => 'index.php?option=com_ra_mailman&view=mail_lsts'],
        ['label' => 'Mailshots', 'url' => 'index.php?option=com_ra_mailman&view=mailshots'],
    ];

    if ($mailmanCanDo->get('core.create')) {
        $mailmanItems[] = ['label' => 'Subscriptions', 'url' => 'index.php?option=com_ra_mailman&view=subscriptions'];
        $mailmanItems[] = ['label' => 'Recipients', 'url' => 'index.php?option=com_ra_mailman&view=recipients'];
        $mailmanItems[] = ['label' => 'MailMan Users', 'url' => 'index.php?option=com_ra_mailman&view=profiles'];
        $mailmanItems[] = ['label' => 'Import list of members', 'url' => 'index.php?option=com_ra_mailman&view=dataload'];
        $mailmanItems[] = ['label' => 'Import Reports', 'url' => 'index.php?option=com_ra_mailman&view=import_reports'];
        $mailmanItems[] = ['label' => 'Mailman Reports', 'url' => 'index.php?option=com_ra_mailman&view=reports'];
    }

    if ($mailmanCanDo->get('core.admin')) {
        $versions = $toolsHelper->getVersions('com_ra_mailman');
        $mailmanItems[] = ['label' => 'Configure com_ra_mailman (v' . $versions->component . ')', 'url' => 'index.php?option=com_config&view=component&component=com_ra_mailman'];
        $mailmanItems[] = ['label' => 'DB version: ' . $versions->db_version, 'url' => '#', 'disabled' => true];
    }

    $blocks[] = [
        'title' => 'Mail Manager',
        'items' => $mailmanItems
    ];
}
// ========== MEMBERS BLOCK ==========

if (ComponentHelper::isEnabled('com_ra_members', true)) {
    $membersCanDo = ContentHelper::getActions('com_ra_members');
    $membersItems = [
        ['label' => 'Members', 'url' => 'index.php?option=com_ra_members&view=members'],
        ['label' => 'Areas and Groups', 'url' => 'index.php?option=com_ra_members&view=organisations'],
        ['label' => 'Roles', 'url' => 'index.php?option=com_ra_members&view=roles'],
    ];

    if ($membersCanDo->get('core.create')) {
//        $membersItems[] = ['label' => 'Recipients', 'url' => 'index.php?option=com_ra_mailman&view=recipients'];
        $membersItems[] = ['label' => 'Import list of members', 'url' => 'index.php?option=com_ra_mailman&view=dataload'];
        $membersItems[] = ['label' => 'Import Reports', 'url' => 'index.php?option=com_ra_mailman&view=import_reports'];
        $membersItems[] = ['label' => 'Membership Reports', 'url' => 'index.php?option=com_ra_members&view=reports'];
        // $membersItems[] = ['label' => 'Process Renewals', 'url' => 'index.php?option=com_ra_mailman&task=system.checkRenewals'];
    }

    if ($membersCanDo->get('core.admin')) {
        $versions = $toolsHelper->getVersions('com_ra_members');
        $membersItems[] = ['label' => 'Configure com_ra_members (v' . $versions->component . ')', 'url' => 'index.php?option=com_config&view=component&component=com_ra_members'];
        $membersItems[] = ['label' => 'DB version: ' . $versions->db_version, 'url' => '#', 'disabled' => true];
    }

    $blocks[] = [
        'title' => 'Members',
        'items' => $membersItems
    ];
}
// ========== ORGANISATION BLOCK ==========
$orgItems = [
    ['label' => 'List of Clusters', 'url' => 'index.php?option=com_ra_tools&view=clusters'],
    ['label' => 'List of Areas', 'url' => 'index.php?option=com_ra_tools&view=area_list'],
    ['label' => 'List of Groups', 'url' => 'index.php?option=com_ra_tools&view=group_list'],
];

if ($toolsHelper->isSuperuser()) {
    $target = $jsonHelper->setUrl('organisation', '');
    $orgItems[] = ['label' => 'Show Organisation feed', 'url' => $target, 'target' => '_blank'];
    $orgItems[] = ['label' => 'Refresh details of Areas', 'url' => 'index.php?option=com_ra_tools&task=area_list.refreshAreas'];
    $orgItems[] = ['label' => 'Refresh details of Groups', 'url' => 'index.php?option=com_ra_tools&task=group_list.refreshGroups'];
}

$blocks[] = [
    'title' => 'Organisation',
    'items' => $orgItems
];

// ========== EVENTS BLOCK ==========
if (ComponentHelper::isEnabled('com_ra_events', true)) {
    $versions = $toolsHelper->getVersions('com_ra_events');

    // For newer versions, use EventsHelper
    if (version_compare($versions->component, '2.1.1', 'ge')) {
        $eventsCanDo = ContentHelper::getActions('com_ra_events');

        $eventsItems = [
            ['label' => 'List of Events', 'url' => 'index.php?option=com_ra_events&view=events'],
        ];

        if ($eventsCanDo->get('core.create')) {
            $eventsItems[] = ['label' => 'List of Bookings', 'url' => 'index.php?option=com_ra_events&view=bookings'];
            $eventsItems[] = ['label' => 'Event Reports', 'url' => 'index.php?option=com_ra_events&view=reports'];
            $eventsItems[] = ['label' => 'Import list of bookings', 'url' => 'index.php?option=com_ra_events&view=dataload'];
            $eventsItems[] = ['label' => 'API Sites', 'url' => 'index.php?option=com_ra_tools&view=apisites'];
        }

        if ($toolsHelper->isSuperuser()) {
            $eventsItems[] = ['label' => 'Event Types', 'url' => 'index.php?option=com_ra_events&view=eventtypes'];
        }

        if ($eventsCanDo->get('core.admin')) {
            $versions = $toolsHelper->getVersions('com_ra_events');
            $eventsItems[] = ['label' => 'Configure com_ra_events (v' . $versions->component . ')', 'url' => 'index.php?option=com_config&view=component&component=com_ra_events'];
            $eventsItems[] = ['label' => 'DB version: ' . $versions->db_version, 'url' => '#', 'disabled' => true];
        }

        $blocks[] = [
            'title' => 'Events',
            'items' => $eventsItems
        ];
    } else {
        // Fallback for older versions
        $eventsCanDo = ContentHelper::getActions('com_ra_events');

        $eventsItems = [
            ['label' => 'List of Events', 'url' => 'index.php?option=com_ra_events&view=events'],
        ];

        if ($eventsCanDo->get('core.create')) {
            $eventsItems[] = ['label' => 'List of Bookings', 'url' => 'index.php?option=com_ra_events&view=bookings'];
            $eventsItems[] = ['label' => 'Event Reports', 'url' => 'index.php?option=com_ra_events&view=reports'];
            $eventsItems[] = ['label' => 'Import list of bookings', 'url' => 'index.php?option=com_ra_events&view=dataload'];
            $eventsItems[] = ['label' => 'API Sites', 'url' => 'index.php?option=com_ra_tools&view=apisites'];
        }

        if ($toolsHelper->isSuperuser()) {
            $eventsItems[] = ['label' => 'Event Types', 'url' => 'index.php?option=com_ra_events&view=eventtypes'];
        }

        if ($eventsCanDo->get('core.admin')) {
            $versions = $toolsHelper->getVersions('com_ra_events');
            $eventsItems[] = ['label' => 'Configure com_ra_events (v' . $versions->component . ')', 'url' => 'index.php?option=com_config&view=component&component=com_ra_events'];
            $eventsItems[] = ['label' => 'DB version: ' . $versions->db_version, 'url' => '#', 'disabled' => true];
        }

        $blocks[] = [
            'title' => 'Events',
            'items' => $eventsItems
        ];
    }
}
// ========== RA SSO BLOCK ==========
if (ComponentHelper::isEnabled('com_ra_sso', true)) {
    $ssoItems = [
        ['label' => 'SSO Account Setup', 'url' => 'index.php?option=com_ra_sso'],
    ];

    $blocks[] = [
        'title' => 'RA SSO',
        'items' => $ssoItems
    ];
}
// ========== RA DELIVERY BLOCK ==========
if (ComponentHelper::isEnabled('com_ra_delivery', true)) {
    $deliveryCanDo = ContentHelper::getActions('com_ra_delivery');

    $deliveryItems = [
        ['label' => 'List Email exceptions', 'url' => 'index.php?option=com_ra_delivery'],
    ];

    if ($deliveryCanDo->get('core.admin')) {
        $versions = $toolsHelper->getVersions('com_ra_delivery');
        $deliveryItems[] = ['label' => 'Configure com_ra_delivery (v' . $versions->component . ')', 'url' => 'index.php?option=com_config&view=component&component=com_ra_delivery'];
        $deliveryItems[] = ['label' => 'DB version: ' . $versions->db_version, 'url' => '#', 'disabled' => true];
    }

    $blocks[] = [
        'title' => 'RA Delivery',
        'items' => $deliveryItems
    ];
}

// ========== WALKS BLOCK ==========
if (ComponentHelper::isEnabled('com_ra_walks', true)) {
    $walksCanDo = ContentHelper::getActions('com_ra_walks');

    $walksItems = [
        ['label' => 'List of Walks', 'url' => 'index.php?option=com_ra_walks&view=walks'],
        ['label' => 'Walk Reports', 'url' => 'index.php?option=com_ra_walks&view=reports'],
    ];

    // Check if walks refresh is available
    if (ComponentHelper::isEnabled('com_ra_wf', true)) {
        if (ContentHelper::getActions('com_ra_wf')->get('core.create')) {
            $walksItems[] = ['label' => 'Refresh details of Walks', 'url' => 'index.php?option=com_ra_wf&task=walks.refresh'];
        }
    } else {
        if ($walksCanDo->get('core.create')) {
            $walksItems[] = ['label' => 'Refresh details of Walks', 'url' => 'index.php?option=com_ra_walks&task=walks.refresh'];
        }
    }

    if ($walksCanDo->get('core.admin')) {
        $versions = $toolsHelper->getVersions('com_ra_walks');
        $walksItems[] = ['label' => 'Configure com_ra_walks (v' . $versions->component . ')', 'url' => 'index.php?option=com_config&view=component&component=com_ra_walks'];
        $walksItems[] = ['label' => 'DB version: ' . $versions->db_version, 'url' => '#', 'disabled' => true];
    }

    $blocks[] = [
        'title' => 'Walks',
        'items' => $walksItems
    ];
}
// ========== WALKS FOLLOW BLOCK ==========
if (ComponentHelper::isEnabled('com_ra_wf', true)) {
    $wfCanDo = ContentHelper::getActions('com_ra_wf');

    $wfItems = [
        ['label' => 'List of Walks to Follow', 'url' => 'index.php?option=com_ra_wf&view=walks'],
        ['label' => 'Walks Follow profiles', 'url' => 'index.php?option=com_ra_wf&view=profiles'],
        ['label' => 'Walks Follow Reports', 'url' => 'index.php?option=com_ra_wf&view=reports'],
    ];

    if ($wfCanDo->get('core.admin')) {
        $versions = $toolsHelper->getVersions('com_ra_wf');
        $wfItems[] = ['label' => 'Configure com_ra_wf (v' . $versions->component . ')', 'url' => 'index.php?option=com_config&view=component&component=com_ra_wf'];
        $wfItems[] = ['label' => 'DB version: ' . $versions->db_version, 'url' => '#', 'disabled' => true];
    }

    $blocks[] = [
        'title' => 'Walks Follow',
        'items' => $wfItems
    ];
}
// ========== RA DEVELOPMENT BLOCK ==========
if (ComponentHelper::isEnabled('com_ra_develop', true)) {
    $developCanDo = ContentHelper::getActions('com_ra_develop');

    $developItems = [
        ['label' => 'Summary of extensions', 'url' => 'index.php?option=com_ra_develop&task=extensions.listExtensions'],
        ['label' => 'Builds', 'url' => 'index.php?option=com_ra_develop&view=builds'],
        ['label' => 'Sub Systems', 'url' => 'index.php?option=com_ra_develop&view=subsystems'],
        ['label' => 'Extension Types', 'url' => 'index.php?option=com_ra_develop&view=extension_types'],
        ['label' => 'Extensions', 'url' => 'index.php?option=com_ra_develop&view=extensions'],
    ];

    if ($developCanDo->get('core.admin')) {
        $versions = $toolsHelper->getVersions('com_ra_develop');
        $developItems[] = ['label' => 'Configure com_ra_develop (v' . $versions->component . ')', 'url' => 'index.php?option=com_config&view=component&component=com_ra_develop'];
        $developItems[] = ['label' => 'DB version: ' . $versions->db_version, 'url' => '#', 'disabled' => true];
    }

    $blocks[] = [
        'title' => 'RA Development',
        'items' => $developItems
    ];
}


// ========== SELF GUIDED WALKS BLOCK ==========
if (ComponentHelper::isEnabled('com_ra_sg', true)) {
    $sgCanDo = ContentHelper::getActions('com_ra_sg');

    $sgItems = [
        ['label' => 'Self Guided walks', 'url' => 'index.php?option=com_ra_tools&view=sg_list'],
        ['label' => 'Categories', 'url' => 'index.php?option=com_categories&extension=com_ra_tools'],
    ];

    if ($sgCanDo->get('core.admin')) {
        $versions = $toolsHelper->getVersions('com_ra_sg');
        $sgItems[] = ['label' => 'Configure com_ra_sg (v' . $versions->component . ')', 'url' => 'index.php?option=com_config&view=component&component=com_ra_sg'];
        $sgItems[] = ['label' => 'DB version: ' . $versions->db_version, 'url' => '#', 'disabled' => true];
    }

    $blocks[] = [
        'title' => 'Self Guided Walks',
        'items' => $sgItems
    ];
}
// ========== PATH MAINTENANCE BLOCK ==========
if (ComponentHelper::isEnabled('com_ra_paths', true)) {
    $pathsCanDo = ContentHelper::getActions('com_ra_paths');

    $pathsItems = [
        ['label' => 'Fault Reports', 'url' => 'index.php?option=com_ra_paths&view=faults'],
        ['label' => 'Followups', 'url' => 'index.php?option=com_ra_paths&view=followups'],
    ];

    if ($pathsCanDo->get('core.create')) {
        $pathsItems[] = ['label' => 'Categories', 'url' => 'index.php?option=com_ra_paths&view=categories'];
        $pathsItems[] = ['label' => 'Statuses', 'url' => 'index.php?option=com_ra_paths&view=statuses'];
        $pathsItems[] = ['label' => 'Parishes', 'url' => 'index.php?option=com_ra_paths&view=parishes'];
        $pathsItems[] = ['label' => 'Boroughs', 'url' => 'index.php?option=com_ra_paths&view=boroughs'];
        $pathsItems[] = ['label' => 'Regions', 'url' => 'index.php?option=com_ra_paths&view=regions'];
        $pathsItems[] = ['label' => 'Sectors', 'url' => 'index.php?option=com_ra_paths&view=sectors'];
    }

    $pathsItems[] = ['label' => 'Enhancements', 'url' => 'index.php?option=com_ra_paths&view=enhancements'];

    if ($pathsCanDo->get('core.admin')) {
        $versions = $toolsHelper->getVersions('com_ra_paths');
        $pathsItems[] = ['label' => 'Configure com_ra_paths (v' . $versions->component . ')', 'url' => 'index.php?option=com_config&view=component&component=com_ra_paths'];
        $pathsItems[] = ['label' => 'DB version: ' . $versions->db_version, 'url' => '#', 'disabled' => true];
    }

    $blocks[] = [
        'title' => 'Path Maintenance',
        'items' => $pathsItems
    ];
}
?>

<!-- Grid-based Dashboard Layout -->
<div class="dashboard-grid">
    <?php foreach ($blocks as $block): ?>
        <div class="dashboard-block">
            <div class="block-header">
                <h3><?php echo $block['title']; ?></h3>
            </div>
            <div class="block-content">
                <ul>
                    <?php foreach ($block['items'] as $item): ?>
                        <li>
                            <?php if (isset($item['disabled']) && $item['disabled']): ?>
                                <span class="item-text"><?php echo $item['label']; ?></span>
                            <?php else: ?>
                                <a href="<?php echo $item['url']; ?>" target="<?php echo $item['target'] ?? '_self'; ?>">
                                    <?php echo $item['label']; ?>
                                </a>
                            <?php endif; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
    <?php endforeach; ?>
</div>


