<?php

/**
 * Signing in to the Ramblers Tools app (iPhone and Android) with a group website account.
 *
 *   index.php?option=com_ra_tools&task=app.authorize    the page the app opens: log in, then Allow
 *   index.php?option=com_ra_tools&task=app.allow        (POST) the Allow / Cancel form
 *   index.php?option=com_ra_tools&task=app.token&format=json     (POST) a one-time code for a token
 *   index.php?option=com_ra_tools&task=app.bookings&format=json  the member's bookings (bearer token)
 *   index.php?option=com_ra_tools&task=app.signout&format=json   (POST) revokes the token
 *   index.php?option=com_ra_tools&task=app.signins       the member's app sign-ins, to remove any
 *   index.php?option=com_ra_tools&task=app.revoke        (POST) removes one of them
 *
 * The contract the apps follow is the app repo's docs/sign-in.md. The JSON endpoints take the
 * bearer token only: they ignore the Joomla session, send no CORS headers and never redirect.
 *
 * 14/11/26 RH Created
 */

namespace Ramblers\Component\Ra_tools\Site\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Factory;
use Joomla\CMS\Language\Text;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Session\Session;
use Joomla\CMS\Uri\Uri;
use Joomla\CMS\User\UserFactoryInterface;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\AppBookings;
use Ramblers\Component\Ra_tools\Site\Helpers\AppSignIn;
use Ramblers\Component\Ra_tools\Site\Helpers\AppSignInStore;

class AppController extends BaseController {

    /** The fields of the app's sign-in request, carried from the authorise page to the form. */
    const REQUEST_FIELDS = ['response_type', 'client_id', 'redirect_uri', 'state', 'code_challenge', 'code_challenge_method'];

    /** GET: the page the app opens in its in-app browser. */
    public function authorize() {
        $params = $this->requestFields('get');
        $problem = AppSignIn::authorizeProblem($params);
        if ($problem !== null) {
            return $this->page('error', ['message' => $problem]);
        }
        $user = $this->app->getIdentity();
        if (!$user || $user->guest) {
            return $this->page('login', [
                        'loginUrl' => Route::_('index.php?option=com_users&view=login&tmpl=component&return=' . base64_encode($this->authorizeUrl($params)), false),
                        'ssoUrl' => $this->ssoUrl(),
            ]);
        }
        return $this->page('consent', [
                    'params' => $params,
                    'name' => $user->name,
                    'logoutUrl' => Route::_('index.php?option=com_users&task=user.logout&' . Session::getFormToken() . '=1&return=' . base64_encode($this->authorizeUrl($params)), false),
        ]);
    }

    /** POST: Allow or Cancel. */
    public function allow() {
        if (!Session::checkToken('post')) {
            return $this->page('error', ['message' => 'This page has expired. Please go back to the app and sign in again.']);
        }
        $params = $this->requestFields('post');
        $problem = AppSignIn::authorizeProblem($params);
        if ($problem !== null) {
            return $this->page('error', ['message' => $problem]);
        }
        $user = $this->app->getIdentity();
        if (!$user || $user->guest) {
            $this->app->redirect($this->authorizeUrl($params));
        }
        if ($this->input->post->getCmd('decision') !== 'allow') {
            return $this->toApp(AppSignIn::callback($params['state'], null, 'access_denied'));
        }
        $code = (new AppSignInStore)->createCode((int) $user->id, $params['client_id'], $params['code_challenge']);
        return $this->toApp(AppSignIn::callback($params['state'], $code));
    }

    /** POST: swaps a one-time code for a token. */
    public function token() {
        $store = new AppSignInStore;
        if ($this->input->getMethod() !== 'POST') {
            return $this->json(405, ['error' => 'invalid_request']);
        }
        if (!$store->allowTry((string) ($_SERVER['REMOTE_ADDR'] ?? ''))) {
            return $this->json(429, ['error' => 'slow_down']);
        }
        $post = $this->input->post;
        $form = [
            'grant_type' => $post->getString('grant_type'),
            'code' => $post->getString('code'),
            'code_verifier' => $post->getString('code_verifier'),
            'redirect_uri' => $post->getString('redirect_uri'),
            'client_id' => $post->getString('client_id'),
        ];
        if ($form['grant_type'] !== 'authorization_code' || $form['code'] === '') {
            return $this->json(400, ['error' => 'invalid_grant']);
        }
        $row = $store->takeCode($form['code']);
        if ($row === null || AppSignIn::codeProblem($row, $form, new \DateTimeImmutable('now', new \DateTimeZone('UTC'))) !== null) {
            return $this->json(400, ['error' => 'invalid_grant']);
        }
        $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById((int) $row['user_id']);
        if (!$user || !$user->id || $user->block) {
            return $this->json(400, ['error' => 'invalid_grant']);
        }
        $agent = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        $token = $store->createToken((int) $user->id, $row['client_id'], $agent);
        $store->log('AS', (int) $user->id, 'App sign-in: ' . $user->name . ', ' . AppSignIn::deviceName($agent));
        return $this->json(200, [
                    'access_token' => $token,
                    'token_type' => 'Bearer',
                    'member' => ['name' => $user->name],
        ]);
    }

    /** GET: the signed-in member's bookings. */
    public function bookings() {
        $userId = (new AppSignInStore)->userForToken(AppSignIn::bearer($_SERVER));
        if ($userId === null) {
            return $this->json(401, ['error' => 'invalid_token']);
        }
        $user = Factory::getContainer()->get(UserFactoryInterface::class)->loadUserById($userId);
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        return $this->json(200, [
                    'member' => ['name' => $user->name],
                    'generated_at' => gmdate('Y-m-d\TH:i:s\Z'),
                    'bookings' => (new AppBookings($db))->forUser($userId, Uri::root()),
        ]);
    }

    /** POST: the app signing out. Answers 200 even if the token was already gone. */
    public function signout() {
        if ($this->input->getMethod() !== 'POST') {
            return $this->json(405, ['error' => 'invalid_request']);
        }
        $store = new AppSignInStore;
        $userId = $store->revokeToken(AppSignIn::bearer($_SERVER));
        if ($userId !== null) {
            $store->log('AO', $userId, 'App sign-out');
        }
        return $this->json(200, new \stdClass);
    }

    /** GET: the member's app sign-ins, each with a Remove button. */
    public function signins() {
        $user = $this->app->getIdentity();
        if (!$user || $user->guest) {
            $return = base64_encode(Uri::root() . 'index.php?option=com_ra_tools&task=app.signins');
            $this->app->redirect(Route::_('index.php?option=com_users&view=login&return=' . $return, false));
        }
        $rows = (new AppSignInStore)->signIns((int) $user->id);
        foreach ($rows as &$row) {
            $row['device'] = AppSignIn::deviceName((string) $row['user_agent']);
        }
        return $this->page('signins', ['signIns' => $rows], false);
    }

    /** POST: removes one of the member's app sign-ins, or all of them. */
    public function revoke() {
        $user = $this->app->getIdentity();
        if (!Session::checkToken('post') || !$user || $user->guest) {
            $this->app->redirect(Route::_('index.php?option=com_ra_tools&task=app.signins', false));
        }
        $id = $this->input->post->getInt('id', 0);
        $store = new AppSignInStore;
        $count = $store->revoke((int) $user->id, $id > 0 ? $id : null);
        if ($count > 0) {
            $store->log('AR', (int) $user->id, 'App sign-in removed on the website (' . $count . ')');
            $this->app->enqueueMessage($count === 1 ? 'The app has been signed out on that device.' : 'The app has been signed out on all your devices.');
        }
        $this->app->redirect(Route::_('index.php?option=com_ra_tools&task=app.signins', false));
    }

    // ---------------------------------------------------------------------------------------

    private function requestFields(string $method): array {
        $source = $method === 'post' ? $this->input->post : $this->input->get;
        $fields = [];
        foreach (self::REQUEST_FIELDS as $name) {
            $fields[$name] = $source->getString($name, '');
        }
        return $fields;
    }

    /** The authorise page for this request, on this site only. */
    private function authorizeUrl(array $params): string {
        return Uri::root() . 'index.php?option=com_ra_tools&task=app.authorize&'
                . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * The RA SSO login link, when RA SSO is set up on this site. Started from this page, RA SSO
     * brings the member back here afterwards (it returns to the page the link was on).
     */
    private function ssoUrl(): ?string {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $prefix = $db->getPrefix();
            if (!in_array($prefix . 'ra_sso_config', $db->getTableList(), true)) {
                return null;
            }
            $query = $db->getQuery(true)
                    ->select(['appname', 'sso_enable', 'client_id', 'app_scope'])
                    ->from($db->quoteName('#__ra_sso_config'))
                    ->where($db->quoteName('id') . ' = 1');
            $config = $db->setQuery($query)->loadAssoc();
        } catch (\Throwable $e) {
            return null;
        }
        if (!$config || (int) $config['sso_enable'] !== 1 || empty($config['client_id']) || empty($config['app_scope'])) {
            return null;
        }
        return Uri::root() . 'api/index.php/v1/ra-sso-login?rarequest=oauthredirect&app_name=' . rawurlencode((string) $config['appname']);
    }

    /**
     * Shows one of the sign-in pages, which may never be shown inside another site's frame.
     * Without the site's menus and modules: on a phone the template puts them all above the
     * page, and the member would have to scroll past them to find the sign-in.
     */
    private function page(string $layout, array $data, bool $bare = true) {
        if ($bare) {
            $this->app->getInput()->set('tmpl', 'component');
        }
        $this->app->setHeader('X-Frame-Options', 'DENY', true);
        $this->app->setHeader('Content-Security-Policy', "frame-ancestors 'none'", true);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->setHeader('Referrer-Policy', 'same-origin', true);
        $view = $this->getView('App', 'html', 'Site');
        $view->setLayout($layout);
        foreach ($data as $key => $value) {
            $view->$key = $value;
        }
        $view->display();
        return $this;
    }

    /** Sends the browser back to the app. Only ever to ramblerstools://signin. */
    private function toApp(string $url) {
        if (strpos($url, AppSignIn::REDIRECT_URI . '?') !== 0) {
            return $this->page('error', ['message' => 'Something went wrong. Please try again from the app.']);
        }
        $this->app->setHeader('Status', '303', true);
        $this->app->setHeader('Location', $url, true);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->sendHeaders();
        $this->app->close();
    }

    private function json(int $status, $body) {
        $this->app->setHeader('Status', (string) $status, true);
        $this->app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $this->app->setHeader('Cache-Control', 'no-store', true);
        $this->app->setHeader('Pragma', 'no-cache', true);
        $this->app->sendHeaders();
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $this->app->close();
    }
}
