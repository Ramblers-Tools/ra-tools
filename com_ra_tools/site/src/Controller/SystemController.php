<?php

/**
 * @version    3.7.4
 * @package    com_ra_tools
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 *
 * These functions are used when an email is to be sent.
 *  type=1: send email to a committee member or event organiser
 *  type=2: send email to walk leader (as at July 26, not yet used)
 *  type=3: send email to all attendees of an Event
 *
 * All transfer control to the screen where an email can be composed, and the
 * actual dispatch of the message is from EmailformModel / Save. After this,
 * control is returned as specified in parameter "callback"
 *
 * 23/01/26 CB support for emailing a single event attendee
 * 09/02/26 CB correction when emailing multiple attendees
 * 26/02/26 CB Catch errors in eventOrganiser
 * 10/03/16 CB check for invalid booking_id in eventAttendees
 * 10/03/26 CB reinstate display of addressee_name for eventOganiser
 * 19/03/26 CB don't reject EventAttendees if no booking_id
 * 13/07/26 CV notes added
 * 09/11/26 CB Replaced getDbo with DatabaseInterface
 */

namespace Ramblers\Component\Ra_tools\Site\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\Application\SiteApplication;
use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Controller\FormController;
use \Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Uri\Uri;
use Ramblers\Component\Ra_events\Site\Helpers\EventsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Emails class.
 *
 * @since  2.0
 */
class SystemController extends FormController {

    protected $criteria_sql;
    protected $db;
    protected $app;
    protected $toolsHelper;

    public function __construct() {
        parent::__construct();
        $this->db = Factory::getContainer()->get('DatabaseDriver');
        $this->toolsHelper = new ToolsHelper;
        $this->app = Factory::getApplication();
// Import CSS
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
    }

    public function contact() {
// Accept contact id, lookup their email address
        $id = Factory::getApplication()->input->getCmd('id', '0');
        if ($id == 0) {
            $this->app->enqueueMessage('Sorry, Contact reference not given', 'error');
            $this->setRedirect('index.php?option=com_ra_tools&task=emailform.cancel');
            return;
        }
        $callback = 'index.php';
        $sql = 'SELECT u.email ';
        $sql .= 'FROM #__contact_details AS c ';
        $sql .= 'LEFT JOIN #__users AS u ON u.id =  c.user_id ';
        $sql .= "WHERE c.id=" . $id;

        $addressee = $this->toolsHelper->getValue($sql);
        if (is_null($addressee)) {
            $this->app->enqueueMessage('Contact not found for id ' . $id, 'error');
            $this->setRedirect('index.php?option=com_ra_tools&task=emailform.cancel');
            return;
        }
        $this->createEmail('RA Tools', 1, $id, $addressee, $callback);
    }

    public function emailContact() {
// send email to the specified Contact
        $id = Factory::getApplication()->input->getCmd('id', '0');
        if ($id == 0) {
            $this->app->enqueueMessage('Sorry, Contact reference not given', 'error');
            $this->setRedirect('index.php?option=com_ra_tools&task=emailform.cancel');
            return;
        }
        $menu_id = Factory::getApplication()->input->getCmd('Itemid', '0');
// Generate a standard object to pass to ra_tools
        $sql = 'SELECT u.email, c.con_position, p.preferred_name ';
        $sql .= 'FROM #__contact_details AS c ';
        $sql .= 'INNER JOIN #__users AS u ON u.id = c.user_id ';
        $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id = c.user_id ';
        $sql .= 'WHERE c.id=' . $id;
        $contact = $this->toolsHelper->getItem($sql);
        if (is_null($contact)) {
            $this->app->enqueueMessage('Contact not found for id ' . $id, 'error');
            $this->setRedirect('index.php?option=com_ra_tools&task=emailform.cancel');
            return;
        }
        $param = new CMSObject;
        $param->sub_system = 'RA Tools';
        $param->record_type = 1;
        $param->ref = $id;
        $param->caption = 'Contact ' . $contact->con_position;
        $param->addressee_name = $contact->preferred_name;
        $param->addressee_email = $contact->email;
        $param->sender_input = 'Y';
        $param->sender_name = '';
        $param->sender_email = '';
        $param->callback = 'index.php?option=com_ra_tools&view=misc&layout=contacts&Itemid=' . $menu_id;
        $this->createEmail($param);
    }

    public function eventAttendees() {
// Accept event id, lookup email address of all those booked on it
// If a booking_id is given, then this is an email to a single attendee
        $id = Factory::getApplication()->input->getInt('id', 0);
        if ($id == 0) {
            $this->app->enqueueMessage('Sorry, Event reference not given', 'error');
            $this->setRedirect('index.php?option=com_ra_tools&task=emailform.cancel');
            return;
        }
        $booking_id = Factory::getApplication()->input->getInt('booking_id', 0);

// Generate a standard object to pass to ra_tools
        $sql = 'SELECT e.title, u.email, p.preferred_name ';
        $sql .= 'FROM #__ra_events AS e ';
        $sql .= 'LEFT JOIN #__contact_details AS c ON c.id = e.contact_id ';
        $sql .= 'LEFT JOIN #__ra_profiles AS p ON p.id = c.user_id ';
        $sql .= 'LEFT JOIN #__users AS u ON u.id = c.user_id ';
        $sql .= 'WHERE e.id=' . $id;
        $event = $this->toolsHelper->getItem($sql);
        if (is_null($event) || $event->email == '') {
            $this->app->enqueueMessage('Email address not found for event ' . $id, 'error');

            echo 'Event = ' . $id . '<br>';
            $sql = 'SELECT contact_id FROM #__ra_events WHERE id=' . $id;
            $contact_id = $this->toolsHelper->getValue($sql);
            echo 'Contact = ' . $contact_id . '<br>';
            if (is_null($contact_id)) {
                $this->app->enqueueMessage('Contact not found for id ' . $contact_id, 'error');
                return;
            }
            $sql = 'SELECT user_id FROM #__contact_details WHERE id=' . $contact_id;
            $user_id = $this->toolsHelper->getValue($sql);
//            echo 'User id = ' . $user_id . '<br>';

            if ($user_id == '') {
                $message = 'No user found for contact id ' . $contact_id;
                echo $message . '<br>';
            } else {
                $sql = 'SELECT email FROM #__users WHERE id=' . $user_id;
                $email = $this->toolsHelper->getValue($sql);
                if ($email == '') {
                    $message = 'No user found WHERE id=' . $user_id;
                    echo $message . '<br>';
                } else {
                    echo 'Email = ' . $email . '<br>';
                }
                $sql = 'SELECT preferred_name FROM #__ra_profiles WHERE id=' . $user_id;
                $preferred_name = $this->toolsHelper->getValue($sql);
                echo 'Preferred_name = ' . $preferred_name . '<br>';
                $this->setRedirect('index.php?option=com_ra_tools&task=emailform.cancel');
            }
            die;
        }

        $param = new CMSObject;
        $param->sub_system = 'RA Events';
        $param->record_type = 3;
        $param->ref = $id;
        $param->caption = "Attendees for '" . $event->title . "'";
        $param->sender_input = 'N';
        $param->sender_name = $event->preferred_name;
        $param->sender_email = $event->email;
        $param->callback = 'index.php?option=com_ra_events&view=event&Itemid=0&id=' . $id;
// Get the email address(es)

        if ($booking_id != 0) {
            $sql = 'SELECT u.email,p.preferred_name ';
            $sql .= 'FROM #__ra_bookings AS b ';
            $sql .= 'INNER JOIN #__users AS u ON u.id =  b.user_id ';
            $sql .= 'INNER JOIN #__ra_profiles AS p ON p.id =  b.user_id ';
            $sql .= "WHERE b.id=" . $booking_id . " ";
            echo $sql . '<br>';

            $item = $this->toolsHelper->getItem($sql);
            if (is_null($item)) {
                $this->app->enqueueMessage('Booking not found for id ' . $booking_id, 'error');
                return;
            }
            $param->addressee_name = $item->preferred_name;
            $param->addressee_email .= $item->email;
            //           die($item->preferred_name . ' ' . $item->email);
            $this->createEmail($param);
        } else {
            $sql = 'SELECT u.email ';
            $sql .= 'FROM #__ra_bookings AS b ';
            $sql .= 'INNER JOIN #__users AS u ON u.id =  b.user_id ';
            $sql .= "WHERE b.state >= 0 ";
            $sql .= "AND b.event_id=" . $id;

            $rows = $this->toolsHelper->getRows($sql);
            //        echo count($rows) . '<br>';
            if (count($rows) == 0) {
                $this->app->enqueueMessage('No attendees found for ' . $event->title, 'warning');
                $this->setRedirect('index.php?option=com_ra_tools&task=emailform.cancel');
                $this->Redirect($url, false);
            } else {
                $param->addressee_name = count($rows) . ' members';
                $param->addressee_email = '';
                foreach ($rows as $row) {
                    if ($param->addressee_email !== '') {
                        $param->addressee_email .= ',';
                    }
                    $param->addressee_email .= $row->email;
                }
                $this->createEmail($param);
            }
        }
    }

    public function eventOrganiser() {
// Accept event id, lookup email address of the organiser
        $id = Factory::getApplication()->input->getCmd('id', '0');
        if ($id == 0) {
            $this->app->enqueueMessage('Sorry, Event reference not given', 'error');
            $this->setRedirect('index.php?option=com_ra_tools&task=emailform.cancel');
            return;
        }
        $sql = 'SELECT e.title, u.email, p.preferred_name, e.contact_id, c.user_id ';
        $sql .= 'FROM #__ra_events AS e ';
        $sql .= 'LEFT JOIN #__contact_details AS c ON c.id = e.contact_id ';
        $sql .= 'LEFT JOIN #__ra_profiles AS p ON p.id = c.user_id ';
        $sql .= 'LEFT JOIN #__users AS u ON u.id = c.user_id ';
        $sql .= 'WHERE e.id=' . $id;
//        echo $sql . '<br>';
        $event = $this->toolsHelper->getItem($sql);
        if (is_null($event)) {
            $this->app->enqueueMessage('Event not found for id ' . $id, 'error');
            $this->setRedirect('index.php?option=com_ra_tools&task=emailform.cancel');
            return;
        }
        if ($event->email == '') {
            $this->app->enqueueMessage('Email address not found for event ' . $id, 'error');

            echo 'Event = ' . $id . '<br>';
            $sql = 'SELECT contact_id FROM #__ra_events WHERE id=' . $id;
            $contact_id = $this->toolsHelper->getValue($sql);
            echo 'Contact = ' . $contact_id . '<br>';

            $sql = 'SELECT user_id FROM #__contact_details WHERE id=' . $contact_id;
            $user_id = $this->toolsHelper->getValue($sql);
//            echo 'User id = ' . $user_id . '<br>';

            if ($user_id == '') {
                $message = 'No user found';
                echo $message . '<br>';
            } else {
                $sql = 'SELECT email FROM #__users WHERE id=' . $user_id;
                $email = $this->toolsHelper->getValue($sql);
                if ($email == '') {
                    $message = 'No user found WHERE id=' . $user_id;
                    echo $message . '<br>';
                } else {
                    echo 'Email = ' . $email . '<br>';
                }
                $sql = 'SELECT preferred_name FROM #__ra_profiles WHERE id=' . $user_id;
                $preferred_name = $this->toolsHelper->getValue($sql);
                echo 'Preferred_name = ' . $preferred_name . '<br>';
                $this->setRedirect('index.php?option=com_ra_tools&task=emailform.cancel');
            }
            die;
        }

// Generate a standard object to pass to ra_tools
        $param = new CMSObject;
        $param->sub_system = 'RA Events';
        $param->record_type = 1;
        $param->ref = $id;
        $param->caption = "Contact for '" . $event->title . "'";
        $param->addressee_name = $event->preferred_name;
        $param->addressee_email = $event->email;
        $param->sender_input = 'Y';
        $param->sender_name = '';
        $param->sender_email = '';
        $param->callback = 'index.php?option=com_ra_events&view=event&id=' . $id;
        $this->createEmail($param);
    }

    public function createEmail($param) {
        /*

          // the input parameter is an object
          sub_system        Tools/Event/Walks
          record_type
          ref               the id of the Contact/Event/Walk etc
          caption           shown on the input form
          addressee_name    always visible
          addressee_email   array
          sender_input      if Y, following two fields must be entered on form
          sender_name
          sender_email
          callback          url to which control should pass after message has been sent
         *

          Types of email sent
          Contact: to,1
          Booking enquiry (to organiser): ev,1
          To all bookers: ev,2
          To aevent organiser, when someone makes a booking: ev,3
          To walk leader: wf,1
          To all followers: wf,2
         */
//(sender = current user id)
// Store parameters in user state
        Factory::getApplication()->setUserState('com_ra_tools.email.sub_system', $param->sub_system);
        Factory::getApplication()->setUserState('com_ra_tools.email.record_type', $param->record_type);
        Factory::getApplication()->setUserState('com_ra_tools.email.ref', $param->ref);
        Factory::getApplication()->setUserState('com_ra_tools.email.caption', $param->caption);
        Factory::getApplication()->setUserState('com_ra_tools.email.sender_input', $param->sender_input);
        Factory::getApplication()->setUserState('com_ra_tools.email.sender_name', $param->sender_name);
        Factory::getApplication()->setUserState('com_ra_tools.email.sender_email', $param->sender_email);
        Factory::getApplication()->setUserState('com_ra_tools.email.addressee_name', $param->addressee_name);
        if ($param->addressee_email == '') {
            Factory::getApplication()->enqueueMessage('Recipient not found for ' . $param->caption, 'error');
            $this->setRedirect('index.php?option=com_ra_tools&task=emailform.cancel');
        } else {
            Factory::getApplication()->setUserState('com_ra_tools.email.addressee_email', $param->addressee_email);
            Factory::getApplication()->setUserState('com_ra_tools.email.callback', $param->callback);
        }

// invoke the view to display the input form
        $this->setRedirect('index.php?option=com_ra_tools&view=emailform&id=0');
    }

    public function test() {
        $this->eventAttendees(1, 4);
        $this->setRedirect('index.php?option=com_ra_tools&task=system.eventAttendees&id=4&booking_id=1 ');

        return;
        $config = Factory::getConfig();
//        var_dump($config);
        echo $config->get('sitename');
        $db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true);
        $query->select('a.*');
        $query->select('p.preferred_name AS contact_name');
        // $query->select('event_type.description AS event_type');
        $query->from('`#__ra_events` AS a');

        //$query->leftJoin('#__ra_event_types AS event_type ON event_type.id = a.event_type_id');
        $query->leftJoin('#__contact_details AS c ON c.id = a.contact_id');
        $query->leftJoin('#__ra_profiles AS p ON p.id = c.user_id');
        $query->where('(a.shareable =1)');
        $query->where('DATEDIFF(a.share_date, CURRENT_DATE) > 0 ');
        $query->where('DATEDIFF(e.event_date, CURRENT_DATE) > 0');
        $query->order('a.id DESC');
        echo $query;
        $this->toolsHelper->showSql($query);
//        $this->setRedirect('index.php?option=com_ra_tools&task=system.emailContact&id=3');
//        $this->redirect;
    }

    public function walkLeader() {
// Accept walk id, lookup email address of the leader
        $id = Factory::getApplication()->input->getCmd('id', '1');
        $callback = 'index.php';
        $sql = 'SELECT u.email ';
        $sql .= 'FROM #__ra_walks AS w ';
//        $sql .= 'INNER JOIN #__ra_profiles AS p ON c.id = w.leader_id ';
        $sql .= 'INNER JOIN #__users AS u ON u.id =  w.leader_id ';
        $sql .= "WHERE e.id=" . $id;
        $param = new CMSOject;
        $param->sub_system = 'RA WalksF';
        $param->record_type = 2;
        $param->caption = 'Contact for ' . $walk->title;
        $param->addressee_name = $walk->preferred_name;
        $param->addressee_email = $walk->email;
        $param->sender_input = 'Y';
        $param->sender_name = '';
        $param->sender_email = '';
        $param->callback = 'option=com_ra_wf&view=walksdetail&id=' . $id;
        $this->createEmail($param);
    }

}
