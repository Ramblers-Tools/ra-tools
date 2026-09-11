<?php

/**
 * 17/06/25 CB created
 * 19/06/25 CB replaced with version in com_ra_tools
 * 30/06/25 CB dump JSON data id no mode
 * 21/08/25 CB changState, delete and publish (from EmailsController)
 * 14/09/25 CB rework Events load (for com_ra_events 2.2.1)
 * 18/09/25 CB check for NULL events
 * 10/02/26 CB removed heading for ShowShared
 * 07/06/25 CB show email sender, not email_id
 * 08/06/26 CB Show all button after apiTest 
 * 13/07/26 CB use SmtpHelper, not ActivityHelper
 * 23/08/26 CB refreshMembers added
 * 05/08/26 CB enqueue any error messages from SmtpHelper
 */

namespace Ramblers\Component\Ra_tools\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\Language\Multilanguage;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\AdminController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Joomla\Utilities\ArrayHelper;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Ramblers\Component\Ra_delivery\Site\Helper\SmtpHelper;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;
use Ramblers\Component\Ra_members\Site\Helper\LoadHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

/**
 * Apisites list controller class.
 *
 * @since  2.1.0
 */
class ApisitesController extends AdminController {

    protected $back = 'administrator/index.php?option=com_ra_tools&view=apisites';
    protected $db;
    protected $app;
    protected $toolsHelper;

    public function __construct() {
        parent::__construct();
        $this->db = Factory::getDbo();
        $this->toolsHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function cancel($key = null, $urlVar = null) {
        $this->setRedirect('index.php?option=com_ra_tools&view=dashboard');
    }

    private function changeState($id, $from, $to) {
        $sql = 'SELECT url, state FROM #__ra_api_sites WHERE id=' . $id;
        $item = $this->toolsHelper->getItem($sql);
        $message = '"' . $item->url . '" ';
        if ($item->state == $from) {
            $sql = 'UPDATE #__ra_api_sites SET state=' . $to . ' WHERE id=' . $id;
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
        $sql = 'SELECT url, state FROM #__ra_api_sites WHERE id=';
        foreach ($primary_keys as $id) {
            $item = $this->toolsHelper->getItem($sql . $id);
            $message = '"' . $item->url . '" ';

            if ($item->state == 0) {
                $this->toolsHelper->executeCommand('DELETE FROM #__ra_api_sites WHERE id=' . $id);
                $message .= 'deleted';
            } else {
                $message .= 'must be unpublished before it can be deleted';
            }
            Factory::getApplication()->enqueueMessage($message, 'info');
            echo $message . '<br>';
        }
        $this->setRedirect('index.php?option=com_ra_tools&view=apisites');
    }

    public function deleteSharedEvents() {
        $eventsHelper = new EventsHelper;
        $eventsHelper->deleteShared();
//        $sql = 'SELECT id, title FROM #__ra_events WHERE api_site_id IS NOT NULL';
//        $rows = $this->toolsHelper->getRows($sql);
//        foreach ($rows as $row) {
//            $this->toolsHelper->executeCommand('DELETE FROM #__ra_events WHERE id=' . $row->id);
//            //               $this->toolsHelper->executeCommand($sql);
//
//            Factory::getApplication()->enqueueMessage('Event ' . row->title . ' deleted', 'info');
//        }

        $this->setRedirect('administrator/index.php?option=com_ra_events&task=reports.sharedEvents');
    }

    /**
     * Method to clone existing Apisites
     *
     * @return  void
     *
     * @throws  Exception
     */
    public function duplicate() {
        // Check for request forgeries
        $this->checkToken();

        // Get id(s)
        $pks = $this->input->post->get('cid', array(), 'array');

        try {
            if (empty($pks)) {
                throw new \Exception(Text::_('COM_RA_EVENTS_NO_ELEMENT_SELECTED'));
            }

            ArrayHelper::toInteger($pks);
            $model = $this->getModel();
            $model->duplicate($pks);
            $this->setMessage(Text::_('COM_RA_EVENTS_ITEMS_SUCCESS_DUPLICATED'));
        } catch (\Exception $e) {
            Factory::getApplication()->enqueueMessage($e->getMessage(), 'warning');
        }

        $this->setRedirect('index.php?option=com_ra_tools&view=apisites');
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
        $this->setRedirect('index.php?option=com_ra_tools&view=apisites');
    }

    public function refreshEvents() {
        $mode = Factory::getApplication()->input->getInt('mode', '1');
        $id = Factory::getApplication()->input->getInt('id', '0');
        if ($id == 0) {
            Factory::getApplication()->enqueueMessage('Site id is zero', 'error');
            echo $this->toolsHelper->backButton($this->back);
            return;
        }
        $eventsHelper = new EventsHelper;

        $details = $eventsHelper->getSharedEvents($id);
        if ($details) {
            $events = $details["data"];
            if (is_null($events)) {
                Factory::getApplication()->enqueueMessage('No Events found for site ' . $id, 'info');
                foreach ($eventsHelper->messages as $message) {
                    Factory::getApplication()->enqueueMessage($message, 'error');
                }
                echo $this->toolsHelper->backButton($this->back);
                return;
            }
            $count = count($events);
//            Factory::getApplication()->enqueueMessage($count . ' Events found', 'info');
            if ($mode == 1) {
                echo '<h2>Updating database</h2>';
                $eventsHelper->showShared($id, $events);
                return;
            } elseif ($mode == 2) {
                $eventsHelper->storeShared($id, $events);
                foreach ($eventsHelper->messages as $message) {
                    echo $message . '<br>';
                }
                $back = 'administrator/index.php?option=com_ra_tools&view=apisites';
                $back .= '&id=' . $id;
                echo $this->toolsHelper->backButton($back);
                return;
            } elseif ($mode == 3) {
                $eventsHelper->showFirst($id, $events);
                return;
            } else {
                $eventsHelper->dumpShared($id, $events);
                return;
            }
        } else {
            foreach ($eventsHelper->messages as $message) {
                Factory::getApplication()->enqueueMessage($message, 'error');
            }
            echo $this->toolsHelper->backButton($this->back);
            return;
        }
    }

    public function refreshMembers(){
        $id = $this->app->input->getInt('id', 0);

        if ($id === 0) {
            $this->app->enqueueMessage('Site id is zero', 'error');
            echo $this->toolsHelper->backButton($this->back);
            return;
        }

        $helper = new LoadHelper;
        $result = $helper->loadMembers($id);
        if ($result === false) {
            $this->app->enqueueMessage('Failed to load members for site ' . $id, 'error');
        }
        foreach ($helper->messages as $message) {
            Factory::getApplication()->enqueueMessage($message, 'info');
        }

        echo $this->toolsHelper->backButton($this->back);
        return;
    }

    public function testDeliveryActivity() {
        $id = $this->app->input->getInt('id', 0);

        if ($id === 0) {
            $this->app->enqueueMessage('Site id is zero', 'error');
            echo $this->toolsHelper->backButton($this->back);
            return;
        }

        $helper = new SmtpHelper;
        $result = $helper->testApiSite($id);

        echo '<h2>RA Delivery helper test</h2>';
        echo '<p>API site id: <b>' . (int) $id . '</b></p>';

        foreach ($helper->getMessages() as $message) {
            $this->app->enqueueMessage($message, 'error');
            echo htmlspecialchars($message, ENT_QUOTES, 'UTF-8') . '<br>';
        }

        if ($result !== false) {
            echo '<br>';
            echo 'Window: <b>' . htmlspecialchars($result['start_date'], ENT_QUOTES, 'UTF-8') . '</b> to <b>'
            . htmlspecialchars($result['end_date'], ENT_QUOTES, 'UTF-8') . '</b><br>';
            echo 'Events returned: <b>' . (int) $result['event_count'] . '</b><br>';
            if ($result['request_id'] !== '') {
                echo 'Request id: <b>' . htmlspecialchars($result['request_id'], ENT_QUOTES, 'UTF-8') . '</b><br>';
            }

            if (!empty($result['events'])) {
                echo '<br><table class="table table-striped">';
                echo '<thead><tr><th>Date</th><th>Event</th><th>Recipient</th><th>Sender</th><th>Subject</th><th>Sender</th></tr></thead>';
                echo '<tbody>';
                foreach (array_slice($result['events'], 0, 20) as $event) {
                    echo '<tr>';
                    echo '<td>' . htmlspecialchars((string) ($event['date'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars((string) ($event['event'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars((string) ($event['recipient'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars((string) ($event['sender'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars((string) ($event['subject'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '<td>' . htmlspecialchars((string) ($event['username'] ?? ''), ENT_QUOTES, 'UTF-8') . '</td>';
                    echo '</tr>';
                }
                echo '</tbody></table>';
            }
        }

        echo '<br>';
        $target = 'administrator/index.php?option=com_ra_delivery';
        echo $this->toolsHelper->buildButton($target, 'Show all');
        echo $this->toolsHelper->backButton($this->back);
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
     * @since   2.1.0
     */
    public function getModel($name = 'Apisite', $prefix = 'Administrator', $config = array()) {
        return parent::getModel($name, $prefix, array('ignore_request' => true));
    }

    /**
     * Method to save the submitted ordering values for records via AJAX.
     *
     * @return  void
     *
     * @since   2.1.0
     *
     * @throws  Exception
     */
    public function saveOrderAjax() {
        // Get the input
        $pks = $this->input->post->get('cid', array(), 'array');
        $order = $this->input->post->get('order', array(), 'array');

        // Sanitize the input
        ArrayHelper::toInteger($pks);
        ArrayHelper::toInteger($order);

        // Get the model
        $model = $this->getModel();

        // Save the ordering
        $return = $model->saveorder($pks, $order);

        if ($return) {
            echo "1";
        }

        // Close the application
        Factory::getApplication()->close();
    }

}
