<?php
/**
 * @package     Joomla.Site
 * @subpackage  com_ra_tools
 * 09/11/26 CB Replaced getDbo with DatabaseInterface
 */

namespace Ramblers\Component\Ra_tools\Site\Model;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Model\ListModel;
use Joomla\CMS\Factory;

class ClustersModel extends ListModel
{
    protected function populateState($ordering = null, $direction = null)
    {
        parent::populateState($ordering, $direction);
        // Additional state population if needed
    }

    public function getListQuery()
    {
        $db    = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true);

        $query->select('c.code, c.name AS Cluster, c.contact_id, con.name, p.preferred_name')
            ->from($db->quoteName('#__ra_clusters', 'c'))
            ->leftJoin($db->quoteName('#__contact_details', 'con') . ' ON con.id = c.contact_id')
            ->leftJoin($db->quoteName('#__ra_profiles', 'p') . ' ON p.id = con.user_id')
            ->order('c.code');
        if (JDEBUG) {
            Factory::getApplication()->enqueueMessage('sql = ' . $this->_db->replacePrefix($query), 'notice');
        }
//        die($this->_db->replacePrefix($query));
        return $query;
    }
}
