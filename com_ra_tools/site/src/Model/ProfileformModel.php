<?php

/**
 * @version    2.1.3
 * @package    com_ra_events
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 20/03/25 CB use CurrentUserInterface;
 * 04/02/26 CB correct access validation
 */

namespace Ramblers\Component\Ra_tools\Site\Model;

// No direct access.
defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use \Joomla\CMS\Factory;
use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table;
use \Joomla\CMS\MVC\Model\FormModel;
use \Joomla\CMS\Object\CMSObject;
use \Joomla\CMS\Helper\TagsHelper;
use \Joomla\CMS\User\CurrentUserInterface;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_tools\Site\Table\Ra_profilesTable;

/**
 * Ra_events model.
 *
 * @since  2.0
 */
class ProfileformModel extends FormModel implements CurrentUserInterface {

    private $item = null;

    /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @return  void
     *
     * @since   2.0
     *
     * @throws  Exception
     */
    // $user = Factory::getApplication()->getSession()->get('user');
    protected function populateState() {
        $app = Factory::getApplication('com_ra_tools');

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_ra_tools.edit.profile.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_ra_tools.edit.profile.id', $id);
        }

        $this->setState('profile.id', $id);

        // Load the parameters.
        $params = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('profile.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
    }

    /**
     * Method to get an ojbect.
     *
     * @param   integer $id The id of the object to get.
     *
     * @return  Object|boolean Object on success, false on failure.
     *
     * @throws  Exception
     */
    public function getItem($id = null) {
        if ($this->item !== null) {
            return $this->item;
        }
        $user = $this->getCurrentUser();
        $id = (int) ($id ?: $user->id);
        if (!$id || (int) $user->id !== $id) {
            return $this->item = false;
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)->select('*')->from($db->quoteName('#__ra_profiles'))
            ->where('id = ' . $id);
        $db->setQuery($query);
        return $this->item = $db->loadObject() ?: false;
    }

    /**
     * Method to get the table
     *
     * @param   string $type   Name of the Table class
     * @param   string $prefix Optional prefix for the table class name
     * @param   array  $config Optional configuration array for Table object
     *
     * @return  Table|boolean Table if found, boolean false on failure
     */
    public function getTable($type = 'ra_profiles', $prefix = 'Ra_toolsTable', $config = array()) {
        return new Ra_profilesTable(Factory::getContainer()->get(DatabaseInterface::class));
    }

    /**
     * Get an item by alias
     *
     * @param   string $alias Alias string
     *
     * @return int Element id
     */
    public function getItemIdByAlias($alias) {
        $table = $this->getTable();
        $properties = $table->getProperties();

        if (!in_array('alias', $properties)) {
            return null;
        }

        $table->load(array('alias' => $alias));
        $id = $table->id;

        return $id;
    }

    /**
     * Method to check in an item.
     *
     * @param   integer $id The id of the row to check out.
     *
     * @return  boolean True on success, false on failure.
     *
     * @since   2.0
     */
    public function checkin($id = null) {
        // Get the id.
        $id = (!empty($id)) ? $id : (int) $this->getState('profile.id');

        if ($id) {
            // Initialise the table
            $table = $this->getTable();

            // Attempt to check the row in.
            if (method_exists($table, 'checkin')) {
                if (!$table->checkin($id)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Method to check out an item for editing.
     *
     * @param   integer $id The id of the row to check out.
     *
     * @return  boolean True on success, false on failure.
     *
     * @since   2.0
     */
    public function checkout($id = null) {
        // Get the user id.
        $id = (!empty($id)) ? $id : (int) $this->getState('profile.id');

        if ($id) {
            // Initialise the table
            $table = $this->getTable();

            // Get the current user object.
            $user = $this->getCurrentUser();

            // Attempt to check the row out.
            if (method_exists($table, 'checkout')) {
                if (!$table->checkout($user->get('id'), $id)) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * Method to get the profile form.
     *
     * The base form is loaded from XML
     *
     * @param   array   $data     An optional array of data for the form to interogate.
     * @param   boolean $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return  Form    A Form object on success, false on failure
     *
     * @since   2.0
     */
    public function getForm($data = array(), $loadData = true) {

        // Get the form.
        $form = $this->loadForm('com_ra_tools.profile', 'profileform', array(
            'control' => 'jform',
            'load_data' => $loadData
                )
        );

        if (empty($form)) {
            return false;
        }
//     Set value of group_code from component default
        $params = ComponentHelper::getParams('com_ra_tools');
        $group_code = $params->get('default_group');

        $form->setFieldAttribute('home_group', 'default', $group_code);
//        die('Model ' . $this->getCurrentUser()->id);
        // If admin registration, upload field labels
        $current_user = $this->getCurrentUser()->id;
        if ($current_user > 0) {
            $form->setFieldAttribute('real_name', 'label', 'Real name');
            $form->setFieldAttribute('real_name', 'description', 'The Users actual name (will not be shown publicly)');
            $form->setFieldAttribute('preferred_name', 'description', 'Name by which the user prefers to be known');
        }
        return $form;
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  array  The default data is an empty array.
     * @since   2.0
     */
    protected function loadFormData() {
        $data = Factory::getApplication()->getUserState('com_ra_tools.edit.profile.data', array());

        if (empty($data)) {
            $data = $this->getItem();
        }

        if ($data) {


            return $data;
        }

        return array();
    }

    /**
     * Method to save the form data.
     *
     * @param   array $data The form data
     *
     * @return  bool
     *
     * @throws  Exception
     * @since   2.0
     */
    public function save($data) {
        $id = (!empty($data['id'])) ? $data['id'] : (int) $this->getState('profile.id');
        $state = (!empty($data['state'])) ? 1 : 0;
        $user = $this->getCurrentUser();

        if ($id) {
            // Check the user can edit this item
            $authorised = $user->authorise('core.edit', 'com_ra_tools') || $user->authorise('core.edit.own', 'com_ra_tools');
        } else {
            if ($user->id == 0) {
                $authorised = true;
            } else {
                // Check the user can create new items in this section
                $authorised = $user->authorise('core.create', 'com_ra_tools');
            }
        }

        if ($authorised !== true) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $table = $this->getTable();

        if (!empty($id)) {
            echo '<br>Model loading $id<br>';
            $table->load($id);
        }
        if (!$table->check($data) == true) {
            return false;
        }

        echo '<br>Model<br>';
        var_dump($data);
        echo '<br>';

        try {
            if ($table->save($data) === true) {
                return $table->id;
            } else {
                Factory::getApplication()->enqueueMessage($table->getError(), 'error');
                return false;
            }
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'error');
            return false;
        }
    }

    /**
     * Method to delete data
     *
     * @param   int $pk Item primary key
     *
     * @return  int  The id of the deleted item
     *
     * @throws  Exception
     *
     * @since   2.0
     */
    public function delete($id) {
        $user = $this->getCurrentUser();

        if (empty($id)) {
            $id = (int) $this->getState('profile.id');
        }

        if ($id == 0 || $this->getItem($id) == null) {
            throw new \Exception(Text::_('COM_RA_EVENTS_ITEM_DOESNT_EXIST'), 404);
        }

        if ($user->authorise('core.delete', 'com_ra_events') !== true) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $table = $this->getTable();

        if ($table->delete($id) !== true) {
            throw new \Exception(Text::_('JERROR_FAILED'), 501);
        }

        return $id;
    }

    /**
     * Check if data can be saved
     *
     * @return bool
     */
    public function getCanSave() {
        $table = $this->getTable();

        return $table !== false;
    }

}
