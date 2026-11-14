<?php

// Checks the walks feed for the Ramblers Tools app. Run with: php tests/WalksFeedMapperTest.php

define('_JEXEC', 1);
require dirname(__DIR__) . '/com_ra_tools/site/src/Helpers/WalksFeedMapper.php';

use Ramblers\Component\Ra_tools\Site\Helpers\WalksFeedMapper;

function check($condition, string $what): void {
    if (!$condition) {
        throw new RuntimeException('Failed: ' . $what);
    }
}

function walk(array $changes = []): array {
    return array_replace_recursive([
        'item_type' => 'group-walk',
        'id' => '100000001',
        'title' => 'Bollington &amp; Kerridge Ridge',
        'description' => "<p>A walk along the ridge.</p>\n<p>Views of&nbsp;White Nancy.</p>",
        'additional_details' => '<p>No need to book.</p><ul><li>Bring lunch</li><li>Boots</li></ul>',
        'start_date_time' => '2026-06-14T10:00:00',
        'end_date_time' => '2026-06-14T15:30:00',
        'start_location' => [
            'latitude' => 53.29, 'longitude' => -2.10,
            'grid_reference_6' => 'SJ935775', 'grid_reference_8' => 'SJ93557750',
            'postcode' => 'SK10 5JH', 'description' => "Pool Bank car park.\r\nFree parking.",
        ],
        'meeting_location' => null,
        'distance_km' => 12.87, 'distance_miles' => 8,
        'ascent_metres' => 320.4,
        'difficulty' => ['code' => 'moderate', 'description' => 'Moderate'],
        'shape' => 'circular',
        'walk_leader' => [
            'name' => 'Sam P.', 'telephone' => '01625 000000', 'has_email' => true,
            'email_form' => 'https://www.ramblers.org.uk/go-walking/group-walks/bollington#contact',
        ],
        'url' => 'https://www.ramblers.org.uk/go-walking/group-walks/bollington',
        'status' => 'confirmed',
        'cancellation_reason' => '',
    ], $changes);
}

$page = 'https://example.org/walks/programme';

// One walk, all the fields.
$w = WalksFeedMapper::walk(walk(), $page);
check($w['id'] === '100000001', 'id');
check($w['title'] === 'Bollington & Kerridge Ridge', 'title entities decoded');
check($w['description'] === "A walk along the ridge.\n\nViews of White Nancy.", 'description as plain text: ' . json_encode($w['description']));
check($w['before_you_go'] === "No need to book.\n• Bring lunch\n• Boots", 'before you go: ' . json_encode($w['before_you_go']));
check($w['start'] === '2026-06-14T10:00:00+01:00', 'summer time start has +01:00');
check($w['end'] === '2026-06-14T15:30:00+01:00', 'end');
check($w['distance_km'] === 12.9 && $w['distance_miles'] === 8.0, 'distances');
check($w['ascent_metres'] === 320, 'ascent');
check($w['difficulty'] === 'Moderate', 'difficulty description');
check($w['shape'] === 'circular', 'shape');
check($w['status'] === 'confirmed' && !isset($w['cancellation_reason']), 'confirmed, no reason');
check(!isset($w['meeting_point']), 'no meeting point');
check($w['start_point'] === [
    'description' => "Pool Bank car park.\nFree parking.", 'postcode' => 'SK10 5JH',
    'grid_ref' => 'SJ93557750', 'latitude' => 53.29, 'longitude' => -2.10,
], 'start point: ' . json_encode($w['start_point']));
check($w['leader'] === [
    'name' => 'Sam P.', 'phone' => '01625 000000',
    'contact_url' => 'https://www.ramblers.org.uk/go-walking/group-walks/bollington#contact',
], 'leader');
check($w['url'] === $page . '#walk=100000001', 'link to the group site');

// Winter time.
$w = WalksFeedMapper::walk(walk(['start_date_time' => '2026-11-15T09:30:00']), $page);
check($w['start'] === '2026-11-15T09:30:00+00:00', 'winter start has +00:00');

// Cancelled walks keep their reason.
$w = WalksFeedMapper::walk(walk(['status' => 'cancelled', 'cancellation_reason' => '<p>Flooding</p>']), $page);
check($w['status'] === 'cancelled' && $w['cancellation_reason'] === 'Flooding', 'cancelled with reason');

// A leader with no email gets no contact link, and links elsewhere are dropped.
$w = WalksFeedMapper::walk(walk(['walk_leader' => ['has_email' => false, 'telephone' => null]]), $page);
check(!isset($w['leader']['contact_url']) && !isset($w['leader']['phone']), 'no contact without email');
$w = WalksFeedMapper::walk(walk(['walk_leader' => ['email_form' => 'http://evil.example/#contact']]), $page);
check(!isset($w['leader']['contact_url']), 'contact link must be www.ramblers.org.uk over https');

// No name, no leader (the app needs one).
$w = WalksFeedMapper::walk(walk(['walk_leader' => ['name' => ' ']]), $page);
check(!isset($w['leader']), 'leader without a name is left out');

// Without a group page, the link goes to the walk on www.ramblers.org.uk.
$w = WalksFeedMapper::walk(walk(), '');
check($w['url'] === 'https://www.ramblers.org.uk/go-walking/group-walks/bollington', 'central link fallback');

// Missing essentials: no walk.
check(WalksFeedMapper::walk(walk(['title' => '']), $page) === null, 'no title, no walk');
check(WalksFeedMapper::walk(walk(['start_date_time' => null]), $page) === null, 'no start, no walk');

// The whole feed: past walks dropped, sorted, limited, lookahead applied, cancelled kept.
$now = new DateTimeImmutable('2026-06-14T12:00:00+01:00');
$response = ['summary' => [], 'data' => [
    walk(['id' => '4', 'start_date_time' => '2026-07-20T10:00:00']),
    walk(['id' => '1', 'start_date_time' => '2026-06-13T10:00:00']),   // yesterday
    walk(['id' => '2', 'start_date_time' => '2026-06-14T10:00:00']),   // earlier today: kept
    walk(['id' => '3', 'start_date_time' => '2026-06-20T10:00:00', 'status' => 'cancelled']),
    walk(['id' => '5', 'start_date_time' => '2026-12-01T10:00:00']),   // beyond 12 weeks
    ['item_type' => 'group-event', 'id' => '9', 'title' => 'Social', 'start_date_time' => '2026-06-15T19:00:00'],
]];
$feed = WalksFeedMapper::feed($response, 'XX01', 'Test Ramblers', $page, 0, 12, $now);
check($feed['version'] === 1, 'version');
check($feed['group'] === ['code' => 'XX01', 'name' => 'Test Ramblers'], 'group');
check($feed['generated_at'] === '2026-06-14T11:00:00Z', 'generated_at in UTC: ' . $feed['generated_at']);
check(array_column($feed['walks'], 'id') === ['2', '3', '4'], 'filtered and sorted: ' . json_encode(array_column($feed['walks'], 'id')));
$feed = WalksFeedMapper::feed($response, 'XX01', 'Test Ramblers', $page, 2, 0, $now);
check(array_column($feed['walks'], 'id') === ['2', '3'], 'limit');

// Valid JSON with no slashes escaped.
check(strpos(json_encode($feed, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE), '\\/') === false, 'json');

// Walk types from the ra_tweaks settings (East Cheshire's live figures).
$types = WalksFeedMapper::walkTypes([
    'walk_type_labels' => '1', 'stroller_max' => '4.9', 'short_max' => '6.9', 'medium_max' => '10.9',
    'stroller_colour' => '#4CAF50', 'short_colour' => '#2196f3', 'medium_colour' => '#f90',
    'long_colour' => 'red', 'evening_colour' => '#5e35b1',
]);
check(array_column($types['bands'], 'name') === ['Stroller', 'Short', 'Medium', 'Long'], 'band names');
check(array_column($types['bands'], 'max_miles') === [4.9, 6.9, 10.9], 'band maximums');
check(!isset($types['bands'][3]['max_miles']), 'Long has no maximum');
check($types['bands'][0]['colour'] === '#4caf50', 'colour lower-cased');
check($types['bands'][2]['colour'] === '#ff9900', 'short colour expanded');
check($types['bands'][3]['colour'] === '#e53935', 'bad colour falls back');
check($types['evening'] === ['name' => 'Evening', 'colour' => '#5e35b1', 'from' => '17:00'], 'evening');
check(WalksFeedMapper::walkTypes(null) === ['bands' => []], 'no ra_tweaks, no types');
check(WalksFeedMapper::walkTypes(['walk_type_labels' => '0']) === ['bands' => []], 'labels off, no types');
check(array_column(WalksFeedMapper::walkTypes([])['bands'], 'max_miles') === [4.0, 7.9, 10.9], 'plugin defaults');
check(WalksFeedMapper::walkTypes(['short_max' => '3'])['bands'] === [], 'out of order, no types');
check(strpos(json_encode($types), '"max_miles":4.9') !== false, 'miles as JSON numbers');

// The changed-walk marker from the ra_tweaks settings.
check(WalksFeedMapper::changedWalks(['marker' => '***', 'colour' => '#f08050']) === ['marker' => '***', 'colour' => '#f08050'], 'changed marker');
check(WalksFeedMapper::changedWalks([]) === ['marker' => '***', 'colour' => '#f08050'], 'changed marker defaults');
check(WalksFeedMapper::changedWalks(['marker' => ' ']) === ['marker' => ''], 'empty marker, off');
check(WalksFeedMapper::changedWalks(null) === ['marker' => ''], 'no ra_tweaks, off');

echo "WalksFeedMapperTest passed\n";
