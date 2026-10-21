<?php

/**
 * @package     Ramblers.Administrator
 * @subpackage  Templates.RaToolsAdmin
 * @copyright   (C) 2026 Ramblers Tools. Narrow override of Joomla's core mod_loginsupport
 *              template, (C) 2019 Open Source Matters, Inc.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 *
 * Adds the Ramblers Tools documentation link and drops the Joomla News link,
 * per Ramblers Tools support requirements. The forum/documentation URLs remain
 * module-configurable (System > Site Modules > Login Support); only the extra
 * Ramblers link and the removal of the News item are template-owned.
 */

defined('_JEXEC') or die;

use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;

?>
<section class="loginsupport">
    <p><?php echo Text::_('MOD_LOGINSUPPORT_HEADLINE'); ?></p>
    <ul class="list-unstyled">
        <li>
            <?php echo HTMLHelper::link(
                'https://docs.ramblers.tools',
                Text::_('TPL_RA_TOOLS_ADMIN_RAMBLERS_DOCS'),
                [
                    'target' => '_blank',
                    'rel'    => 'nofollow noopener',
                    'title'  => Text::sprintf('JBROWSERTARGET_NEW_TITLE', Text::_('TPL_RA_TOOLS_ADMIN_RAMBLERS_DOCS')),
                ]
            ); ?>
        </li>
        <li>
            <?php echo HTMLHelper::link(
                $params->get('forum_url'),
                Text::_('MOD_LOGINSUPPORT_FORUM'),
                [
                    'target' => '_blank',
                    'rel'    => 'nofollow noopener',
                    'title'  => Text::sprintf('JBROWSERTARGET_NEW_TITLE', Text::_('MOD_LOGINSUPPORT_FORUM')),
                ]
            ); ?>
        </li>
        <li>
            <?php echo HTMLHelper::link(
                $params->get('documentation_url'),
                Text::_('MOD_LOGINSUPPORT_DOCUMENTATION'),
                [
                    'target' => '_blank',
                    'rel'    => 'nofollow noopener',
                    'title'  => Text::sprintf('JBROWSERTARGET_NEW_TITLE', Text::_('MOD_LOGINSUPPORT_DOCUMENTATION')),
                ]
            ); ?>
        </li>
    </ul>
</section>
