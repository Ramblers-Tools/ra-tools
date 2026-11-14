<?php

namespace Ramblers\Component\Ra_tools\Site\Field;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Form\Field\ListField;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\Database\DatabaseInterface;

/**
 * Selects a Joomla contact associated with an RA profile.
 */
class RacontactField extends ListField
{
    protected $type = 'racontract';

    protected function getOptions(): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select($db->quoteName('c.id', 'value'))
            ->select($db->quoteName('p.preferred_name', 'text'))
            ->from($db->quoteName('#__contact_details', 'c'))
            ->innerJoin($db->quoteName('#__ra_profiles', 'p') . ' ON ' . $db->quoteName('p.id') . ' = ' . $db->quoteName('c.user_id'))
            ->innerJoin($db->quoteName('#__users', 'u') . ' ON ' . $db->quoteName('u.id') . ' = ' . $db->quoteName('c.user_id'))
            ->order($db->quoteName('p.preferred_name') . ' ASC');
        $db->setQuery($query);

        $options = parent::getOptions();
        foreach ($db->loadObjectList() as $row) {
            $options[] = HTMLHelper::_('select.option', (int) $row->value, (string) $row->text);
        }

        return $options;
    }
}
