<?php

/**
 * Various common functions used to access Json feeds
 *
 * @version     3.5.5
 * @package     com_ra_tools
 * @author      Charlie Bigley <charlie@bigley.me.uk>
 *
 * 16/06/23 CB Created
 * 21/04/25 CB get API from configuration settings, added support for Organisation feed
 * 18/08/25 CB get API key from ra_api_sites
 * 11/03/26 CB added displayFields and fetchApiData 
 25/03/25 CB correct getRemoteData
 */

namespace Ramblers\Component\Ra_tools\Site\Helpers;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Uri\Uri;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;

class JsonHelper {

    private static $messages = [];
 //   private $api_key;
    private $url = 'https://walks-manager.ramblers.org.uk/api/volunteers/';
    public $feedType = 'walksevents';          // This can be over-written

    public static function getMessages()
    {
        return self::$messages;
    }

    /**
     * Display all fields from the first record in the response
     * @param array $response Decoded API response
     * @return array List of field names and values
     */
    public static function displayFields($response)
    {
        if (!isset($response['data'][0])) {
            return ['error' => 'No records found'];
        }
        return $response['data'][0]['attributes'] ?? $response['data'][0];
    }

    public function getCountEvents($code) {
        // https://walks-manager.ramblers.org.uk/api/volunteers/walksevents?types=walkevents&types=group-event&api-key=<key from API sites>&groups=CF
        return $this->getJson('group-event', 'groups=' . $code, 'Y');
    }

    public function getCountOrganisations($code) {
        return $this->getJson('organisation', 'groups=' . $code, 'Y');
    }

    public function getCountWalks($code) {
//        $count = $this->getJson('group-walk', 'groups=' . $code, 'Y');
//        if (($count ==0)|| $count == ''){
//            return '-';
//        }
        return $this->getJson('group-walk', 'groups=' . $code, 'Y');
    }

    public function getJson($type, $criteria, $count = 'N') {

        /*
         * Documention is on https://app.swaggerhub.com/apis-docs/abateman/Ramblers-third-parties/1.0.0#/default/get_api_volunteers_walksevents
         * $type can be: walkevents, group-event or organisation
         */

//        if ($type == 'organisation') {
//            $url = $this->url . 'groups';
//        } else {
//            $url = $this->url . 'walksevents?types=';
//            $url .= $type;
//        }
//
//        $api_key = $this->toolsHelper->lookupApiKey();
//        if ($api_key == '') {
//            $message = $message = 'API key not found - please create a record in API sites';
//            Factory::getApplication()->enqueueMessage($message, 'error');
//            return false;
//        }
//
//        $url .= '&api-key=' . $api_key . '&' . $criteria;
        $feedurl = $this->setUrl($type, $criteria);
//        if (JDEBUG) {
//        echo 'getJson:' . $feedurl . '<br>';
//        }
//        $url .= '&limit=3';
//        $url .= '&dow=7';
//      set up maximum time of 10 minutes
        $max = 10 * 60;
        set_time_limit($max);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $feedurl);
        curl_setopt($ch, CURLOPT_HEADER, false);         // do not include header in output
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // do not follow redirects
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // do not output result
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $max);  // allow xx seconds for timeout
        curl_setopt($ch, CURLOPT_TIMEOUT, $max);         // allow xx seconds for timeout
//      curl_setopt($ch, CURLOPT_REFERER, "com_ra_tools"); // say who wants the feed

        $data = curl_exec($ch);
        $error = curl_error($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        if ($httpCode !== 200) {
            $message = 'Error: ' . $httpCode;
            $message .= ', ' . $error;
            $toolsHelper = new Toolshelper;
            if ($toolsHelper->isSuperuser()) {
                $message .= ' ' . $url;
            }

            Factory::getApplication()->enqueueMessage($message, 'error');
            return;
        }

        $temp = json_decode($data);
//        echo '<br><b>Summary</b><br>';
//        var_dump($temp->summary);
//        echo '<br><b>Data</b><br>';
//        var_dump($temp->data);

        if ($count == 'Y') {
            return $temp->summary->count;
        } else {
            return$temp->data;
        }
    }

     /**
     * Execute a curl command to fetch API data
     * @param int $api_site_id ID of the record in api_sites
     * @param string $endpoint The required endpoint URL
     * @return array Decoded response or error info
     */

    public static function getRemoteData($site_id, $endpoint)
    {
        self::$messages = [];
        /*
        $site_id is the id of the record in api_sites
        $endpoint is the project_code/view_name (e.g. /api/index.php/v1/ra_events/events)
        Derived from EventsHelper/getRemoteEvents, but generalised
         */
        $sql = 'SELECT * FROM #__ra_api_sites WHERE id=' . $site_id;
        $toolsHelper = new ToolsHelper;
        $site = $toolsHelper->getItem($sql);
        $token = trim($site->token);

        $url = $site->url . $endpoint;
        if (JDEBUG) {
            $message = 'Site id ' . $site_id . ', ';
            $message .= 'Seeking data from ' . $url;
            Factory::getApplication()->enqueueMessage($message, 'notice');
            self::$messages[] = $message;
            $message = 'Token is ' . $token;
            self::$messages[] = $message;
        }
//      set up maximum time of 5 minutes
        $max = 5 * 60;
        set_time_limit($max);

// HTTP request headers
        $headers = [
            'Accept: application/vnd.api+json',
            'Content-Type: application/json',
//            'Authorization: Bearer ' . $token,
            sprintf('X-Joomla-Token: %s', $token),
        ];

        $curl = curl_init();
        curl_setopt_array(
            $curl,
            [
                CURLOPT_URL => $url,
                CURLOPT_HEADER => false, // do not include header in output
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_ENCODING => 'utf-8',
                CURLOPT_MAXREDIRS => 10,
                CURLOPT_CONNECTTIMEOUT => $max,
                CURLOPT_TIMEOUT => $max,
                CURLOPT_FOLLOWLOCATION => true,
                CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_2TLS,
                CURLOPT_CUSTOMREQUEST => 'GET',
//            CURLOPT_REFERER => "com_ra_tools", // say who wants the feed
                CURLOPT_HTTPHEADER => $headers,
//        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false); // do not follow redirects
//        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);  // do not output result
            ]
        );

        $responseData = curl_exec($curl);
        $httpCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        if ($responseData == false) {
            $error = curl_error($curl);

            if ($httpCode !== 200) {
                $message = 'Error: ' . $httpCode;
                $message .= ', ' . $error;
                $toolsHelper = new Toolshelper;
                if ($toolsHelper->isSuperuser()) {
                    $message .= ' ' . $url;
                }
                self::$messages[] = $message;
                return false;
            }
        }
//        if (curl_errno($curl)) {
//            echo curl_error($curl);
//        }
        curl_close($curl);

        if ($httpCode !== 200) {
            $message = 'Error: ' . $httpCode;
            if ($httpCode == 401) {
                $message .= 'Authorization Required (Token missing or invalid)';
            } else {
                $message .= $error;
            }
            self::$messages[] = $message;
            if (isset($responseHeaders) && $responseHeaders !== '') {
                self::$messages[] = 'Response data: ' . trim($responseData);
            }
//            return false;
        }
        $details = json_decode($responseData, true);
        if ($details === null && json_last_error() !== JSON_ERROR_NONE) {
            self::$messages[] = 'JSON decode error: ' . json_last_error_msg();
        }

        if (isset($details['errors'])) {
            foreach ($details['errors'] as $error) {
                self::$messages[] = 'API Error: ' . $error['title'] . ' (Code: ' . $error['code'] . ')';
            }
            // You might want to log these messages or display them to the user
            // For now, just returning false as the operation failed.
            return false;
        }



        if (isset($details['data']['attributes'])) {
            // Single item response
            $item = (object) $details['data']['attributes'];
            if (JDEBUG) {
                echo '<b>Start of item</b><br>';
                var_dump ($item);
                echo '<br><b>End of item</b><br>';
                echo '<br>========<br>';
            }

            return $item;
        } elseif (isset($details['data']) && is_array($details['data'])) {
            // Collection response
            $items = [];
            foreach ($details['data'] as $itemData) {
                if (isset($itemData['attributes'])) {
                    $item = (object) $itemData['attributes'];
                    $item->id = $itemData['id'];
                    $items[] = $item;
                }
            }
            return $items;
        }       


        return $details;
    }   

    private function getUrl($type, $criteria) {
        if ($type == 'organisation') {
            $url = $this->url . 'groups';
        } else {
            $url = $this->url . 'walksevents?types=';
            $url .= $type;
        }
        $this->setKey();
        // Never echo or die() on this value - it carries the API key
        return $url . $this->api_key . $criteria;
    }

    private function setKey() {
        $toolsHelper = new Toolshelper;
        $api_key = $toolsHelper->lookupApiKey();
        if ($api_key == '') {
            $message = 'API key not found - please create a record in API sites';
            Factory::getApplication()->enqueueMessage($message, 'error');
            return false;
        }

        $this->api_key = '&api-key=' . $api_key;
    }

    public function setUrl($type, $criteria) {
        // $type can be: walkevents, group-event or organisation
        if ($type == 'organisation') {
            $url = $this->url . 'groups?';
        } else {
            $url = $this->url . 'walksevents?types=';
            $url .= $type . '&';
        }
        $toolsHelper = new Toolshelper;
        $api_key = $toolsHelper->lookupApiKey();
        if ($api_key == '') {
            $message = 'API key not found - please create a record in API sites';
            Factory::getApplication()->enqueueMessage($message, 'error');
            return false;
        }

        $url .= 'api-key=' . $api_key;
        if (trim($criteria) !== '') {
            $url .= '&' . $criteria;
        }
//        if (JDEBUG) {
//        echo 'setUrl: ' . $url . '<br>';
//        }
        return $url;
    }

    public function showEventButton($id) {
        // Parameter may be a comma delimited array of ids
        // Returns a button with a link to show the JSON feed for the given event
        $this->setKey();
        $target = $this->setUrl('group-event', 'ids=' . $id);
        $toolsHelper = new ToolsHelper;

        return$toolsHelper->imageButton('I', $target, true);
    }

    public function showEventsButton($code) {
        // Returns a button with a link to show the JSON feed for the Area/Group

        $target = $this->setUrl('group-event', 'groups=' . $code);
        $toolsHelper = new ToolsHelper;

        return$toolsHelper->imageButton('I', $target, true);
    }

    public function showFirst($api_site_id, $events) {
        $sql = 'SELECT * FROM #__ra_api_sites WHERE id=' . $api_site_id;
        $site = $this->toolsHelper->getItem($sql);
        if (is_null($site)) {
            echo 'API site ' . $api_site_id . ' not found';
            return;
        }   
        echo '<h2>First record on feed from ';
        echo $site->title;
        echo '</h2>';
        $website = $site->url;
        // Accept either the raw JSON payload (with a data key) or the data array directly.
        $payload = $events;
        if (isset($events['data']) && is_array($events['data'])) {
            $payload = $events['data'];
        }

        $count = is_array($payload) ? count($payload) : 0;
        echo $count . ' records returned<br>';

        if ($count === 0) {
            echo 'No records to display<br>';
        } else {
            $event = $payload[0];

            $attributes = isset($event['attributes']) && is_array($event['attributes']) ? $event['attributes'] : array();

            echo '<table class="table">';
            echo '<tr><th>Field</th><th>Value</th></tr>';
            $fieldCount = 0;
            foreach ($attributes as $key => $val) {
                $fieldCount++;
                if (is_array($val) || is_object($val)) {
                    $val = json_encode($val);
                }
                echo '<tr>';
                echo '<td>' . htmlspecialchars((string) $key, ENT_QUOTES, 'UTF-8') . '</td>';
                echo '<td>' . htmlspecialchars((string) $val, ENT_QUOTES, 'UTF-8') . '</td>';
                echo '</tr>';
            }
            echo '</table>';
            echo $fieldCount . ' fields<br>';

        }

    }

    public function showWalkButton($id) {
        // Parameter may be a comma delimited array of ids
        // Returns a button with a link to show the JSON feed for the given walk

        $target = $this->setUrl('group-walk', 'groups=' . $code);
        $toolsHelper = new ToolsHelper;

        return$toolsHelper->imageButton('I', $target, true);
    }

    public function showWalksButton($code) {
        // Parameter may be a comma delimited array of ids
        // Returns a button with a link to show the JSON feed for the given walk
        // 'group-walk', 'groups=' . $code
        $target = $this->setUrl('group-walk', 'groups=' . $code);
        $toolsHelper = new ToolsHelper;

        return$toolsHelper->imageButton('I', $target, true);
    }

    public function groupFeed($group_code) {
        // Returns link to enable display of all walks for given Group
        return $this->url . 'group-walk&groups=' . $group_code . $this->key;
    }

}
