<?php
/*
* 02/11/26 CB Created
*/
defined('_JEXEC') or die;
use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

$wa = $this->document->getWebAssetManager();
$wa->useScript('keepalive')->useScript('form.validate');
HTMLHelper::_('bootstrap.tooltip');

$availableComponents = [
    'com_ra_tools' => ['field' => 'access_to', 'label' => 'RA Tools'],
];

if (ComponentHelper::isEnabled('com_ra_events', true)) {
    $availableComponents['com_ra_events'] = ['field' => 'access_ev', 'label' => 'RA Events'];   
}
if (ComponentHelper::isEnabled('com_ra_mailman', true)) {
    $availableComponents['com_ra_mailman'] = ['field' => 'access_ma', 'label' => 'RA MailMan'];   
}
if (ComponentHelper::isEnabled('com_ra_members', true)) {
    $availableComponents['com_ra_members'] = ['field' => 'access_me', 'label' => 'RA Members'];   
}
if (ComponentHelper::isEnabled('com_ra_tools', true)) {
    $availableComponents['com_ra_tools'] = ['field' => 'access_to', 'label' => 'RA Tools'];   
}
if (ComponentHelper::isEnabled('com_ra_treasurer', true)) {
    $availableComponents['com_ra_treasurer'] = ['field' => 'access_tr', 'label' => 'RA Treasurer'];   
}
$availableComponents = array_filter(
        $availableComponents,
    static fn($component, $option) => ToolsHelper::isInstalled($option),
    ARRAY_FILTER_USE_BOTH
);
?>

<form action="<?php echo Route::_('index.php?option=com_ra_tools&task=access_permission.save'); ?>"
      method="post" enctype="multipart/form-data" name="adminForm" id="access-permission-form"
      class="form-validate form-horizontal">
    <div class="row">
        <div class="col-md-12">
            <fieldset class="adminform">
                <?php echo $this->form->renderField('id'); ?>
                <?php echo $this->form->renderField('user'); ?>
                <?php foreach ($availableComponents as $component) : ?>
                    <?php echo $this->form->renderField($component['field']); ?>
                <?php endforeach; ?>
            </fieldset>
        </div>
    </div>

    <input type="hidden" name="task" value="access_permission.save" />
    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<p>Select a user and specify access for the installed Ramblers components.</p>