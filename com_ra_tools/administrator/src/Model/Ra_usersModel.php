<?php
/*
* 25/10/26 CB created
*/
namespace Ramblers\Component\Ra_tools\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;

class Ra_usersModel extends ListModel
{
    protected function getListQuery()
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([
                'DISTINCT u.id',
                'u.name',
                'p.preferred_name',
                'u.email'
            ])
            ->from($db->quoteName('#__users', 'u'))
            ->innerJoin(
                $db->quoteName('#__ra_profiles', 'p')
                . ' ON p.id = u.id'
            )
            ->where($db->quoteName('p.home_group') . ' = ' . $db->quote('NS03'))
            ->order($db->quoteName('p.preferred_name') . ' ASC');

        $search = trim(
            (string) $this->getState('filter.search', '')
        );

        if ($search !== '') {
            $pattern = '%' . $db->escape($search, true) . '%';

            $query->where(
                '('
                . 'u.name LIKE ' . $db->quote($pattern, false)
                . ' OR p.preferred_name LIKE ' . $db->quote($pattern, false)
                . ' OR u.email LIKE ' . $db->quote($pattern, false)
                . ')'
            );
        }

        return $query;
    }
}


