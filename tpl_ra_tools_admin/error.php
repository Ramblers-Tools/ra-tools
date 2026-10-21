<?php

/**
 * @package     Ramblers.Administrator
 * @subpackage  Templates.RaToolsAdmin
 * @copyright   (C) 2026 Ramblers Tools. Structural markup derived from the Joomla Atum
 *              administrator template, (C) 2017 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 */

defined('_JEXEC') or die;

use Joomla\CMS\Factory;

/** @var \Joomla\CMS\Document\ErrorDocument $this */

// Authenticated versus guest have different displays
$user = Factory::getUser();

if ($user->guest) {
    require __DIR__ . '/error_login.php';
} else {
    require __DIR__ . '/error_full.php';
}
