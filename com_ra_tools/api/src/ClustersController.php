<?php
/**
 * @package     com_ra_tools
 * @subpackage  api
 * @copyright   Copyright (C) 2026. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * API controller for clusters
 * 09/11/26 CB Replaced getDbo with DatabaseInterface
 */

namespace Ramblers\Component\Ra_tools\Api\Controller;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Response\JsonResponse;

class ClustersController extends BaseController
{
    public function getList()
    {
        $db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('a.*, p.preferred_name, u.email')
            ->from($db->quoteName('#__ra_clusters', 'a'))
            ->innerJoin($db->quoteName('#__contact_details', 'c') . ' ON c.id = a.contact_id')
            ->innerJoin($db->quoteName('#__users', 'u') . ' ON u.id = c.user_id')
            ->innerJoin($db->quoteName('#__ra_profiles', 'p') . ' ON p.id = c.user_id');
        $db->setQuery($query);
        $rows = $db->loadObjectList();
        return new JsonResponse($rows);
    }
}
