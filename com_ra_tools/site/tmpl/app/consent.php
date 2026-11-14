<?php
/** Ramblers Tools app sign-in: Allow or Cancel. Always shown, even if allowed before. 14/11/26 RH */
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$e = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="ra-app-signin" style="max-width: 32rem; margin: 1.5rem auto; padding: 0 1rem;">
    <h1 style="font-size: 1.75rem;">Allow the Ramblers Tools app?</h1>
    <p>You're logged in as <strong><?php echo $e($this->name); ?></strong>.
        <a href="<?php echo $e($this->logoutUrl); ?>">Not you?</a></p>
    <p>The app will be able to see your name and your event bookings. It can't change anything, and
        it never sees your password.</p>
    <form method="post" action="<?php echo Route::_('index.php?option=com_ra_tools&task=app.allow'); ?>">
        <?php foreach ($this->params as $field => $value) : ?>
            <input type="hidden" name="<?php echo $e($field); ?>" value="<?php echo $e($value); ?>">
        <?php endforeach; ?>
        <?php echo HTMLHelper::_('form.token'); ?>
        <p><button type="submit" name="decision" value="allow" class="btn btn-primary w-100">Allow</button></p>
        <p><button type="submit" name="decision" value="cancel" class="btn btn-secondary w-100">Cancel</button></p>
    </form>
    <p class="text-muted">You can sign the app out at any time from
        <a href="<?php echo Route::_('index.php?option=com_ra_tools&task=app.signins'); ?>">your app sign-ins</a>.</p>
</div>
