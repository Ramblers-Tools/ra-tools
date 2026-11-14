<?php

// Checks signing in to the Ramblers Tools app. Run with: php tests/AppSignInTest.php

define('_JEXEC', 1);
require dirname(__DIR__) . '/com_ra_tools/site/src/Helpers/AppSignIn.php';
require dirname(__DIR__) . '/com_ra_tools/site/src/Helpers/AppBookings.php';

use Ramblers\Component\Ra_tools\Site\Helpers\AppBookings;
use Ramblers\Component\Ra_tools\Site\Helpers\AppSignIn;

function check($condition, string $what): void {
    if (!$condition) {
        throw new RuntimeException('Failed: ' . $what);
    }
    echo "ok  $what\n";
}

$utc = new DateTimeZone('UTC');
$now = new DateTimeImmutable('2026-11-14 12:00:00', $utc);

// RFC 7636 appendix B.
$verifier = 'dBjftJeZ4CVP-mB92K27uhbUJU1p1r_wW1gFWFOEjXk';
$challenge = 'E9Melhoa2OwvFrEMTJguCHaoeK1t8URWbuGJSstw-cM';
check(AppSignIn::verifierMatches($verifier, $challenge), 'the RFC 7636 example verifier matches');
check(!AppSignIn::verifierMatches($verifier . 'x', $challenge), 'a different verifier does not');
check(!AppSignIn::verifierMatches('short', $challenge), 'a too-short verifier is refused');

$good = [
    'response_type' => 'code', 'client_id' => 'ramblers-tools-ios', 'redirect_uri' => 'ramblerstools://signin',
    'state' => 'abcDEF123_-xyz', 'code_challenge' => $challenge, 'code_challenge_method' => 'S256',
];
check(AppSignIn::authorizeProblem($good) === null, 'a request from the iPhone app is accepted');
check(AppSignIn::authorizeProblem(['client_id' => 'ramblers-tools-android'] + $good) === null, 'and from the Android app');
foreach ([
    'client_id' => 'someone-else', 'redirect_uri' => 'https://evil.example/', 'response_type' => 'token',
    'code_challenge_method' => 'plain', 'code_challenge' => 'too-short', 'state' => '',
] as $field => $bad) {
    check(AppSignIn::authorizeProblem([$field => $bad] + $good) !== null, "a bad $field is refused");
}

check(AppSignIn::callback('s1', 'c+d') === 'ramblerstools://signin?code=c%2Bd&state=s1', 'the callback carries the code and state');
check(AppSignIn::callback('s1', null) === 'ramblerstools://signin?error=access_denied&state=s1', 'Cancel sends access_denied');

$secret = AppSignIn::newSecret();
check(strlen($secret) === 43 && preg_match('/^[A-Za-z0-9_-]+$/', $secret), 'secrets are 256-bit base64url');
check(AppSignIn::newSecret() !== $secret, 'and different every time');
check(strlen(AppSignIn::hash($secret)) === 64, 'hashes are SHA-256 hex');

$code = ['expires' => '2026-11-14 12:00:30', 'client_id' => 'ramblers-tools-ios', 'redirect_uri' => 'ramblerstools://signin', 'code_challenge' => $challenge];
$form = ['client_id' => 'ramblers-tools-ios', 'redirect_uri' => 'ramblerstools://signin', 'code_verifier' => $verifier];
check(AppSignIn::codeProblem($code, $form, $now) === null, 'a fresh code with the right verifier is swapped');
check(AppSignIn::codeProblem($code, $form, $now->modify('+31 seconds')) === 'expired', 'an expired code is not');
check(AppSignIn::codeProblem($code, ['client_id' => 'ramblers-tools-android'] + $form, $now) === 'client', 'nor one for a different app');
check(AppSignIn::codeProblem($code, ['code_verifier' => str_repeat('a', 43)] + $form, $now) === 'verifier', 'nor one with the wrong verifier');

$token = ['created' => '2026-01-01 00:00:00', 'last_used' => '2026-11-01 00:00:00', 'revoked' => null];
check(AppSignIn::tokenUsable($token, $now), 'a token in use works');
check(!AppSignIn::tokenUsable(['revoked' => '2026-11-02 00:00:00'] + $token, $now), 'a revoked token does not');
check(!AppSignIn::tokenUsable(['last_used' => '2026-05-01 00:00:00'] + $token, $now), 'nor one unused for over 180 days');
check(!AppSignIn::tokenUsable(['created' => '2025-11-01 00:00:00'] + $token, $now), 'nor one over a year old');

$t = str_repeat('A', 43);
check(AppSignIn::bearer(['HTTP_AUTHORIZATION' => "Bearer $t"]) === $t, 'the token is read from Authorization');
check(AppSignIn::bearer(['REDIRECT_HTTP_AUTHORIZATION' => "Bearer $t"]) === $t, 'or where Apache moved it');
check(AppSignIn::bearer(['HTTP_X_RAMBLERS_TOKEN' => $t]) === $t, 'or from X-Ramblers-Token');
check(AppSignIn::bearer(['HTTP_AUTHORIZATION' => "Basic $t"]) === '', 'but not from Basic auth');
check(AppSignIn::bearer([]) === '', 'and nothing means no token');

check(AppSignIn::deviceName('RamblersTools/1 CFNetwork/1 Darwin/25') === 'iPhone or iPad', 'iOS requests are named');
check(AppSignIn::deviceName('Dalvik/2.1.0 (Linux; U; Android 15)') === 'Android phone', 'Android requests are named');

// Bookings
$row = fn(array $changes = []) => $changes + [
    'id' => '7', 'event_id' => '42', 'num_places' => '2', 'partner' => '', 'state' => '1', 'is_paid' => '0',
    'title' => 'Christmas &amp; dinner', 'event_date' => '2026-12-04', 'event_date_end' => null, 'event_time' => '19:00',
    'location' => '<p>The Inn</p>', 'requires_payment' => '1', 'event_state' => '1',
];
$b = AppBookings::booking($row(), ['Sam'], 'https://example.org/');
check($b['event_title'] === 'Christmas & dinner' && $b['location'] === 'The Inn', 'titles and locations are plain text');
check($b['start'] === '2026-12-04T19:00:00+00:00', 'the start is UK time');
check($b['status'] === 'confirmed' && $b['places'] === 2 && $b['guests'] === ['Sam'], 'status, places and guests come through');
check($b['requires_payment'] === true && $b['is_paid'] === false, 'payment is shown');
check($b['url'] === 'https://example.org/index.php?option=com_ra_events&view=event&id=42', 'the event page is linked');
foreach (['0' => 'requested', '-1' => 'waiting_list', '-2' => 'cancelled', '9' => 'requested'] as $state => $status) {
    check(AppBookings::booking($row(['state' => $state]), [], '')['status'] === $status, "state $state is $status");
}
check(AppBookings::booking($row(['event_state' => '-2']), [], '')['status'] === 'cancelled', 'a cancelled event cancels the booking');
check(AppBookings::booking($row(['partner' => 'Pat']), [], '')['guests'] === ['Pat'], 'an older booking\'s partner is a guest');
check(!array_key_exists('is_paid', AppBookings::booking($row(['requires_payment' => '0']), [], '')), 'no payment, no paid flag');
$list = AppBookings::bookings([$row(['id' => '1', 'state' => '-2']), $row(['id' => '2', 'state' => '0']), $row(['id' => '3', 'event_id' => '43', 'state' => '-2'])], [], '');
check(array_column($list, 'id') === ['2', '3'], 'a cancelled booking is hidden when the event was booked again');

echo "All passed\n";
