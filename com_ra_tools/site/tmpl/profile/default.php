<?php
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$escape = static fn ($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="ra-profile">
    <h2><?php echo $escape($this->item->preferred_name ?: $this->item->name); ?></h2>
    <dl class="dl-horizontal">
        <dt>Real name</dt><dd><?php echo $escape($this->item->real_name); ?></dd>
        <dt>Email</dt><dd><?php echo $escape($this->item->user_email); ?></dd>
        <dt>Home group</dt><dd><?php echo $escape($this->item->home_group); ?></dd>
    </dl>
    <form action="<?php echo Route::_('index.php?option=com_ra_tools&task=profileform.save'); ?>" method="post" class="form-validate form-horizontal">
        <div class="control-group">
            <label class="control-label" for="preferred_name">Preferred name</label>
            <div class="controls"><input type="text" id="preferred_name" name="jform[preferred_name]" value="<?php echo $escape($this->item->preferred_name); ?>" maxlength="100" required></div>
        </div>
        <input type="hidden" name="jform[id]" value="<?php echo (int) $this->item->id; ?>">
        <input type="hidden" name="jform[home_group]" value="<?php echo $escape($this->item->home_group); ?>">
        <button type="submit" class="btn btn-primary">Save preferred name</button>
        <a class="btn btn-secondary" href="<?php echo Route::_('index.php?option=com_ra_tools&task=profileform.cancel'); ?>">Cancel</a>
        <?php echo HTMLHelper::_('form.token'); ?>
    </form>
</div>
<?php
echo '<br>';
$target = 'index.php?option=com_ra_tools&view=profile&layout=access';
echo $this->toolsHelper->buildButton($target,'View your Access permissions',false,'red');
if (ComponentHelper::isEnabled('com_ra_mailman', true)) {
    $target = 'index.php?option=com_ra_mailman&view=profile&layout=subscriptions';
    echo $this->toolsHelper->buildButton($target,'Review your mailing-list subscriptions',false,'red');
}
if (ComponentHelper::isEnabled('com_ra_events', true)) {
    $target = 'index.php?option=com_ra_events&view=profileform&layout=bookings';
    echo $this->toolsHelper->buildButton($target,'Review your Event bookings',false,'red');
}

