<?php
/**
 * @version    3.7.4
 * @package    com_ra_tools
 * @author     GitHub Copilot
 * @copyright  2026
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 */
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

HTMLHelper::_('behavior.core');
HTMLHelper::_('bootstrap.tooltip');

$toolsHelper = new ToolsHelper;
$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
$wa->registerAndUseStyle('dashboard', 'com_ra_tools/dashboard.css');

$target_display = 'administrator/index.php?option=com_ra_tools&view=standardarticle&id=';
$target_refresh = 'administrator/index.php?option=com_ra_tools&task=standardarticles.refresh';
?>
<form action="<?php echo Route::_('index.php?option=com_ra_tools&view=standardarticles'); ?>" method="post" name="adminForm" id="adminForm">
    <h1>Standard Articles</h1>
    <p>Articles retrieved from site: <?php echo $this->site_name; ?>, category ID: <?php echo $this->category_id; ?></p>

    <table class="table table-striped">
        <thead>
            <tr>
                <th>Title</th>
                <th>Remote Modified</th>
                <th>Local Modified</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php if (!empty($this->items)) : ?>
                <?php
                foreach ($this->items as $i => $item) :
                    //           var_dump($item);
                    //           die;
                    echo '<tr class="row' . ($i % 2) . '">';
                    echo '<td>';
                    $target = $target_display . $item->id;
                    echo $this->toolsHelper->buildLink($target, $this->escape($item->title));
                    echo '</td>';
                    echo '<td>' . $this->escape($item->modified) . '</td>';

                    $local_article = $this->getLocal($item->title);
                    if ($local_article) {
                        $label = 'Overwrite';
                        $local_id = $local_article->id;
                        $modified = $local_article->modified;
                    } else {
                        $label = 'Import';
                        $local_id = 0;
                        $modified = '';
                    }

                    echo '<td>' . $modified . '</td>';
                    $target = $target_refresh . '&remote_id=' . $item->id . '&local_id=' . $local_id;
                    echo '<td>' . $this->toolsHelper->buildButton($target, $label) . '</td>';
                    ?>

                    </tr>
                <?php endforeach; ?>
            <?php else : ?>
                <tr>
                    <td colspan="4">No articles found.</td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
    <input type="hidden" name="task" value="">
    <?php echo HTMLHelper::_('form.token'); ?>
</form>
