<?php

/**
 * @version    CVS: 3.0.0
 * @package    com_ra_tools
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2024 Charlie Bigley
 * @license    GNU General Public License version 3 or later; see LICENSE.txt
 * 21/04/25 CB remove Ra_toolsHelper
 * 09/11/26 CB Replaced getDbo with DatabaseInterface
 */

namespace Ramblers\Component\Ra_tools\Administrator\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\MVC\Model\ListModel;
use \Joomla\Component\Fields\Administrator\Helper\FieldsHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\Database\ParameterType;
use \Joomla\Utilities\ArrayHelper;

/**
 * Methods supporting a list of Areas records.
 *
 * @since  3.0.0
 */
class AreasModel extends ListModel {

    /**
     * Constructor.
     *
     * @param   array  $config  An optional associative array of configuration settings.
     *
     * @see        JController
     * @since      1.6
     */
    public function __construct($config = array()) {
        if (empty($config['filter_fields'])) {
            $config['filter_fields'] = array(
                'id', 'a.id',
                'nation_id', 'a.nation_id',
                'code', 'a.code',
                'name', 'a.name',
                'details', 'a.details',
                'website', 'a.website',
                'co_url', 'a.co_url',
                'cluster', 'a.cluster',
                'chair_id', 'a.chair_id',
                'latitude', 'a.latitude',
                'longitude', 'a.longitude',
                'state', 'a.state',
                'created_by', 'a.created_by',
            );
        }

        parent::__construct($config);
    }

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @param   string  $ordering   Elements order
     * @param   string  $direction  Order direction
     *
     * @return void
     *
     * @throws Exception
     */
    protected function populateState($ordering = null, $direction = null) {
        // List state information.
        parent::populateState("a.id", "ASC");

        $context = $this->getUserStateFromRequest($this->context . '.filter.search', 'filter_search');
        $this->setState('filter.search', $context);

        // Split context into component and optional section
        if (!empty($context)) {
            $parts = FieldsHelper::extract($context);

            if ($parts) {
                $this->setState('filter.component', $parts[0]);
                $this->setState('filter.section', $parts[1]);
            }
        }
    }

    /**
     * Method to get a store id based on model configuration state.
     *
     * This is necessary because the model is used by the component and
     * different modules that might need different sets of data or different
     * ordering requirements.
     *
     * @param   string  $id  A prefix for the store id.
     *
     * @return  string A store id.
     *
     * @since   3.0.0
     */
    protected function getStoreId($id = '') {
        // Compile the store id.
        $id .= ':' . $this->getState('filter.search');
        $id .= ':' . $this->getState('filter.state');

        return parent::getStoreId($id);
    }

    /**
     * Build an SQL query to load the list data.
     *
     * @return  DatabaseQuery
     *
     * @since   3.0.0
     */
    protected function getListQuery() {
        // Create a new query object.
        $db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true);

        // Select the required fields from the table.
        $query->select(
                $this->getState(
                        'list.select', 'DISTINCT a.*'
                )
        );
        $query->from('`#__ra_areas` AS a');

        // Join over the users for the checked out user
        $query->select("uc.name AS uEditor");
        $query->join("LEFT", "#__users AS uc ON uc.id=a.checked_out");
        // Join over the foreign key 'nation_id'
        $query->select('`#__ra_nations_4029950`.`name` AS nations_fk_value_4029950');
        $query->join('LEFT', '#__ra_nations AS #__ra_nations_4029950 ON #__ra_nations_4029950.`id` = a.`nation_id`');

        // Join over the user field 'chair_id'
        $query->select('`chair_id`.name AS `chair_id`');
        $query->join('LEFT', '#__users AS `chair_id` ON `chair_id`.id = a.`chair_id`');

        // Join over the user field 'created_by'
        $query->select('`created_by`.name AS `created_by`');
        $query->join('LEFT', '#__users AS `created_by` ON `created_by`.id = a.`created_by`');

        // Filter by published state
        $published = $this->getState('filter.state');

        if (is_numeric($published)) {
            $query->where('a.state = ' . (int) $published);
        } elseif (empty($published)) {
            $query->where('(a.state IN (0, 1))');
        }

        // Filter by search in title
        $search = $this->getState('filter.search');

        if (!empty($search)) {
            if (stripos($search, 'id:') === 0) {
                $query->where('a.id = ' . (int) substr($search, 3));
            } else {
                $search = $db->Quote('%' . $db->escape($search, true) . '%');
            }
        }

        // Add the list ordering clause.
        $orderCol = $this->state->get('list.ordering', "a.id");
        $orderDirn = $this->state->get('list.direction', "ASC");

        if ($orderCol && $orderDirn) {
            $query->order($db->escape($orderCol . ' ' . $orderDirn));
        }

        return $query;
    }

    /**
     * Get an array of data items
     *
     * @return mixed Array of data items on success, false on failure.
     */
    public function getItems() {
        $items = parent::getItems();

        foreach ($items as $oneItem) {

            if (isset($oneItem->nation_id)) {
                $values = explode(',', $oneItem->nation_id);
                $textValue = array();

                foreach ($values as $value) {
                    $db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
                    $query = $db->getQuery(true);
                    $query
                            ->select('`#__ra_nations_4029950`.`name`')
                            ->from($db->quoteName('#__ra_nations', '#__ra_nations_4029950'))
                            ->where($db->quoteName('#__ra_nations_4029950.id') . ' = ' . $db->quote($db->escape($value)));

                    $db->setQuery($query);
                    $results = $db->loadObject();

                    if ($results) {
                        $textValue[] = $results->name;
                    }
                }

                $oneItem->nation_id = !empty($textValue) ? implode(', ', $textValue) : $oneItem->nation_id;
            }
        }

        return $items;
    }

}
