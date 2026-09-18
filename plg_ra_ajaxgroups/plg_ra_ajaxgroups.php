<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseInterface;

final class PlgAjaxPlg_ra_ajaxgroups extends CMSPlugin
{
    public function onAjaxPlg_ra_ajaxgroups()
    {
        $area = strtoupper((string) Factory::getApplication()->input->get('area', '', 'cmd'));

        if (!preg_match('/^[A-Z0-9]{2}$/', $area)) {
            return [];
        }

        /** @var DatabaseInterface $db */
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([$db->quoteName('code'), $db->quoteName('name')])
            ->from($db->quoteName('#__ra_groups'))
            ->where($db->quoteName('code') . ' LIKE ' . $db->quote($area . '%'))
            ->order($db->quoteName('code') . ' ASC');

        $db->setQuery($query);

        return $db->loadAssocList() ?: [];
    }
}
