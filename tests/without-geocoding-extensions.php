<?php

// PHP 5.4-compatible checks: no PHPUnit or optional extension is needed.
require_once __DIR__ . '/runtime-bootstrap.php';

use AnthonyMartin\GeoLocation\GeoLocation;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Exceptions\UnexpectedResponseException;

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

if (function_exists('curl_init') && function_exists('json_decode')) {
    throw new RuntimeException('Run with cURL or JSON unavailable.');
}
$point = new GeoPoint(40, -74);
$box = $point->boundingBox(5, 'km');
if ($point->distanceTo($point, 'km') !== 0.0 || !$box->contains($point) ||
    !$point->inPolygon($box->getPolygon())) {
    throw new RuntimeException('Geometry must work without geocoding extensions.');
}
if (GeoLocation::getGeocodeFromGoogle('address', 'key') !== null) {
    throw new RuntimeException('Legacy geocoding must return null without extensions.');
}
try {
    GeoPoint::fromAddress('address', 'key');
    throw new RuntimeException('Modern geocoding must report the missing extension.');
} catch (UnexpectedResponseException $exception) {
    $missing = function_exists('curl_init') ? 'JSON' : 'cURL';
    if (strpos($exception->getMessage(), $missing) === false) {
        throw new RuntimeException('Missing extension error must name ' . $missing);
    }
}
restore_error_handler();
echo 'Geometry and missing-extension handling passed on PHP ' . PHP_VERSION . ".\n";
