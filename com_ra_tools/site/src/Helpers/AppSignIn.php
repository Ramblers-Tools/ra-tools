<?php

/**
 * Signing in to the Ramblers Tools app: the rules, kept apart from Joomla so they can be tested
 * on their own (tests/AppSignInTest.php). The database side is AppSignInStore, and the pages and
 * endpoints are in Controller/AppController.php.
 *
 * The contract the apps follow is in the app repo's docs/sign-in.md: OAuth 2.0 authorisation code
 * flow with PKCE (S256), a fixed redirect to ramblerstools://signin, one-time codes that last 60
 * seconds, and bearer tokens stored here only as a SHA-256 hash.
 *
 * 14/11/26 RH Created
 */

namespace Ramblers\Component\Ra_tools\Site\Helpers;

defined('_JEXEC') or die;

class AppSignIn {

    /** The apps that may sign in. */
    const CLIENT_IDS = ['ramblers-tools-ios', 'ramblers-tools-android'];

    /** The only address a code is ever sent to. */
    const REDIRECT_URI = 'ramblerstools://signin';

    const CODE_SECONDS = 60;

    /** A token not used for this long stops working. */
    const IDLE_DAYS = 180;

    /** And every token stops working this long after it was made. */
    const MAX_DAYS = 365;

    /** Token endpoint tries per IP address per minute. */
    const TRIES_PER_MINUTE = 10;

    /**
     * Checks the request to the authorise page. Returns null if it's fine, or a message for an
     * error page. Never redirect on a bad request: the redirect address is what's in doubt.
     */
    public static function authorizeProblem(array $params): ?string {
        if (!in_array($params['client_id'] ?? '', self::CLIENT_IDS, true)) {
            return 'This sign-in link is not from the Ramblers Tools app.';
        }
        if (($params['redirect_uri'] ?? '') !== self::REDIRECT_URI) {
            return 'This sign-in link is not from the Ramblers Tools app.';
        }
        if (($params['response_type'] ?? '') !== 'code') {
            return 'This sign-in link is not complete. Please try again from the app.';
        }
        if (($params['code_challenge_method'] ?? '') !== 'S256') {
            return 'This sign-in link is not complete. Please try again from the app.';
        }
        // base64url of a SHA-256 hash: 43 characters.
        if (!preg_match('/^[A-Za-z0-9_-]{43}$/', $params['code_challenge'] ?? '')) {
            return 'This sign-in link is not complete. Please try again from the app.';
        }
        if (!preg_match('/^[A-Za-z0-9._~-]{8,128}$/', $params['state'] ?? '')) {
            return 'This sign-in link is not complete. Please try again from the app.';
        }
        return null;
    }

    /** The address the browser is sent back to the app with. */
    public static function callback(string $state, ?string $code, ?string $error = null): string {
        $query = $code !== null ? ['code' => $code, 'state' => $state] : ['error' => $error ?? 'access_denied', 'state' => $state];
        return self::REDIRECT_URI . '?' . http_build_query($query, '', '&', PHP_QUERY_RFC3986);
    }

    /** RFC 7636: base64url(SHA-256(verifier)) must equal the challenge. */
    public static function verifierMatches(string $verifier, string $challenge): bool {
        if (!preg_match('/^[A-Za-z0-9._~-]{43,128}$/', $verifier)) {
            return false;
        }
        return hash_equals($challenge, self::base64url(hash('sha256', $verifier, true)));
    }

    /** A random secret: 32 bytes (256 bits) as base64url. */
    public static function newSecret(int $bytes = 32): string {
        return self::base64url(random_bytes($bytes));
    }

    /** What's stored in place of a code or token. */
    public static function hash(string $secret): string {
        return hash('sha256', $secret);
    }

    public static function base64url(string $bytes): string {
        return rtrim(strtr(base64_encode($bytes), '+/', '-_'), '=');
    }

    /** Whether a stored code may still be swapped for a token. */
    public static function codeProblem(array $row, array $form, \DateTimeImmutable $now): ?string {
        if (new \DateTimeImmutable($row['expires'], new \DateTimeZone('UTC')) < $now) {
            return 'expired';
        }
        if (!hash_equals($row['client_id'], (string) ($form['client_id'] ?? ''))) {
            return 'client';
        }
        if (!hash_equals($row['redirect_uri'], (string) ($form['redirect_uri'] ?? ''))) {
            return 'redirect';
        }
        if (!self::verifierMatches((string) ($form['code_verifier'] ?? ''), $row['code_challenge'])) {
            return 'verifier';
        }
        return null;
    }

    /** Whether a stored token still works. Dates are UTC 'Y-m-d H:i:s'. */
    public static function tokenUsable(array $row, \DateTimeImmutable $now): bool {
        if (!empty($row['revoked'])) {
            return false;
        }
        $utc = new \DateTimeZone('UTC');
        $created = new \DateTimeImmutable($row['created'], $utc);
        if ($created->modify('+' . self::MAX_DAYS . ' days') < $now) {
            return false;
        }
        $lastUsed = !empty($row['last_used']) ? new \DateTimeImmutable($row['last_used'], $utc) : $created;
        return $lastUsed->modify('+' . self::IDLE_DAYS . ' days') >= $now;
    }

    /** The bearer token from the request headers, or ''. Never from the query string. */
    public static function bearer(array $server): string {
        foreach (['HTTP_AUTHORIZATION', 'REDIRECT_HTTP_AUTHORIZATION'] as $name) {
            if (!empty($server[$name]) && preg_match('/^Bearer\s+([A-Za-z0-9_-]{20,200})$/i', trim($server[$name]), $m)) {
                return $m[1];
            }
        }
        // For hosts that strip the Authorization header before PHP sees it.
        $alt = trim((string) ($server['HTTP_X_RAMBLERS_TOKEN'] ?? ''));
        return preg_match('/^[A-Za-z0-9_-]{20,200}$/', $alt) ? $alt : '';
    }

    /** A short description of the device, from its user agent, for the member's list of sign-ins. */
    public static function deviceName(string $userAgent): string {
        if (stripos($userAgent, 'iPad') !== false) {
            return 'iPad';
        }
        if (stripos($userAgent, 'iPhone') !== false || stripos($userAgent, 'Darwin') !== false || stripos($userAgent, 'CFNetwork') !== false) {
            return 'iPhone or iPad';
        }
        if (stripos($userAgent, 'Android') !== false || stripos($userAgent, 'Dalvik') !== false) {
            return 'Android phone';
        }
        return 'Phone';
    }
}
