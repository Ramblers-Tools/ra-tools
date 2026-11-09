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
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;

/** @var \Joomla\CMS\Document\HtmlDocument $this */

$app   = Factory::getApplication();
$input = $app->getInput();
$wa    = $this->getWebAssetManager();

// Detecting active variables
$option       = $input->get('option', '');
$view         = $input->get('view', '');
$layout       = $input->get('layout', 'default');
$task         = $input->get('task', 'display');
$cpanel       = $option === 'com_cpanel' || ($option === 'com_admin' && $view === 'help');
$hiddenMenu   = $app->getInput()->get('hidemainmenu');
$sidebarState = $input->cookie->get('atumSidebarState', '');

// Browsers support SVG favicons
$this->addHeadLink(HTMLHelper::_('image', 'joomla-favicon.svg', '', [], true, 1), 'icon', 'rel', ['type' => 'image/svg+xml']);
$this->addHeadLink(HTMLHelper::_('image', 'favicon.ico', '', [], true, 1), 'alternate icon', 'rel', ['type' => 'image/vnd.microsoft.icon']);

// Template params: branding — falls back to the approved Ramblers Tools master lockups shipped with this template
$logoBrandLarge = $this->params->get('logoBrandLarge')
    ? Uri::root(false) . htmlspecialchars($this->params->get('logoBrandLarge'), ENT_QUOTES)
    : Uri::root(false) . 'media/templates/administrator/ra_tools_admin/images/ramblers-tools-lockup-reversed.svg';
$logoBrandSmall = $this->params->get('logoBrandSmall')
    ? Uri::root(false) . htmlspecialchars($this->params->get('logoBrandSmall'), ENT_QUOTES)
    : Uri::root(false) . 'media/templates/administrator/ra_tools_admin/images/ramblers-tools-icon.svg';
$logoBrandLargeAlt = htmlspecialchars($this->params->get('logoBrandLargeAlt', 'Ramblers Tools'), ENT_COMPAT, 'UTF-8');
$logoBrandSmallAlt = htmlspecialchars($this->params->get('logoBrandSmallAlt', 'Ramblers Tools'), ENT_COMPAT, 'UTF-8');

// Environment badge and help entry point are configured per-instance, never hard-coded
$environmentLabel = htmlspecialchars($this->params->get('environmentLabel', ''), ENT_QUOTES, 'UTF-8');
$helpUrl          = $this->params->get('helpUrl', '');

// Light mode is enforced site-wide — the colour-scheme switcher is hidden
// (see the "colorScheme" template param, defaulted to "0"/disabled, which
// mod_user's own template checks before rendering the toggle) and this never
// honours a dark-mode cookie, so dark mode can't be reached even by an old
// cookie from before the switch was removed.
$themeModeAttr = ' data-bs-theme="light" data-color-scheme="light"';

require __DIR__ . '/inc/colorvars.php';

$dir = $this->direction === 'rtl' ? 'rtl' : 'ltr';
$wa->useStyle('template.ra_tools_admin.' . $dir)
    ->useStyle('template.ra_tools_admin.brand')
    ->useScript('template.ra_tools_admin')
    ->useStyle('template.active.language')
    ->useStyle('template.user')
    ->addInlineStyle($raColorVarsInlineStyle);

// Override 'template.active' asset to set correct ltr/rtl dependency
$wa->registerStyle('template.active', '', [], [], ['template.ra_tools_admin.' . $dir, 'template.ra_tools_admin.brand']);

// Prototype-only restyle of com_ra_tools's dashboard, loaded on any com_ra_tools view. This
// is a template-side visual override (targets the dashboard's existing .dashboard-grid/
// .dashboard-block class hooks) to test the look before deciding whether to pursue the real
// change in the com_ra_tools component source. No component files are modified.
// Not scoped to just the dashboard view: other views (e.g. "System reports") reuse the exact
// same .dashboard-grid/.dashboard-block markup, so the same override needs to reach them too.
if ($option === 'com_ra_tools') {
    $wa->useStyle('template.ra_tools_admin.dashboard-preview')
        ->useScript('template.ra_tools_admin.dashboard-preview');
    $this->addScriptDeclaration(
        'window.raToolsAdminDashboardPreviewBase = '
        . json_encode(Uri::root(true) . '/media/templates/administrator/ra_tools_admin/images/dashboard-preview/') . ';'
    );
}

// Set some meta data
$this->setMetaData('viewport', 'width=device-width, initial-scale=1');

// Atum's own template.js (reused for sidebar/dark-mode behaviour) looks up this exact key
Text::script('TPL_ATUM_MORE_ELEMENTS');

// @see html/layouts/status.php — reused unmodified from Atum; renders the real user-identity/status modules
$statusModules = LayoutHelper::render('status', ['modules' => 'status']);
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

<jdoc:include type="modules" name="customtop" style="none" />

<?php // Header ?>
<header id="header" class="header">
    <div class="header-inside">
        <div class="header-title d-flex">
            <div class="d-flex align-items-center">
                <?php if ($hiddenMenu || $cpanel) : ?>
                    <div class="logo <?php echo $sidebarState === 'closed' ? 'small' : ''; ?>">
                        <?php echo HTMLHelper::_('image', $logoBrandLarge, $logoBrandLargeAlt, ['loading' => 'eager', 'decoding' => 'async'], false, 0); ?>
                        <?php echo HTMLHelper::_('image', $logoBrandSmall, $logoBrandSmallAlt, ['class' => 'logo-collapsed', 'loading' => 'eager', 'decoding' => 'async'], false, 0); ?>
                    </div>
                <?php else : ?>
                    <a class="logo <?php echo $sidebarState === 'closed' ? 'small' : ''; ?>" href="<?php echo Route::_('index.php'); ?>">
                        <?php echo HTMLHelper::_('image', $logoBrandLarge, Text::_('TPL_RA_TOOLS_ADMIN_BACK_TO_CONTROL_PANEL'), ['loading' => 'eager', 'decoding' => 'async'], false, 0); ?>
                        <?php echo HTMLHelper::_('image', $logoBrandSmall, Text::_('TPL_RA_TOOLS_ADMIN_BACK_TO_CONTROL_PANEL'), ['class' => 'logo-collapsed', 'loading' => 'eager', 'decoding' => 'async'], false, 0); ?>
                    </a>
                <?php endif; ?>
            </div>
            <jdoc:include type="modules" name="title" />
        </div>
        <div class="header-meta d-flex align-items-center">
            <?php if ($environmentLabel !== '') : ?>
                <span class="ra-env-badge" role="status"><?php echo $environmentLabel; ?></span>
            <?php endif; ?>
            <?php if ($helpUrl) : ?>
                <a class="ra-help-link" href="<?php echo htmlspecialchars($helpUrl, ENT_QUOTES); ?>"><?php echo Text::_('TPL_RA_TOOLS_ADMIN_HELP_SUPPORT'); ?></a>
            <?php endif; ?>
            <?php echo $statusModules; ?>
        </div>
    </div>
</header>

<?php // Wrapper ?>
<div id="wrapper" class="d-flex wrapper<?php echo $hiddenMenu ? '0' : ''; ?> <?php echo $sidebarState; ?>">
    <?php // Sidebar ?>
    <?php if (!$hiddenMenu) : ?>
        <?php HTMLHelper::_('bootstrap.collapse', '.toggler-burger'); ?>
        <button class="navbar-toggler toggler-burger collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-wrapper" aria-controls="sidebar-wrapper" aria-expanded="false" aria-label="<?php echo Text::_('JTOGGLE_SIDEBAR_MENU'); ?>">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div id="sidebar-wrapper" class="sidebar-wrapper sidebar-menu" <?php echo $hiddenMenu ? 'data-hidden="' . $hiddenMenu . '"' : ''; ?>>
            <div id="sidebarmenu" class="sidebar-sticky">
                <div class="sidebar-toggle item item-level-1">
                    <a id="menu-collapse" href="#" aria-label="<?php echo Text::_('JTOGGLE_SIDEBAR_MENU'); ?>">
                        <span id="menu-collapse-icon" class="<?php echo $sidebarState === 'closed' ? 'icon-toggle-on' : 'icon-toggle-off'; ?> icon-fw" aria-hidden="true"></span>
                        <span class="sidebar-item-title"><?php echo Text::_('JTOGGLE_SIDEBAR_MENU'); ?></span>
                    </a>
                </div>
                <jdoc:include type="modules" name="menu" style="none" />
            </div>
        </div>
    <?php endif; ?>

    <?php // container-fluid ?>
    <div class="container-fluid container-main">
        <?php if (!$cpanel) : ?>
            <?php // Subheader ?>
            <?php HTMLHelper::_('bootstrap.collapse', '.toggler-toolbar'); ?>
            <button class="navbar-toggler toggler-toolbar toggler-burger collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#subhead-container" aria-controls="subhead-container" aria-expanded="false" aria-label="<?php echo Text::_('TPL_RA_TOOLS_ADMIN_TOOLBAR'); ?>">
                <span class="toggler-toolbar-icon"></span>
            </button>
            <div id="subhead-container" class="subhead mb-3">
                <div class="row">
                    <div class="col-md-12">
                        <jdoc:include type="modules" name="toolbar" style="none" />
                    </div>
                </div>
            </div>
        <?php endif; ?>
        <section id="content" class="content">
            <?php // Begin Content ?>
            <jdoc:include type="modules" name="top" style="html5" />
            <div class="row">
                <div class="col-md-12">
                    <main>
                        <jdoc:include type="message" />
                        <jdoc:include type="component" />
                    </main>
                </div>
                <?php if ($this->countModules('bottom')) : ?>
                    <jdoc:include type="modules" name="bottom" style="html5" />
                <?php endif; ?>
            </div>
            <?php // End Content ?>
        </section>
    </div>
</div>
<jdoc:include type="modules" name="debug" style="none" />
</body>
</html>
