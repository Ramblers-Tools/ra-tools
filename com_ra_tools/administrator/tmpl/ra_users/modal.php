<?php

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Router\Route;

$input = Factory::getApplication()->input;
$fieldId = $input->getString('field', '');
$modalId = $input->getString('modal_id', '');
$groupCode = $input->getString('group_code', '');
$signature = $input->getString('signature', '');
$search = (string) $this->state->get('filter.search', '');
$action = Route::_('index.php?option=com_ra_tools&view=ra_users&layout=modal&tmpl=component', false);
?>

<form action="<?php echo htmlspecialchars($action, ENT_QUOTES, 'UTF-8'); ?>" method="get" name="adminForm" id="adminForm">
    <input type="hidden" name="option" value="com_ra_tools">
    <input type="hidden" name="view" value="ra_users">
    <input type="hidden" name="layout" value="modal">
    <input type="hidden" name="tmpl" value="component">
    <input type="hidden" name="field" value="<?php echo $this->escape($fieldId); ?>">
    <input type="hidden" name="modal_id" value="<?php echo $this->escape($modalId); ?>">
    <input type="hidden" name="group_code" value="<?php echo $this->escape($groupCode); ?>">
    <input type="hidden" name="signature" value="<?php echo $this->escape($signature); ?>">

    <?php if (!$this->groupSelectionValid) : ?>
        <div class="alert alert-danger m-3" role="alert">This user picker has an invalid or expired group selection.</div>
    <?php else : ?>
        <div class="p-3">
            <label for="filter_search" class="form-label">Search users</label>
            <div class="input-group">
                <input type="search" name="filter_search" id="filter_search" class="form-control"
                       value="<?php echo $this->escape($search); ?>" placeholder="Name, preferred name, or email">
                <button type="submit" class="btn btn-primary">Search</button>
            </div>
            <div class="form-text">Group: <?php echo $this->escape($groupCode); ?></div>
        </div>

        <table class="table table-striped mb-0">
            <thead>
                <tr>
                    <th scope="col">Preferred name</th>
                    <th scope="col">Name</th>
                    <th scope="col">Home group</th>
                    <th scope="col">Membership No</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($this->items as $user) : ?>
                    <?php $title = trim((string) $user->preferred_name) !== '' ? $user->preferred_name : $user->name; ?>
                    <tr>
                        <td>
                            <button type="button" class="btn btn-link p-0" data-ra-user-select
                                    data-id="<?php echo (int) $user->id; ?>"
                                    data-title="<?php echo $this->escape($title); ?>"
                                    data-field-id="<?php echo $this->escape($fieldId); ?>"
                                    data-modal-id="<?php echo $this->escape($modalId); ?>">
                                <?php echo $this->escape($title); ?>
                            </button>
                        </td>
                        <td><?php echo $this->escape($user->name); ?></td>
                        <td><?php echo $this->escape($user->home_group); ?></td>
                        <td><?php echo $this->escape($user->membershipNo); ?></td>
                    </tr>
                <?php endforeach; ?>
                <?php if (!$this->items) : ?>
                    <tr><td colspan="4" class="text-muted">No users found in this group.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>

        <div class="p-3">
            <?php echo $this->pagination->getListFooter(); ?>
        </div>
    <?php endif; ?>
</form>

<script>
(function () {
    document.addEventListener('click', function (event) {
        var button = event.target.closest('[data-ra-user-select]');
        if (!button) {
            return;
        }

        event.preventDefault();
        if (window.parent && typeof window.parent.jSelectRaContact === 'function') {
            window.parent.jSelectRaContact(
                button.getAttribute('data-id'),
                button.getAttribute('data-title'),
                button.getAttribute('data-field-id'),
                button.getAttribute('data-modal-id')
            );
        }
    });
})();
</script>
