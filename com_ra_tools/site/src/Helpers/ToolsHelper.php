<?php

/**
 * Various common functions used throughout the project
 *
 * @version     3.7.5
 * @package     com_ra_tools
 * @copyright   Copyright (C) 2020. All rights reserved.
 * @license     GNU General Public License version 2 or later; see LICENSE.txt
 * @author      Charlie <webmaster@bigley.me.uk> - https://www.stokeandnewcastleramblers.org.uk

 * 03/09/25 CB don't log emails in batch mode, showExtensions
 * 24/09/25 CB isInstalled
 * 05/10/25 CB show contact details
 * 12/10/25 CB lookupUser
 * 26/02/26 CB add buildEmailPreamble
 * 06/04/26 CB envelopeIcon
 * 20/04/26 CB showMonthMatrix drilldown by date, deleted get_superuser
 * 06/05/26 CB showAccess - include com_ra_members
 * 19/05/25 CB createLog - check for ref more than 10 chars
 * 25/05/26 CB correction in email logging
 * 30/05/26 CB changed error handling in getItem, getRows and getValue to set $this->error and return false, rather than returning the error message in the return value.
 *             This is to allow the calling code to distinguish between a query that returns no rows (or a SQL NULL value) and a query that fails with an error.
 * 22/06/26 CB in showAccess, show home group (and membershipNumber), include group in list of events
 * 06/07/26 CB invoke ra-delivery/SmtpHelper to send email using API if component is installed and enabled
 * 15/07/26 CB improved image hanling for emails: insert code required for embedLocalEmailImages
 * 17/07/26 CB added function buildDashboardReportBlock (for dashboard and reports menus)
 * 10/08/26 CB added getNearestOrganisations, allow new windows in buildDashboardBlock
 */

namespace Ramblers\Component\Ra_tools\Site\Helpers;

defined('_JEXEC') or die;

use Joomla\CMS\Component\ComponentHelper;
use Joomla\CMS\Factory;
use Joomla\CMS\Helper\ContentHelper;
use Joomla\CMS\HTML\HTMLHelper;
use Joomla\CMS\Object\CMSObject;
use Joomla\CMS\Router\Route;
use Joomla\CMS\Toolbar\ToolbarHelper;
use Joomla\CMS\Uri\Uri;
use Joomla\Database\DatabaseInterface;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsTable;
use Ramblers\Component\Ra_delivery\Site\Helper\SmtpHelper;

class ToolsHelper {

    public $db;
    public $error;
    public $user;

    /**
     * Row count from the most recent helper operation that populates it.
     *
     * Current writers are getItem(), getJson(), getRows(), and showSql().
     * The value is reset to 0 before the query helpers run, then updated with
     * the database row count on success.
     *
     * @var int
     */
    public $rows;
    public $image_folder = "media/com_ra_tools/";
    protected $app;
    protected $toolsTable;
    protected $website_root;

    function __construct() {
        $this->app = Factory::getApplication();
        $this->user = $this->app->getIdentity();
        $this->db = Factory::getDbo();
        $this->rows = 0;
        $this->toolsTable = new ToolsTable();
    }

    static function addSlash($folder) {
// Checks that last character is a forwards slash, adds one if necessary
        if (substr($folder, (strlen($folder) - 1)) != "/") {
            $folder .= "/";
        }
        return $folder;
    }

    static function anchor($label = 'Top') {
        // returns a linkto the given anchor, e.g. <a name=top>:</a>
        $anchor = strtolower($label);
        return '<a href="#' . $anchor . '">' . $label . '</a>';
    }

    function auditButton($id, $table, $callback) {
// display a button to show audit records
// $table needs no prefix (eg ra_walks)
// within $callback, = should be replaced with EQ, and & replaced with --
        $target = 'index.php?option=com_ra_wf&task=reports.showAudit&table=' . $table . '&id=' . $id;
        if ($callback == '') {
            return $this->buildLink($target, "Show Audit", True, "link-button button-p4485");
        } else {
            $target .= '&callback=' . $callback;
            return $this->buildLink($target, "Show Audit", False, "link-button button-p4485");
        }
    }

    function backButton($target) {
        return $this->buildButton($target, 'Back', false, 'granite');
    }

    function buildButton($url, $text, $newWindow = 0, $colour = '') {
        $class = $this->lookupColourCode($colour, 'B');
        //       echo "colour=$colour, code=$code, class=$class<br>";
        return $this->buildLink($url, $text, $newWindow, $class);
    }

    public function buildDashboardReportBlock($title, array $links, $newWindow = false) {
        if (empty($links)) {
            return '';
        }

        $html = '<div class="dashboard-block">';
        $html .= '<div class="block-header">';
        $html .= '<h4>' . htmlspecialchars((string) $title, ENT_QUOTES, 'UTF-8') . '</h4>';
        $html .= '</div>';
        $html .= '<div class="block-content">';
        $html .= '<ul>';

        foreach ($links as $caption => $task) {
            if (is_int($caption)) {
                $html .= '<li>' . $task . '</li>';
            } else {
                $html .= '<li>' . $this->buildLink($task, $caption, $newWindow) . '</li>';
            }
        }

        $html .= '</ul>';
        $html .= '</div>';
        $html .= '</div>';

        return $html;
    }

    public function buildEmailPreamble() {
//      Generate the opening HTML wrapper for an email body fragment.
        $header = '<!DOCTYPE html>';
        $header .= '<html>';
        $header .= '<head>';
        $header .= '<meta charset="UTF-8">';
        $header .= '<meta name="viewport" content="width=device-width, initial-scale=1.0">';
        $header .= '<style>';
        $header .= 'body { font-family: Arial, sans-serif; margin: 0; padding: 10px; }';
        $header .= 'div { box-sizing: border-box; }';
        $header .= '</style>';
        $header .= '</head>';
        $header .= '<body>';
        return $header;
    }

    private function collapseDuplicatedAbsoluteUrls($html) {
        do {
            $previous = $html;
            $html = preg_replace('#https?://([^/"\'>\s]+)/(https?://\1/)#i', '$2', $html);
            $html = preg_replace('#https?://[^"\'>\s]*/(https?://[^"\'>\s]+)#i', '$1', $html);
        } while ($html !== $previous);

        return $html;
    }

    private function getEmailAssetRoot() {
        $root = trim((string) Uri::root());

        if (!preg_match('#^https?://#i', $root)) {
            $config = Factory::getConfig();
            $root = $config ? trim((string) $config->get('live_site')) : '';
        }

        return rtrim($this->collapseDuplicatedAbsoluteUrls($root), '/') . '/';
    }

    private function getHostFromUrl($url) {
        $host = parse_url($url, PHP_URL_HOST);

        return is_string($host) ? strtolower($host) : '';
    }

    private function extractLocalEmailImagePath($src) {
        $src = trim($this->collapseDuplicatedAbsoluteUrls((string) $src));

        if ($src === '' || preg_match('#^(?:data:|cid:|mailto:|tel:|#)#i', $src)) {
            return '';
        }

        if (preg_match('#^/?images/#i', $src)) {
            return ltrim($src, '/');
        }

        if (!preg_match('#/((?:[^/"\'>\s]+/)*images/[^"\'>\s]+)#i', $src, $path_match)) {
            return '';
        }

        $root_host = $this->getHostFromUrl($this->getEmailAssetRoot());
        $src_host = $this->getHostFromUrl($src);
        $has_nested_host = preg_match('#^https?://[^/"\'>\s]+/(?:https?://|[^/"\'>\s]+\.[^/"\'>\s]+/)#i', $src);

        if (!$has_nested_host && $src_host !== $root_host) {
            return '';
        }

        return preg_replace('#^(?:[^/]+/)*?(images/)#i', '$1', $path_match[1]);
    }

    public function makeEmailImageUrlsAbsolute($body) {
        $root = $this->getEmailAssetRoot();
        $body = $this->collapseDuplicatedAbsoluteUrls($body);

        return preg_replace_callback('/(<img\b[^>]*\bsrc=)(["\'])([^"\']+)(\2)/i', function ($matches) use ($root) {
            $src = trim($this->collapseDuplicatedAbsoluteUrls($matches[3]));
            $local_path = $this->extractLocalEmailImagePath($src);

            if ($local_path !== '') {
                return $matches[1] . $matches[2] . $root . ltrim($local_path, '/') . $matches[4];
            }

            if ($src === '' || preg_match('#^(?:[a-z][a-z0-9+.-]*:|//|#)#i', $src)) {
                return $matches[1] . $matches[2] . $src . $matches[4];
            }

            return $matches[1] . $matches[2] . $root . ltrim($src, '/') . $matches[4];
        }, $body);
    }

    private function embedLocalEmailImages($body, &$inline_images, &$inline_debug) {
        $embedded = [];
        $inline_debug = [
            'images' => 0,
            'local' => 0,
            'found' => 0,
            'missing' => 0,
            'last' => ''
        ];

        return preg_replace_callback('/(<img\b[^>]*\bsrc=)(["\'])([^"\']+)(\2)/i', function ($matches) use (&$embedded, &$inline_images, &$inline_debug) {
            $inline_debug['images']++;
            $relative_path = $this->extractLocalEmailImagePath($matches[3]);
            $inline_debug['last'] = substr((string) $matches[3], 0, 120);

            if ($relative_path === '') {
                return $matches[0];
            }
            $inline_debug['local']++;

            $relative_path = rawurldecode($relative_path);
            $relative_path = str_replace('\\', '/', $relative_path);
            $relative_path = preg_replace('#/+#', '/', $relative_path);

            if (strpos($relative_path, '..') !== false) {
                return $matches[0];
            }

            $path = $this->findEmailImagePath($relative_path);
            if ($path == '') {
                $inline_debug['missing']++;
                return $matches[0];
            }
            $inline_debug['found']++;

            $cid_host = $this->getHostFromUrl($this->getEmailAssetRoot());
            $cid = 'ra_img_' . md5($relative_path) . '@' . ($cid_host ?: 'local');
            if (!isset($embedded[$cid])) {
                $inline_images[$cid] = $path;
                $embedded[$cid] = true;
            }

            return $matches[1] . $matches[2] . 'cid:' . $cid . $matches[4];
        }, $body);
    }

    private function findEmailImagePath($relative_path) {
        $relative_path = ltrim($relative_path, '/');
        $paths = [
            JPATH_ROOT . '/' . $relative_path,
            dirname(JPATH_ROOT) . '/' . $relative_path,
            JPATH_ROOT . '/public_html/' . $relative_path,
            dirname(JPATH_ROOT) . '/public_html/' . $relative_path,
        ];

        foreach ($paths as $path) {
            if (file_exists($path)) {
                return $path;
            }
        }

        return '';
    }

    static function buildError($ExistingMessage, $NewMessage) {
        if ($ExistingMessage == "") {
            return $NewMessage;
        } else {
            return ($ExistingMessage . ", " . $NewMessage);
        }
    }

    static function buildGroups($parameter) {
//      Builds string suitable for a database query,
//      If single group is given, returns
///      = 'G001'
//      If multiple groups are given, returns them in format:
//      IN ('GG01,'GG02') etc
        if (strlen($parameter) == 2) {
            return "LIKE '" . $parameter . "%' ";
        } else {
            if (strpos($parameter, ",") === false) {
                return "= '" . $parameter . "' ";
            } else {
                $groups = explode(",", $parameter);
                $this->group_list = "";
                foreach ($groups as $group) {
                    $this->group_list .= "'" . $group . "',";
                }
// Remove trailing comma
                return "IN (" . substr($this->group_list, 0, (strlen($this->group_list) - 1)) . ")";
            }
        }
    }

    static function buildLink($url, $text, $newWindow = 0, $class = "") {
        // If called for an internal link, URL should start with a leading /
        // If ommitted, a / is added
        $q = chr(34);
        $out = PHP_EOL . "<a ";
//        echo "BuildLink: url = $url, substr=" . substr($url, 0, 4) . ", text=$text, root=" . Uri::root() . "<br>";
        if (!$class == "") {
            $out .= "class=" . $q . $class . $q;
        }
        $out .= " href=" . $q;
        //       echo substr($url, 0, 14);
        if ((substr($url, 0, 14) == 'administrator/') || (substr($url, 0, 10) == 'index.php?')) {
            $out .= '/';
        }
        $out .= $url . $q;
        if ($newWindow) {
            $out .= " target = " . $q . "_blank" . $q;
        } else {
            $out .= " target = " . $q . "_self" . $q;
        }
        $out .= ">";
        if ($text == "")
            $out .= $url;
        else
            $out .= $text;
        $out .= "</a>" . PHP_EOL;
//        echo "BuildLink: output = $out";
        return $out;
    }

    function buildLinkRoute($mode, $gpx, $folder = "", $class = "") {

        if ($folder == "") {
            $app = Factory::getApplication();
            $params = $app->getParams();
            $folder = $params->get('routes') . "/";
        } else {
            $path = $folder . "/";
        }

        if ($mode == "I") {   // Image link required
            $target = "index.php?option = com_ra_tools&task = misc.showRoute&gpx = ";
            $target .= $folder . "/" . $gpx;
// Factory::getApplication()->enqueueMessage("target before is " . $target, 'info');
// Spaces and forwards slashes are stripped out, but replaced in
// the Controller routes.showRoute
            $target2 = str_replace("/", "xFz", $target);
            $target2 = str_replace(" ", "qHj", $target2);
// Factory::getApplication()->enqueueMessage("target after is " . $target2, 'info');
            return $this->imageButton("I", $target2, true);
        } else {
            $target = $folder . $gpx;
            if ($mode == "D") {  // Download link
                return $this->imageButton("D", $target, true);
            } else {
                return $this->buildLink($target, $gpx, 1, $class);
            }
        }
    }

    /**
     * Build the query for search from the search columns
     *
     * @param    string        $searchWord        Search for this text

     * @param    string        $searchColumns    The columns in the DB to search for
     *
     * @return    string        $query            Append the search to this query
     */
    public static function buildSearchQuery($searchWord, $searchColumns, $query) {
        if (stripos($searchWord, 'id:') === 0) {
            $query->where('a.id = ' . (int) substr($searchWord, 3));
            return $query;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $where = array();

        foreach ($searchColumns as $i => $searchColumn) {
            $where[] = $db->qn(trim($searchColumn)) . ' LIKE ' . $db->q('%' . $db->escape($searchWord, true) . '%');
        }
        if (!empty($where)) {
            $query->where('(' . implode(' OR ', $where) . ')');
        }

        return $query;
    }

    static function canDo($component) {
        // 10/10/24 CB this is not used in component com_ra_tools - probably redundant
        // 03/12/24 CB this is not used in component com_ra_events
        // if (Factory::getApplication()->loadIdentity()->authorise('core.create', $component)) {
        if (Factory::getApplication()->getSession()->get('user')->authorise('core.create', $component)) {
            return true;
        } else {
            return "Sorry, you don't have access permission for " . $component;
        }
    }

    /**
     * Gets the edit permission for an user
     *
     * @param   string  $component  The component asset name
     *
     * @param   mixed  $item  The item
     *
     * @return  bool
     */
    public static function canUserEdit($component, $item) {
        $user = Factory::getApplication()->getIdentity();

        if (empty($item->id)) {
            return $user->authorise('core.create', $component);
        }

        if ($user->authorise('core.edit', $component)) {
            return true;
        }

        return isset($item->created_by)
            && (int) $item->created_by === (int) $user->id
            && $user->authorise('core.edit.own', $component);
    }

    static function convert_to_ASCII($name) {
        $new = '';
//        echo strlen($name) . '<br>.';
        for ($i = 0; $i < (strlen($name)); $i++) {
//            echo '->' . substr($name, $i, 1) . ' = ';
            $token = ord(substr($name, $i, 1));
//            echo $token . '/';
            $new .= str_pad($token, 3, "0", STR_PAD_LEFT);
//            echo ' ' . $new;
//            echo '<br>';
        }
        return $new;
    }

    static function convert_from_ASCII($token) {

        $max = (strlen($token) / 3);
//        echo strlen($token) . $max . '<br>.';
        $new = '';
        $pointer = 0;
        for ($i = 0; $i < $max; $i++) {
//            echo '->' . substr($token, $pointer, 3) . ' = ';
            $temp = chr(substr($token, $pointer, 3));
//            echo $temp . '/';
            $new .= chr(substr($token, $pointer, 3));
//            echo ' ' . $new;
//            echo '<br>';
            $pointer = $pointer + 3;
        }
        return $new;
    }

    public function countGroupFollowers($code) {
        // Finds number of walk/followers for given Area or Group
        $sql = 'SELECT COUNT(p.id) FROM #__ra_profiles as p ';
        $sql .= 'INNER JOIN #__ra_walks_follow AS f ON f.user_id = p.id ';
        $sql .= 'INNER JOIN #__ra_walks AS w ON w.id = f.walk_id ';
        $sql .= 'WHERE w.group_code ';
        if (strlen($code) == 2) {
            $sql .= "like'" . $code . "%' ";
        } else {
            $sql .= "='" . $code . "' ";
        }
//        $sql .= "GROUP BY p.id ";
        return $this->getValue($sql);
    }

    function countFutureEvents($type_id) {
// find the number of future, active Event of the specified type
        $sql = "SELECT count(id) as num ";
        $sql .= "from #__ra_events ";
        $sql .= "WHERE datediff(event_date, CURRENT_DATE) >=0 ";
        $sql .= "AND state=1 ";
        $sql .= "AND event_type_id=" . $type_id;
        return $this->getValue($sql);
    }

    function createAuditRecord($field_name, $old_value, $new_value, $object_id, $table) {
//        echo "Helper::createAudit:$table - $field_name: id=$object_id ,$old_value -> $new_value <br>";
        if (trim($old_value) == trim($new_value)) {

        } else {
            if (trim($old_value) == "") {
                $record_type = "A";
                $field_value = $new_value;
            } else {
                if (trim($new_value) == "") {
                    $record_type = "D";
                    $field_value = $old_value;
                } else {
                    $record_type = "U";
                    $field_value = $old_value . "->" . $new_value;
                }
            }

            $sql = "INSERT INTO #__" . $table . "_audit ( ";
            $sql .= "date_amended,";
            $sql .= "object_id,";
            $sql .= "field_name,";
            $sql .= "record_type,";
            $sql .= "field_value) Values (";
            $sql .= "'" . date("Y") . "-" . date("m") . "-" . date("d") . " ";
            $sql .= date("H") . ":" . date("i") . ":" . date("s") . "',";
            $sql .= $object_id . ",";
            $sql .= "'" . $field_name . "',";
            $sql .= "'" . $record_type . "',";
//      $sql .= "'" & gTool.CheckApostrophy(Left$(strFieldValue, 100)) & "',"
            if (strpos($field_value, "'") > 0) {
//             echo chr(92) . "|" ;
                $sql .= "'" . substr($field_value, 0, strpos($field_value, "'"));
                $sql .= "|";
                $sql .= substr($field_value, strpos($field_value, "'") + 1) . "')";
            } else {
                $sql .= "'" . HTMLSpecialChars($field_value) . "')";
            }
//            echo "DatabaseAccess; CreateAuditRecord $sql<br>";
            if ($this->executeCommand($sql)) {
                $this->message = "CreateAudit: Updated $field_name for record $object_id in table $table: old=" . $old_value . ", new=" . $new_value;
                return 1;
            } else {
                $this->message .= "<br>$sql";
                return 1;
            }
        }
    }

    /*
      This function takes four parameters:
      $user_id refers to the id of the record in #_users for a particular User
      $function_code is a two digit code
      $reference_id refers to the id of a project-specific table
      $extra_id is included for future proofing

      It returns a securely encrypted string of ASCII characters that encapsulate the four input
      values, plus the date of encryption

      If the value supplied for user_id is zero, the function uses the value of the current user
     */

    public function encodeToken($user_id, $function_code, $reference_id, $extra_id = 0) {
        $userId = (int) $user_id;

        if ($userId === 0) {
            $userId = (int) Factory::getApplication()->getIdentity()->id;
        }

        $plaintext = $this->buildCompactTokenPayload(
                str_pad((string) $function_code, 2, '0', STR_PAD_LEFT),
                $userId,
                (int) $reference_id,
                (int) $extra_id,
                time()
        );

        if ($plaintext === false) {
            return false;
        }

        $key = $this->loadTokenKey();

        if ($key === false) {
            return false;
        }

        if (function_exists('sodium_crypto_secretbox')) {
            $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = sodium_crypto_secretbox($plaintext, $nonce, $key);
            return $this->base64UrlEncode('S' . $nonce . $ciphertext);
        }

        if (function_exists('openssl_encrypt')) {
            $nonce = random_bytes(12);
            $tag = '';
            $ciphertext = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);

            if ($ciphertext === false) {
                $this->error = 'Unable to encrypt token payload';
                return false;
            }

            return $this->base64UrlEncode('O' . $nonce . $tag . $ciphertext);
        }

        $this->error = 'No supported encryption extension available';
        return false;
    }

    public function decodeToken($token) {
        /*
          This takes as input an string of ASCII characters that has been created
          by function encode.

          It returns a standard Joomla object with five constituents:
          user_id
          function_code
          reference_id
          extra_id
          num_days - integer for the number of days that have elapsed since the
          token was generated

          If for any reason it cannot decrypt the token, it will return false
         */

        if (!is_string($token) || trim($token) === '') {
            return false;
        }

        $binary = $this->base64UrlDecode($token);

        if ($binary === false || strlen($binary) < 2) {
            return false;
        }

        $key = $this->loadTokenKey(false);

        if ($key === false) {
            return false;
        }

        $method = substr($binary, 0, 1);
        $payload = substr($binary, 1);
        $plaintext = false;

        if ($method === 'S' && function_exists('sodium_crypto_secretbox_open')) {
            if (strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
                return false;
            }

            $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $ciphertext = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
            $plaintext = sodium_crypto_secretbox_open($ciphertext, $nonce, $key);
        } elseif ($method === 'O' && function_exists('openssl_decrypt')) {
            if (strlen($payload) <= 28) {
                return false;
            }

            $nonce = substr($payload, 0, 12);
            $tag = substr($payload, 12, 16);
            $ciphertext = substr($payload, 28);
            $plaintext = openssl_decrypt($ciphertext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag);
        }

        if ($plaintext === false) {
            return false;
        }

        $details = $this->parseCompactTokenPayload($plaintext);

        if ($details === false) {
            return false;
        }

        $result = new CMSObject;
        $result->user_id = (int) $details->user_id;
        $result->function_code = (string) $details->function_code;
        $result->reference_id = (int) $details->reference_id;
        $result->extra_id = (int) $details->extra_id;
        $result->num_days = max(0, (int) floor((time() - (int) $details->issued_at) / 86400));

        return $result;
    }

    private function buildCompactTokenPayload($functionCode, $userId, $referenceId, $extraId, $issuedAt) {
        $segments = array(
            $this->encodeTokenSegment($userId),
            $this->encodeTokenSegment($referenceId),
            $this->encodeTokenSegment($extraId),
            $this->encodeTokenSegment($issuedAt),
        );

        foreach ($segments as $segment) {
            if ($segment === false) {
                $this->error = 'Unable to encode token payload';
                return false;
            }
        }

        return (string) $functionCode . implode('', $segments);
    }

    private function encodeTokenSegment($value) {
        $digits = (string) (int) $value;
        $length = strlen($digits);

        if ($length > 35) {
            $this->error = 'Token value exceeds supported length';
            return false;
        }

        return strtoupper(base_convert((string) $length, 10, 36)) . $digits;
    }

    private function parseCompactTokenPayload($payload) {
        if (!is_string($payload) || strlen($payload) < 6) {
            return false;
        }

        $functionCode = substr($payload, 0, 2);

        if (!ctype_digit($functionCode)) {
            return false;
        }

        $pointer = 2;
        $userId = $this->decodeTokenSegment($payload, $pointer);
        $referenceId = $this->decodeTokenSegment($payload, $pointer);
        $extraId = $this->decodeTokenSegment($payload, $pointer);
        $issuedAt = $this->decodeTokenSegment($payload, $pointer);

        if ($userId === false || $referenceId === false || $extraId === false || $issuedAt === false || $pointer !== strlen($payload)) {
            return false;
        }

        $details = new CMSObject;
        $details->user_id = (int) $userId;
        $details->function_code = $functionCode;
        $details->reference_id = (int) $referenceId;
        $details->extra_id = (int) $extraId;
        $details->issued_at = (int) $issuedAt;

        return $details;
    }

    private function decodeTokenSegment($payload, &$pointer) {
        if ($pointer >= strlen($payload)) {
            return false;
        }

        $lengthToken = strtoupper(substr($payload, $pointer, 1));

        if (!ctype_digit($lengthToken) && ($lengthToken < 'A' || $lengthToken > 'Z')) {
            return false;
        }

        $length = (int) base_convert($lengthToken, 36, 10);
        $pointer++;

        if ($length < 1 || ($pointer + $length) > strlen($payload)) {
            return false;
        }

        $value = substr($payload, $pointer, $length);

        if (!ctype_digit($value)) {
            return false;
        }

        $pointer += $length;
        return $value;
    }

    public function loadTokenKey($generateIfMissing = true) {
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true)
                ->select($db->quoteName('key_value'))
                ->from($db->quoteName('#__ra_control'))
                ->where($db->quoteName('record_type') . ' = 1');

        try {
            $db->setQuery($query);
            $configuredKey = trim((string) $db->loadResult());
        } catch (\Exception $ex) {
            $configuredKey = '';
        }

        if ($configuredKey !== '') {
            return $this->normaliseTokenKey($configuredKey);
        }

        if (!$generateIfMissing) {
            $error = 'Token secret key is not configured';
            $this->createLog('RA tools', '27', '0', $error);
            $this->error = $error;
            return false;
        }

        $generatedKey = base64_encode(random_bytes(32));

        if ($this->persistTokenKey($generatedKey)) {
            $this->createLog('RA tools', '26', '0', 'Generated new token key');
            return $this->normaliseTokenKey($generatedKey);
        }

        $error = 'Unable to store token key in #__ra_control';
        $this->createLog('RA tools', '27', '0', $error);
        $this->error = $error;
        return false;
    }

    public function createLog($sub_system, $record_type, $ref, $message) {
        $sql = "INSERT INTO #__ra_logfile (`log_date`, `sub_system`, `record_type`, `ref`, `message`) VALUES ";
        $sql .= "(CURRENT_TIMESTAMP";
        $sql .= ',' . $this->db->quote($sub_system);
        $sql .= ',' . $this->db->quote($record_type);
        $sql .= ',' . $this->db->quote(substr($ref, 0, 10));
        $sql .= ',' . $this->db->quote($message) . ')';
        echo "Helper::createLog: $sql<br>";
        $this->executeCommand($sql);
        /*
          $db = $this->getDbo();
          $query = $db->getQuery(true);
          $query->insert($db->quoteName('#__ra_logfile'))
          ->set('log_date = CURRENT_TIMESTAMP')
          ->set('sub_system = ' . $db->quote($sub_system))
          ->set('record_type = ' . $db->quote($record_type))
          ->set('ref = ' . $db->quote($ref))
          ->set('message = ' . $db->quote($message));
          $db->setQuery($query)->execute();
         */
    }

    static function envelopeIcon() {
        return '<span class="icon-envelope" aria-hidden="true"></span>';
    }

    private function base64UrlDecode($value) {
        $value = strtr($value, '-_', '+/');
        $padding = strlen($value) % 4;

        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        return base64_decode($value, true);
    }

    private function base64UrlEncode($value) {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    private function normaliseTokenKey($configuredKey) {
        $binaryKey = base64_decode($configuredKey, true);

        if ($binaryKey !== false && strlen($binaryKey) === 32) {
            return $binaryKey;
        }

        return hash('sha256', $configuredKey, true);
    }

    private function persistTokenKey($generatedKey) {
        $createTable = 'CREATE TABLE IF NOT EXISTS `#__ra_control` ('
                . '`record_type` INT NOT NULL,'
                . '`key_value` VARCHAR(255) NOT NULL,'
                . 'PRIMARY KEY (`record_type`)'
                . ') ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 DEFAULT COLLATE=utf8mb4_unicode_ci';

        if (!$this->executeCommand($createTable)) {
            return false;
        }

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $sql = 'INSERT INTO ' . $db->quoteName('#__ra_control')
                . ' (' . $db->quoteName('record_type') . ', ' . $db->quoteName('key_value') . ')'
                . ' VALUES (1, ' . $db->quote($generatedKey) . ')'
                . ' ON DUPLICATE KEY UPDATE ' . $db->quoteName('key_value') . ' = VALUES(' . $db->quoteName('key_value') . ')';

        try {
            $db->setQuery($sql);
            $db->execute();
            return true;
        } catch (\Exception $ex) {
            $this->error = $ex->getCode() . ' ' . $ex->getMessage();
            return false;
        }
    }

    function executeCmd($sql) {
// Deprecated !
        $this->executeCommand($sql);
    }

    function executeCommand($sql) {
        $config = Factory::getConfig();
        $dbPrefix = $config->get('dbprefix');
        $sql2 = str_replace("#__", $dbPrefix, $sql);
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true);
            $db->setQuery($sql2);
            $db->execute();
            return true;
        } catch (\Exception $ex) {
            $this->error = $ex->getCode() . ' ' . $ex->getMessage();
//            if (JDEBUG) {
//                echo 'Helper::executeCommmand' . $this->error . '<br>';
//            }
            return false;
        }
    }

    function expandArea($area_code) {
// Given a two character Area code, returns a list of constituent Group code
        $list = "";
        $sql = "Select code from #__ra_groups as `groups` ";
        $sql .= "where code like '" . $area_code . "%' ";
        $rows = $this->getRows($sql);
        foreach ($rows as $row) {
            $list .= $row->code . ",";
        }
        return substr($list, 0, (strlen($list) - 1));
    }

    function exportQuery($sql, $filename) {

        $date = date('Y-m-d');
        $export_file = $filename . '-' . $date . ':' . (new DateTime())->format('g:i:s') . '.csv"';

        $app = Factory::getApplication();
        $app->setHeader('Content-Type', 'text/csv; charset=utf-8', true);
        $app->setHeader('Content-disposition', 'attachment; filename="' . $export_file, true);
        $app->setHeader('Cache-Control', 'no-cache', true);
        $app->sendHeaders();

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery($sql);

        try {
            if (!$result = $db->loadAssocList()) {
                echo "No Qualifying Rows";
                if (JDEBUG) {
                    Factory::getApplication()->enqueueMessage($sql, 'info');
                }
            } else {
                foreach (array_keys($result[0]) as $token) {
                    echo $db->escape($token) . ',';
                }
                echo PHP_EOL;
                foreach ($result as $row) {
                    foreach ($row as $token) {
                        echo $db->escape($token) . ',';
                    }
                    echo PHP_EOL;
                }
            }
        } catch (\Exception $e) {
// never show getMessage() to the public
            Factory::getApplication()->enqueueMessage("Query Syntax Error: " . $e->getMessage(), 'error');
        }
        $app->close();
    }

    public function feedbackButton($encoded) {
        echo $this->buildLink("components/com_ra_tools/Feedback.php?ref=" . $encoded, "Feedback", True, "link-button button-p1815");
    }

    public function generateButton($target, $type, $newWindow = False) {
// Generic function to display Home / Back / Prev / Next buttons etc
// Find the reference point for the icon
        $base = Uri::root();
        if (substr($base, -14, 13) == 'administrator') {
            $target = substr($base, 0, strlen($base) - 14) . $target;
        } else {
            $target = $base . $target;
        }
// Display a button, using the given target
        $icon = 'media/com_ra_tools/' . strtolower($type) . '.png';
        $alt = $type;
        $q = chr(34);
        $link = "<a href=" . $q . $target . $q;
        if ($newWindow) {
            $link .= " target =" . $q . "_blank";
        } else {
            $link .= " target =" . $q . "_self";
        }
        $link .= $q . "><img src=" . $q;
        //$link .= $base . $icon . $q . " alt=" . $q . $alt . $q . " width=" . $q . "60" . $q;
        // $link .= " height=" . $q . "20" . $q . "/></a>" . PHP_EOL;
        $link .= $base . $icon . $q . " alt=" . $q . $alt . $q . "/></a>" . PHP_EOL;

        return $link;
    }

    /**
     * Gets a list of the actions that can be performed.
     *
     * @return  CMSObject
     *
     * Usage: $canDo = ToolsHelper::getActions();
     *  if ($canDo->get('core.create')) {
     *      $toolbar->addNew('walk.add');
     *   }
     */
    public static function getActions($component = 'com_ra_tools') {
        $user = Factory::getApplication()->getIdentity();
        $result = new CMSObject;

        $actions = array(
            'core.admin', 'core.manage', 'core.create', 'core.edit', 'core.edit.own', 'core.edit.state', 'core.delete'
        );

        foreach ($actions as $action) {
            $result->set($action, $user->authorise($action, $component));
        }

        return $result;
    }

    public function getAreaCode($group_code) {
        $sql = "SELECT id FROM #__ra_areas WHERE code='";
        return $this->getValue($sql . substr($group_code, 0, 2) . "'");
    }

    public function getDatabase($params) {

    }

    /**
     * Gets the files attached to an item
     *
     * @param   int     $pk     The item's id
     *
     * @param   string  $table  The table's name
     *
     * @param   string  $field  The field's name
     *
     * @return  array  The files
     */
    public static function getFiles($pk, $table, $field) {
        $db = Factory::getContainer()->get('DatabaseDriver');
        $query = $db->getQuery(true);

        $query
                ->select($field)
                ->from($table)
                ->where('id = ' . (int) $pk);

        $db->setQuery($query);

        return explode(',', $db->loadResult());
    }

    /**
     * Run a single-row query and return the result.
     *
     * Return contract:
     * - object: query succeeded and returned a row
     * - null: query succeeded but matched no rows
     * - false: query failed and $this->error contains the database error
     *
     * @param   string  $sql  The SQL query to execute.
     *
     * @return  object|null|false
     */
    public function getItem($sql) {
        $this->rows = 0;
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true);
            $db->setQuery($sql);
            $db->execute();
            $this->rows = $db->getNumRows();
            $item = $db->loadObject();
            return $item;
        } catch (\Exception $ex) {
            $this->error = $ex->getCode() . ' ' . $ex->getMessage();
            if (JDEBUG) {
                $this->app->enqueueMessage('Helper::getItem: ' . $this->error, 'error');
                $this->app->enqueueMessage('Helper::getItem SQL: ' . $sql, 'error');
            }
            return false;
        }
    }

    public function getJson($sql) {
        $this->rows = 0;
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true);
            $db->setQuery($sql);
            $db->execute();
            $this->rows = $db->getNumRows();
//            print_r($this->rows);
            $rows = $db->loadAssocList();
            return json_encode($rows, JSON_PRETTY_PRINT);
        } catch (\Exception $ex) {
            $this->error = $ex->getCode() . ' ' . $ex->getMessage();
//            if (JDEBUG) {
//                echo $this->error;
//            }
            return false;
        }
    }

    public function getNearestOrganisations($code, int $limit = 5, $display = 'N') {
        // First get the latitude and longitude of the selected organisation
        if (strlen($code) == 4) {
            $type = 'groups';
        } else {
            $type .= 'areas';
        }
        $sql = 'SELECT latitude, longitude FROM #__ra_' . $type . ' WHERE code = "' . $code . '"';
        $org = $this->getItem($sql);

        if (!$org) {
            $this->app->enqueueMessage('Group not found for ' . $code, 'warning');
            return false;
        }

        $lat = (float) $org->latitude;
        $lon = (float) $org->longitude;
//        $lat = $org->latitude;
//        $lon = $org->longitude;
        if ($lat == 0) {
            $this->app->enqueueMessage('Latitude is zero for ' . $code, 'warning');
            return false;
        }
        if ($lon == 0) {
            $this->app->enqueueMessage('Longitude is zero for ' . $code, 'warning');
            return false;
        }
        // Earth's radius in miles
        $earthRadius = 6371;

        // Find the five nearest organisations
        $sql = 'SELECT id, code, name, latitude, longitude,
      (
      ' . $earthRadius . ' * ACOS(
      COS(RADIANS(' . $lat . '))
     * COS(RADIANS(latitude))
     * COS(RADIANS(longitude) - RADIANS(' . $lon . '))
      + SIN(RADIANS(' . $lat . '))
     * SIN(RADIANS(latitude))
      )
      ) AS distance ';
        $sql .= 'FROM #__ra_' . $type . ' ';
        $sql .= 'WHERE code <> "' . $code . '" ';
        $sql .= 'ORDER BY distance ASC LIMIT ' . $limit;
        $rows = $this->getRows($sql);
        //       echo $sql;
        if ($display == 'Y') {
            $objTable = new ToolsTable;
            echo '<h4>' . $limit . ' Nearest ' . $type . '<h4>';
            $objTable->add_header("Num,Code,Name,Miles");
            $i = 0;
            foreach ($rows as $row) {
                $i++;
                $objTable->add_item($i);
                $objTable->add_item($row->code);
                $objTable->add_item($row->name);
                $objTable->add_item(round($row->distance));
                //$objTable->add_item($row->distance);

                $objTable->generate_line();
            }
            $objTable->generate_table();
        }
        return $rows;
    }

    public function getRows($sql) {
        /*
          Example of usage:
          $this->toolsTable->add_header("aa,bb");
          $rows = $this->toolsHelper->getRows($sql);
          foreach ($rows as $row) {
          $this->toolsTable->add_item($row->aa);
          $this->toolsTable->add_item($row->bb);
          $this->toolsTable->generate_line();
          }
          $this->toolsTable->generate_table();
         */
        $this->rows = 0;
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true);
            $db->setQuery($sql);
            $db->execute();
            $this->rows = $db->getNumRows();
//            print_r($this->rows);
            $rows = $db->loadObjectList();
            return $rows;
        } catch (\Exception $ex) {
            $this->error = $ex->getCode() . ' ' . $ex->getMessage();
            if (JDEBUG) {
                $this->app->enqueueMessage('Helper::getRows: ' . $this->error, 'error');
                $this->app->enqueueMessage('Helper::getRows SQL: ' . $sql, 'error');
            }
            return false;
        }
    }

    /**
     * Run a scalar query and return the first column from the first row.
     *
     * Return contract:
     * - scalar|null: query succeeded; null means either no matching row or a SQL NULL value
     * - false: query failed and $this->error contains the database error
     *
     * @param   string  $sql    The SQL query to execute.
     * @param   int     $debug  Retained for compatibility; currently unused.
     *
     * @return  mixed|false
     */
    function getValue($sql) {
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true);
            $db->setQuery($sql);
            return $db->loadResult();
        } catch (\Exception $ex) {
            $this->error = $ex->getCode() . ' ' . $ex->getMessage();
            if (JDEBUG) {
                $this->app->enqueueMessage('Helper::getValue: ' . $this->error, 'error');
                $this->app->enqueueMessage('Helper::getValue SQL: ' . $sql, 'error');
            }
            return false;
        }
    }

    /**
     *     returns details of the component version and the database version
     *
     * @return  CMSObject
     *
     */
    public function getVersions($component = 'com_ra_tools') {
        $sql = 'SELECT s.version_id AS db_version ';
        $sql .= 'FROM #__extensions as e ';
        $sql .= 'LEFT JOIN #__schemas AS s ON s.extension_id = e.extension_id ';
        $sql .= 'WHERE e.element="' . $component . '"';
        //       echo "$sql<br>";

        $sql = 'SELECT e.manifest_cache, s.version_id AS db_version ';
        $sql .= 'FROM #__extensions as e ';
        $sql .= 'LEFT JOIN #__schemas AS s ON s.extension_id = e.extension_id ';
        $sql .= 'WHERE e.element="' . $component . '"';
        // if (JDEBUG){
        //      echo "$sql<br>";
        // }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $query = $db->getQuery(true);
        $db->setQuery($sql);
        $db->execute();
        $item = $db->loadObject();
        if ($item == false) {
            echo 'Can\'t find versions for ' . $component . '<br>';
            return false;
        }
        $values = json_decode($item->manifest_cache);

        $versions = new CMSObject;
        $versions->component = $values->version;
        $versions->db_version = $item->db_version;
        return $versions;
    }

    function imageButton($mode, $target, $newWindow = False) {
        if (substr($target, 0, 4) == "http") {
            $root_to_base = "";
        } else {
            $root_to_base = Uri::root();
        }

        if ($mode == "D") {
            $icon = "download.png";
            $alt = "Download";
        } elseif ($mode == "DD") {
            $icon = "drilldown.png";
            $alt = "Drilldown";
        } elseif ($mode == "E") {
            $icon = "email.png";
            $alt = "Send email";
        } elseif ($mode == "E2") {
            $icon = "email2.png";
            $alt = "Send email";
        } elseif ($mode == "F") {
            $icon = "tick.png";
            $alt = "Follow";
        } elseif ($mode == "G") {
            $icon = "gps.png";
            $alt = "GPX";
        } elseif ($mode == "GO") {
            $icon = "google.png";
            $alt = "Google";
        } elseif ($mode == "I") {
            $icon = "info.png";
            $alt = "Info";
        } elseif ($mode == "L") {
            $icon = "logo_90px.png";
            $alt = "Logo";
        } elseif ($mode == "M") {
            $icon = "icon-cog.png";
            $alt = "Update";
        } elseif ($mode == "P") {
            $icon = "map_pin.png";
            $alt = "Info";
        } elseif ($mode == "R") {
            $icon = "radius.png";
            $alt = "Radius";
        } elseif ($mode == "U") {
            $icon = "upload.png";
            $alt = "upload";
        } elseif ($mode == "W") {
            $icon = "walksfinder.png";
            $alt = "walksfinder";
        } elseif ($mode == "X") {
            $icon = "cross.png";
            $alt = "Delete";
        }
        $q = chr(34);
        $link = "<a href=" . $q;
        $link .= $root_to_base . $target . $q;
//        echo $target;
        if ($newWindow) {
            $link .= " target =" . $q . "_blank";
        } else {
            $link .= " target =" . $q . "_self";
        }
        $link .= $q . "><img src=" . $q;
        //$link .= Uri::root() . $this->image_folder . $icon . $q . " alt=" . $q . $icon . $q . " width=" . $q . "30" . $q;
        $link .= Uri::root() . $this->image_folder . $icon . $q . " alt=" . $q . $icon . $q . " width=" . $q . "20" . $q;
        $link .= " height=" . $q . "20" . $q . "/></a>" . PHP_EOL;
        return $link;
    }

    function isFollower($walk_id, $user_id) {
// See if the given User is following thie given Walk
        $sql = "Select id from #__ra_walks_follow where walk_id=";
        $sql .= $walk_id . " AND user_id=" . $user_id;
//        echo $sql . "<br>";
        return $this->getValue($sql);
    }

    static function isInstalled($component) {
        return ComponentHelper::isEnabled($component, true);
    }

    function isSuperuser() {
        /*
         * 16/09/25 CB THIS FAILS IF INVOKED IN BATCH MODE
         */
// Returns true or false
// For the current user, looks up the id, and if member of SuperUser group
        $user = Factory::getApplication()->getIdentity();
        $this->userId = $user->id;
        return $user->authorise('core.admin');
    }

    private function logEmail($to, $reply_to, $subject, $body, $attachments, $bcc) {
        if ($this->user->id == 0) {
            // batch mode - nobody has logged in
            return true;
        }
        $date = Factory::getDate('now', Factory::getConfig()->get('offset'))->toSql(true);
        $attachments_string = '';

        echo 'Sending email<br>';
        echo "Sent $date<br>";
        if ($bcc !== '') {
            echo 'bcc ';
            var_dump($bcc);
            echo "<br>";
        }
        if (is_array($to)) {
            if (count($to) == 1) {
                $addressee_email = $to[0] . '(array)';
            } else {
                $addressee_email = implode(',', $to);
            }
        } else {
            $addressee_email = $to;
        }
        echo "To $addressee_email<br>";
        $sender_email = $reply_to;
        if (is_array($bcc)) {
            echo 'bcc (array) ';
            if (count($bcc) == 1) {
                $addressee_email .= $bcc[0];
            } else {
                $blind_copies = implode(',', $bcc);
                echo $blind_copies . '<br>';
                $addressee_email .= ',' . $blind_copies;
                echo "To (including bcc) $addressee_email<br>";
            }
        } else {
            echo "bcc $bcc<br>";
            if ($bcc !== '') {
                $addressee_email .= ',' . $bcc;
                echo "To (including bcc) $addressee_email<br>";
            }
        }
        if (is_array($attachments)) {
            echo 'attachments_string (array) ';
            if (count($attachments) == 1) {
                $attachments_string = $attachments[0];
            } else {
                $attachments_string = implode(',', $attachments);
                echo $attachments_string . '<br>';
            }
        } else {
            echo "attacch $attachments<br>";
            if ($attachments !== '') {
                $attachments_string = $attachments;
            }
        }
        echo "Reply to $reply_to<br>";
        echo "Subject $subject<br>";
        echo $body . '<br>';

        $db = Factory::getDbo();
        $helper = New ToolsHelper;

        $query = $db->getQuery(true);
        $query
                ->insert($db->quoteName('#__ra_emails'))
                ->set('sub_system ="RA Tools"')
                ->set('record_type=2')
                ->set('ref =' . $db->quote($this->user->id))
                ->set('date_sent =' . $db->quote($date))
                ->set('sender_name =' . $db->quote($this->user->name))
                ->set('sender_email =' . $db->quote($reply_to))
                ->set('addressee_name =' . $db->quote($addressee_email))
                ->set('addressee_email =' . $db->quote($addressee_email))
                ->set('title =' . $db->quote($subject))
                ->set('body =' . $db->quote($body))
                ->set('attachments =' . $db->quote($attachments_string))
                ->set('state =1')
                ->set('created =' . $db->quote($date))
                ->set('created_by =' . $db->quote($this->user->id));
        echo $query;
        $db->setQuery($query);
        $return = $db->execute();

        if ($return == false) {
            Factory::getApplication()->enqueueMessage('Unable to  create email log record', 'Warning');
        }
        //      die;
    }

    function logMessage($record_type, $ref, $message) {
        $this->createLog('Walks', $record_type, $ref, $message);
    }

    public function lookupApiKey($url = 'https://ramblers.org.uk') {
        $sql = 'SELECT token FROM #__ra_api_sites ';
        $sql .= 'WHERE url="' . $url . '"';
        return $this->getValue($sql);
    }

    function lookupArea($area_code) {
        return $this->getValue("SELECT name FROM #__ra_areas where code='" . $area_code . "' ");
    }

    /*
      --mintcake:#9BC8AB;
      --sunset:#F08050;
      --granite:#404141;
      --rosycheeks:#F6B09D;
      --sunrise:#F9B104;
      --cloudy:#FFFFFF;
      --mintcakedark:#ABD8BB;
      --cancelled:#C60C30;
      --lightgrey: #C0C0C0;
      --midgrey: #808080;

      --pantone0110:#D7A900;  mustard
      --pantone0159:#C75B12;  orange
      --pantone0186:#C60C30;  red
      --pantone0555:#206C49;  darkgreen
      --pantone0583:#A8B400;  lightgreen
      --pantone1815:#782327;  maroon
      --pantone4485:#5B491F;  mud
      --pantone5565:#8BA69C;  gray
      --pantone7474:#007A87;  teal
     *
      case ($colour="mintcake'); code = :#9BC8AB;
      --sunset:#F08050;
      --granite:#404141;
      --rosycheeks:#F6B09D;
      --sunrise:#F9B104;
      --cloudy:#FFFFFF;
      --mintcakedark:#ABD8BB;
      --cancelled:#C60C30;
      --lightgrey: #C0C0C0;
      --midgrey: #808080;
     */

    static function lookupColourCode($colour, $option = 'B') {
        $code = '';
        switch ($colour) {
            case ($colour == 'mustard');
                $code = '0110';
                break;
            case ($colour == 'orange');
                $code = '0159';
                break;
            case ($colour == 'red');
                $code = '0186';
                break;
            case ($colour == 'darkgreen');
                $code = '0555';
                break;
            case ($colour == 'lightgreen');
                $code = '0583';
                break;
            case ($colour == 'maroon');
                $code = '1815';
                break;
            case ($colour == 'mud');
                $code = '4485';
                break;
            case ($colour == 'grey');
                $code = '5565';
                break;
            case ($colour == 'teal');
                $code = '7474';
                break;
            default;
                $class = $colour;
        }
        if ($code == '') { // colour name given
            $class .= $code;
        } else {
            if ($option == 'B') {
                $class = 'button-p' . $code;
            } else {
                $class = 'pantone' . $code;
            }
        }
        if ($option == 'B') {
            return 'link-button ' . $class;
        } else {
            return 'table table-striped ' . $class;
        }
    }

    function lookupGroup($group_code) {
        return $this->getValue("SELECT name FROM #__ra_groups WHERE code='" . $group_code . "' ");
    }

    public function lookupUser($user_id) {
        return $this->getValue("SELECT preferred_name FROM #__ra_profiles WHERE id='" . $user_id . "' ");
    }

    static function lookupMonth($mm) {
        switch ($mm) {
            case ($mm == 1);
                return "Jan";
            case ($mm == 2);
                return "Feb";
            case ($mm == 3);
                return "Mar";
            case ($mm == 4);
                return "Apr";
            case ($mm == 5);
                return "May";
            case ($mm == 6);
                return "Jun";
            case ($mm == 7);
                return "Jul";
            case ($mm == 8);
                return "Aug";
            case ($mm == 9);
                return "Sep";
            case ($mm == 10);
                return "Oct";
            case ($mm == 11);
                return "Nov";
            case ($mm == 12);
                return "Dec";
            default;
                return "zzz";
        }
    }

    function readFile($filename) {
// returns the content of the specified file from the media directory

        $root_to_base = Uri::root();

        $directory = "media/com_ra_tools/";
        if (file_exists($directory . $filename)) {
            return file_get_contents($directory . $filename);
        } else {
            $this->error = "(file $filename not found)";
            return false;
        }
    }

    static function selectLimit($limit, $target) {
// Generate a drop down list of integers for Limit
// When an item is selected from the list, a Javascript function will be invoked
// to pass control the the URL specified in $target

        $options[] = 10;
        $options[] = 20;
        $options[] = 30;
        $options[] = 50;
        $options[] = 100;

        echo 'Limit: <select id=selectLimit name=limit onChange="changeLimit(' . chr(39) . $target . chr(39) . ')">';
        for ($i = 0; $i < 5; $i++) {
            echo '<option value=' . $options[$i];
            if ($options[$i] == $limit) {
                echo ' selected';
            }
            echo '>' . $options[$i];
            echo '</option>';
        }
        echo '</select> ';
    }

    static function selectScope($scope, $target) {
// Generate a drop down list, but ensure the current state is listed first
// Overly complicated - could use select to specify current seleted option
        switch ($scope) {
            case ($scope == 'F');
                $options = 'FHAD';
                break;
            case ($scope == 'H');
                $options = 'HFAD';
                break;
            case ($scope == 'D');
                $options = 'DFHA';
                break;
            default;
                $options = 'AFHD';
        }

        echo 'Scope: <select id=selectScope name=scope onChange="changeScope(' . chr(39) . $target . chr(39) . ')">';
        for ($i = 0; $i < 4; $i++) {
            echo '<option value=' . substr($options, $i, 1) . '>';
            if (substr($options, $i, 1) == "F") {              // Future walks
                echo 'Future walks';
            } elseif (substr($options, $i, 1) == "H") {   // Historic
                echo 'Past walks';
            } elseif (substr($options, $i, 1) == "D") {   // Draft/ Cancelled/Archived
                echo 'Draft/Cancelled walks';
            } else {
                echo 'All walks';
            }
            echo '</option>';
        }
        echo '</select>';
    }

    static function removeSlash($url) {
// Checks that last character is NOT a forwards slash, removes it if necessary
        if (substr($url, (strlen($url) - 1)) == "/") {
            $url = substr($url, 0, (strlen($url) - 1));
        }
        return $url;
    }

    static function sanitisePath($parent_folder, $sub_folder) {
// trim whitespace and slashes from both ends of the parameters
        $parent_folder = trim($parent_folder, "/\\ \t\n\r\0\x0B");
        $sub_folder = trim($sub_folder, "/\\ \t\n\r\0\x0B");

        $sub_folder = str_replace(array('/', '\\'), DIRECTORY_SEPARATOR, $sub_folder);
//        echo "sub $sub_folder<br>";
//compile the full absolute path
        $path = $parent_folder;
        if (!$sub_folder == "") {
            $path .= DIRECTORY_SEPARATOR . $sub_folder;
        }
//        echo "Helper: path=$path<br>";
        return $path;
    }

    function sendEmail($to, $reply_to, $subject, $message, $attachments = '', $bcc = '') {
        $body = $this->buildEmailPreamble();
        $body .= $message;
        // Some older callers still append the closing tags themselves; only add them when missing.
        if (!preg_match('/<\/body>\s*<\/html>\s*$/i', $body)) {
            $body .= '</body></html>';
        }

        if (ComponentHelper::isEnabled('com_ra_delivery', true)) {
            $smtpHelper = new SmtpHelper;
            Factory::getApplication()->enqueueMessage('Sending using API ', 'info');
            return $smtpHelper->sendEmail($to, $reply_to, $subject, $body, $attachments, $bcc);
        }
        // Some older callers still append the closing tags themselves; only add them when missing.
        if (!preg_match('/<\/body>\s*<\/html>\s*$/i', $body)) {
            $body .= '</body></html>';
        }

        $body = $this->makeEmailImageUrlsAbsolute($body);
        $inline_images = [];
        $inline_debug = [];
        $body = $this->embedLocalEmailImages($body, $inline_images, $inline_debug);

        $log_target = is_array($to) ? implode(',', $to) : (string) $to;
        $log_target = substr($log_target, 0, 240);
        if (JDEBUG) {
            $this->createLog('RA Tools', '20', $log_target, 'sendEmail entered for subject "' . $subject . '"');
        }

        $params = ComponentHelper::getParams('com_ra_tools');
        $email_log_level = $params->get('email_log_level', '0');
        // Log level -2 is solely to benchmark the overhead of sending via SMTP
        if ($email_log_level == -2) {
            return true;
        }
        if (($email_log_level == 1) OR ($email_log_level == -1)) {
            $this->logEmail($to, $reply_to, $subject, $body, $attachments, $bcc);
            if ($email_log_level == -1) {
                return true;
            }
        }
        // Clone the Joomla mailer so each send starts with a clean PHPMailer instance.
        $mailer = clone Factory::getMailer();
        if (property_exists($mailer, 'SMTPKeepAlive')) {
            $mailer->SMTPKeepAlive = false;
        }
        if (method_exists($mailer, 'smtpClose')) {
            $mailer->smtpClose();
        }
        $config = Factory::getConfig();
        if (is_null($config)) {
            // being run in batch mode
            $params = ComponentHelper::getParams('com_ra_mailman');
            $email_details = $params['email_details'];
            $sender = explode('.', $email_details);
        } else {
            $sender = array(
                $config->get('mailfrom'),
                $config->get('fromname')
            );

            if ($reply_to == '') {
                $reply_to = $config->get('mailfrom');
            }
        }

        try {
            if (JDEBUG) {
                $this->createLog('RA Tools', '21', $log_target, 'Configuring mailer sender and recipients');
            }

            if (method_exists($mailer, 'clearAllRecipients')) {
                $mailer->clearAllRecipients();
            }
            if (method_exists($mailer, 'clearReplyTos')) {
                $mailer->clearReplyTos();
            }
            if (method_exists($mailer, 'clearAttachments')) {
                $mailer->clearAttachments();
            }
            if (method_exists($mailer, 'clearCustomHeaders')) {
                $mailer->clearCustomHeaders();
            }

            $mailer->setSender($sender);
            $mailer->addRecipient($to);
            if ($bcc !== '') {
                $mailer->addBcc($bcc);
            }
            $mailer->addReplyTo($reply_to);
            $mailer->isHtml(true);
            $mailer->Encoding = 'base64';
            $mailer->setSubject($subject);
            if (method_exists($mailer, 'addEmbeddedImage')) {
                foreach ($inline_images as $cid => $path) {
                    $mailer->addEmbeddedImage($path, $cid, basename($path));
                }
            }
            $mailer->setBody($body);
            // Keep the final body exactly as prepared above if Joomla's wrapper adjusts setBody().
            $mailer->Body = $body;

//     Optional file attachments - must be an array
            if ($attachments !== '') {
                $mailer->addAttachment($attachments);
            }

            if (JDEBUG) {
                $this->createLog('RA Tools', '22', $log_target, 'Calling mailer->Send()');
            }

            $result = $mailer->Send();

            if ($result) {
                if (JDEBUG) {
                    $this->createLog('RA Tools', '23', $log_target, 'mailer->Send() returned true');
                }
                return true;
            }

            $error_info = property_exists($mailer, 'ErrorInfo') ? (string) $mailer->ErrorInfo : 'Unknown mailer error';
            $this->createLog('RA Tools', '24', $log_target, 'mailer->Send() returned false: ' . $error_info);
            return false;
        } catch (\Throwable $exception) {
            $this->createLog('RA Tools', '25', $log_target, 'sendEmail exception: ' . $exception->getMessage());
            return false;
        } finally {
            if (method_exists($mailer, 'smtpClose')) {
                $mailer->smtpClose();
            }
        }
    }

    public function showAccess($id) {

        $sql = 'SELECT u.name, p.home_group, p.preferred_name, p.membershipNumber ';
        $sql .= 'FROM #__users AS u ';
        $sql .= 'LEFT JOIN #__ra_profiles as p on p.id = u.id ';
        $sql .= 'WHERE u.id=' . $id;
        $item = $this->getItem($sql);

        $user = Factory::getApplication()->getIdentity();
        echo 'You are logged in as ' . $item->name . ' (' . $id . ')';
        echo ', your home group is ' . $item->home_group;
        echo ', your preferred name is ' . $item->preferred_name;
        if ($item->membershipNumber > 0) {
            echo ', your membership Number is ' . $item->membershipNumber;
        }
        echo ', and you are a member of the following UserGroups<br>';
        $sql = 'SELECT g.title  ';
        $sql .= 'FROM #__user_usergroup_map AS map ';
        $sql .= 'INNER JOIN #__usergroups as g on g.id = map.group_id ';
        $sql .= 'WHERE map.user_id=' . $id . ' ';
        $sql .= 'ORDER BY g.title ';

        $item = $this->showQuery($sql);
        $sql = 'SELECT c.name, c.con_position, cat.title  ';
        $sql .= 'FROM #__contact_details AS c ';
        $sql .= 'INNER JOIN #__categories AS cat ON cat.id = c.catid ';
        $sql .= 'WHERE cat.extension = "com_contact" AND c.user_id=' . $id . ' ';
        //       echo $sql;
        $contact = $this->getItem($sql);
        if (!is_null($contact)) {
            echo 'You are registered as a Contact, with name <b>' . $contact->name;
            if ($contact->con_position !== '') {
                echo '</b> and Role of <b>' . $contact->con_position;
            }
            if ($contact->title !== '') {
                echo '</b> and Category of <b>' . $contact->title;
            }
            echo '</b><br>';
        }

        $this->showAccessComponent($id, 'com_ra_tools', 'RA Tools');
        $this->showAccessComponent($id, 'com_ra_mailman', 'RA MailMan');
        if (ComponentHelper::isEnabled('com_ra_mailman', true)) {
            $this->showAccessComponent($id, 'com_ra_members', 'RA Members');
        }
        // corporate mailman, quit
        $this->showAccessComponent($id, 'com_ra_events', 'RA Events');
        $this->showAccessComponent($id, 'com_ra_walks', 'RA Walks');
        $this->showAccessComponent($id, 'com_ra_wf', 'RA Walks Follow');

//        echo '<h3>RA Guided Walks</h3>' . PHP_EOL;
//        $this->showAccessComponent('com_ra_sg');
    }

    private function showAccessComponent($id, $component, $title) {
        if (!ComponentHelper::isEnabled($component, true)) {
//           echo '<h4>Not installed, or is disabled</h4>';
            return;
        }
        echo '<h2>' . $title . ' (' . $component . ')</h2>';
        $canDo = ContentHelper::getActions($component);
        echo 'Access backend ' . $this->showLiteral($canDo->get('core.manage')) . '<br>';
        echo 'Create ' . $this->showLiteral($canDo->get('core.create')) . '<br>';
        echo 'Delete ' . $this->showLiteral($canDo->get('core.delete')) . '<br>';
        echo 'Edit ' . $this->showLiteral($canDo->get('core.edit')) . '<br>';
        echo 'Edit state ' . $this->showLiteral($canDo->get('core.edit.state')) . '<br>';
        echo 'Change config ' . $this->showLiteral($canDo->get('core.admin')) . '<br>';
        if ($component == 'com_ra_mailman') {
            // See if Author of any lists
            $this->showLists($id);
        }
        if ($component == 'com_ra_events') {
            // See if Organiser of any events
            $this->showEvents($id);
        }
    }

    public function showDateMatrix($date_field, $table, $criteria, $title, $link, $back, $new_window = false) {
        // deprecated function - use showMonthMatrix instead
        $this->showMonthMatrix($date_field, $table, $criteria, $title, $link, $back, $new_window);
    }

    public function showDayMatrix($date_field, $table, $yyyy, $mm, $criteria, $title, $link, $back, $new_window = false) {
        if (Factory::getApplication()->isClient('administrator')) {
            ToolBarHelper::title($title);
        } else {
            echo '<h2>' . $title . '</h2>';
        }

        if ($link !== '') {
            $target = $link . '&date=' . $yyyy . '-' . $mm . '-';
        }
        $sql = 'SELECT COUNT(*) AS `record_count` ';
        $sql .= 'FROM ' . $table . '  ';
        $sql .= 'WHERE YEAR(' . $date_field . ')=' . $yyyy . ' ';
        $sql .= 'AND MONTH(' . $date_field . ')=' . $mm . ' ';
        if ($criteria !== '') {
            $sql .= 'AND ' . $criteria . '  ';
        }
        $sql .= 'AND DAY(' . $date_field . ')=';
        //       echo "$sql<br>";

        if ($mm == 12) {
            $next_month_yy = ($yyyy + 1);
            $next_month_mm = 01;
        } else {
            $next_month_yy = $yyyy;
            $next_month_mm = ($mm + 1);
        }
        $first_of_next_month = $next_month_yy . '-' . $next_month_mm . '-01';
        //       $date = 'yy=' . $next_month_yy . '&mm=' . $next_month_mm;

        $first_of_this_month = $yyyy . '-' . $mm . '-01';
        //       echo "first $first_of_this_month<br>";
        $daynum = date("N", strtotime($first_of_this_month));
// 1 = Monday, 7 - Sunday

        $datediff = strtotime($first_of_next_month) - strtotime($first_of_this_month);
        $days_in_month = round($datediff / (60 * 60 * 24));

//        echo "daynum=$daynum for $first_of_this_month, next month=$first_of_next_month, days_in_month=$days_in_month<br>";

        $this->toolsTable->add_header("Monday,Tuesday,Wednesday,Thursday,Friday,Saturday,Sunday");

        $col = 1;
        for ($i = 1; $col < $daynum; $i++) {
            $this->toolsTable->add_item(' ');
            $col++;
        }

        for ($i = 1; $i < ($days_in_month + 1); $i++) {
            $date = $yyyy . '-';
            if (strlen($mm) == 1) {
                $date .= '0' . $this->mm;
            } else {
                $date .= $mm;
            }
            $date .= '-';
            if ($i < 10) {
                $date .= '0' . $i;
            } else {
                $date .= $i;
            }
            $details = $i . ': <b>';
            $details = sprintf('%02d', $i) . ': <b>';
            $count = $this->getValue($sql . sprintf('%02d', $i));
            if ($count > 0) {
                if ($target == '') {
                    $details .= $count . '</b>';
                } else {
                    $details .= $this->buildLink($target . sprintf('%02d', $i), $count);
                }
            }
            $this->toolsTable->add_item($details);
            $col++;
            if ($col == 8) {
                $this->toolsTable->generate_line();
                $col = 1;
            }
        }

        $this->toolsTable->generate_line();
        $this->toolsTable->generate_table();
        if ($back !== '') {
            echo $this->backButton($back);
        }
        return;

        $rows = $this->getRows($sql);
        foreach ($rows as $row) {
            $this->toolsTable->add_item($row->Year);

            for ($m = 1; $m < 13; $m++) {
                $sql = 'SELECT COUNT(*) AS `record_count` ';
                $sql .= 'FROM ' . $table . ' ';
                $sql .= "WHERE YEAR(" . $date_field . ")='" . $row->Year . "' ";
                $sql .= "AND MONTH(" . $date_field . ")='" . $m . "' ";
                if ($criteria !== '') {
                    $sql .= 'AND ' . $criteria . '  ';
                    echo $sql . '<br>';
                }
//
                if ($link == '') {
                    $this->toolsTable->add_item($this->getValue($sql));
                } else {
                    $count = $this->getValue($sql);
                    if ($count == 0) {
                        $this->toolsTable->add_item('');
                    } else {
                        $target = $link . '&year=' . $row->Year . '&month=' . $m;
                        $this->toolsTable->add_item($this->buildLink($target, $count, $new_window));
                    }
                }
            }

            $this->toolsTable->generate_line();
        }
        $this->toolsTable->generate_table();
        if ($back !== '') {
            echo $this->backButton($back);
        }
    }

    public function showEmail($id) {
        $toolsHelper = new ToolsHelper;
        $sql = 'SELECT e.* ';
        $sql .= 'FROM #__ra_emails AS e ';
        $sql .= 'WHERE id=' . $id;
        $email = $toolsHelper->getItem($sql);
        ToolBarHelper::title('Email ' . $email->id);
        if (is_null($email)) {
            echo 'No data found<br>';
            echo $sql . '<br>';
            return;
        }
        echo '<b>' . $email->title . '</b><br>';
        echo '<div style="padding-left: 19px;">'; // create div with offset left margin
        echo $email->body . '<br>';
        echo '</div>';
//        echo '<br>';
        echo 'Sent ' . HTMLHelper::_('date', $email->date_sent, 'd M y H:i') . '<br>';
        echo 'Sender name ' . $email->sender_name . '<br>';
        echo 'Sender email ' . $email->sender_email . '<br>';
        echo 'Addressee name ' . $email->addressee_name . '<br>';
        echo 'Addressee email ' . $email->addressee_email . '<br>';
        echo 'Record type ' . $email->record_type . '<br>';
        echo 'Reference ' . $email->sub_system . ' ' . $email->ref . '<br>';
        if ($email->sub_system == 'RA Events') {
            $sql = 'SELECT e.title, e.event_date, e.event_time, t.description ';
            $sql .= 'FROM #__ra_events AS e ';
            $sql .= 'LEFT JOIN #__ra_event_types AS t ON t.id = e.event_type_id ';
            $sql .= 'WHERE e.id=' . $email->ref;
            $event = $this->getItem($sql);

            echo '<div style="padding-left: 19px;">'; // create div with offset left margin
            echo 'Type: <b>' . $event->description . '</b><br>';
            echo 'Title: <b>' . $event->title . '</b><br>';
            echo 'Date: <b>' . HTMLHelper::_('date', $event->event_date, 'd-M-y') . '</b><br>';
            echo '</div>';
        }
    }

    public function showEvents($id, $future = 'Y') {

        $sql = 'SELECT e.id, e.event_date, e.title, COUNT(b.id) as cnt, group_code, ';
        $sql .= 'SUM(b.num_places) as num , e.max_bookings, e.notify_organiser, t.description ';
        $sql .= 'FROM #__ra_events AS e ';
        $sql .= 'INNER JOIN #__contact_details AS c ON c.id = e.contact_id ';
        $sql .= 'INNER JOIN #__ra_event_types AS t ON t.id = e.event_type_id ';
        $sql .= 'LEFT JOIN #__ra_bookings AS b ON b.event_id = e.id ';
        $sql .= 'WHERE c.user_id=' . $id;
        if ($future == 'Y') {
            $sql .= ' AND DATEDIFF(e.event_date, CURRENT_DATE)>=0 ';
            $missing = 'You are not organising any Future Events';
        } else {
            $sql .= ' AND DATEDIFF(e.event_date, CURRENT_DATE)<0 ';
            $missing = 'You have yet not organising any Events';
        }
        $sql .= ' GROUP BY e.id, e.event_date, e.title, e.max_bookings, e.notify_organiser, t.description';
        $sql .= ' ORDER BY e.event_date DESC';

        $rows = $this->getRows($sql);
        if ((count($rows) == 0)) {
            echo $missing;
        } else {
            $this->toolsTable->add_header("Date,Group,Event,Type,Max places,Notify,Count bookings,Total places,Emails");
            if ($future == 'Y') {
                echo '<h2>Future Events organised by you</h2>';
            } else {
                echo '<h2>Past Events organised by you</h2>';
            }
            $sql = 'SELECT COUNT(id) as num ';
            $sql .= 'FROM #__ra_emails AS e ';
            $sql .= 'WHERE sub_system="RA Events" ';
            $sql .= 'AND ref=';
            $target = 'index.php?option=com_ra_tools&task=emails.showByRef';
            foreach ($rows as $row) {
                $count = $this->getValue($sql . $row->id);
                $this->toolsTable->add_item($row->event_date);
                $this->toolsTable->add_item($row->group_code);
                $event_link = Route::_('index.php?option=com_ra_events&view=event&id=' . $row->id);
                $this->toolsTable->add_item('<a href="' . $event_link . '">' . $row->title . '</a>');
                $this->toolsTable->add_item($row->description);
                $this->toolsTable->add_item($row->max_bookings);
                if ($row->notify_organiser == 1) {
                    $this->toolsTable->add_item('Yes');
                } else {
                    $this->toolsTable->add_item('No');
                }
                $this->toolsTable->add_item($row->cnt);
                $this->toolsTable->add_item($row->num);
                $this->toolsTable->add_item($count);
                $this->toolsTable->generate_line();
            }
            $this->toolsTable->generate_table();
            if (count($rows) > 1) {
                echo count($rows) . ' Events<br>';
            }
        }
    }

    private function showLiteral($value) {
        if ($value) {
            return '<img src="/media/com_ra_tools/tick.png" width="20" height="20">' . 'Permitted';
        } else {
            return '<img src="/media/com_ra_tools/cross.png" width="20" height="20">' . 'Denied';
        }
    }

    /*


      $field = 'walk_date';
      $table = ' #__ra_walks';
      $criteria = '';
      $title = 'Walks by month';
      $link = '';
      $back = $this->back;
      //        $this->showDateMatrix($field,$table, $criteria,, $title, $link, $back);
     */

    public function showLocation($latitude, $longitude, $mode = 'O') {
        /*
         * Returns an image button with a link to the location
         * mode = G - Google maps
         * mode = S = Streetmap
         * other OSM
         */


        if ((floatval($latitude) == 0) or (floatval($longitude) == 0)) {
            return '';
        }
        if ($mode == 'G') {
            $target = "https://www.google.com/maps?q=";
            $target .= $latitude;
            $target .= "," . $longitude;
            $target .= "&z=9";
            return $this->imageButton("GO", $target, True);
        } elseif ($mode == 'S') {
            // 27/05/24 CB Strretmap prefers to have Nothing / Eas                                               ting
            $target = 'https://streetmap.co.uk/loc/' . $latitude . ',';
            if ($longitude > 0) {
                $target .= 'E' . $longitude;
            } else {
                $target .= 'W' . abs($longitude);
            }
            return $this->imageButton("I", $target, True);
        } else {
            // https://www.openstreetmap.org/?mlat=43.59021&mlon=1.40741#map=11/43.5902/1.4074
            $target = "https://www.openstreetmap.org?mlat=" . $latitude;
            $target .= "&mlon" . $longitude; // . "&zoom=13";
            $target .= '#map=13/' . $latitude . '/' . $longitude;
//            echo '<i class="fa-solid fa-map-pin"></i>';
            return $this->imageButton("P", $target, True);
        }
    }

    function showLogo($align = 'R') {
        $icon = "logo_90px.png";
// '<img src="media/com_ra_tools/logo_90px.png" alt="logo" width="40" height="64" style="float: right;">';
        return '<img src="' . $this->image_folder . "/" . $icon . '" alt="logo" width="91" height="91" style="float: right;">';
    }

    public function showExtensions() {
        $this->toolsTable->add_header('Type,Name,Element,Enabled,Version,DB version,id');
        $extensions = array(
            'tpl_hydrogen_ramblers',
            'com_hy_schema',
            'com_ra_decode',
            'com_ra_events',
            'com_ra_mailman',
            'com_ra_paths',
            'com_ra_tools',
            'com_ra_walks',
            'com_ra_wf',
            'mod_ra_calendar_download',
            'mod_ra_events',
            'mod_ra_paths',
            'mod_ra_walks',
            'mod_rafooter',
            'mod_raheader',
            'ra_events',
            'ra_eventscli',
            'ra_mailman',
            'ramblers',
            'ramblerswalks',
        );
        $sql = 'SELECT e.extension_id, e.name, e.type, e.element, e.enabled, ';
        $sql .= 'e.manifest_cache, s.version_id ';
        $sql .= 'FROM #__extensions as e ';
        $sql .= 'LEFT JOIN #__schemas AS s ON s.extension_id = e.extension_id ';
        $sql .= 'WHERE element="';
        foreach ($extensions as $extension) {
//    echo '<h3>' . $extension . '</h3>';
//    echo $sql . $extension . '"';
            $rows = $this->getRows($sql . $extension . '"');
            if (!is_null($rows)) {
                foreach ($rows as $row) {
                    $cache = $row->manifest_cache;
                    $pos = strpos($cache, 'version');
                    $temp = substr($cache, $pos + 10);
                    $pos = strpos($temp, '"');
                    $this->toolsTable->add_item($row->type);
                    $this->toolsTable->add_item($row->name);
                    $this->toolsTable->add_item($row->element);
                    $this->toolsTable->add_item($row->enabled);
                    $this->toolsTable->add_item(substr($temp, 0, $pos));
                    $this->toolsTable->add_item($row->version_id);
                    $this->toolsTable->add_item($row->extension_id);
                    $this->toolsTable->generate_line();
                }
            }
        }


        $this->toolsTable->generate_table();
    }

    private function showLists($id) {
        $sql = ' FROM #__ra_mail_lists AS l ';
        $sql .= 'INNER JOIN #__ra_mail_subscriptions AS s ON s.list_id = l.id ';
        $sql .= 'WHERE s.user_id=' . $id;
        $count = $this->getValue('SELECT COUNT(*)' . $sql);
        if ($count > 0) {
            echo '<b>You are an Author for the following list';
            if ($count > 1) {
                echo 's';
            }
            echo ':</b><br>' . PHP_EOL;
            echo '<ul>' . PHP_EOL;
            $rows = $this->getRows('SELECT l.name, l.group_code' . $sql);
            foreach ($rows as $row) {
                echo '<li>' . $row->group_code . ' ' . $row->name . '</li>' . PHP_EOL;
            }
            echo '</ul>' . PHP_EOL;
        }
    }

    public function showMonthMatrix($date_field, $table, $criteria, $title, $link, $back, $new_window = false) {

        /*
         * Example of usage
          $field = 'event_date';
          $table = ' #__ra_events';
          $criteria = 'state=1';          // N.B. do not include WHERE
          $title = 'Events by month';
          $link = "administrator/index.php?option=com_ra_events&task=reports.test&year=d&month=";
          $back = $this->back;
          $helper->showMonthMatrix($field, $table, $criteria, $title, $link, $back);
         */
        if (Factory::getApplication()->isClient('administrator')) {
            ToolBarHelper::title($title);
        } else {
            echo '<h2>' . $title . '</h2>';
        }
        $sql = 'SELECT YEAR(' . $date_field . ') AS `Year`, COUNT(*) AS `record_count` ';
        $sql .= 'FROM ' . $table . '  ';
        if ($criteria !== '') {
            $sql .= 'WHERE ' . $criteria . '  ';
        }
        $sql .= 'GROUP BY YEAR(' . $date_field . ') ';
        $sql .= 'ORDER BY YEAR(' . $date_field . ')';
        $toolsTable = new ToolsTable();

        $toolsTable->add_header("Year,Jan,Feb,Mar,Apr,May,Jun,Jul,Aug,Sep,Oct,Nov,Dec");
        $rows = $this->getRows($sql);
        foreach ($rows as $row) {
            $toolsTable->add_item($row->Year);

            for ($m = 1; $m < 13; $m++) {
                $sql = 'SELECT COUNT(*) AS `record_count` ';
                $sql .= 'FROM ' . $table . ' ';
                $sql .= "WHERE YEAR(" . $date_field . ")='" . $row->Year . "' ";
                $sql .= "AND MONTH(" . $date_field . ")='" . $m . "' ";
                if ($criteria !== '') {
                    $sql .= 'AND ' . $criteria . '  ';
                }
//
                if ($link == '') {
                    $toolsTable->add_item($this->getValue($sql));
                } else {
                    $count = $this->getValue($sql);
                    if ($count == 0) {
                        $toolsTable->add_item('');
                    } else {
                        $target = $link . '&year=' . $row->Year . '&month=' . $m;
                        $toolsTable->add_item($this->buildLink($target, $count, $new_window));
                    }
                }
            }

            $toolsTable->generate_line();
        }
        $toolsTable->generate_table();
        if ($back !== '') {
            echo $this->backButton($back);
        }
    }

    function showPrint($target, $newWindow = 0) {
// Given the URL of the current page, generates CSS to display a link for creating a
// pop-up dialogue that allows the current page to be printed (without menus etc)
// See https://docs.joomla.org/Adding_print_pop-up_functionality_to_a_component
// See also https://docs.joomla.org/Customizing_the_print_pop-up
        if (substr($target, 0, 4) == "http") {
            $root_to_base = "";
        } else {
            $root_to_base = Uri::root();
        }

        $objApp = Factory::getApplication();
        $isModal = $objApp->input->getCmd('print', '') == 1; // 'print=1' will only be present in the url of the modal window, not in the presentation of the page
        if ($isModal) {
            $href = '"#" onclick="window.print(); return false;"';
        } else {
            $href = 'status=no,toolbar=no,scrollbars=yes,titlebar=no,menubar=no,resizable=yes,width=640,height=480,directories=no,location=no';
            $href = "window.open(this.href,'win2','" . $href . "'); return false;";
            $href = $target . '&tmpl=component&print=1 ' . $href;
        }
        $icon = $root_to_base . "/media/com_ra_tools/print.png";
        $return = '<a href="' . $href . '" ';
        if ($newWindow) {
            $return .= 'target ="_blank" ';
        }
        $return .= '><img src="' . $icon . '" alt="Print" width="30" height="30"/></a>';
//       $return .= 'Print</a>';
        return $return;
    }

    function showQuery($sql, $class = 'table table-striped') {
// From https://stackoverflow.com/questions/49703792/how-to-securely-write-a-joomla-select-query-with-an-in-condition-in-the-where-cl

        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $db->setQuery($sql);

        try {
            if (!$result = $db->loadAssocList()) {
                echo "No Qualifying Rows";
                if (JDEBUG) {
                    Factory::getApplication()->enqueueMessage($sql, 'info');
                }
            } else {
                echo "";
                echo '<div class="table-responsive">' . PHP_EOL;
                echo "<table class=\"" . $class . "\">";
                echo "<tr><th>", implode("</th><th>", array_keys($result[0])), "</th></tr>";
                foreach ($result as $row) {
                    echo "<tr><td>", implode("</td><td>", $row), "</td></tr>";
                }
                echo "</table>";
                echo '</div">' . PHP_EOL;   // table-responsive
            }
        } catch (\Exception $e) {
// never show getMessage() to the public
            Factory::getApplication()->enqueueMessage("Query Syntax Error: " . $e->getMessage(), 'error');
        }
    }

    function showSql($sql, $csv = '', $class = 'table table-striped') {
// Parse the sql to derive the list of fields
// Usage: (assuming $sql and $filename have been defined)
// $objTable = new ToolsTable();
// $objTable->set_csv($filename);
// $objTable->add_header("aa,bb");
// $rows = $objHelper->getRows($sql);
// foreach ($rows as $row) {
// $objTable->add_item($row->aa);
// $objTable->add_item($row->bb);
// $objTable->generate_line();
// }
// $objTable->generate_table();
        $this->error = '';
        $debug = 0;
        $field_count = 0;
        $first_line = True;
        $objTable = new ToolsTable();
// if as value has been supplied, data will be written to a CSV file of that name
        $objTable->set_csv($csv);

        if ((strtoupper(substr($sql, 0, 7)) == 'SELECT ') and (strpos(strtoupper($sql), ' FROM ') > 0)) {
            $fields = explode(',', substr($sql, 6, (strpos(strtoupper($sql), ' FROM ') - 5)));
//          if a field name contains a comma, eg date_format(walk_date,'%a %e-%m-%y')
//          it may have been split unnecessarily, so check to see
//          if an entry contains an opening bracket but not closing bracket, append to it the next entry
            $ipointer = 0;
            $skip = false;
            foreach ($fields as $field) {
                if ($debug) {
                    echo "Field " . $ipointer . '=' . $field;
                    if ($skip) {
                        echo ', skip=True';
                    }
                    echo '<br>';
                }
                $open_bracket = strpos($field, '(');
                if ($open_bracket == 0) {
                    $concatenate = false;
                } else {
                    if ($debug) {
                        echo "--" . ' Found ( in ' . $field . ' at position ' . $open_bracket . '<br>';
                    }
                    $close_bracket = strpos($field, ')');
                    if ($close_bracket == 0) {
                        $concatenate = true;
                    } else {
                        $concatenate = false;

                        if ($debug) {
                            echo '-- Found ) in ' . $field . ' at position ' . $close_bracket . '<br>';
                        }
                    }
                }
                if ($concatenate) {
                    $sql_fields[$ipointer] = $fields[$ipointer] . $fields[$ipointer + 1];
                    $skip = true;
                } else {
                    if ($skip) {
                        $skip = false;
                    } else {
                        $sql_fields[$ipointer] = $fields[$ipointer];
                    }
                }
                $ipointer++;
            }
        } else {
            $this->error = "Invalid SQL: Must contain SELECT .. FROM";
            return false;
        }
        foreach ($sql_fields as $field) {
            if (strlen($field) > 0) {
                $field_count++;
                if ($debug) {
                    echo $field . '<br>';
                }
                $ipos = strpos(strtoupper($field), ' AS ');
                if ($ipos > 0) {
//                        echo 'Found' . substr($field, $ipos + 4) . '<br>';
                    $as = substr($field, $ipos + 4);
                    if (substr($as, 0, 1) == "'") {
                        $objTable->add_column(substr($as, 1, strlen($as) - 2), "L");
                    } else {
                        $objTable->add_column(substr($field, $ipos + 4), "L");
                    }
                } else {
//                        echo $field . '<br>';
                    $objTable->add_column($field, "L");
                }
            }
        }

        if ($debug) {
            echo 'Helper::showSql: sql=' . $sql . '<br>';
        }
        try {
            $db = Factory::getContainer()->get(DatabaseInterface::class);
            $query = $db->getQuery(true);
            $db->setQuery($sql);
            $db->execute();
            $num_rows = $db->getNumRows();
            if ($debug) {
                echo 'Helper::showSql: sql=' . "Rows=$num_rows, Cols=$field_count<br>" . '<br>';
            }
            $matrix = $db->loadRowList();
            for ($row = 0; $row < $num_rows; $row++) {
                if ($first_line === True) {
                    $first_line = False;
                    $objTable->generate_header();
                }
                for ($col = 0; $col < $field_count; $col++) {
                    $objTable->add_item($matrix[$row][$col]);
                }
                $objTable->generate_line();
            }

            $objTable->generate_table();
// Count the actual rows of data
            $this->rows = $objTable->get_rows() - 1;
            return true;
        } catch (\Exception $ex) {
            $this->error = $ex->getMessage();
//            echo "Helper::showSql: " . $this->error;
            return false;
        }
    }

    function standardButton($type, $target, $new_window = false) {
        switch ($type) {
            case ($type == 'Go');
                $colour = 'red';
                break;
            case ($type == 'Send');
                $colour = 'red';
                break;
            default;
                $colour = 'Granite';
        }
        return $this->buildButton($target, $type, $new_window, $colour);
    }

    static function stateDescription($state) {
        switch ($state) {
            case 0:
                return 'unpublished';
            case 1:
                return 'published';
            case 2:
                return 'archived';
            case -2:
                return 'trashed';
            default;
                return "state $state not recognised";
        }
    }

    static function testInput($data) {
        $data = trim($data);
        $data = stripslashes($data);
        $data = htmlspecialchars($data);
        return $data;
    }

    static function validActive(&$active, &$message) {
        $active = strtoupper($active);
        if (($active === "Y") or ($active == "N")) {
            return 1;
        } else {
            $message = "Active: must be ";
            if (trim($active) == "") {
                $message .= "given";
            } else {
                $message .= "Y or N (not $active)";
            }
            return 0;
        }
    }

// end function ValidActive

    public function validGroupcode($value) {

        if (strlen($value) != 4) {
            $this->error = 'Group code must be four characters XXnn';
            return false;
        }

        $char_0_1 = substr($value, 0, 2);
        $char_2_3 = substr($value, 2, 2);
        if (!ctype_alpha($char_0_1)) {
            $this->error = 'First two characters must be alpha ' . $char_0_1 . '/' . $char_2_3;
            return false;
        }
        if (!ctype_digit($char_2_3)) {
            $this->error = 'Last two characters must be numeric ' . $char_2_3;
            return false;
        }
        $db = Factory::getContainer()->get(DatabaseInterface::class);
        $sql = 'SELECT COUNT(id) FROM #__ra_groups ';
        $sql .= 'WHERE code=' . $db->q($value);
        $count = (int) $this->getValue($sql);

        if ($count == 0) {
            $this->error = 'Group code ' . $value . ' not found';
            return false;
        }

        return true;
    }

    static function validateEmail($email) {
        // This should be a Rule
        if (filter_var($email, FILTER_VALIDATE_EMAIL)) {
//            echo "Email address '$this->email' is considered valid.\n";
            return true;
        } else {
            return false;
        }
    }

    function walksfinderButton($walk_id) {
// display a button to show walk details from CO site
        $target = 'https://www.ramblers.org.uk/go-walking/find-a-walk-or-route/walk-detail.aspx?walkID=';
        return $this->buildLink($target . $walk_id, "Walksfinder", True, "link-button button-p0110");
    }

}
