<?php

namespace Ramblers\Component\Ra_tools\Site\Helpers;

\defined('_JEXEC') or die;

use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Joomla\CMS\Factory;
use Joomla\CMS\Filesystem\File;
use RuntimeException;

/**
 * Safe filesystem and GPX XML operations for the GPX views.
 */
final class GpxFileHelper {

    private const MAX_UPLOAD_BYTES = 20971520;

    private static function childText(?DOMNode $parent, string $name): string {
        if (!$parent) {
            return '';
        }
        foreach ($parent->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->localName) === $name) {
                return trim($child->textContent);
            }
        }
        return '';
    }

    public static function configuredFolder(): string {
        $app = Factory::getApplication();
        $inputFolder = $app->getInput()->getString('folder', '');
        if ($inputFolder === '') {
            $inputFolder = $app->getInput()->getString('target_folder', '');
        }
        if ($inputFolder !== '') {
            return trim($inputFolder, "/\\ ");
        }

        $menu = $app->getMenu()->getActive();
        if (!$menu) {
            throw new RuntimeException('Choose a GPX directory in the gpxs menu item settings.');
        }

        $params = $menu->getParams();
        return trim((string) $params->get('target_folder', ''), "/\\ ");
    }

    public static function countRoutes(): int {
        return count(self::routeFilenames(self::workingDirectory()));
    }

    private static function distance(DOMXPath $xpath): string {
        $embedded = $xpath->query('//*[local-name()="distance" or local-name()="distance_km" or local-name()="distance_miles"]');
        if ($embedded !== false && $embedded->length > 0) {
            $node = $embedded->item(0);
            $value = trim($node->textContent);
            if ($value !== '') {
                $unit = $node instanceof DOMElement ? ($node->getAttribute('unit') ?: $node->getAttribute('units')) : '';
                if ($unit === '' && strtolower($node->localName) === 'distance_km') {
                    $unit = 'km';
                } elseif ($unit === '' && strtolower($node->localName) === 'distance_miles') {
                    $unit = 'mi';
                }
                return trim($value . ' ' . $unit);
            }
        }

        $points = $xpath->query('//*[local-name()="trkpt"]');
        if ($points === false || $points->length < 2) {
            $points = $xpath->query('//*[local-name()="rtept"]');
        }
        if ($points === false || $points->length < 2) {
            return '';
        }

        $distanceKm = 0.0;
        $previous = null;
        foreach ($points as $point) {
            if (!$point instanceof DOMElement || !$point->hasAttribute('lat') || !$point->hasAttribute('lon')) {
                continue;
            }
            $latitude = (float) $point->getAttribute('lat');
            $longitude = (float) $point->getAttribute('lon');
            if (abs($latitude) > 90 || abs($longitude) > 180) {
                continue;
            }
            if ($previous !== null) {
                $distanceKm += self::haversineKm($previous[0], $previous[1], $latitude, $longitude);
            }
            $previous = [$latitude, $longitude];
        }

        if ($distanceKm <= 0) {
            return '';
        }

        return number_format($distanceKm * 0.621371, 1) . ' mi (' . number_format($distanceKm, 1) . ' km)';
    }

    private static function haversineKm(float $lat1, float $lon1, float $lat2, float $lon2): float {
        $earthRadiusKm = 6371.0088;
        $latDelta = deg2rad($lat2 - $lat1);
        $lonDelta = deg2rad($lon2 - $lon1);
        $a = sin($latDelta / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($lonDelta / 2) ** 2;

        return 2 * $earthRadiusKm * asin(min(1, sqrt($a)));
    }

    public static function listRoutes(int $limitstart = 0, int $limit = 15): array {
        $directory = self::workingDirectory();
        $files = array_slice(self::routeFilenames($directory), max(0, $limitstart), max(1, $limit));
        $items = [];
        foreach ($files as $filename) {
            try {
                $route = self::readRoute($filename);
                $items[] = $route;
            } catch (RuntimeException $exception) {
                $items[] = [
                    'filename' => $filename,
                    'name' => '',
                    'description' => '',
                    'start' => '',
                    'start_latitude' => '',
                    'start_longitude' => '',
                    'distance' => '',
                    'error' => $exception->getMessage(),
                ];
            }
        }

        return $items;
    }

    private static function loadDocument(string $contents): DOMDocument {
        $previous = libxml_use_internal_errors(true);
        $document = new DOMDocument('1.0', 'UTF-8');
        $document->resolveExternals = false;
        $document->substituteEntities = false;
        $loaded = $document->loadXML($contents, LIBXML_NONET | LIBXML_NOBLANKS);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        if (!$loaded || !$document->documentElement || strtolower($document->documentElement->localName) !== 'gpx' || $document->doctype !== null) {
            throw new RuntimeException('The file is not valid GPX XML.');
        }

        return $document;
    }

    private static function locationDetails(string $name, DOMNode $point): array {
        $latitude = '';
        $longitude = '';
        $coordinates = '';
        if ($point instanceof DOMElement && $point->hasAttribute('lat') && $point->hasAttribute('lon')) {
            $candidateLatitude = $point->getAttribute('lat');
            $candidateLongitude = $point->getAttribute('lon');
            if (is_numeric($candidateLatitude) && is_numeric($candidateLongitude) && abs((float) $candidateLatitude) <= 90 && abs((float) $candidateLongitude) <= 180) {
                $latitude = $candidateLatitude;
                $longitude = $candidateLongitude;
                $coordinates = $latitude . ', ' . $longitude;
            }
        }

        return [
            'text' => $name !== '' ? ($coordinates !== '' ? $name . ' (' . $coordinates . ')' : $name) : $coordinates,
            'latitude' => $latitude,
            'longitude' => $longitude,
        ];
    }

    private static function metadataText(DOMElement $root, string $field): string {
        foreach (['metadata', ''] as $containerName) {
            foreach ($root->childNodes as $container) {
                if (!$container instanceof DOMElement || ($containerName !== '' && strtolower($container->localName) !== $containerName) || ($containerName === '' && strtolower($container->localName) !== $field)) {
                    continue;
                }
                if ($containerName === '') {
                    return trim($container->textContent);
                }
                foreach ($container->childNodes as $child) {
                    if ($child instanceof DOMElement && strtolower($child->localName) === $field) {
                        return trim($child->textContent);
                    }
                }
            }
        }

        foreach (['rte', 'trk'] as $containerName) {
            foreach ($root->childNodes as $container) {
                if (!$container instanceof DOMElement || strtolower($container->localName) !== $containerName) {
                    continue;
                }
                foreach ($container->childNodes as $child) {
                    if ($child instanceof DOMElement && strtolower($child->localName) === $field) {
                        return trim($child->textContent);
                    }
                }
            }
        }

        return '';
    }

    private static function normaliseFilename(string $filename, bool $appendExtension = false): string {
        $filename = trim(str_replace('\\', '/', $filename));
        if ($filename !== basename($filename) || str_contains($filename, '/') || str_contains($filename, "\0")) {
            throw new RuntimeException('Filename must not contain a directory path.');
        }
        $extension = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        if ($appendExtension && $extension === '') {
            $filename .= '.gpx';
            $extension = 'gpx';
        }
        if ($extension !== 'gpx') {
            throw new RuntimeException('Only files with the .gpx extension are accepted.');
        }
        if (preg_match('/^[A-Za-z0-9][A-Za-z0-9._ -]{0,119}\.gpx$/i', $filename) !== 1) {
            throw new RuntimeException('Filename may contain letters, numbers, spaces, dots, underscores and hyphens only.');
        }

        return $filename;
    }

    public static function readRoute(string $filename): array {
        $path = self::resolveFile($filename);
        $document = self::loadDocument((string) file_get_contents($path));
        $xpath = new DOMXPath($document);
        $root = $document->documentElement;

        $name = self::metadataText($root, 'name');
        $description = self::metadataText($root, 'desc');
        if ($description === '') {
            $description = self::metadataText($root, 'cmt');
        }
        $start = self::startLocation($xpath);

        return [
            'filename' => basename($path),
            'name' => $name,
            'description' => $description,
            'start' => $start['text'],
            'start_latitude' => $start['latitude'],
            'start_longitude' => $start['longitude'],
            'distance' => self::distance($xpath),
            'error' => '',
        ];
    }

    private static function resolveFile(string $filename): string {
        $filename = self::normaliseFilename($filename);
        $directory = self::workingDirectory();
        $path = realpath($directory . DIRECTORY_SEPARATOR . $filename);
        if ($path === false || !is_file($path) || !is_readable($path) || !str_starts_with($path, $directory . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The requested GPX file was not found in the selected directory.');
        }

        return $path;
    }

    private static function routeFilenames(string $directory): array {
        $entries = scandir($directory);
        if (!is_array($entries)) {
            return [];
        }

        $files = array_values(array_filter($entries, static function (string $filename) use ($directory): bool {
                    return strtolower(pathinfo($filename, PATHINFO_EXTENSION)) === 'gpx' && is_file($directory . DIRECTORY_SEPARATOR . $filename);
                }));
        natcasesort($files);

        return array_values($files);
    }

    public static function saveRoute(string $filename, string $name, string $description, string $saveAs = ''): string {
        $source = self::resolveFile($filename);
        $name = trim($name);
        $description = trim($description);
        if ($name === '' || mb_strlen($name) > 200) {
            throw new RuntimeException('Route name is required and must be 200 characters or fewer.');
        }
        if (mb_strlen($description) > 10000) {
            throw new RuntimeException('Route description must be 10,000 characters or fewer.');
        }

        $contents = file_get_contents($source);
        if ($contents === false) {
            throw new RuntimeException('Unable to read the GPX file.');
        }
        $document = self::loadDocument($contents);
        $root = $document->documentElement;
        self::setMetadataText($document, $root, 'name', $name);
        self::setMetadataText($document, $root, 'desc', $description);

        $targetName = $saveAs === '' ? basename($source) : self::normaliseFilename($saveAs, true);
        $directory = self::workingDirectory();
        $target = $directory . DIRECTORY_SEPARATOR . $targetName;
        if ($targetName !== basename($source) && file_exists($target)) {
            throw new RuntimeException('A GPX file with that filename already exists.');
        }

        $xml = $document->saveXML();
        if ($xml === false) {
            throw new RuntimeException('Unable to encode the updated GPX file.');
        }

        if ($targetName === basename($source)) {
            $temporary = tempnam($directory, '.gpx-');
            if ($temporary === false || file_put_contents($temporary, $xml, LOCK_EX) === false || !rename($temporary, $target)) {
                if (isset($temporary) && is_file($temporary)) {
                    unlink($temporary);
                }
                throw new RuntimeException('Unable to save the GPX file.');
            }
        } else {
            if (!is_writable($source)) {
                throw new RuntimeException(
                    'The existing GPX file is not writable by the web server. Grant the web server write access before renaming it.'
                );
            }
            $written = file_put_contents($source, $xml, LOCK_EX);
            if ($written !== strlen($xml)) {
                throw new RuntimeException('Unable to update the GPX file completely.');
            }
            if (!rename($source, $target)) {
                throw new RuntimeException('The GPX file was updated but could not be renamed.');
            }
        }

        return $targetName;
    }

    private static function setMetadataText(DOMDocument $document, DOMElement $root, string $field, string $value): void {
        $container = null;
        foreach ($root->childNodes as $candidate) {
            if ($candidate instanceof DOMElement && strtolower($candidate->localName) === 'metadata') {
                $container = $candidate;
                break;
            }
        }

        if (!$container) {
            foreach ($root->childNodes as $candidate) {
                if ($candidate instanceof DOMElement && strtolower($candidate->localName) === $field) {
                    $node = $candidate;
                    while ($node->firstChild) {
                        $node->removeChild($node->firstChild);
                    }
                    $node->appendChild($document->createTextNode($value));
                    return;
                }
            }
        }

        foreach (['rte', 'trk'] as $containerName) {
            if ($container) {
                break;
            }
            foreach ($root->childNodes as $candidate) {
                if ($candidate instanceof DOMElement && strtolower($candidate->localName) === $containerName) {
                    $container = $candidate;
                    break;
                }
            }
        }

        if (!$container) {
            $container = $document->createElementNS($root->namespaceURI, 'metadata');
            $root->insertBefore($container, $root->firstChild);
        }

        $node = null;
        foreach ($container->childNodes as $child) {
            if ($child instanceof DOMElement && strtolower($child->localName) === $field) {
                $node = $child;
                break;
            }
        }
        if (!$node) {
            $qualifiedName = $root->prefix ? $root->prefix . ':' . $field : $field;
            $node = $document->createElementNS($root->namespaceURI, $qualifiedName);
            $container->appendChild($node);
        }

        while ($node->firstChild) {
            $node->removeChild($node->firstChild);
        }
        $node->appendChild($document->createTextNode($value));
    }

    private static function startLocation(DOMXPath $xpath): array {
        $waypoints = $xpath->query('//*[local-name()="wpt"]');
        if ($waypoints !== false) {
            foreach ($waypoints as $waypoint) {
                $waypointName = self::childText($waypoint, 'name');
                if ($waypointName !== '' && preg_match('/\b(start|start point|begin)\b/i', $waypointName)) {
                    return self::locationDetails($waypointName, $waypoint);
                }
            }
        }

        foreach (['trkpt', 'rtept'] as $pointName) {
            $points = $xpath->query('//*[local-name()="' . $pointName . '"]');
            if ($points !== false && $points->length > 0) {
                $point = $points->item(0);
                return self::locationDetails(self::childText($point, 'name'), $point);
            }
        }

        if ($waypoints !== false && $waypoints->length > 0) {
            $waypoint = $waypoints->item(0);
            return self::locationDetails(self::childText($waypoint, 'name'), $waypoint);
        }

        return ['text' => '', 'latitude' => '', 'longitude' => ''];
    }

    public static function upload(array $upload): string {
        if (!isset($upload['error'], $upload['tmp_name'], $upload['name']) || (int) $upload['error'] !== UPLOAD_ERR_OK) {
            throw new RuntimeException('The GPX upload did not complete successfully.');
        }
        if ((int) ($upload['size'] ?? 0) < 1 || (int) $upload['size'] > self::MAX_UPLOAD_BYTES) {
            throw new RuntimeException('GPX files must be between 1 byte and 20 MB.');
        }

        $filename = self::normaliseFilename((string) $upload['name']);
        $contents = file_get_contents((string) $upload['tmp_name']);
        if ($contents === false) {
            throw new RuntimeException('Unable to read the uploaded file.');
        }
        self::loadDocument($contents);

        $target = self::workingDirectory() . DIRECTORY_SEPARATOR . $filename;
        if (file_exists($target)) {
            throw new RuntimeException('A GPX file with that filename already exists.');
        }

        if (!File::upload((string) $upload['tmp_name'], $target, false, false)) {
            throw new RuntimeException('Unable to store the uploaded GPX file.');
        }

        return $filename;
    }

    public static function workingDirectory(): string {
        $relative = str_replace('\\', '/', self::configuredFolder());
        if ($relative === '' || $relative === '-1' || str_contains($relative, "\0")) {
            throw new RuntimeException('Choose an existing GPX directory in the gpxs menu item settings.');
        }

        foreach (explode('/', $relative) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                throw new RuntimeException('The selected GPX directory is invalid.');
            }
        }

        $imagesRoot = realpath(JPATH_ROOT . '/images');
        $directory = realpath(($imagesRoot ?: JPATH_ROOT . '/images') . '/' . $relative);
        if ($imagesRoot === false || $directory === false || !is_dir($directory) || !str_starts_with($directory, $imagesRoot . DIRECTORY_SEPARATOR)) {
            throw new RuntimeException('The selected GPX directory does not exist under images.');
        }

        return $directory;
    }

}
