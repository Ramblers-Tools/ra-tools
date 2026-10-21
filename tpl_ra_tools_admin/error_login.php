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

$option = $input->get('option', '');
$view   = $input->get('view', '');
$layout = $input->get('layout', 'default');
$task   = $input->get('task', 'display');

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

$loginLogo = $this->params->get('loginLogo')
    ? Uri::root(false) . htmlspecialchars($this->params->get('loginLogo'), ENT_QUOTES)
    : Uri::root(false) . 'media/templates/administrator/ra_tools_admin/images/ramblers-tools-lockup.svg';
$loginLogoAlt = htmlspecialchars($this->params->get('loginLogoAlt', 'Ramblers Tools'), ENT_COMPAT, 'UTF-8');

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
        <?php echo $statusModules; ?>
    </header>

    <div id="wrapper" class="d-flex wrapper">
        <div class="container-fluid container-main">
            <section id="content" class="content h-100">
                <main class="d-flex justify-content-center align-items-center h-100">
                    <div id="element-box" class="card">
                        <div class="card-body">
                            <div class="main-brand d-flex align-items-center justify-content-center">
                                <?php echo HTMLHelper::_('image', $loginLogo, $loginLogoAlt, ['loading' => 'eager', 'decoding' => 'async'], false, 0); ?>
                            </div>
                            <h1 class="text-dark"><?php echo Text::_('JERROR_AN_ERROR_HAS_OCCURRED'); ?></h1>
                            <jdoc:include type="message" />
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
                        </div>
                    </div>
                </main>
            </section>
        </div>

        <div id="sidebar-wrapper" class="sidebar-wrapper">
            <div id="main-brand" class="main-brand">
                <h1><?php echo $app->get('sitename'); ?></h1>
                <a href="<?php echo Uri::root(); ?>"><?php echo Text::_('TPL_RA_TOOLS_ADMIN_LOGIN_SIDEBAR_VIEW_WEBSITE'); ?></a>
            </div>
            <div id="sidebar">
                <?php if ($renderModules) : ?>
                    <jdoc:include type="modules" name="sidebar" style="body" />
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php if ($renderModules) : ?>
        <jdoc:include type="modules" name="debug" style="none" />
    <?php endif; ?>
</body>
</html>
