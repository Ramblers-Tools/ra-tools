<?php

/**
 * @package     Ramblers.Administrator
 * @subpackage  Templates.RaToolsAdmin
 * @copyright   (C) 2026 Ramblers Tools. Structural markup derived from the Joomla Atum
 *              administrator template, (C) 2016 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Uri\Uri;

/** @var \Joomla\CMS\Document\HtmlDocument $this */

$app   = Factory::getApplication();
$input = $app->getInput();
$wa    = $this->getWebAssetManager();

$option = $input->get('option', '');
$view   = $input->get('view', '');
$layout = $input->get('layout', 'default');
$task   = $input->get('task', 'display');

$this->addHeadLink(HTMLHelper::_('image', 'joomla-favicon.svg', '', [], true, 1), 'icon', 'rel', ['type' => 'image/svg+xml']);
$this->addHeadLink(HTMLHelper::_('image', 'favicon.ico', '', [], true, 1), 'alternate icon', 'rel', ['type' => 'image/vnd.microsoft.icon']);

$loginLogo = $this->params->get('loginLogo')
    ? Uri::root(false) . htmlspecialchars($this->params->get('loginLogo'), ENT_QUOTES)
    : Uri::root(false) . 'media/templates/administrator/ra_tools_admin/images/ramblers-tools-lockup.svg';
$loginLogoAlt = htmlspecialchars($this->params->get('loginLogoAlt', 'Ramblers Tools'), ENT_COMPAT, 'UTF-8');

$logoBrandLarge = $this->params->get('logoBrandLarge')
    ? Uri::root(false) . htmlspecialchars($this->params->get('logoBrandLarge'), ENT_QUOTES)
    : Uri::root(false) . 'media/templates/administrator/ra_tools_admin/images/ramblers-tools-lockup-reversed.svg';
$logoBrandLargeAlt = htmlspecialchars($this->params->get('logoBrandLargeAlt', 'Ramblers Tools'), ENT_COMPAT, 'UTF-8');

// Environment label and support text are configured per-instance; never hard-code a specific host name
$environmentLabel = htmlspecialchars($this->params->get('environmentLabel', ''), ENT_QUOTES, 'UTF-8');
$supportText      = $this->params->get('supportText', '');
$helpUrl          = $this->params->get('helpUrl', '');

// Light mode is enforced site-wide — see index.php for the full rationale.
$themeModeAttr = ' data-bs-theme="light" data-color-scheme="light"';

require __DIR__ . '/inc/colorvars.php';

$dir = $this->direction === 'rtl' ? 'rtl' : 'ltr';
$wa->useStyle('template.ra_tools_admin.' . $dir)
    ->useStyle('template.ra_tools_admin.brand')
    ->useScript('template.ra_tools_admin')
    ->useStyle('template.active.language')
    ->useStyle('template.user')
    ->addInlineStyle($raColorVarsInlineStyle);

$wa->registerStyle('template.active', '', [], [], ['template.ra_tools_admin.' . $dir, 'template.ra_tools_admin.brand']);

$this->setMetaData('viewport', 'width=device-width, initial-scale=1');

Text::script('JGLOBAL_WARNCOOKIES');

HTMLHelper::_('bootstrap.dropdown');
?>
<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>"<?php echo $themeModeAttr; ?>>
<head>
    <jdoc:include type="metas" />
    <jdoc:include type="styles" />
    <jdoc:include type="scripts" />
</head>

<body class="admin <?php echo $option . ' view-' . $view . ' layout-' . $layout . ($task ? ' task-' . $task : ''); ?>">

    <noscript>
        <div class="alert alert-danger" role="alert">
            <?php echo Text::_('JGLOBAL_WARNJAVASCRIPT'); ?>
        </div>
    </noscript>

    <main class="ra-login-shell">
        <section class="ra-login-brand">
            <div class="ra-login-brand-mark">
                <?php echo HTMLHelper::_('image', $logoBrandLarge, $logoBrandLargeAlt, ['loading' => 'eager', 'decoding' => 'async'], false, 0); ?>
            </div>
            <?php if ($environmentLabel !== '') : ?>
                <span class="ra-env-badge" role="status"><?php echo $environmentLabel; ?></span>
            <?php endif; ?>
            <div class="ra-login-copy">
                <p class="ra-login-eyebrow"><?php echo Text::_('TPL_RA_TOOLS_ADMIN_ADMINISTRATION'); ?></p>
                <h1><?php echo Text::_('TPL_RA_TOOLS_ADMIN_LOGIN_HEADLINE'); ?></h1>
                <p><?php echo Text::_('TPL_RA_TOOLS_ADMIN_LOGIN_INTRO'); ?></p>
            </div>
            <?php if ($supportText || $helpUrl) : ?>
                <div class="ra-support-box">
                    <strong><?php echo Text::_('TPL_RA_TOOLS_ADMIN_NEED_HELP'); ?></strong>
                    <?php if ($supportText) : ?>
                        <span><?php echo htmlspecialchars($supportText, ENT_QUOTES, 'UTF-8'); ?></span>
                    <?php endif; ?>
                    <?php if ($helpUrl) : ?>
                        <a class="ra-help-link" href="<?php echo htmlspecialchars($helpUrl, ENT_QUOTES); ?>"><?php echo Text::_('TPL_RA_TOOLS_ADMIN_HELP_SUPPORT'); ?></a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
            <div id="sidebar-wrapper" class="sidebar-wrapper">
                <jdoc:include type="modules" name="sidebar" style="body" />
            </div>
        </section>

        <section class="ra-login-stage">
            <div class="ra-login-card">
                <div class="main-brand logo text-center">
                    <?php echo HTMLHelper::_('image', $loginLogo, $loginLogoAlt, ['loading' => 'eager', 'decoding' => 'async'], false, 0); ?>
                </div>
                <div class="login_message">
                    <jdoc:include type="message" />
                </div>
                <jdoc:include type="component" />
            </div>
        </section>
    </main>
    <jdoc:include type="modules" name="debug" style="none" />
</body>
</html>
