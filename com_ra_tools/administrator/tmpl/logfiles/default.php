<?php
/**
 * 01/05/25 CB created from generated code
 * 09/11/26 CB escape output when displaying it
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\HTML\HTMLHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Uri\Uri;
use \Joomla\CMS\Router\Route;
use \Joomla\CMS\Layout\LayoutHelper;
use \Joomla\CMS\Language\Text;
use Joomla\CMS\Session\Session;

HTMLHelper::_('bootstrap.tooltip');
HTMLHelper::_('behavior.multiselect');

// Import CSS
$wa = $this->document->getWebAssetManager();
$wa->useStyle('com_ra_tools.admin')
        ->useScript('com_ra_tools.admin');

$user = Factory::getApplication()->getIdentity();
$userId = $user->get('id');
$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');
$canOrder = $user->authorise('core.edit.state', 'com_ra_tools');

if (!empty($saveOrder)) {
    $saveOrderingUrl = 'index.php?option=com_ra_tools&task=logfiles.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}
?>

<form action="<?php echo Route::_('index.php?option=com_ra_tools&view=logfiles'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

                <div class="clearfix"></div>
                <table class="table table-striped" id="logfileList">
                    <thead>
                        <tr>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Date', 'a.log_date', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Sub sys', 'a.sub_system', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Record type', 'a.record_type', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Message', 'a.message', $listDirn, $listOrder); ?>
                            </th>

                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <td colspan="<?php echo isset($this->items[0]) ? count(get_object_vars($this->items[0])) : 10; ?>">
                                <?php echo $this->pagination->getListFooter(); ?>
                            </td>
                        </tr>
                    </tfoot>
                    <tbody <?php if (!empty($saveOrder)) : ?> class="js-draggable" data-url="<?php echo $this->escape($saveOrderingUrl); ?>" data-direction="<?php echo $this->escape(strtolower($listDirn)); ?>" <?php endif; ?>>
                        <?php
                        foreach ($this->items as $i => $item) :
                            $ordering = ($listOrder == 'a.ordering');
                            $canCreate = $user->authorise('core.create', 'com_ra_tools');
                            $canEdit = $user->authorise('core.edit', 'com_ra_tools');
                            $canCheckin = $user->authorise('core.manage', 'com_ra_tools');
                            $canChange = $user->authorise('core.edit.state', 'com_ra_tools');
                            ?>
                            <tr class="row<?php echo $i % 2; ?>" data-draggable-group='1' data-transition>
                                <td>
                                    <?php echo $this->escape($item->log_date); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->sub_system); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->record_type); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->message); ?>
                                </td>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <input type="hidden" name="task" value=""/>
                <input type="hidden" name="boxchecked" value="0"/>
                <input type="hidden" name="list[fullorder]" value="<?php echo $this->escape($listOrder); ?> <?php echo $this->escape($listDirn); ?>"/>
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
