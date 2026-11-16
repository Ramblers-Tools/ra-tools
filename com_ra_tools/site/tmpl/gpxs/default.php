<?php

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$escape = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$itemId = (int) Joomla\CMS\Factory::getApplication()->input->getInt('Itemid', 0);
$folderQuery = '&folder=' . rawurlencode($this->folder);
$intro = (string) $this->params->get('page_intro', '');
?>

<div class="com-ra-tools-gpxs">
    <h1><?php echo $this->canManage ? 'Edit GPX routes' : 'Show GPX routes'; ?></h1>
    <?php if (!$this->canManage) : ?>
        <p>Login to update the title and description of GPX routes.</p>
    <?php endif; ?>
    <?php if ($intro !== '') : ?>
        <div class="com-ra-tools-gpxs__intro"><?php echo $intro; ?></div>
    <?php endif; ?>
    <p>Directory: <?php echo $escape($this->folder); ?></p>

    <?php if ($this->items === []) : ?>
        <p>There are no GPX files in this directory.</p>
    <?php else : ?>
        <div class="table-responsive">
            <table class="table table-striped">
                <thead>
                    <tr>
                        <th scope="col">Filename</th>
                        <th scope="col">Route name</th>
                        <th scope="col">Start location</th>
                        <th scope="col">Distance</th>
                        <?php if ($this->canManage) : ?>
                            <th scope="col">Edit</th>
                        <?php endif; ?>
                        <th scope="col">Location</th>
                        <th scope="col">Route</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($this->items as $item) : ?>
                        <tr>
                            <td>
                                <?php if ($item['error'] === '') : ?>
                                    <a href="<?php echo Route::_('index.php?option=com_ra_tools&view=gpx&file=' . rawurlencode($item['filename']) . $folderQuery . '&Itemid=' . $itemId, false); ?>">
                                        <?php echo $escape($item['filename']); ?>
                                    </a>
                                <?php else : ?>
                                    <?php echo $escape($item['filename']); ?>
                                    <span class="text-danger" title="<?php echo $escape($item['error']); ?>">(invalid GPX)</span>
                                <?php endif; ?>
                            </td>
                            <?php 
                            echo '<td>' .  $escape($item['name']) . '</td>';                                
                            echo '<td>' .  $escape($item['start']) . '</td>';
                            echo '<td>' .  $escape($item['distance']) . '</td>';
                            if ($this->canManage) {
                                echo '<td><a href="' . Route::_('index.php?option=com_ra_tools&view=gpx&file=' . rawurlencode($item['filename']) . $folderQuery . '&Itemid=' . $itemId, false) . '">Edit</a></td>';
                            }
                            // 
                            echo '<td>' . $this->toolsHelper->showLocation(
                                $item['start_latitude'],
                                $item['start_longitude'],
                                'G'
                            ) . '</td>';
                            $target = 'index.php?option=com_ra_tools&view=misc&layout=routes&folder='
                                . rawurlencode($this->folder) . '&gpxfile=' . rawurlencode($item['filename'])
                                . '&Itemid=' . $itemId;
                            echo '<td>' .  $this->toolsHelper->buildLink($target,'Show') . '</td>';
                            ?>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>

    <?php if ($this->pagination->pagesTotal > 1) : ?>
        <nav class="com-ra-tools-gpxs__pagination" aria-label="GPX routes pages">
            <?php echo $this->pagination->getPagesLinks(); ?>
        </nav>
    <?php 
    endif; 
    $target = 'index.php?option=com_ra_tools&view=misc&layout=routes&folder='
        . rawurlencode($this->folder) . '&Itemid=' . $itemId;
    echo $this->toolsHelper->buildButton($target, 'Show on a map');
    ?>
    <?php if ($this->canManage) : ?>
        <section class="com-ra-tools-gpxs__upload">
            <h2>Upload a GPX file</h2>
            <form id="form-gpx-upload"
                  action="<?php echo Route::_('index.php?option=com_ra_tools&task=gpxs.upload&Itemid=' . $itemId, false); ?>"
                  method="post" enctype="multipart/form-data" class="form-validate form-horizontal">
                <div class="mb-3">
                    <label class="form-label" for="gpx_file">GPX file</label>
                    <input class="form-control" type="file" id="gpx_file" name="gpx_file"
                           accept=".gpx,application/gpx+xml,application/xml,text/xml" required>
                    <div class="form-text">Select a valid GPX XML file (maximum 20 MB). Existing files will not be overwritten.</div>
                </div>
                <div class="control-group">
                    <div class="controls">
                        <button type="submit" class="validate btn btn-primary">
                            <span class="fas fa-check" aria-hidden="true"></span>
                            Upload
                        </button>
                    </div>
                </div>
                <input type="hidden" name="option" value="com_ra_tools">
                <input type="hidden" name="Itemid" value="<?php echo $itemId; ?>">
                <?php echo HTMLHelper::_('form.token'); ?>
            </form>
        </section>
    <?php endif; ?>
</div>
