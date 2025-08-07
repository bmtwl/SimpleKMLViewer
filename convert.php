<?php

$kmlFile = 'kml/source.kml';
$geojsonFile = 'geojson/source.geojson';

// Global style registry
$globalStyles = [];

// Check if conversion is needed
if (!file_exists($kmlFile)) {
    die("Error: KML file not found at $kmlFile");
}

if (!file_exists($geojsonFile) || filemtime($kmlFile) > filemtime($geojsonFile)) {
    convertKmlToGeoJson($kmlFile, $geojsonFile);
}

/**
 * Convert KML file to GeoJSON format
 * @param string $kmlPath Path to input KML file
 * @param string $geojsonPath Path to output GeoJSON file
 */
function convertKmlToGeoJson($kmlPath, $geojsonPath) {
    global $globalStyles;

    $xml = simplexml_load_file($kmlPath);

    if (!$xml) {
        return;
    }

    // Register namespace
    $xml->registerXPathNamespace('kml', 'http://www.opengis.net/kml/2.2');

    // Parse styles first
    parseKmlStyles($xml, $globalStyles);

    $jsonFeatures = [];

    // Check for <Document>
    $root = isset($xml->Document) ? $xml->Document : $xml;
    parseKmlFolder($root, $jsonFeatures, 'Root');

    $geojson = [
        'type' => 'FeatureCollection',
        'features' => $jsonFeatures
    ];

    // Ensure output directory exists
    $dir = dirname($geojsonPath);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    file_put_contents($geojsonPath, json_encode($geojson, JSON_PRETTY_PRINT));
}

/**
 * Parse KML styles and store in global registry
 * @param SimpleXMLElement $xml KML XML object
 * @param array &$styles Reference to styles array
 */
function parseKmlStyles($xml, &$styles) {
    // Parse <Style>
    foreach ($xml->xpath('//kml:Style') as $styleNode) {
        $id = (string)$styleNode['id'];
        if (!$id) continue;

        $lineStyle = $styleNode->LineStyle;
        $color = null;
        $width = null;

        if ($lineStyle) {
            $color = (string)$lineStyle->color;
            $width = (float)$lineStyle->width;
        }

        $styles[$id] = [
            'color' => $color ? kmlColorToRgb($color) : null,
            'width' => $width ?: 2
        ];
    }

    // Parse <StyleMap>
    foreach ($xml->xpath('//kml:StyleMap') as $styleMapNode) {
        $id = (string)$styleMapNode['id'];
        if (!$id) continue;

        foreach ($styleMapNode->Pair as $pair) {
            if ((string)$pair->key === 'normal') {
                $url = (string)$pair->styleUrl;
                $url = ltrim($url, '#');
                if (isset($styles[$url])) {
                    $styles[$id] = $styles[$url];
                }
                break;
            }
        }
    }
}

/**
 * Convert KML color format (AABBGGRR) to RGB hex
 * @param string $abgr KML color string
 * @return string|null RGB hex color or null if invalid
 */
function kmlColorToRgb($abgr) {
    // KML uses AABBGGRR
    if (strlen($abgr) < 8) return null;
    $b = substr($abgr, 2, 2);
    $g = substr($abgr, 4, 2);
    $r = substr($abgr, 6, 2);
    return "#$r$g$b";
}

/**
 * Recursively parse KML folders
 * @param SimpleXMLElement $folderNode Folder node to parse
 * @param array &$features Reference to features array
 * @param string $parentFolder Parent folder name
 */
function parseKmlFolder($folderNode, &$features, $parentFolder = '') {
    $folderName = (string)$folderNode->name ?: $parentFolder;

    foreach ($folderNode->children() as $child) {
        $tag = $child->getName();
        if ($tag === 'Placemark' || $tag === 'kml:Placemark') {
            $feature = kmlPlacemarkToGeoJson($child, $folderName);
            if ($feature) {
                $features[] = $feature;
            }
        } elseif ($tag === 'Folder' || $tag === 'kml:Folder') {
            parseKmlFolder($child, $features, $folderName);
        }
    }
}

/**
 * Convert KML Placemark to GeoJSON Feature
 * @param SimpleXMLElement $placemark Placemark node
 * @param string $folder Folder name
 * @return array|null GeoJSON feature or null if invalid
 */
function kmlPlacemarkToGeoJson($placemark, $folder) {
    global $globalStyles;

    $name = (string)$placemark->name;
    $description = (string)$placemark->description;

    $meta = [];
    if ($placemark->ExtendedData) {
        foreach ($placemark->ExtendedData->Data as $data) {
            $meta[(string)$data['name']] = (string)$data->value;
        }
    }

    preg_match_all('/<img[^>]+src="([^">]+)"/i', $description, $imgMatches);
    $images = $imgMatches[1];

    // Style
    $styleUrl = (string)$placemark->styleUrl;
    $styleUrl = ltrim($styleUrl, '#');
    $style = null;
    if ($styleUrl && isset($globalStyles[$styleUrl])) {
        $style = $globalStyles[$styleUrl];
    }

    // Geometry
    $geometry = null;
    if (isset($placemark->Point)) {
        $geometry = kmlPointToGeoJson($placemark->Point);
    } elseif (isset($placemark->LineString)) {
        $geometry = kmlLineStringToGeoJson($placemark->LineString);
    } elseif (isset($placemark->Polygon)) {
        $geometry = kmlPolygonToGeoJson($placemark->Polygon);
    }

    if (!$geometry) return null;

    return [
        'type' => 'Feature',
        'properties' => [
            'name' => $name,
            'description' => $description,
            'folder' => $folder,
            'images' => $images,
            'meta' => $meta,
            'style' => $style
        ],
        'geometry' => $geometry
    ];
}

/**
 * Convert KML Point to GeoJSON Point
 * @param SimpleXMLElement $pointNode Point node
 * @return array|null GeoJSON point or null if invalid
 */
function kmlPointToGeoJson($pointNode) {
    $coords = explode(',', (string)$pointNode->coordinates);
    if (count($coords) < 2) return null;
    return [
        'type' => 'Point',
        'coordinates' => [(float)$coords[0], (float)$coords[1]]
    ];
}

/**
 * Convert KML LineString to GeoJSON LineString
 * @param SimpleXMLElement $lineNode LineString node
 * @return array|null GeoJSON linestring or null if invalid
 */
function kmlLineStringToGeoJson($lineNode) {
    $coords = parseKmlCoordinates((string)$lineNode->coordinates);
    if (empty($coords)) return null;
    return [
        'type' => 'LineString',
        'coordinates' => $coords
    ];
}

/**
 * Convert KML Polygon to GeoJSON Polygon
 * @param SimpleXMLElement $polyNode Polygon node
 * @return array|null GeoJSON polygon or null if invalid
 */
function kmlPolygonToGeoJson($polyNode) {
    $outer = parseKmlCoordinates((string)$polyNode->outerBoundaryIs->LinearRing->coordinates);
    if (empty($outer)) return null;
    return [
        'type' => 'Polygon',
        'coordinates' => [$outer]
    ];
}

/**
 * Parse KML coordinate string into array of [lng, lat] pairs
 * @param string $str Coordinate string
 * @return array Array of coordinate pairs
 */
function parseKmlCoordinates($str) {
    $points = array_filter(array_map('trim', explode(' ', trim($str))));
    $coords = [];
    foreach ($points as $point) {
        $parts = explode(',', $point);
        if (count($parts) >= 2) {
            $coords[] = [(float)$parts[0], (float)$parts[1]];
        }
    }
    return $coords;
}
?>

