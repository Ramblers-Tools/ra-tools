<?php

/**
 * A member's bookings for the Ramblers Tools app, from ra_events' tables (#__ra_bookings,
 * #__ra_booking_guests, #__ra_events). The format is the app repo's docs/events-feed.md
 * ("My bookings"). If ra_events isn't installed the list is empty.
 *
 * #__ra_bookings.state, as ra_events uses it (checked against com_ra_events, 14/11/26):
 *    0 Provisional: booked, waiting for the organiser to confirm. Counts as a place taken.
 *    1 Confirmed. Counts as a place taken.
 *   -1 Waitlisted: the event was full. Doesn't count as a place taken.
 *   -2 Cancelled, by the member or the organiser.
 *
 * Like the member's bookings page on the site, it lists bookings for events that haven't
 * ended yet. If a member cancelled and booked the same event again, only the live booking is
 * sent.
 *
 * 14/11/26 RH Created
 */

namespace Ramblers\Component\Ra_tools\Site\Helpers;

defined('_JEXEC') or die;

class AppBookings {

    const ZONE = 'Europe/London';

    const STATUS = [0 => 'requested', 1 => 'confirmed', -1 => 'waiting_list', -2 => 'cancelled'];

    /** #__ra_events.state for a cancelled event. */
    const EVENT_CANCELLED = -2;

    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function forUser(int $userId, string $siteRoot): array {
        $prefix = $this->db->getPrefix();
        $tables = $this->db->getTableList();
        if (!in_array($prefix . 'ra_bookings', $tables, true) || !in_array($prefix . 'ra_events', $tables, true)) {
            return [];
        }
        $query = $this->db->getQuery(true)
                ->select([
                    'b.id', 'b.event_id', 'b.num_places', 'b.partner', 'b.state', 'b.is_paid', 'b.created',
                    'e.title', 'e.event_date', 'e.event_date_end', 'e.event_time', 'e.location',
                    'e.requires_payment', 'e.state AS event_state',
                ])
                ->from($this->db->quoteName('#__ra_bookings', 'b'))
                ->join('INNER', $this->db->quoteName('#__ra_events', 'e'), 'e.id = b.event_id')
                ->where('b.user_id = :user')
                ->where('COALESCE(e.event_date_end, e.event_date) >= CURRENT_DATE')
                ->order('e.event_date ASC, b.id ASC')
                ->bind(':user', $userId, \Joomla\Database\ParameterType::INTEGER);
        $rows = $this->db->setQuery($query)->loadAssocList() ?: [];

        $guests = [];
        if ($rows && in_array($prefix . 'ra_booking_guests', $tables, true)) {
            $ids = array_map('intval', array_column($rows, 'id'));
            $query = $this->db->getQuery(true)
                    ->select(['booking_id', 'name'])
                    ->from($this->db->quoteName('#__ra_booking_guests'))
                    ->whereIn($this->db->quoteName('booking_id'), $ids)
                    ->order('id ASC');
            foreach ($this->db->setQuery($query)->loadAssocList() ?: [] as $guest) {
                $guests[(int) $guest['booking_id']][] = $guest['name'];
            }
        }
        return self::bookings($rows, $guests, $siteRoot);
    }

    /** Rows from the query above as the app's bookings. Plain PHP, for tests. */
    public static function bookings(array $rows, array $guests, string $siteRoot): array {
        // Hide a cancelled booking when the member has a live one for the same event.
        $live = [];
        foreach ($rows as $row) {
            if ((int) $row['state'] !== -2) {
                $live[(string) $row['event_id']] = true;
            }
        }
        $bookings = [];
        foreach ($rows as $row) {
            if ((int) $row['state'] === -2 && isset($live[(string) $row['event_id']])) {
                continue;
            }
            $booking = self::booking($row, $guests[(int) $row['id']] ?? [], $siteRoot);
            if ($booking !== null) {
                $bookings[] = $booking;
            }
        }
        return $bookings;
    }

    public static function booking(array $row, array $guestNames, string $siteRoot): ?array {
        $date = self::date($row['event_date'] ?? null);
        $title = self::text($row['title'] ?? null);
        if ($date === null || $title === null) {
            return null;
        }
        $zone = new \DateTimeZone(self::ZONE);
        $time = self::time($row['event_time'] ?? null);
        $start = new \DateTimeImmutable($date . ' ' . ($time ?? '00:00'), $zone);
        $endDate = self::date($row['event_date_end'] ?? null);
        $end = $endDate !== null && $endDate > $date ? new \DateTimeImmutable($endDate . ' 00:00', $zone) : null;

        $status = self::STATUS[(int) $row['state']] ?? 'requested';
        if ((int) ($row['event_state'] ?? 1) === self::EVENT_CANCELLED) {
            $status = 'cancelled';
        }
        $names = array_values(array_filter(array_map([self::class, 'text'], $guestNames)));
        $partner = self::text($row['partner'] ?? null);
        if ($partner !== null && !$names) {
            // Older two-place bookings keep the second person's name here.
            $names = [$partner];
        }
        $requiresPayment = (int) ($row['requires_payment'] ?? 0) === 1;
        $booking = [
            'id' => (string) $row['id'],
            'event_id' => (string) $row['event_id'],
            'event_title' => $title,
            'start' => $start->format(DATE_ATOM),
            'end' => $end ? $end->format(DATE_ATOM) : null,
            'location' => self::text($row['location'] ?? null),
            'places' => max(1, (int) ($row['num_places'] ?? 1)),
            'guests' => $names,
            'status' => $status,
            'requires_payment' => $requiresPayment,
            'is_paid' => $requiresPayment ? (int) ($row['is_paid'] ?? 0) === 1 : null,
            'url' => rtrim($siteRoot, '/') . '/index.php?option=com_ra_events&view=event&id=' . rawurlencode((string) $row['event_id']),
        ];
        return array_filter($booking, fn($v) => $v !== null);
    }

    private static function date($value): ?string {
        $value = substr(trim((string) ($value ?? '')), 0, 10);
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || $value === '0000-00-00') {
            return null;
        }
        [$y, $m, $d] = array_map('intval', explode('-', $value));
        return checkdate($m, $d, $y) ? $value : null;
    }

    private static function time($value): ?string {
        if (!preg_match('/^\s*(\d{1,2})[:.](\d{2})\s*$/', (string) ($value ?? ''), $m)) {
            return null;
        }
        return (int) $m[1] < 24 && (int) $m[2] < 60 ? sprintf('%02d:%02d', (int) $m[1], (int) $m[2]) : null;
    }

    private static function text($value): ?string {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $value = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $value === '' ? null : $value;
    }
}
