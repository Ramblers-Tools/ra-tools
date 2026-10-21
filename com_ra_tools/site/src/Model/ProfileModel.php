<?php
namespace Ramblers\Component\Ra_tools\Site\Model;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\MVC\Model\ItemModel;
use Joomla\Database\DatabaseInterface;

/** Supplies the logged-in user's profile and optional component details. */
class ProfileModel extends ItemModel
{
    protected $_item;

    protected function populateState(): void
    {
        $user = Factory::getApplication()->getIdentity();
        $this->setState('profile.id', (int) ($user?->id ?? 0));
    }

    public function getItem($id = null)
    {
        $id = (int) ($id ?: $this->getState('profile.id'));
        $user = Factory::getApplication()->getIdentity();

        if (!$id || !$user || (int) $user->id !== $id) {
            return false;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('p.*, u.name, u.name AS real_name, u.username, u.email AS user_email')
            ->from($db->quoteName('#__ra_profiles', 'p'))
            ->leftJoin($db->quoteName('#__users', 'u') . ' ON u.id = p.id')
            ->where('p.id = ' . $id);
        $db->setQuery($query);

        return $this->_item = $db->loadObject() ?: false;
    }

    /** Return the user's active mailing-list subscriptions. */
    public function getSubscriptions(int $userId): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('l.name, l.group_code, s.created, s.expiry_date')
            ->from($db->quoteName('#__ra_mail_subscriptions', 's'))
            ->innerJoin($db->quoteName('#__ra_mail_lists', 'l') . ' ON l.id = s.list_id')
            ->where('s.user_id = ' . $userId)
            ->where('s.state = 1')
            ->where('l.state = 1')
            ->order('l.group_code, l.name');
        $db->setQuery($query);

        return (array) $db->loadObjectList();
    }

    /** Return the user's active bookings, limited to published events. */
    public function getBookings(int $userId): array
    {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select('e.title, e.event_date, e.event_time, b.num_places, b.state')
            ->from($db->quoteName('#__ra_bookings', 'b'))
            ->innerJoin($db->quoteName('#__ra_events', 'e') . ' ON e.id = b.event_id')
            ->where('b.user_id = ' . $userId)
            ->where('b.state = 1')
            ->where('e.state = 1')
            ->order('e.event_date, e.event_time');
        $db->setQuery($query);

        return (array) $db->loadObjectList();
    }
}
