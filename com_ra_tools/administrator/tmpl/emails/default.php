<?php
/**
 * 16/07/25 CB regenerated
 * 27/07/25 CB show sender_name
 * 01/08/25 CB show addressee_name
 * 08/12/25 CB seek attachments in images/com_ra_tools/emails
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

$target = 'index.php?option=com_ra_tools&task=emails.showEmail&id=';
?>

<form action="<?php echo Route::_('index.php?option=com_ra_tools&view=emails'); ?>" method="post"
      name="adminForm" id="adminForm">
    <div class="row">
        <div class="col-md-12">
            <div id="j-main-container" class="j-main-container">
                <?php echo LayoutHelper::render('joomla.searchtools.default', array('view' => $this)); ?>

                <div class="clearfix"></div>
                <table class="table table-striped" id="emailList">
                    <thead>
                        <tr>
                            <th class="w-1 text-center">
                                <input type="checkbox" autocomplete="off" class="form-check-input" name="checkall-toggle" value=""
                                       title="<?php echo Text::_('JGLOBAL_CHECK_ALL'); ?>" onclick="Joomla.checkAll(this)"/>
                            </th>

                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Title', 'a.title', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Body', 'a.body', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Attachments', 'a.attachments', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Sender', 'a.sender_name', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Addressee', 'a.addressee_name', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Date Sent', 'a.date_sent', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Sub Sys', 'a.sub_system', $listDirn, $listOrder); ?>
                            </th>
                            <th class='left'>
                                <?php echo HTMLHelper::_('searchtools.sort', 'Type', 'a.record_type', $listDirn, $listOrder); ?>
                            </th>

                            <th scope="col" class="w-3 d-none d-lg-table-cell" >
                                <?php echo HTMLHelper::_('searchtools.sort', 'Status', 'a.state', $listDirn, $listOrder); ?>					</th>
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
                                <td>
                                    <a href="<?php echo Route::_($target . (int) $item->id); ?>">
                                        <?php echo $this->escape($item->title); ?>
                                </td>
                                <?php
                                echo '<td class = "item-details">';
                                if (strlen($item->body) > 516) {
                                    echo $this->escape(strip_tags(substr($item->body, 0, 516))) . ' ....';
//        $link = '';
//        echo $this->objHelper->buildLink($link, 'Read more', true, 'readmore') . PHP_EOL;
                                } else {
                                    echo $this->escape(strip_tags(rtrim($item->body))) . PHP_EOL;
                                }
                                echo '</td>';
                                ?>
                                <td>
                                    <?php
                                    if (!empty($item->attachments)) :
                                        $attachmentsArr = explode(',', $item->attachments);
                                        foreach ($attachmentsArr as $fileSingle) :
                                            if (!is_array($fileSingle)) :
                                                $uploadPath = '/images/com_ra_tools/emails/' . rawurlencode($fileSingle);
                                                $attachmentUrl = Route::_(rtrim(Uri::root(), '/') . $uploadPath, false);
                                                echo '<a href="' . $this->escape($attachmentUrl) . '" target="_blank" title="See the attachments">' . $this->escape($fileSingle) . '</a> | ';
                                            endif;
                                        endforeach;
                                    else:
                                        echo $this->escape($item->attachments);
                                    endif;
                                    ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->sender_name); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->addressee_name); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->date_sent); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->sub_system); ?>
                                </td>
                                <td>
                                    <?php echo $this->escape($item->record_type); ?>
                                </td>
                                <td class="text-center">
                                    <?php echo HTMLHelper::_('jgrid.published', $item->state, $i, 'emails.', $canChange, 'cb'); ?>
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
