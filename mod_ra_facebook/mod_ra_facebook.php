<?php

/**
 * @module     mod_ra_facebook
 * @author     Charlie Bigley
 * @website    https://demo.stokeandnewcastleramblers.org.uk
 * @copyleft   Copyleft 2022 Charlie Bigley webmaster@stokeandnewcastleramblers.org.uk All rights reserved.
 * @license    http://www.gnu.org/licenses/gpl.html GNU/GPL
 * 23/1012/22 CB created from mod_ra_sidebar
  24/07/23 CB rewritten for Joomla 4
 */
// No direct access to this file
defined('_JEXEC') or die;

use Joomla\CMS\Helper\ModuleHelper;

$moduleclass_sfx = htmlspecialchars((string) $params->get('moduleclass_sfx', ''), ENT_QUOTES, 'UTF-8');

require ModuleHelper::getLayoutPath('mod_ra_facebook', $params->get('layout', 'default'));
