<?php
/** The member's Ramblers Tools app sign-ins, each with a Remove button. 14/11/26 RH */
\defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Router\Route;

$e = fn($value) => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
$when = fn($utc) => $utc ? HTMLHelper::_('date', $utc, 'j M Y') : '';
$action = Route::_('index.php?option=com_ra_tools&task=app.revoke');
?>
<div class="ra-app-signins" style="max-width: 40rem; margin: 2rem auto;">
    <h1>Your Ramblers Tools app sign-ins</h1>
    <?php if (!$this->signIns) : ?>
        <p>The Ramblers Tools app isn't signed in to your account on any device.</p>
    <?php else : ?>
        <p>The app is signed in to your account on these devices. Remove one to sign it out there.</p>
        <table class="table">
            <thead><tr><th>Device</th><th>Signed in</th><th>Last used</th><th></th></tr></thead>
            <tbody>
                <?php foreach ($this->signIns as $signIn) : ?>
                    <tr>
                        <td><?php echo $e($signIn['device']); ?></td>
                        <td><?php echo $e($when($signIn['created'])); ?></td>
                        <td><?php echo $e($when($signIn['last_used'])); ?></td>
                        <td>
                            <form method="post" action="<?php echo $action; ?>">
                                <input type="hidden" name="id" value="<?php echo (int) $signIn['id']; ?>">
                                <?php echo HTMLHelper::_('form.token'); ?>
                                <button type="submit" class="btn btn-sm btn-danger">Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        <?php if (count($this->signIns) > 1) : ?>
            <form method="post" action="<?php echo $action; ?>">
                <?php echo HTMLHelper::_('form.token'); ?>
                <button type="submit" class="btn btn-danger">Sign out of the app everywhere</button>
            </form>
        <?php endif; ?>
    <?php endif; ?>
</div>
