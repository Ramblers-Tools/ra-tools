<?php

/**
 * @package     Ramblers.Administrator
 * @subpackage  Templates.RaToolsAdmin
 * @copyright   (C) 2026 Ramblers Tools. Structural markup derived from the Joomla Atum
 *              administrator template, (C) 2018 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\Layout\LayoutHelper;
use Joomla\CMS\Uri\Uri;

/** @var \Joomla\CMS\Document\ErrorDocument $this */

$app   = Factory::getApplication();
$input = $app->getInput();
$wa    = $this->getWebAssetManager();

$option     = $input->get('option', '');
$view       = $input->get('view', '');
$layout     = $input->get('layout', 'default');
$task       = $input->get('task', 'display');
$cpanel     = $option === 'com_cpanel';
$hiddenMenu = $app->getInput()->get('hidemainmenu');

$this->addHeadLink(HTMLHelper::_('image', 'joomla-favicon.svg', '', [], true, 1), 'icon', 'rel', ['type' => 'image/svg+xml']);
$this->addHeadLink(HTMLHelper::_('image', 'favicon.ico', '', [], true, 1), 'alternate icon', 'rel', ['type' => 'image/vnd.microsoft.icon']);

$logoBrandLarge = $this->params->get('logoBrandLarge')
    ? Uri::root(false) . htmlspecialchars($this->params->get('logoBrandLarge'), ENT_QUOTES)
    : Uri::root(false) . 'media/templates/administrator/ra_tools_admin/images/ramblers-tools-lockup-reversed.svg';
$logoBrandSmall = $this->params->get('logoBrandSmall')
    ? Uri::root(false) . htmlspecialchars($this->params->get('logoBrandSmall'), ENT_QUOTES)
    : Uri::root(false) . 'media/templates/administrator/ra_tools_admin/images/ramblers-tools-icon.svg';
$logoBrandLargeAlt = htmlspecialchars($this->params->get('logoBrandLargeAlt', 'Ramblers Tools'), ENT_COMPAT, 'UTF-8');
$logoBrandSmallAlt = htmlspecialchars($this->params->get('logoBrandSmallAlt', 'Ramblers Tools'), ENT_COMPAT, 'UTF-8');

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

// Light mode is enforced site-wide — see index.php for the full rationale.
$themeModeAttr = ' data-bs-theme="light" data-color-scheme="light"';

// The module renderer will not work properly due to incomplete Application initialisation
$renderModules = $app->getIdentity() && $app->getLanguage();

// @see html/layouts/status.php — reused unmodified from Atum
$statusModules = $renderModules ? LayoutHelper::render('status', ['modules' => 'status']) : '';
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

    <header id="header" class="header d-flex">
        <div class="header-title d-flex">
            <div class="d-flex align-items-center">
                <div class="logo">
                    <?php echo HTMLHelper::_('image', $logoBrandLarge, $logoBrandLargeAlt, ['loading' => 'eager', 'decoding' => 'async'], false, 0); ?>
                    <?php echo HTMLHelper::_('image', $logoBrandSmall, $logoBrandSmallAlt, ['class' => 'logo-collapsed', 'loading' => 'eager', 'decoding' => 'async'], false, 0); ?>
                </div>
            </div>
            <?php if ($renderModules) : ?>
                <jdoc:include type="modules" name="title" />
            <?php endif; ?>
        </div>
        <?php if ($renderModules) : ?>
            <?php echo $statusModules; ?>
        <?php endif; ?>
    </header>

    <div id="wrapper" class="d-flex wrapper<?php echo $hiddenMenu ? '0' : ''; ?>">
        <div class="container-fluid container-main">
            <?php if (!$cpanel) : ?>
                <a class="btn btn-subhead d-md-none d-lg-none d-xl-none" data-bs-toggle="collapse"
                   data-bs-target=".subhead-collapse"><?php echo Text::_('TPL_RA_TOOLS_ADMIN_TOOLBAR'); ?>
                    <span class="icon-wrench"></span></a>
                <div id="subhead" class="subhead mb-3">
                    <div id="container-collapse" class="container-collapse"></div>
                    <div class="row">
                        <div class="col-md-12">
                            <?php if ($renderModules) : ?>
                                <jdoc:include type="modules" name="toolbar" style="none" />
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            <?php endif; ?>
            <section id="content" class="content">
                <jdoc:include type="message" />
                <?php if ($renderModules) : ?>
                    <jdoc:include type="modules" name="top" style="html5" />
                <?php endif; ?>
                <div class="row">
                    <div class="col-md-12">
                        <h1><?php echo Text::_('JERROR_AN_ERROR_HAS_OCCURRED'); ?></h1>
                        <blockquote class="blockquote">
                            <span class="badge bg-secondary"><?php echo $this->error->getCode(); ?></span>
                            <?php echo htmlspecialchars($this->error->getMessage(), ENT_QUOTES, 'UTF-8'); ?>
                        </blockquote>
                        <?php if ($this->debug) : ?>
                            <div>
                                <?php echo $this->renderBacktrace(); ?>
                                <?php if ($this->error->getPrevious()) : ?>
                                    <?php $loop = true; ?>
                                    <?php $this->setError($this->_error->getPrevious()); ?>
                                    <?php while ($loop === true) : ?>
                                        <p><strong><?php echo Text::_('JERROR_LAYOUT_PREVIOUS_ERROR'); ?></strong></p>
                                        <p><?php echo htmlspecialchars($this->_error->getMessage(), ENT_QUOTES, 'UTF-8'); ?></p>
                                        <?php echo $this->renderBacktrace(); ?>
                                        <?php $loop = $this->setError($this->_error->getPrevious()); ?>
                                    <?php endwhile; ?>
                                    <?php $this->setError($this->error); ?>
                                <?php endif; ?>
                            </div>
                        <?php endif; ?>
                        <p>
                            <a href="<?php echo $this->baseurl; ?>" class="btn btn-secondary">
                                <span class="icon-dashboard" aria-hidden="true"></span>
                                <?php echo Text::_('JGLOBAL_TPL_CPANEL_LINK_TEXT'); ?></a>
                        </p>
                    </div>

                    <?php if ($renderModules && $this->countModules('bottom')) : ?>
                        <jdoc:include type="modules" name="bottom" style="html5" />
                    <?php endif; ?>
                </div>
            </section>
        </div>

        <?php if ($renderModules && !$hiddenMenu) : ?>
            <button class="navbar-toggler toggler-burger collapsed" type="button" data-bs-toggle="collapse" data-bs-target="#sidebar-wrapper" aria-controls="sidebar-wrapper" aria-expanded="false" aria-label="<?php echo Text::_('JTOGGLE_SIDEBAR_MENU'); ?>">
                <span class="navbar-toggler-icon"></span>
            </button>

            <div id="sidebar-wrapper" class="sidebar-wrapper sidebar-menu" <?php echo $hiddenMenu ? 'data-hidden="' . $hiddenMenu . '"' : ''; ?>>
                <div id="sidebarmenu">
                    <div class="sidebar-toggle item item-level-1">
                        <a id="menu-collapse" href="#" aria-label="<?php echo Text::_('JTOGGLE_SIDEBAR_MENU'); ?>">
                            <span id="menu-collapse-icon" class="icon-toggle-off icon-fw" aria-hidden="true"></span>
                            <span class="sidebar-item-title"><?php echo Text::_('JTOGGLE_SIDEBAR_MENU'); ?></span>
                        </a>
                    </div>
                    <jdoc:include type="modules" name="menu" style="none" />
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php if ($renderModules) : ?>
        <jdoc:include type="modules" name="debug" style="none" />
    <?php endif; ?>
</body>
</html>
