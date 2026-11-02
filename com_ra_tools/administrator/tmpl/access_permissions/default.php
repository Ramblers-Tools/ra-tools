<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

$objHelper = new ToolsHelper;
$self = 'index.php?option=com_ra_tools&view=access_permissions';
$components = [];

foreach ([
    'ra_events_enabled' => ['option' => 'com_ra_events', 'property' => 'access_ev', 'label' => 'Events'],
    'ra_mailman_enabled' => ['option' => 'com_ra_mailman', 'property' => 'access_mm', 'label' => 'MailMan'],
    'ra_members_enabled' => ['option' => 'com_ra_members', 'property' => 'access_me', 'label' => 'Members'],
    'ra_tools_enabled' => ['option' => 'com_ra_tools', 'property' => 'access_to', 'label' => 'Tools'],
    'ra_treasurer_enabled' => ['option' => 'com_ra_treasurer', 'property' => 'access_tr', 'label' => 'Treasurer'],
] as $enabledProperty => $component) {
    if ($this->$enabledProperty) {
        $components[$component['option']] = $component;
    }
}

$renderAccessButton = static function (int $userId, string $component, bool $hasAccess): string {
    $task = $hasAccess ? 'access_permission.revoke' : 'access_permission.grant';
    $label = $hasAccess ? 'Revoke' : 'Grant';
    $colour = $hasAccess ? 'sunset' : 'forest';

    return '<button type="submit" class="btn btn-sm ' . $colour . '" data-task="' . $task . '"'
            . ' data-user-id="' . $userId . '" data-component="'
            . htmlspecialchars($component, ENT_QUOTES, 'UTF-8') . '"'
            . ' onclick="this.form.elements[\'task\'].value=this.dataset.task;'
            . 'this.form.elements[\'user_id\'].value=this.dataset.userId;'
            . 'this.form.elements[\'component\'].value=this.dataset.component;">'
            . $label . '</button>';
};
?>

<form action="<?php echo Route::_($self); ?>" method="post" name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php if (empty($this->items)) : ?>
                    <div class="alert alert-info">
                        <span class="fa fa-info-circle" aria-hidden="true"></span>
                        <span class="sr-only"><?php echo Text::_('INFO'); ?></span>
                        <?php echo Text::_('JGLOBAL_NO_MATCHING_RESULTS'); ?>
                    </div>
                <?php else : ?>
                    <table class="table" id="accessPermissionsList">
                        <thead>
                            <tr>
                                <th>Name</th>
                                <?php foreach ($components as $component) : ?>
                                    <th><?php echo htmlspecialchars($component['label'], ENT_QUOTES, 'UTF-8'); ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($this->items as $index => $item) : ?>
                                <tr class="row<?php echo $index % 2; ?>">
                                    <td><?php echo htmlspecialchars((string) $item->preferred_name, ENT_QUOTES, 'UTF-8'); ?></td>
                                    <?php foreach ($components as $option => $component) : ?>
                                        <td>
                                            <?php $hasAccess = (int) ($item->{$component['property']} ?? 0) === 1; ?>
                                            <?php echo $hasAccess ? 'Y' : 'N'; ?>
                                            <?php echo $renderAccessButton((int) $item->id, $option, $hasAccess); ?>
                                        </td>
                                    <?php endforeach; ?>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>

                <input type="hidden" name="task" id="task" value="" />
                <input type="hidden" name="user_id" id="user_id" value="0" />
                <input type="hidden" name="component" id="component" value="" />
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>

<p>This lists users with access to installed Ramblers components. Use the buttons to grant or revoke access.</p>