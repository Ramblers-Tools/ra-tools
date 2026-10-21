<?php

/**
 * @version    3.4.2
 * @package    com_ra_tools
 * @author     Charlie Bigley <webmaster@bigley.me.uk>
 * @copyright  2024 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 13/09/24 Created by component-generator
 * 19/09/24 CB renamed from Uploadform, delete unwanted code
 */

namespace Ramblers\Component\Ra_tools\Site\Model;

// No direct access.
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table;
use \Joomla\CMS\MVC\Model\FormModel;
use \Joomla\CMS\Object\CMSObject;
use \Joomla\CMS\Helper\TagsHelper;

/**
 * Ra_tools model.
 *
 * @since  1.0.4
 */
class UploadModel extends FormModel {

    private $item = null;

    /**
     * Check if data can be saved
     *
     * @return bool
     */
    public function getCanSave() {
        $table = $this->getTable();

        return $table !== false;
    }
    
     /**
     * Method to get the data form.
     *
     * The base form is loaded from XML
     *
     * @param   array   $data     An optional array of data for the form to interogate.
     * @param   boolean $loadData True if the form is to load its own data (default case), false if not.
     *
     * @return  Form    A Form object on success, false on failure
     *
     * @since   1.0.4
     */
    public function getForm($data = array(), $loadData = true) {
        // Get the form.
        $form = $this->loadForm('com_ra_tools.upload', 'upload', array(
            'control' => 'jform',
            'load_data' => $loadData
                )
        );

        if (empty($form)) {
            return false;
        }
        // Validation is done on the server (Model / validate) so as to give proper error message
//        $upload_mimes = Factory::getApplication()->getUserState('com_ra_tools.upload_mimes', 'text/plain');
//        $form->setFieldAttribute('csv_file', 'accept', $upload_mimes);
        return $form;
    }

    /**
     * Method to get an object.
     *
     * @param   integer $id The id of the object to get.
     *
     * @return  Object|boolean Object on success, false on failure.
     *
     * @throws  Exception
     */
    public function getItem($id = null) {

        if ($this->item === null) {
            $this->item = false;

            if (empty($id)) {
                $id = $this->getState('upload.id');
            }

            // Get a level row instance.
            $table = $this->getTable();
            $properties = $table->getProperties();
            $this->item = ArrayHelper::toObject($properties, CMSObject::class);

            if ($table !== false && $table->load($id) && !empty($table->id)) {
                $user = Factory::getApplication()->getIdentity();
                $id = $table->id;

                $canEdit = $user->authorise('core.edit', 'com_ra_tools') || $user->authorise('core.create', 'com_ra_tools');

                if (!$canEdit) {
                    throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
                }

                // Convert the Table to a clean CMSObject.
                $properties = $table->getProperties(1);
                $this->item = ArrayHelper::toObject($properties, CMSObject::class);
            }
        }

        return $this->item;
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
    public function getTable($type = 'Upload', $prefix = 'Site', $config = array()) {
        return parent::getTable($type, $prefix, $config);
    }

    /**
     * Method to get the data that should be injected in the form.
     *
     * @return  array  The default data is an empty array.
     * @since   1.0.4
     */
    protected function loadFormData() {
        $data = Factory::getApplication()->getUserState('com_ra_tools.edit.upload.data', array());

        if (empty($data)) {
            $data = $this->getItem();
        }

        if ($data) {


            return $data;
        }

        return array();
    }

        /**
     * Method to auto-populate the model state.
     *
     * Note. Calling getState in this method will result in recursion.
     *
     * @return  void
     *
     * @since   1.0.4
     *
     * @throws  Exception
     */
    protected function populateState() {
        $app = Factory::getApplication('com_ra_tools');

        // Load state from the request userState on edit or from the passed variable on default
        if (Factory::getApplication()->input->get('layout') == 'edit') {
            $id = Factory::getApplication()->getUserState('com_ra_tools.edit.upload.id');
        } else {
            $id = Factory::getApplication()->input->get('id');
            Factory::getApplication()->setUserState('com_ra_tools.edit.upload.id', $id);
        }

        $this->setState('upload.id', $id);

        // Load the parameters.
        $params = $app->getParams();
        $params_array = $params->toArray();

        if (isset($params_array['item_id'])) {
            $this->setState('upload.id', $params_array['item_id']);
        }

        $this->setState('params', $params);
    }

    /**
     * Method to save the form data.
     *
     * @param   array $data The form data
     *
     * @return  bool
     *
     * @throws  Exception
     * @since   1.0.4
     */
    public function save($data) {
        $id = (!empty($data['id'])) ? $data['id'] : (int) $this->getState('upload.id');
        $user = Factory::getApplication()->getIdentity();

        if ($id) {
            // Check the user can edit this item
            $authorised = $user->authorise('core.edit', 'com_ra_tools') || $authorised = $user->authorise('core.edit.own', 'com_ra_tools');
        } else {
            // Check the user can create new items in this section
            $authorised = $user->authorise('core.create', 'com_ra_tools');
        }

        if ($authorised !== true) {
            throw new \Exception(Text::_('JERROR_ALERTNOAUTHOR'), 403);
        }

        $table = $this->getTable();

        return $table->save($data);
    }

    public function validate($form, $data, $group = true) {
        $app = Factory::getApplication();

        // Permitted MIME types are defined in the menu entry, and saved by the View
        $MIMETypes = $app->getUserState('com_ra_tools.upload_mimes', 'text/plain');

        // following code copied from table / check

        $files = $app->input->files->get('jform', array(), 'raw');
        $array = $app->input->get('jform', array(), 'ARRAY');
        var_dump($files);
        echo '<br>';
        echo $files['file_name'][0];
        var_dump($files['file_name'][0]);
        echo '<br>';
        echo $files['file_name'][0]['name'];
        echo '<br>';
        echo $files['file_name'][0]['size'];

        if ($files['file_name'][0]['name'] == '') {
            $app->enqueueMessage('Please select a file', 'error');
            return false;
        }
        if ($files['file_name'][0]['size'] == 0) {
            $app->enqueueMessage($files['file_name'][0]['name'] . ' is empty', 'error');
            return false;
        }


        foreach ($files['file_name'] as $singleFile) {
            jimport('joomla.filesystem.file');

            // Check if the server found any error.
            $fileError = $singleFile['error'];
            $message = '';

            if ($fileError > 0 && $fileError != 4) {
                switch ($fileError) {
                    case 1:
                        $message = Text::_('File size exceeds allowed by the server');
                        break;
                    case 2:
                        $message = Text::_('File size exceeds allowed by the html form');
                        break;
                    case 3:
                        $message = Text::_('Partial upload error');
                        break;
                }

                if ($message != '') {
                    $app->enqueueMessage($message, 'warning');
                    return false;
                }
            } elseif ($fileError == 4) {
                if (isset($array['file_name'])) {
                    $this->file_name = $array['file_name'];
                }
            } else {
                // Check for filetype
                $validMIMEArray = explode(',', $MIMETypes);
                $fileMime = $singleFile['type'];

                if (!in_array($fileMime, $validMIMEArray)) {
                    $app->enqueueMessage('File <b>' . $singleFile['name'] . '</b>, type <b>' . $fileMime . '</b> is not allowed (must be ' . $MIMETypes . ')', 'warning');

                    return false;
                }
            }
        }


        return $data;
    }

}
