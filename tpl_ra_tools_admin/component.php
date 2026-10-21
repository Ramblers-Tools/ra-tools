<?php

/**
 * @package     Ramblers.Administrator
 * @subpackage  Templates.RaToolsAdmin
 * @copyright   (C) 2026 Ramblers Tools. Structural markup derived from the Joomla Atum
 *              administrator template, (C) 2012 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;

/** @var \Joomla\CMS\Document\HtmlDocument $this */

$app = Factory::getApplication();
$wa  = $this->getWebAssetManager();

require __DIR__ . '/inc/colorvars.php';

$dir = $this->direction === 'rtl' ? 'rtl' : 'ltr';
$wa->useStyle('template.ra_tools_admin.' . $dir)
    ->useStyle('template.ra_tools_admin.brand')
    ->useStyle('template.active.language')
    ->useStyle('template.user')
    ->addInlineStyle($raColorVarsInlineStyle);

// No template.js for modals
$wa->disableScript('template.ra_tools_admin');

$wa->registerStyle('template.active', '', [], [], ['template.ra_tools_admin.' . $dir, 'template.ra_tools_admin.brand']);

$this->addHeadLink(HTMLHelper::_('image', 'joomla-favicon.svg', '', [], true, 1), 'icon', 'rel', ['type' => 'image/svg+xml']);
$this->addHeadLink(HTMLHelper::_('image', 'favicon.ico', '', [], true, 1), 'alternate icon', 'rel', ['type' => 'image/vnd.microsoft.icon']);

// Light mode is enforced site-wide — see index.php for the full rationale.
$themeModeAttr = ' data-bs-theme="light" data-color-scheme="light"';
?>

<!DOCTYPE html>
<html lang="<?php echo $this->language; ?>" dir="<?php echo $this->direction; ?>" <?php echo $themeModeAttr; ?>>

<head>
    <jdoc:include type="metas" />
    <jdoc:include type="styles" />
    <jdoc:include type="scripts" />
</head>

<body class="contentpane component">
    <jdoc:include type="message" />
    <jdoc:include type="component" />
</body>

</html>
