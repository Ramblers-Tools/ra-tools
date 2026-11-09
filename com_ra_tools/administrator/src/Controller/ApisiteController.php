<?php

/**
 * @version    2.3.4
 * @package    com_ra_tools
 * @author     Charlie Bigley <charlie@bigley.me.uk>
 * @copyright  2025 Charlie Bigley
 * @license    GNU General Public License version 2 or later; see LICENSE.txt
 * 26/02/26 CB added queryEndpoints method
 * 09/11/26 CB Replaced getDbo with DatabaseInterface
 */

namespace Ramblers\Component\Ra_tools\Administrator\Controller;

\defined('_JEXEC') or die;

use Joomla\CMS\MVC\Controller\FormController;
use Joomla\CMS\Factory;
use Ramblers\Component\Ra_tools\Site\Helpers\ToolsHelper;

/**
 * Apisite controller class.
 *
 */
class ApisiteController extends FormController {
    protected $view_list = 'apisites';

    /**
     * Proxy for getModel - Required for FormController to load the model
     *
     * @param   string  $name    Optional. Model name
     * @param   string  $prefix  Optional. Class prefix
     * @param   array   $config  Optional. Configuration array for model
     *
     * @return  object	The Model
     */
    public function getModel($name = 'Apisite', $prefix = 'Administrator', $config = array()) {
        return parent::getModel($name, $prefix, array('ignore_request' => true));
    }

    /**
     * Query API endpoints for a given site
     *
     * @return void
     */
    public function queryEndpoints() {
        $id = Factory::getApplication()->input->getInt('id', 0);
        
        // Register CSS style
        $wa = Factory::getApplication()->getDocument()->getWebAssetManager();
        $wa->registerAndUseStyle('ramblers', 'com_ra_tools/ramblers.css');
        
        if ($id == 0) {
            Factory::getApplication()->enqueueMessage('API Site ID is missing', 'error');
            $this->setRedirect('index.php?option=com_ra_tools&view=apisites');
            return;
        }

        $db = \Joomla\CMS\Factory::getContainer()->get(\Joomla\Database\DatabaseInterface::class);
        $query = $db->getQuery(true)
            ->select(['id', 'url', 'token', 'title', 'sub_system'])
            ->from($db->quoteName('#__ra_api_sites'))
            ->where($db->quoteName('id') . ' = ' . (int)$id);
        $db->setQuery($query);
        $item = $db->loadObject();

        if (!$item) {
            Factory::getApplication()->enqueueMessage('API Site not found', 'error');
            $this->setRedirect('index.php?option=com_ra_tools&view=apisites');
            return;
        }

        echo $this->displayEndpoints($item);
        $back = 'administrator/index.php?option=com_ra_tools&view=apisites';
        $toolsHelper = new ToolsHelper;     
        echo $toolsHelper->backButton($back);
    }

    /**
     * Display API endpoints for a given site
     *
     * @param object $item The API site item
     * @return string HTML output
     */
    private function displayEndpoints($item) {
        $html = '';
        $html .= '<div class="container-lg" style="margin-top: 20px;">';
        $html .= '<div class="row">';
        $html .= '<div class="col-12">';
        
        $html .= '<h2>API Endpoints for: ' . htmlspecialchars($item->title) . '</h2>';
        $html .= '<p><strong>Site:</strong> ' . htmlspecialchars($item->url) . '</p>';
        $html .= '<p><strong>Sub System:</strong> ' . htmlspecialchars($item->sub_system) . '</p>';
        
        $endpoints = $this->queryApiEndpoints($item);
        
        if ($endpoints === false) {
            $html .= '<div class="alert alert-danger">Unable to retrieve endpoints from the API site</div>';
        } elseif (empty($endpoints)) {
            $html .= '<div class="alert alert-info">No endpoints found or unable to discover endpoints</div>';
        } else {
            // Separate endpoints by type
            $joomlaEndpoints = array_filter($endpoints, function($ep) {
                return isset($ep['type']) && $ep['type'] === 'Joomla';
            });
            $customEndpoints = array_filter($endpoints, function($ep) {
                return !isset($ep['type']) || $ep['type'] !== 'Joomla';
            });
            
            // Display Joomla Endpoints
            if (!empty($joomlaEndpoints)) {
                $html .= '<h3>Joomla Endpoints';
                if ($joomlaEndpoints[0]['permission'] ?? false) {
                    $html .= ' <span class="badge bg-success"><i class="fas fa-check"></i> Permission OK</span>';
                } else {
                    $html .= ' <span class="badge bg-danger"><i class="fas fa-times"></i> Permission Denied</span>';
                }
                $html .= '</h3>';
                $html .= '<table class="table table-striped">';
                $html .= '<thead>';
                $html .= '<tr><th>Endpoint</th><th>Method</th><th>Description</th></tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($joomlaEndpoints as $endpoint) {
                    $method = isset($endpoint['method']) ? strtoupper(htmlspecialchars($endpoint['method'])) : 'GET';
                    $html .= '<tr>';
                    $html .= '<td><code>' . htmlspecialchars($endpoint['path']) . '</code></td>';
                    $html .= '<td><span class="badge bg-info">' . $method . '</span></td>';
                    $html .= '<td>' . htmlspecialchars($endpoint['description']) . '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '</table>';
                $html .= '<br/>';
            }
            
            // Display Custom Endpoints
            $html .= '<h3>Custom Endpoints</h3>';
            if (empty($customEndpoints)) {
                $html .= '<p><em>None found</em></p>';
            } else {
                $html .= '<table class="table table-striped">';
                $html .= '<thead>';
                $html .= '<tr><th>Endpoint</th><th>Method</th><th>Permission</th><th>Description</th></tr>';
                $html .= '</thead>';
                $html .= '<tbody>';
                
                foreach ($customEndpoints as $endpoint) {
                    $method = isset($endpoint['method']) ? strtoupper(htmlspecialchars($endpoint['method'])) : 'GET';
                    $permission = isset($endpoint['permission']) && $endpoint['permission'] ? '<span class="badge bg-success"><i class="fas fa-check"></i></span>' : '<span class="badge bg-warning"><i class="fas fa-question"></i></span>';
                    $html .= '<tr>';
                    $html .= '<td><code>' . htmlspecialchars($endpoint['path']) . '</code></td>';
                    $html .= '<td><span class="badge bg-info">' . $method . '</span></td>';
                    $html .= '<td>' . $permission . '</td>';
                    $html .= '<td>' . htmlspecialchars($endpoint['description']) . '</td>';
                    $html .= '</tr>';
                }
                
                $html .= '</tbody>';
                $html .= '</table>';
            }
        }
        
        $html .= '<br/>';
        $html .= '</div>';
        $html .= '</div>';
        $html .= '</div>';
        
        return $html;
    }

    /**
     * Query the API site to discover available endpoints
     *
     * @param object $item The API site item
     * @return array|false Array of endpoints or false on failure
     */
    private function queryApiEndpoints($item) {
        $endpoints = [];
        $hasPermission = false;
        
        // Test a simple endpoint to verify permission
        $testUrl = rtrim($item->url, '/') . '/api/index.php/v1/content/articles';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $testUrl);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/vnd.api+json',
            'Content-Type: application/json',
            'X-Joomla-Token: ' . trim($item->token)
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        // 200 = success, 401 = unauthorized, 403 = forbidden
        $hasPermission = ($httpCode == 200);
        
        // Try to query the API root endpoint for custom endpoint discovery
        $apiUrl = rtrim($item->url, '/') . '/api/index.php';
        
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $apiUrl);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'Accept: application/vnd.api+json',
            'Content-Type: application/json',
            'X-Joomla-Token: ' . trim($item->token)
        ]);
        
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        
        if ($httpCode == 200) {
            $data = json_decode($response, true);
            
            if (is_array($data)) {
                // Parse discovered endpoints from API response
                if (isset($data['links']) && is_array($data['links'])) {
                    foreach ($data['links'] as $link) {
                        if (isset($link['href']) || isset($link['rel'])) {
                            $endpoints[] = [
                                'path' => $link['href'] ?? $link['rel'] ?? '',
                                'method' => $link['method'] ?? 'GET',
                                'description' => $link['title'] ?? $link['description'] ?? '',
                                'type' => 'Custom',
                                'permission' => true
                            ];
                        }
                    }
                }
                
                // Also check for common API documentation endpoints
                if (isset($data['routes']) && is_array($data['routes'])) {
                    foreach ($data['routes'] as $path => $route) {
                        if (is_array($route)) {
                            foreach ($route as $method => $details) {
                                $endpoints[] = [
                                    'path' => $path,
                                    'method' => strtoupper($method),
                                    'description' => is_array($details) && isset($details['description']) ? $details['description'] : '',
                                    'type' => 'Custom',
                                    'permission' => true
                                ];
                            }
                        }
                    }
                }
            }
        }
        
        // Add standard Joomla API endpoints with tested permission status
        $joomlaEndpoints = [
            [
                'path' => '/api/index.php/v1/articles',
                'method' => 'GET',
                'description' => 'Get articles',
                'type' => 'Joomla',
                'permission' => $hasPermission
            ],
            [
                'path' => '/api/index.php/v1/articles/:id',
                'method' => 'GET',
                'description' => 'Get article by ID',
                'type' => 'Joomla',
                'permission' => $hasPermission
            ],
            [
                'path' => '/api/index.php/v1/categories',
                'method' => 'GET',
                'description' => 'Get categories',
                'type' => 'Joomla',
                'permission' => $hasPermission
            ],
            [
                'path' => '/api/index.php/v1/categories/:id',
                'method' => 'GET',
                'description' => 'Get category by ID',
                'type' => 'Joomla',
                'permission' => $hasPermission
            ],
            [
                'path' => '/api/index.php/v1/users',
                'method' => 'GET',
                'description' => 'Get users',
                'type' => 'Joomla',
                'permission' => $hasPermission
            ],
            [
                'path' => '/api/index.php/v1/users/:id',
                'method' => 'GET',
                'description' => 'Get user by ID',
                'type' => 'Joomla',
                'permission' => $hasPermission
            ],
            [
                'path' => '/api/index.php/v1/menu-items',
                'method' => 'GET',
                'description' => 'Get menu items',
                'type' => 'Joomla',
                'permission' => $hasPermission
            ],
            [
                'path' => '/api/index.php/v1/contact-forms',
                'method' => 'GET',
                'description' => 'Get contact forms',
                'type' => 'Joomla',
                'permission' => $hasPermission
            ],
            [
                'path' => '/api/index.php/v1/banners',
                'method' => 'GET',
                'description' => 'Get banners',
                'type' => 'Joomla',
                'permission' => $hasPermission
            ],
            [
                'path' => '/api/index.php/v1/content/tags',
                'method' => 'GET',
                'description' => 'Get tags',
                'type' => 'Joomla',
                'permission' => $hasPermission
            ],
            [
                'path' => '/api/index.php/v1/fields',
                'method' => 'GET',
                'description' => 'Get custom fields',
                'type' => 'Joomla',
                'permission' => $hasPermission
            ],
        ];
        
        // Merge Joomla endpoints with discovered endpoints
        $endpoints = array_merge($endpoints, $joomlaEndpoints);
        
        return $endpoints;
    }

}
