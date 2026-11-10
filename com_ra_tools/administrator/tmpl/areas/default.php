<?php
/**
 * 22/02/25 CB delete chair_id
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
    $saveOrderingUrl = 'index.php?option=com_ra_tools&task=areas.saveOrderAjax&tmpl=component&' . Session::getFormToken() . '=1';
    HTMLHelper::_('draggablelist.draggable');
}
?>

<form action="<?php echo Route::_('index.php?option=com_ra_tools&view=areas'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

                <div class="clearfix"></div>
                <table class="table table-striped" id="areaList">
                    <thead>
                        <tr>
                            <th class="w-1 text-center">
                                <input type="checkbox" autocomplete="off" class="form-check-input" name="checkall-toggle" value=""
                                       title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)"/>
                            </th>


                            <th  scope="col" class="w-1 text-center">
                                <?php echo HTMLHelper::_('searchtools.sort', 'JSTATUS', 'a.state', $listDirn, $listOrder); ?>
                            </th>

                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Nation', 'a.nation_id', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_TOOLS_AREAS_CODE', 'a.code', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_TOOLS_AREAS_NAME', 'a.name', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Cluster', 'a.cluster', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_TOOLS_AREAS_WEBSITE', 'a.website', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_TOOLS_AREAS_CO_URL', 'a.co_url', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_TOOLS_AREAS_CLUSTER', 'a.cluster', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_TOOLS_AREAS_LATITUDE', 'a.latitude', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_TOOLS_AREAS_LONGITUDE', 'a.longitude', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'COM_RA_TOOLS_AREAS_CREATED_BY', 'a.created_by', $listDirn, $listOrder); ?>
                            </th>

                            <th scope="col" class="w-3 d-none d-lg-table-cell" >

                                <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>					</th>
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
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                                </td>


                                <td class="text-center">
                                    <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'areas.', $canChange, 'cb'); ?>
                                </td>

                                <td>
                                    <?php echo $this->escape($item->nation_id); ?>
                                </td>
                                <td>
                                    <?php if (isset($item->checked_out) && $item->checked_out && ($canEdit || $canChange)) : ?>
                                        <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->uEditor, $item->checked_out_time, 'areas.', $canCheckin); ?>
                                    <?php endif; ?>
                                    <?php if ($canEdit) : ?>
                                        <a href="<?php echo Route::_('index.php?option=com_ra_tools&task=area.edit&id=' . (int) $item->id); ?>">
                                            <?php echo $this->escape($item->code); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo $this->escape($item->code); ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->name); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->cluster); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->website); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->co_url); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->cluster); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->latitude); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->longitude); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->created_by); ?>
                                </td>
                                <td class="d-none d-lg-table-cell">
                                    <?php echo $this->escape($item->id); ?>
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
