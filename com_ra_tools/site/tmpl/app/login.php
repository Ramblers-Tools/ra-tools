<?php
/** Ramblers Tools app sign-in: the member isn't logged in yet. 14/11/26 RH */
\defined('_JEXEC') or die;
?>
<div class="ra-app-signin" style="max-width: 32rem; margin: 1.5rem auto; padding: 0 1rem;">
    <h1 style="font-size: 1.75rem;">Sign in to the Ramblers Tools app</h1>
    <p>Log in to <?php echo htmlspecialchars(\Joomla\CMS\Factory::getApplication()->get('sitename'), ENT_QUOTES, 'UTF-8'); ?>
        to use your account in the app.</p>
    <p><a class="btn btn-primary w-100" href="<?php echo htmlspecialchars($this->loginUrl, ENT_QUOTES, 'UTF-8'); ?>">Log in</a></p>
    <?php if ($this->ssoUrl) : ?>
        <p><a class="btn btn-primary w-100" href="<?php echo htmlspecialchars($this->ssoUrl, ENT_QUOTES, 'UTF-8'); ?>">Log in with Single Sign On</a></p>
    <?php endif; ?>
    <p class="text-muted">The app never sees your password.</p>
</div>
