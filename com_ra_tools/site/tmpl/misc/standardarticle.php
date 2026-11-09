<?php
/**
 * 09/11/26 CB Created
 */
\defined('_JEXEC') or die;

$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
$wa->registerAndUseStyle('dashboard', 'com_ra_tools/dashboard.css');
$id = $this->menu_params->get('id', 0);
$sql = 'Select `title`, `introtext`, created, modified ';
$sql .= 'FROM #__content WHERE id=' . $id;
$item = $this->toolsHelper->getItem($sql);
if (is_null($item)) {
    $this->app->enqueueMessage('Standard article ' . $id . '  not found');
    return;
}
?>
<div style="background-color: <?php echo $this->site->colour; ?>; padding: 10px; border-radius: 5px;">
    <h2><?php echo $item->title; ?></h2>
    <p><?php echo $item->introtext; ?></p>
    <?php if (!empty($item->created)) : ?>
        <p><strong>Created:</strong> <?php echo $item->created; ?></p>
    <?php endif; ?>
    <?php if (!empty($item->modified)) : ?>
        <p><strong>Modified:</strong> <?php echo $item->modified; ?></p>
    <?php endif; ?>

</div>


