<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$itemId = (int) Factory::getApplication()->input->getInt('Itemid', 0);
$folder = (string) Factory::getApplication()->input->getString('folder', '');
$folderParam = $folder !== '' ? '&folder=' . rawurlencode($folder) : '';
$listUrl = Route::_('index.php?option=com_ra_tools&view=gpxs' . $folderParam . '&Itemid=' . $itemId, false);
$pageTitle = $this->canManage ? 'Edit GPX route' : 'Show GPX route';
?>

<div class="com-ra-tools-gpx">
    <h1><?php echo $pageTitle; ?></h1>
    <p>Directory: <?php echo $escape($this->folder); ?></p>
    <p>Current filename: <?php echo $escape($this->item['filename']); ?></p>

    <?php if ($this->canManage) : ?>
        <form action="<?php echo Route::_('index.php?option=com_ra_tools&task=gpx.save' . $folderParam . '&Itemid=' . $itemId, false); ?>"
              method="post" class="form-validate">
            <div class="mb-3">
                <label class="form-label" for="route_name">Route name</label>
                <input class="form-control" type="text" id="route_name" name="route_name"
                       value="<?php echo $escape($this->item['name']); ?>" maxlength="200" required>
            </div>
            <div class="mb-3">
                <label class="form-label" for="description">Route description</label>
                <textarea class="form-control" id="description" name="description" rows="6" maxlength="10000"><?php echo $escape($this->item['description']); ?></textarea>
            </div>
            <div class="mb-3">
                <label class="form-label" for="save_as">Filename</label>
                <input class="form-control" type="text" id="save_as" name="save_as" maxlength="124"
                       value="<?php echo $escape($this->item['filename']); ?>" required>
                <div class="form-text">Keep this filename to update the current file, or enter a different .gpx filename to create a copy.</div>
            </div>
            <button type="submit" class="btn btn-primary">Save and close</button>
            <a class="btn btn-secondary" href="<?php echo $listUrl; ?>">Cancel</a>
            <input type="hidden" name="file" value="<?php echo $escape($this->item['filename']); ?>">
            <input type="hidden" name="folder" value="<?php echo $escape($folder); ?>">
            <input type="hidden" name="option" value="com_ra_tools">
            <input type="hidden" name="Itemid" value="<?php echo $itemId; ?>">
            <?php echo HTMLHelper::_('form.token'); ?>
        </form>
    <?php else : ?>
        <p>Login to update</p>
        <dl class="row">
            <dt class="col-sm-3">Route name</dt>
            <dd class="col-sm-9"><?php echo $escape($this->item['name']); ?></dd>
            <dt class="col-sm-3">Route description</dt>
            <dd class="col-sm-9"><?php echo nl2br($escape($this->item['description'])); ?></dd>
        </dl>
        <a class="btn btn-secondary" href="<?php echo $listUrl; ?>">Back to GPX routes</a>
    <?php endif; ?>
</div>
