<?php
/**
 * Ramblers Tools app sign-in: the member isn't logged in yet. The login form posts to app.login,
 * so a wrong password comes back here, not to the full site's login page.
 * 14/11/26 RH Created
 * 15/11/26 RH The login form is on this page
 */
\defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$e = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>
<div class="ra-app-signin" style="max-width: 32rem; margin: 1.5rem auto; padding: 0 1rem;">
    <h1 style="font-size: 1.75rem;">Sign in to the Ramblers Tools app</h1>
    <p>Log in to <?php echo $e(Factory::getApplication()->get('sitename')); ?> to use your account in the app.</p>
    <?php if ($this->error) : ?>
        <div class="alert alert-warning" role="alert"><?php echo $e($this->error); ?></div>
    <?php endif; ?>
    <form method="post" action="<?php echo Route::_('index.php?option=com_ra_tools&task=app.login'); ?>">
        <?php foreach ($this->params as $field => $value) : ?>
            <input type="hidden" name="<?php echo $e($field); ?>" value="<?php echo $e($value); ?>">
        <?php endforeach; ?>
        <?php echo HTMLHelper::_('form.token'); ?>
        <div class="mb-3">
            <label class="form-label" for="ra-app-username">Username</label>
            <input class="form-control" type="text" id="ra-app-username" name="username" value="<?php echo $e($this->username); ?>"
                   autocomplete="username" autocapitalize="none" autocorrect="off" spellcheck="false" required
                   <?php echo $this->username === '' ? 'autofocus' : ''; ?>>
        </div>
        <div class="mb-3">
            <label class="form-label" for="ra-app-password">Password</label>
            <input class="form-control" type="password" id="ra-app-password" name="password" autocomplete="current-password" required
                   <?php echo $this->username !== '' ? 'autofocus' : ''; ?>>
        </div>
        <p><button type="submit" class="btn btn-primary w-100">Log in</button></p>
    </form>
    <p><a href="<?php echo $e($this->resetUrl); ?>">Forgotten your password?</a> ·
        <a href="<?php echo $e($this->remindUrl); ?>">Forgotten your username?</a></p>
    <?php if ($this->ssoUrl) : ?>
        <p class="mt-4">Or, if you use the Ramblers single sign-on:</p>
        <p><a class="btn btn-secondary w-100" href="<?php echo $e($this->ssoUrl); ?>">Log in with Single Sign On</a></p>
    <?php endif; ?>
    <p class="text-muted">The app never sees your password: you're typing it on the website.</p>
</div>
