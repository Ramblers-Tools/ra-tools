<?php
/**
 * 25/10/26 CB Created
 */
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$fieldId = Factory::getApplication()->input->getString('field', '');

$this->document->getWebAssetManager()
    ->useScript('modal-content-select');
?>

<script>
window.jSelectRaContact = function(id, title, fieldId) {
    if (!fieldId) {
        return;
    }

    var field = document.getElementById(fieldId);
    var fieldName = document.getElementById(fieldId + '_name');

    if (field) {
        field.value = id;
    }

    if (fieldName) {
        fieldName.value = title;
    }

    if (window.parent && window.parent.jQuery && window.parent.jQuery.fn.modal) {
        window.parent.jQuery('.modal').modal('hide');
    }
};
</script>

<form
    action="<?php echo Route::_(
        'index.php?option=com_ra_tools'
        . '&view=ra_users'
        . '&layout=modal'
        . '&tmpl=component'
        . '&field=' . urlencode($fieldId)
    ); ?>"
    method="post"
    name="adminForm"
    id="adminForm"
>
    <table class="table">
        <thead>
            <tr>
                <th>Name</th>
                <th>Preferred Name</th>
                <th>Email</th>
            </tr>
        </thead>

        <tbody>
            <?php foreach ($this->items as $user) : ?>
                <tr>
                    <td>
                        <a href="#"
                           data-content-select
                           data-id="<?php echo (int) $user->id; ?>"
                           data-title="<?php echo $this->escape($user->name); ?>"
                           data-field-id="<?php echo $this->escape($fieldId); ?>">
                            <?php echo $this->escape($user->name); ?>
                        </a>
                    </td>
                    <td>
                        <?php echo $this->escape($user->preferred_name); ?>
                    </td>
                    <td>
                        <?php echo $this->escape($user->email); ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>

    <?php echo $this->pagination->getListFooter(); ?>

    <?php echo HTMLHelper::_('form.token'); ?>
</form>

<script>
(function() {
    document.addEventListener('click', function(event) {
        var link = event.target.closest('[data-content-select]');
        if (!link) {
            return;
        }

        event.preventDefault();

        var id = link.getAttribute('data-id');
        var title = link.getAttribute('data-title');
        var fieldId = link.getAttribute('data-field-id');

        if (window.parent && window.parent.jSelectRaContact) {
            window.parent.jSelectRaContact(id, title, fieldId);
        }
    });
})();
</script>
