<?php

/**
 * @version    3.3.4
 * @package    com_ra_tools
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 20/07/25 CB Publish / Unpublish / Delete
 * 01/08/25 CB show Event details
 */

namespace Ramblers\Component\Ra_tools\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Emails list controller class.
 *
 * @since  2.0
 */
class EmailsController extends AdminController {

    protected $toolsHelper;

    public function __construct() {
        parent::__construct();
        $this->toolsHelper = new ToolsHelper;

        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function cancel($key = null, $urlVar = null) {
        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
    }

    private function changeState($id, $from, $to) {
        $sql = 'SELECT title, state FROM #__ra_emails WHERE id=' . $id;
        $item = $this->toolsHelper->getItem($sql);
        $message = '"' . $item->title . '" ';
        if ($item->state == $from) {
            $sql = 'UPDATE #__ra_emails SET state=' . $to . ' WHERE id=' . $id;
            $message .= ToolsHelper::stateDescription($to);
        } else {
            Factory::getApplication()->enqueueMessage($message . 'is already ' . ToolsHelper::stateDescription($to));
            return;
        }
        Factory::getApplication()->enqueueMessage($message, 'info');
        $this->toolsHelper->executeCommand($sql);
    }

    public function delete() {
        $primary_keys = $this->input->post->get('cid', array(), 'array');
        $sql = 'SELECT title, state FROM #__ra_emails WHERE id=';
        foreach ($primary_keys as $id) {
            $item = $this->toolsHelper->getItem($sql . $id);
            $message = '"' . $item->title . '" ';

            if ($item->state == 0) {
                $this->toolsHelper->executeCommand('DELETE FROM #__ra_emails WHERE id=' . $id);
                $message .= 'deleted';
            } else {
                $message .= 'must be unpublished before it can be deleted';
            }
            Factory::getApplication()->enqueueMessage($message, 'info');
            echo $message . '<br>';
        }
        $this->setRedirect('index.php?option=com_ra_tools&view=emails');
    }

    /**
     * Proxy for getModel.
     *
     * @param   string  $name    Optional. Model name
     * @param   string  $prefix  Optional. Class prefix
     * @param   array   $config  Optional. Configuration array for model
     *
     * @return  object	The Model
     *
     * @since   2.0
     */
    public function getModel($name = 'Email', $prefix = 'Administrator', $config = array()) {
        return parent::getModel($name, $prefix, array('ignore_request' => true));
    }

    public function publish() {
        $primary_keys = $this->input->post->get('cid', array(), 'array');

        switch ($this->task) {
            case 'publish':
                $from = 0;
                $to = 1;
                break;
            case 'unpublish':
                $from = 1;
                $to = 0;
                break;
            default;
                Factory::getApplication()->enqueueMessage($this->task . ' not recognised', 'warning');
                return;
        }
//        Factory::getApplication()->enqueueMessage($this->task . ", from $from to $to ", 'warning');
        foreach ($primary_keys as $id) {
            $message = $this->changeState($id, $from, $to);
            Factory::getApplication()->enqueueMessage($message, 'info');
        }
        $this->setRedirect('index.php?option=com_ra_tools&view=emails');
    }

    public function showEmail() {
        $id = $this->app->input->getInt('id', '0');
        $toolsHelper = new ToolsHelper;
        $toolsHelper->showEmail($id);
        $back = 'administrator/index.php?option=com_ra_tools&view=emails';
        echo $toolsHelper->backButton($back);
    }

}
