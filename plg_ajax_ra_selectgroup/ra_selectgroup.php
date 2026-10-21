<?php
defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Event\Plugin\AjaxEvent;
use Joomla\CMS\Plugin\CMSPlugin;
use Joomla\Database\DatabaseInterface;

final class PlgAjaxRa_selectgroup extends CMSPlugin
{
    public function onAjaxRa_selectgroup(AjaxEvent $event): void
    {
        $application = $event->getApplication();
        $area = strtoupper((string) $application->input->get('area', '', 'cmd'));

        if (!preg_match('/^[A-Z0-9]{2}$/', $area)) {
            $event->updateEventResult([]);
            return;
        }

        /** @var DatabaseInterface $db */
        $db = Factory::getContainer()->get(DatabaseInterface::class);

        $query = $db->getQuery(true)
            ->select([$db->quoteName('g.code'), $db->quoteName('g.name')])
            ->from($db->quoteName('#__ra_groups', 'g'))
            ->leftJoin($db->quoteName('#__ra_areas', 'a') . ' ON a.id = g.area_id')
            ->where('(a.code = ' . $db->quote($area) . ' OR g.code LIKE ' . $db->quote($area . '%') . ')')
            ->order($db->quoteName('g.code') . ' ASC');

        $db->setQuery($query);
        $groups = $db->loadAssocList() ?: [];

        $event->updateEventResult($groups);
    }
}
