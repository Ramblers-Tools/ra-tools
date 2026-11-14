<?php
/*
* 25/10/26 CB created
*/
namespace Ramblers\Component\Ra_tools\Administrator\Model;

defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;
use Joomla\Database\DatabaseInterface;

class Ra_usersModel extends ListModel
{
    protected $validGroupSelection = false;

    protected function populateState($ordering = null, $direction = null)
    {
        parent::populateState('p.preferred_name', 'ASC');

        $input = Factory::getApplication()->input;
        $groupCode = strtoupper(trim($input->getString('group_code', '')));
        $fieldId = $input->getString('field', '');
        $signature = $input->getString('signature', '');
        $search = trim($input->getString('filter_search', ''));

        $this->setState('filter.group_code', $groupCode);
        $this->setState('filter.field', $fieldId);
        $this->setState('filter.signature', $signature);
        $this->setState('filter.search', $search);

        $secret = (string) Factory::getConfig()->get('secret', '');
        if ($secret === ''
                || preg_match('/^[A-Z0-9]{2,4}$/', $groupCode) !== 1
                || preg_match('/^[A-Za-z][A-Za-z0-9_-]*$/', $fieldId) !== 1
                || preg_match('/^[a-f0-9]{64}$/i', $signature) !== 1) {
            return;
        }

        $userId = (int) Factory::getApplication()->getIdentity()->id;
        $expected = hash_hmac('sha256', $groupCode . '|' . $fieldId . '|' . $userId, $secret);
        if (!hash_equals($expected, $signature)) {
            return;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
                ->select('COUNT(*)')
                ->from($db->quoteName('#__ra_groups'))
                ->where($db->quoteName('code') . ' = ' . $db->quote($groupCode));
        $db->setQuery($query);
        $this->validGroupSelection = (int) $db->loadResult() === 1;
    }

    public function hasValidGroupSelection(): bool
    {
        return $this->validGroupSelection;
    }

    protected function getListQuery()
    {
        $db = $this->getDatabase();

        $query = $db->getQuery(true)
            ->select([
                'DISTINCT u.id',
                'u.name',
                'p.preferred_name',
                'p.home_group',
                'p.membershipNo'
            ])
            ->from($db->quoteName('#__users', 'u'))
            ->innerJoin(
                $db->quoteName('#__ra_profiles', 'p')
                . ' ON p.id = u.id'
            )
            ->order($db->quoteName('p.preferred_name') . ' ASC');

        if (!$this->validGroupSelection) {
            return $query->where('1 = 0');
        }

        $query->where(
                $db->quoteName('p.home_group') . ' = ' . $db->quote($this->getState('filter.group_code'))
        );

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


