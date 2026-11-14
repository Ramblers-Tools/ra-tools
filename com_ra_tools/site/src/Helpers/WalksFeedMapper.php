<?php

/**
 * Turns a Walks Manager walksevents response into the walks feed the Ramblers Tools app reads.
 * The feed format is described in the app's docs/walks-feed.md (version 1).
 *
 * Plain PHP with no Joomla calls, so it can be tested from the command line.
 *
 * 14/11/26 RH Created
 */

namespace Ramblers\Component\Ra_tools\Site\Helpers;

defined('_JEXEC') or die;

class WalksFeedMapper {

    /** Walks Manager times have no time zone; they are UK local time. */
    const ZONE = 'Europe/London';

    /**
     * Build the whole feed.
     *
     * @param array  $response   Decoded walksevents response ({summary, data[]})
     * @param string $groupCode  e.g. CH02
     * @param string $groupName  e.g. East Cheshire Ramblers
     * @param string $walkPage   Page on the group site that shows the walks; "#walk=<id>" is added
     *                           to it. Empty to link to the walk on www.ramblers.org.uk instead.
     * @param int    $limit      Most walks to include (0 for no limit). Cancelled walks count.
     * @param int    $weeks      Only walks starting within this many weeks (0 for no limit)
     * @param \DateTimeImmutable|null $now  For tests; defaults to the current time
     * @param \DateTimeImmutable|null $generatedAt  When the walks were fetched
     */
    public static function feed(array $response, string $groupCode, string $groupName, string $walkPage,
            int $limit = 0, int $weeks = 0, ?\DateTimeImmutable $now = null,
            ?\DateTimeImmutable $generatedAt = null): array {
        $zone = new \DateTimeZone(self::ZONE);
        $now = ($now ?? new \DateTimeImmutable('now'))->setTimezone($zone);
        // Keep the whole of today, so a walk that has already started still shows.
        $from = $now->setTime(0, 0);
        $to = $weeks > 0 ? $from->modify('+' . $weeks . ' weeks') : null;

        $walks = [];
        foreach ($response['data'] ?? [] as $item) {
            if (!is_array($item) || ($item['item_type'] ?? 'group-walk') !== 'group-walk') {
                continue;
            }
            $walk = self::walk($item, $walkPage);
            if ($walk === null) {
                continue;
            }
            $start = new \DateTimeImmutable($walk['start']);
            if ($start < $from || ($to !== null && $start >= $to)) {
                continue;
            }
            $walks[] = $walk;
        }
        usort($walks, fn($a, $b) => (new \DateTimeImmutable($a['start'])) <=> (new \DateTimeImmutable($b['start']))
                    ?: strcmp($a['id'], $b['id']));
        if ($limit > 0) {
            $walks = array_slice($walks, 0, $limit);
        }

        return [
            'version' => 1,
            'group' => ['code' => $groupCode !== '' ? $groupCode : null, 'name' => $groupName],
            'generated_at' => ($generatedAt ?? $now)->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'walks' => $walks,
        ];
    }

    /** One walk, or null if it has no id, title or start time. */
    public static function walk(array $w, string $walkPage = ''): ?array {
        $id = trim((string) ($w['id'] ?? ''));
        $title = self::text($w['title'] ?? null);
        $start = self::time($w['start_date_time'] ?? null);
        if ($id === '' || $title === null || $start === null) {
            return null;
        }

        $cancelled = strtolower((string) ($w['status'] ?? '')) === 'cancelled';
        $walk = [
            'id' => $id,
            'title' => $title,
            'description' => self::plainText($w['description'] ?? null),
            'before_you_go' => self::plainText($w['additional_details'] ?? null),
            'start' => $start,
            'end' => self::time($w['end_date_time'] ?? null),
            'distance_km' => self::number($w['distance_km'] ?? null),
            'distance_miles' => self::number($w['distance_miles'] ?? null),
            'ascent_metres' => isset($w['ascent_metres']) && is_numeric($w['ascent_metres']) ? (int) round($w['ascent_metres']) : null,
            'difficulty' => self::text(is_array($w['difficulty'] ?? null) ? ($w['difficulty']['description'] ?? null) : ($w['difficulty'] ?? null)),
            'shape' => self::text($w['shape'] ?? null),
            'status' => $cancelled ? 'cancelled' : 'confirmed',
            'cancellation_reason' => $cancelled ? self::plainText($w['cancellation_reason'] ?? null) : null,
            'meeting_point' => self::location($w['meeting_location'] ?? null),
            'start_point' => self::location($w['start_location'] ?? null),
            'leader' => self::leader($w['walk_leader'] ?? null),
            'url' => self::walkUrl($id, $walkPage, $w['url'] ?? null),
        ];
        return array_filter($walk, fn($v) => $v !== null);
    }

    /** A UK local time from Walks Manager as ISO 8601 with its offset, e.g. 2026-06-14T10:00:00+01:00. */
    public static function time($value): ?string {
        $value = trim((string) ($value ?? ''));
        if ($value === '') {
            return null;
        }
        try {
            // A time that already says its zone keeps it; otherwise it's UK time.
            $date = new \DateTimeImmutable($value, new \DateTimeZone(self::ZONE));
        } catch (\Exception $e) {
            return null;
        }
        return $date->format(DATE_ATOM);
    }

    /** HTML from Walks Manager as plain text, keeping paragraph and line breaks. */
    public static function plainText($html): ?string {
        $text = (string) ($html ?? '');
        $text = str_replace(["\r\n", "\r"], "\n", $text);
        // Line breaks and the ends of paragraphs, list items and headings become new lines.
        $text = preg_replace('#<br\s*/?>#i', "\n", $text);
        $text = preg_replace('#<li[^>]*>#i', '• ', $text);
        $text = preg_replace('#</(p|div|li|h[1-6]|tr|ul|ol)>#i', "\n", $text);
        $text = strip_tags($text);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = str_replace("\u{00A0}", ' ', $text);
        // Tidy spaces within lines, then allow at most one blank line in a row.
        $lines = array_map(fn($line) => trim(preg_replace('/[ \t]+/', ' ', $line)), explode("\n", $text));
        $text = preg_replace("/\n{3,}/", "\n\n", implode("\n", $lines));
        $text = trim($text);
        return $text === '' ? null : $text;
    }

    private static function text($value): ?string {
        if (!is_string($value) && !is_numeric($value)) {
            return null;
        }
        $value = trim(html_entity_decode(strip_tags((string) $value), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        return $value === '' ? null : $value;
    }

    private static function number($value): ?float {
        return is_numeric($value) ? round((float) $value, 1) : null;
    }

    private static function location($loc): ?array {
        if (!is_array($loc)) {
            return null;
        }
        $lat = $loc['latitude'] ?? null;
        $lon = $loc['longitude'] ?? null;
        $hasPlace = is_numeric($lat) && is_numeric($lon) && !((float) $lat == 0.0 && (float) $lon == 0.0);
        $location = array_filter([
            'description' => self::plainText($loc['description'] ?? null),
            'postcode' => self::text($loc['postcode'] ?? null),
            'grid_ref' => self::text($loc['grid_reference_8'] ?? $loc['grid_reference_6'] ?? null),
            'latitude' => $hasPlace ? (float) $lat : null,
            'longitude' => $hasPlace ? (float) $lon : null,
        ], fn($v) => $v !== null);
        return $location ?: null;
    }

    /** The leader's name and phone (only what they chose to publish), and the central office contact form. */
    private static function leader($leader): ?array {
        if (!is_array($leader)) {
            return null;
        }
        $form = (string) ($leader['email_form'] ?? '');
        $contact = !empty($leader['has_email']) && self::isRamblersPage($form) ? $form : null;
        // The app needs a name to show a leader at all.
        $name = self::text($leader['name'] ?? null);
        if ($name === null) {
            return null;
        }
        return array_filter([
            'name' => $name,
            'phone' => self::text($leader['telephone'] ?? null),
            'contact_url' => $contact,
        ], fn($v) => $v !== null);
    }

    private static function walkUrl(string $id, string $walkPage, $centralUrl): ?string {
        if ($walkPage !== '') {
            return $walkPage . '#walk=' . rawurlencode($id);
        }
        $centralUrl = (string) ($centralUrl ?? '');
        return self::isRamblersPage($centralUrl) ? $centralUrl : null;
    }

    /** Only pass on links to the central Ramblers site, which the app trusts for these. */
    private static function isRamblersPage(string $url): bool {
        $parts = parse_url($url);
        return ($parts['scheme'] ?? '') === 'https' && strtolower($parts['host'] ?? '') === 'www.ramblers.org.uk';
    }
}
