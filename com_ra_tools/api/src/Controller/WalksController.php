<?php

/**
 * The walks feed for the Ramblers Tools app: GET /api/index.php/v1/ra_tools/walks
 *
 * Public, no token needed: it only shows what is already public on Walks Manager. The Walks
 * Manager API key stays on the server. It lists every published walk from today onwards for the
 * group in "Default Group code", like the group's programme page, with cancelled walks included
 * so the app can show them.
 *
 * Walks Manager is asked at most once every 15 minutes. If it can't be reached, the last walks it
 * sent are served instead, so the app keeps working.
 *
 * The format is described in the app's docs/walks-feed.md.
 *
 * 14/11/26 RH Created
 * 14/11/26 RH Adds the group's walk types and changed-walk marker from the ra_tweaks settings
 */

namespace Ramblers\Component\Ra_tools\Api\Controller;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Http\HttpFactory;
use Joomla\CMS\MVC\Controller\BaseController;
use Joomla\CMS\Plugin\PluginHelper;
use Joomla\Database\DatabaseInterface;
use Joomla\Registry\Registry;
use Ramblers\Component\Ra_tools\Site\Helpers\JsonHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\WalksFeedMapper;

class WalksController extends BaseController {

    /** How long a copy of the walks is used before Walks Manager is asked again. */
    const FRESH_SECONDS = 900;

    const WALKS_MANAGER = 'https://walks-manager.ramblers.org.uk/api/volunteers/walksevents';

    public function feed() {
        $params = ComponentHelper::getParams('com_ra_tools');
        $group = strtoupper(trim((string) $params->get('default_group', '')));
        if (!preg_match('/^[A-Z]{2}[0-9]{2}$/', $group)) {
            $this->send(503, ['error' => 'This site has no group set up for the walks feed.']);
        }

        $cached = $this->readCache($group);
        $copy = $cached;
        if ($cached === null || time() - $cached['fetched_at'] >= self::FRESH_SECONDS) {
            $response = $this->fetch($group);
            if ($response !== null) {
                $copy = ['fetched_at' => time(), 'response' => $response];
                $this->writeCache($group, $copy);
            }
        }
        if ($copy === null) {
            $this->send(502, ['error' => 'Walks Manager could not be reached. Please try again later.']);
        }

        $feed = WalksFeedMapper::feed(
                $copy['response'],
                $group,
                $this->groupName($group, $copy['response']),
                trim((string) $params->get('app_walks_page', '')),
                0,
                0,
                null,
                new \DateTimeImmutable('@' . $copy['fetched_at'])
        );
        $tweaks = $this->tweaksSettings();
        $feed['walk_types'] = WalksFeedMapper::walkTypes($tweaks);
        $feed['changed_walks'] = WalksFeedMapper::changedWalks($tweaks);
        $this->send(200, $feed);
    }

    /** The ra_tweaks plugin's settings, or null when it isn't installed and enabled. */
    private function tweaksSettings(): ?array {
        try {
            $plugin = PluginHelper::getPlugin('system', 'ra_tweaks');
        } catch (\Throwable $e) {
            return null;
        }
        if (!is_object($plugin) || !isset($plugin->params)) {
            return null;
        }
        return (new Registry($plugin->params))->toArray();
    }

    /** The group's walks from Walks Manager, or null if it couldn't be reached. */
    private function fetch(string $group): ?array {
        $jsonHelper = new JsonHelper;
        // The API sites record has been set up under both addresses.
        $key = trim((string) $jsonHelper->lookupApiKey());
        if ($key === '') {
            $key = trim((string) $jsonHelper->lookupApiKey('https://ramblers.org'));
        }
        if ($key === '') {
            return null;
        }

        $url = self::WALKS_MANAGER . '?' . http_build_query([
                    'types' => 'group-walk',
                    'groups' => $group,
                    'api-key' => $key,
        ]);
        try {
            $http = HttpFactory::getHttp();
            $result = $http->get($url, ['Accept' => 'application/json'], 20);
        } catch (\Throwable $e) {
            // Never pass on the message: it may include the address, and so the key.
            return null;
        }
        if ((int) $result->code !== 200) {
            return null;
        }
        $data = json_decode((string) $result->body, true);
        return is_array($data) && isset($data['data']) && is_array($data['data']) ? $data : null;
    }

    private function groupName(string $group, array $response): string {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true)
                    ->select($db->quoteName('name'))
                    ->from($db->quoteName('#__ra_groups'))
                    ->where($db->quoteName('code') . ' = :code')
                    ->bind(':code', $group);
            $name = trim((string) $db->setQuery($query)->loadResult());
            if ($name !== '') {
                return $name;
            }
        } catch (\Throwable $e) {
            // Fall back to the name Walks Manager gives.
        }
        $name = trim((string) ($response['data'][0]['group_name'] ?? ''));
        return $name !== '' ? $name : $group;
    }

    private function cacheFile(string $group): string {
        return JPATH_CACHE . '/com_ra_tools/walks-feed-' . strtolower($group) . '.json';
    }

    private function readCache(string $group): ?array {
        $file = $this->cacheFile($group);
        if (!is_file($file)) {
            return null;
        }
        $copy = json_decode((string) @file_get_contents($file), true);
        return is_array($copy) && isset($copy['fetched_at'], $copy['response']['data']) ? $copy : null;
    }

    private function writeCache(string $group, array $copy): void {
        $file = $this->cacheFile($group);
        if (!is_dir(dirname($file))) {
            @mkdir(dirname($file), 0755, true);
        }
        // Write then rename, so a request at the same moment never reads half a file.
        $temp = $file . '.' . getmypid() . '.tmp';
        if (@file_put_contents($temp, json_encode($copy)) !== false) {
            @rename($temp, $file);
        }
    }

    private function send(int $status, array $body) {
        $app = Factory::getApplication();
        $app->setHeader('Status', (string) $status, true);
        $app->setHeader('Content-Type', 'application/json; charset=utf-8', true);
        $app->setHeader('Cache-Control', $status === 200 ? 'public, max-age=' . self::FRESH_SECONDS : 'no-store', true);
        $app->sendHeaders();
        echo json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $app->close();
    }
}
