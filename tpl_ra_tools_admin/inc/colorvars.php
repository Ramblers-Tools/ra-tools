<?php

/**
 * @package     Ramblers.Administrator
 * @subpackage  Templates.RaToolsAdmin
 * @copyright   (C) 2026 Ramblers Tools
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Atum's compiled CSS derives most surface colours (dropdowns, hover states, sidebar
 * shades) from --hue via hsl(); without it those declarations are invalid and render
 * blank. This computes the same inline :root block Atum's own PHP templates set,
 * with Ramblers forest-palette defaults instead of Atum's blue defaults.
 *
 * @var \Joomla\CMS\Document\HtmlDocument|\Joomla\CMS\Document\ErrorDocument $this
 */

defined('_JEXEC') or die;

$raLinkColor = $this->params->get('link-color', '#2f4b40');
[$raR, $raG, $raB] = sscanf($raLinkColor, '#%02x%02x%02x');

$raColorVarsInlineStyle = ':root {
    --hue: ' . $this->params->get('hue', 158) . ';
    --template-bg-light: ' . $this->params->get('bg-light', '#f6f2e9') . ';
    --template-text-dark: ' . $this->params->get('text-dark', '#22352e') . ';
    --template-text-light: ' . $this->params->get('text-light', '#fffefa') . ';
    --link-color: ' . $raLinkColor . ';
    --link-color-rgb: ' . $raR . ',' . $raG . ',' . $raB . ';
    --template-special-color: ' . $this->params->get('special-color', '#f0814f') . ';
    /* Atum hardcodes these two "info" toolbar-button variables (used by the
       Help/Options buttons) to its own default blue rather than deriving them
       from --hue like --atum-btn-primary-* correctly does — override directly. */
    --atum-btn-info-icon: ' . $raLinkColor . ';
    --atum-btn-info-bg-hvr: ' . $raLinkColor . ';
}';
