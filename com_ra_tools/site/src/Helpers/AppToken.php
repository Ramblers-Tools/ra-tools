<?php

/**
 * The one place that checks the Ramblers Tools app's bearer token, for every component that
 * answers the app (com_ra_tools bookings, com_ra_footpaths records). The rules are in AppSignIn
 * and the app repo's docs/sign-in.md.
 *
 *   $user = AppToken::user('footpaths', $error);
 *   if ($user === null) { answer 401 when $error is 'invalid_token', 403 when 'insufficient_scope' }
 *
 * The token comes from the Authorization header (Bearer), or X-Ramblers-Token for hosts that strip
 * Authorization; never from the query string. The Joomla session is ignored: a logged-in browser
 * gets nothing without a token. Tokens are never logged.
 *
 * 18/11/26 RH Created (4.4.0)
 */

namespace Ramblers\Component\Ra_tools\Site\Helpers;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserFactoryInterface;

final class AppToken {

    /**
     * The member signed in with this request's token, or null with $error set to 'invalid_token'
     * (no token, or an unknown, expired or revoked one, or a blocked member) or
     * 'insufficient_scope' (the token works but doesn't include $scope). Also notes when the token
     * was last used.
     */
    public static function user(?string $scope = null, ?string &$error = null): ?User {
        $error = null;
        $row = (new AppSignInStore)->tokenFor(AppSignIn::bearer($_SERVER));
        if ($row === null) {
            $error = 'invalid_token';
            return null;
        }
        if ($scope !== null && !AppSignIn::hasScope($row['scope'], $scope)) {
            $error = 'insufficient_scope';
            return null;
        }
        $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($row['user_id']);
        if (!$user || !$user->id || $user->block) {
            $error = 'invalid_token';
            return null;
        }
        return $user;
    }
}
