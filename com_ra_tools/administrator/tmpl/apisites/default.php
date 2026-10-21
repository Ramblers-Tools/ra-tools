<?php
/**
 * 30/06/25 CB show background colours
 * 27/08/25 CB correct refresh
 * 22/09/25 CB show colour in colour, show title
 * 09/03/26 CB only refresh events for active sites
 * 24/09/26 CB add LoadUsers
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
$wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');

$userId = $this->user->id;
$listOrder = $this->state->get('list.ordering');
$listDirn = $this->state->get('list.direction');
$canOrder = $this->user->authorise('core.edit.state', 'com_ra_tools');
$isSuperuser = $this->toolsHelper->isSuperuser();

$target_info = 'administrator/index.php?option=com_ra_tools&task=apisites.refreshEvents&mode=3&id=';
$target_refresh = 'administrator/index.php?option=com_ra_tools&task=apisites.refreshEvents&mode=2&id=';
$target_refresh_members = 'administrator/index.php?option=com_ra_tools&task=apisites.refreshMembers&id=';
$target_delivery_test = 'administrator/index.php?option=com_ra_tools&task=apisites.testDeliveryActivity&id=';
?>

<form action="<?php echo Route::_('index.php?option=com_ra_tools&view=apisites'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

                <div class="clearfix"></div>
                <table class="table table-striped" id="apisiteList">
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
                                <?php echo HTMLHelper::_('searchtools.sort', 'Website', 'a.url', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Title', 'a.title', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Sub system', 'a.sub_system', $listDirn, $listOrder); ?>
                            </th>

                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Colour', 'a.colour', $listDirn, $listOrder); ?>
                            </th>

                            <th scope="col" class="w-3 d-none d-lg-table-cell" >

                                <?php echo HTMLHelper::_('searchtools.sort', 'JGRID_HEADING_ID', 'a.id', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                Endpoints
                            </th>
                            <?php echo '<th><th>'; ?>
                        </tr>
                    </thead>
                    <tfoot>
                        <tr>
                            <td colspan="<?php echo isset($this->items[0]) ? count(get_object_vars($this->items[0])) : 10; ?>">
                                <?php echo $this->pagination->getListFooter(); ?>
                            </td>
                        </tr>
                    </tfoot>
                    <tbody <?php if (!empty($saveOrder)) : ?> class="js-draggable" data-url="<?php echo $saveOrderingUrl; ?>" data-direction="<?php echo strtolower($listDirn); ?>" <?php endif; ?>>
                        <?php
                        foreach ($this->items as $i => $item) :
                            $ordering = ($listOrder == 'a.ordering');
                            $canCreate = $this->user->authorise('core.create', 'com_ra_tools');
                            $canEdit = $this->user->authorise('core.edit', 'com_ra_tools');
                            $canCheckin = $this->user->authorise('core.manage', 'com_ra_tools');
                            $canChange = $this->user->authorise('core.edit.state', 'com_ra_tools');
                            ?>
                            <tr class="row<?php echo $i % 2; ?>" data-draggable-group='1' data-transition>
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('grid.id', $i, $item->id); ?>
                                </td>


                                <td class="text-center">
                                    <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'apisites.', $canChange, 'cb'); ?>
                                </td>

                                <td>
                                    <?php if (isset($item->checked_out) && $item->checked_out && ($canEdit || $canChange)) : ?>
                                        <?php echo HTMLHelper::_('jgrid.checkedout', $i, $item->uEditor, $item->checked_out_time, 'apisites.', $canCheckin); ?>
                                    <?php endif; ?>
                                    <?php if ($isSuperuser) : ?>
                                        <a href="<?php echo Route::_('index.php?option=com_ra_tools&task=apisite.edit&id=' . (int) $item->id); ?>">
                                            <?php echo $this->escape($item->url); ?>
                                        </a>
                                    <?php else : ?>
                                        <?php echo $this->escape($item->url); ?>
                                    <?php endif; ?>
                                </td>

                                <?php
                                echo '<td>' . $item->title . '</td>';
                                echo '<td>' . $item->sub_system . '</td>';
                                echo '<td style="background: ' . $item->colour . '; ">';
                                echo $item->colour . '</td>';
                                echo '<td  class="d-none d-lg-table-cell">' . $item->id . '</td>';
                                echo '<td>';
                                echo '<a href="' . Route::_('index.php?option=com_ra_tools&task=apisite.queryEndpoints&id=' . (int)$item->id) . '" class="btn btn-sm btn-info" title="Query Endpoints">';
                                echo '<i class="fas fa-plug"></i>';
                                echo '</a>';
                                echo '</td>';
                                if ($item->state ==1 AND ($item->sub_system == 'RA Events')) {
                                    echo '<td>' . $this->toolsHelper->imageButton('I', $target_info . $item->id) . '</td>';
                                    echo '<td>';
                                    echo $this->toolsHelper->buildButton($target_refresh . $item->id, 'Refresh', false, 'button-p0583');
                                    echo '</td>';
                                } elseif ($item->state == 1 AND ($item->sub_system == 'RA Delivery')) {
                                    echo '<td></td>';
                                    echo '<td>';
                                    echo $this->toolsHelper->buildButton($target_delivery_test . $item->id, 'Test', false, 'button-p0583');
                                    echo '</td>'; 
                                 } elseif ($item->state == 1 AND ($item->sub_system == 'RA Members')) {
                                    echo '<td></td>';
                                    echo '<td>';
                                    echo $this->toolsHelper->buildButton($target_refresh_members . $item->id, 'Load', false, 'button-p0583');
                                    echo '</td>';  
                                } else {
                                    echo '<td></td>';
                                    echo '<td></td>';
                                }
                                ?>

                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>

                <input type="hidden" name="task" value=""/>
                <input type="hidden" name="boxchecked" value="0"/>
                <input type="hidden" name="list[fullorder]" value="<?php echo $listOrder; ?> <?php echo $listDirn; ?>"/>
                <?php echo HTMLHelper::_('form.token'); ?>
            </div>
        </div>
    </div>
</form>
