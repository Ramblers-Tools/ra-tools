<?php
/** Ramblers Tools app sign-in: something isn't right with the request. 14/11/26 RH */
\defined('_JEXEC') or die;
?>
<div class="ra-app-signin" style="max-width: 32rem; margin: 1.5rem auto; padding: 0 1rem;">
    <h1 style="font-size: 1.75rem;">Couldn't sign in to the app</h1>
    <p><?php echo htmlspecialchars($this->message, ENT_QUOTES, 'UTF-8'); ?></p>
</div>
