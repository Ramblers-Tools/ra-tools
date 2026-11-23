<?php

/**
 * 08/06/25 CB change date logic in bind
 * 08/12/25 CB upload attachments to /images
 * 21/11/26 CB instantiating table class updated for Joomla 6
 */

namespace Ramblers\Component\Ra_tools\Administrator\Table;

// No direct access
defined('_JEXEC') or die;

use \Joomla\Utilities\ArrayHelper;
use \Joomla\CMS\Factory;
use \Joomla\CMS\Access\Access;
use \Joomla\CMS\Language\Text;
use \Joomla\CMS\Table\Table as Table;
use \Joomla\CMS\Versioning\VersionableTableInterface;
use Joomla\CMS\Tag\TaggableTableInterface;
use Joomla\CMS\Tag\TaggableTableTrait;
use \Joomla\Database\DatabaseDriver;
use \Joomla\CMS\Filter\OutputFilter;
use \Joomla\CMS\Filesystem\File;
use \Joomla\Registry\Registry;
use \Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use \Joomla\CMS\Helper\ContentHelper;

/**
 * Email table
 *
 * @since 2.0
 */
class EmailTable extends Table implements VersionableTableInterface, TaggableTableInterface {

    use TaggableTableTrait;

    /**
     * Indicates that columns fully support the NULL value in the database
     *
     * @var    boolean
     * @since  4.0.0
     */
    protected $_supportNullValue = true;
    protected $upload_path = '/images/com_ra_tools/emails/';

    /**
     * Constructor
     *
     * @param   JDatabase  &$db  A database connector object
     */
    public function __construct(DatabaseDriver $db) {
        $this->typeAlias = 'com_ra_tools.email';
        parent::__construct('#__ra_emails', 'id', $db);
        $this->setColumnAlias('published', 'state');
    }

    /**
     * Get the type alias for the history table
     *
     * @return  string  The alias as described above
     *
     * @since   2.0
     */
    public function getTypeAlias() {
        return $this->typeAlias;
    }

    /**
     * Overloaded bind function to pre-process the params.
     *
     * @param   array  $array   Named array
     * @param   mixed  $ignore  Optional array or list of parameters to ignore
     *
     * @return  boolean  True on success.
     *
     * @see     Table:bind
     * @since   2.0
     * @throws  \InvalidArgumentException
     */
    public function bind($array, $ignore = '') {
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        $task = Factory::getApplication()->input->get('task');
        $user = Factory::getApplication()->getIdentity();

        $input = Factory::getApplication()->input;
        $task = $input->getString('task', '');

        if ($array['id'] == 0) {
            $array['created'] = $date;
            $array['created_by'] = $user->id;
            $array['date_sent'] = $date;
        } else {
            $array['modified_by'] = $user->id;
            $array['modified'] = $date;
        }

        // Support for multi file field: attachments
        if (!empty($array['attachments'])) {
            if (is_array($array['attachments'])) {
                $array['attachments'] = implode(',', $array['attachments']);
            } elseif (strpos($array['attachments'], ',') != false) {
                $array['attachments'] = explode(',', $array['attachments']);
            }
        } else {
            $array['attachments'] = '';
        }

        if (isset($array['params']) && is_array($array['params'])) {
            $registry = new Registry;
            $registry->loadArray($array['params']);
            $array['params'] = (string) $registry;
        }

        if (isset($array['metadata']) && is_array($array['metadata'])) {
            $registry = new Registry;
            $registry->loadArray($array['metadata']);
            $array['metadata'] = (string) $registry;
        }

        if (!$user->authorise('core.admin', 'com_ra_tools.email.' . $array['id'])) {
            $actions = Access::getActionsFromFile(
                            JPATH_ADMINISTRATOR . '/components/com_ra_tools/access.xml',
                            "/access/section[@name='email']/"
            );
            $default_actions = Access::getAssetRules('com_ra_tools.email.' . $array['id'])->getData();
            $array_jaccess = array();

            foreach ($actions as $action) {
                if (key_exists($action->name, $default_actions)) {
                    $array_jaccess[$action->name] = $default_actions[$action->name];
                }
            }

            $array['rules'] = $this->JAccessRulestoArray($array_jaccess);
        }

        // Bind the rules for ACL where supported.
        if (isset($array['rules']) && is_array($array['rules'])) {
            $this->setRules($array['rules']);
        }

        return parent::bind($array, $ignore);
    }

    /**
     * Method to store a row in the database from the Table instance properties.
     *
     * If a primary key value is set the row with that primary key value will be updated with the instance property values.
     * If no primary key value is set a new row will be inserted into the database with the properties from the Table instance.
     *
     * @param   boolean  $updateNulls  True to update fields even if they are null.
     *
     * @return  boolean  True on success.
     *
     * @since   2.0
     */
    public function store($updateNulls = true) {
        return parent::store($updateNulls);
    }

    /**
     * This function convert an array of Access objects into an rules array.
     *
     * @param   array  $jaccessrules  An array of Access objects.
     *
     * @return  array
     */
    private function JAccessRulestoArray($jaccessrules) {
        $rules = array();

        foreach ($jaccessrules as $action => $jaccess) {
            $actions = array();

            if ($jaccess) {
                foreach ($jaccess->getData() as $group => $allow) {
                    $actions[$group] = ((bool) $allow);
                }
            }

            $rules[$action] = $actions;
        }

        return $rules;
    }

    /**
     * Overloaded check function
     *
     * @return bool
     */
    public function check() {


        // Support multi file field: attachments
        $app = Factory::getApplication();
        $files = $app->input->files->get('jform', array(), 'raw');
        $array = $app->input->get('jform', array(), 'ARRAY');
        if (empty($files['attachments'][0])) {
            $temp = $files;
            $files = array();
            $files['attachments'][] = $temp['attachments'];
        }

        if ($files['attachments'][0]['size'] > 0) {
            // Deleting existing files
            $oldFiles = ToolsHelper::getFiles($this->id, $this->_tbl, 'attachments');

            foreach ($oldFiles as $f) {
                $oldFile = JPATH_ROOT . $this->upload_path . $f;

                if (file_exists($oldFile) && !is_dir($oldFile)) {
                    unlink($oldFile);
                }
            }

            $this->attachments = "";

            foreach ($files['attachments'] as $singleFile) {
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
                    if (isset($array['attachments'])) {
                        $this->attachments = $array['attachments'];
                    }
                } else {

                    // Check for filetype
                    $okMIMETypes = 'application/pdf,application/zip,image/bmp,image/png,image/jpeg,text/csv';
                    $validMIMEArray = explode(',', $okMIMETypes);
                    $fileMime = $singleFile['type'];

                    if (!in_array($fileMime, $validMIMEArray)) {
                        $app->enqueueMessage('This filetype is not allowed', 'warning');

                        return false;
                    }

                    // Replace any special characters in the filename
                    jimport('joomla.filesystem.file');
                    $filename = File::stripExt($singleFile['name']);
                    $extension = File::getExt($singleFile['name']);
                    $filename = preg_replace("/[^A-Za-z0-9]/i", "-", $filename);
                    $filename = $filename . '.' . $extension;
                    $uploadFile = JPATH_ROOT . $this->upload_path . $filename;
                    $fileTemp = $singleFile['tmp_name'];

                    if (!File::exists($uploadFile)) {
                        if (!File::upload($fileTemp, $uploadFile)) {
                            $app->enqueueMessage('Error moving file', 'warning');

                            return false;
                        }
                    }

                    $this->attachments .= (!empty($this->attachments)) ? "," : "";
                    $this->attachments .= $filename;
                }
            }
        } else {
            $this->attachments .= $array['attachments_hidden'];
        }

        return parent::check();
    }

    /**
     * Define a namespaced asset name for inclusion in the #__assets table
     *
     * @return string The asset name
     *
     * @see Table::_getAssetName
     */
    protected function _getAssetName() {
        $k = $this->_tbl_key;

        return $this->typeAlias . '.' . (int) $this->$k;
    }

    /**
     * Returns the parent asset's id. If you have a tree structure, retrieve the parent's id using the external key field
     *
     * @param   Table   $table  Table name
     * @param   integer  $id     Id
     *
     * @see Table::_getAssetParentId
     *
     * @return mixed The id on success, false on failure.
     */
    protected function _getAssetParentId($table = null, $id = null) {
        // We will retrieve the parent-asset from the Asset-table
        $assetParent = new \Joomla\CMS\Table\Asset($this->getDatabase());

        // Default: if no asset-parent can be found we take the global asset
        $assetParentId = $assetParent->getRootId();

        // The item has the component as asset-parent
        $assetParent->loadByName('com_ra_tools');

        // Return the found asset-parent-id
        if ($assetParent->id) {
            $assetParentId = $assetParent->id;
        }

        return $assetParentId;
    }

    //XXX_CUSTOM_TABLE_FUNCTION

    /**
     * Delete a record by id
     *
     * @param   mixed  $pk  Primary key value to delete. Optional
     *
     * @return bool
     */
    public function delete($pk = null) {
        $this->load($pk);
        $result = parent::delete($pk);

        if ($result) {
            jimport('joomla.filesystem.file');

            $checkImageVariableType = gettype($this->attachments);

            switch ($checkImageVariableType) {
                case 'string':
                    File::delete(JPATH_ROOT . $this->upload_path . $this->attachments);
                    break;
                default:
                    foreach ($this->attachments as $attachmentsFile) {
                        File::delete(JPATH_ROOT . $this->upload_path . $attachmentsFile);
                    }
            }
        }

        return $result;
    }

}
