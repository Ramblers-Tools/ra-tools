<?php
/**
 * 09/11/26 CB escape output when displaying it
 */
\defined('_JEXEC') or die;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
$toolsHelper = new ToolsHelper;

$wa = $this->document->getWebAssetManager();
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
$wa->registerAndUseStyle('dashboard', 'com_ra_tools/dashboard.css');
?>
<div style="background-color: <?php echo $this->escape($this->site->colour); ?>; padding: 10px; border-radius: 5px;">
    <h2><?php echo $this->escape($this->item->title); ?></h2>
        <p><?php echo $this->escape($this->item->text); ?></p>
    <?php if (!empty($this->item->created)) : ?>
        <p><strong>Created:</strong> <?php echo $this->escape($this->item->created); ?></p>
    <?php endif; ?>
    <?php if (!empty($this->item->modified)) : ?>
        <p><strong>Modified:</strong> <?php echo $this->escape($this->item->modified); ?></p>
    <?php endif; ?>
    <p><em>(Article ID: <?php echo $this->escape($this->item->id); ?> from <a href="<?php echo $this->escape($this->site->url); ?>" target="_blank" rel="noopener noreferrer"><?php echo $this->escape($this->site->url); ?></a>)</em></p>
</div>
<?php
$back = 'administrator/index.php?option=com_ra_tools&view=standardarticles';
echo $toolsHelper->backButton($back);
