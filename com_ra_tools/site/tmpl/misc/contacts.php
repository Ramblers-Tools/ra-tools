<?php

/**
 * @version     3.3.1
 * @package     com_ra_tools
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie <webmaster@bigley.me.uk> - https://www.stokeandnewcastleramblers.org.uk
 * 07/11/24 created from contacts
 * 08/02/25 CB pass menu_id when displaying the contact form
 * 05/07/25 CB don't attempt to update email_to
 * 14/07/25 CB use Tools / Email
 * 09/11/26 CB Updates for Joomla 6 compatibility
 */
// No direct access
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

defined('_JEXEC') or die;
$objHelper = new ToolsHelper;
echo '<h2>' . $this->params->get('page_title') . '</h2>';

$app = \Joomla\CMS\Factory::getApplication();
$menu_params = $app->getMenu()->getActive()->getParams();
$category_id = $menu_params->get('category_id', 0);
$sort = $menu_params->get('sort', 0);
$intro = $menu_params->get('page_intro', '');
$show_phone = $menu_params->get('show_phone', 1);
$show_email = $menu_params->get('show_email', 1);
$show_images = $menu_params->get('show_images', 0);

//$sql = 'SELECT title FROM #__categories WHERE id=' . $category_id;
//$category = $this->toolsHelper->getValue($sql);
if (!$intro == '') {
    echo $intro;
}
$target = 'index.php?option=com_ra_tools&task=system.emailContact&Itemid=' . $this->menu_id;
$target .= '&id=';
$objTable = new ToolsTable;
$sql = 'SELECT c.id, c.email_to, c.user_id, u.id AS UserId, u.email,';
if ($sort == 'name') {
    $objTable->add_column("Name", "L");
    $objTable->add_column("Role", "L");
    $sql .= 'c.name, c.con_position ';
    $order = 'c.name';
} else {
    $sql .= 'c.con_position, c.name ';
    $objTable->add_column("Role", "L");
    $objTable->add_column("Name", "L");
    if ($sort == 'role') {
        $order = 'c.con_position, c.name';
    } else {
        $order = 'c.ordering';
    }
}
if ($show_phone == 1) {
    $objTable->add_column("Phone", "L");
    $sql .= ', c.telephone, c.mobile ';
}

$objTable->add_column("email", "L");

$objTable->generate_header();
$sql .= 'FROM #__contact_details AS c ';
$sql .= 'LEFT JOIN #__users AS u ON u.id =  c.user_id ';
$sql .= 'INNER JOIN #__categories AS cat ON cat.id =  c.catid ';
$sql .= "WHERE c.con_position IS NOT NULL ";
$sql .= 'AND c.published=1 ';
$sql .= 'AND c.catid=' . $category_id;
$sql .= ' ORDER BY ' . $order;
if (JDEBUG) {
    \Joomla\CMS\Factory::getApplication()->enqueueMessage('sql=' . $sql, 'message');
}

$rows = $this->toolsHelper->getRows($sql);
foreach ($rows as $row) {
    if ($sort == 'name') {
        $objTable->add_item($row->name);
        $objTable->add_item($row->con_position); // . ' ' . $row->UserId);
    } else {
        $objTable->add_item($row->con_position);
        $objTable->add_item($row->name);
    }
    if ($show_phone == 1) {
        $phone = $row->telephone;
        if ($phone == '') {
            $phone = $row->mobile;
        } else {
            if (!$row->mobile == '') {
                $phone .= '<br>' . $row->mobile;
            }
        }
        $objTable->add_item($phone);
    }
    if ($row->user_id == 0) {
        if ($row->email_to == '') {
            $objTable->add_item('');
        } else {
            $objTable->add_item(\Joomla\CMS\HTML\HTMLHelper::_('email.cloak', $row->email_to, 1, 'Send email', 0));
        }
    } else {
        $objTable->add_item($this->toolsHelper->buildLink($target . $row->id, "Email"));
    }
    $objTable->generate_line();
}

$objTable->generate_table();

