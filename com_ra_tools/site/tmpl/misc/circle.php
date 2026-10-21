<?php

/**
 * 19/01/26 CB Changes to implement new radius selection
 * 20/11/26 CB show radius distance as miles
 * 30/08/28 CB correct use of menu setting
 */
// No direct access
defined('_JEXEC') or die;

use \Joomla\CMS\Factory;
use \Joomla\CMS\Component\ComponentHelper;

// Load the component params
$params = ComponentHelper::getParams('com_ra_tools');
// Get parameters for circle map layout
$group = $this->app->input->getCmd('group', '');
if ($group == '') {
    $group = $params->get('default_group', '');
}
// If vaule is given as a parameter, use that, otherwise use the menu setting
$radius = $this->app->input->getInt('radius', '');
if ($radius == '') {
   $radius = $this->menu_params->get('radius_miles', '25');
}

echo '<h2>Map showing ' . $radius . ' miles radius around group ' . $group . ' ' . PHP_EOL;    
echo $this->toolsHelper->lookupGroup($group) . '</h2>' . PHP_EOL;
$intro = $this->menu_params->get('page_intro', '');
if (!$intro == '') {
    echo $intro;
}
// Get the document and Web Asset Manager
$doc = Factory::getDocument();
$wa = $doc->getWebAssetManager();

// Add Leaflet CSS and JavaScript
$wa->registerAndUseStyle('leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/leaflet.min.css');
$wa->registerAndUseScript('leaflet', 'https://cdnjs.cloudflare.com/ajax/libs/leaflet/1.7.1/leaflet.min.js');

// Get the item with latitude and longitude
$item = $this->toolsHelper->getItem('SELECT latitude, longitude from #__ra_groups where code="' . $group . '"');

// Display Leaflet map with radius circle
echo '<div id="map" style="height: 400px; margin: 20px 0;"></div>' . PHP_EOL;
echo '<script>' . PHP_EOL;
echo 'var map = L.map("map").setView([' . $item->latitude . ', ' . $item->longitude . '], 10);' . PHP_EOL;
echo 'L.tileLayer("https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png", {' . PHP_EOL;
echo '  attribution: "&copy; OpenStreetMap contributors"' . PHP_EOL;
echo '}).addTo(map);' . PHP_EOL;
echo 'L.circleMarker([' . $item->latitude . ', ' . $item->longitude . '], {' . PHP_EOL;
echo '  color: "red",' . PHP_EOL;
echo '  fill: true,' . PHP_EOL;
echo '  fillColor: "red",' . PHP_EOL;
echo '  fillOpacity: 0.2,' . PHP_EOL;
echo '  radius: 10' . PHP_EOL;
echo '}).addTo(map);' . PHP_EOL;
echo 'var circle = L.circle([' . $item->latitude . ', ' . $item->longitude . '], {' . PHP_EOL;
echo '  color: "blue",' . PHP_EOL;
echo '  fill: false,' . PHP_EOL;
echo '  weight: 2,' . PHP_EOL;
echo '  radius: ' . ($radius * 1609.34) . PHP_EOL;
echo '}).addTo(map);' . PHP_EOL;
echo 'map.fitBounds(circle.getBounds());' . PHP_EOL;
echo '</script>' . PHP_EOL;
?>
