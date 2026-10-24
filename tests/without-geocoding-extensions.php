<?php

// Standalone geometry and geocoding checks without optional extension functions.
require_once __DIR__ . '/runtime-bootstrap.php';

use AnthonyMartin\GeoLocation\GoogleGeocoder;
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
try {
    (new GoogleGeocoder('key'))->geocode('address');
    throw new RuntimeException('Modern geocoding must report the missing extension.');
} catch (UnexpectedResponseException $exception) {
    $missing = function_exists('curl_init') ? 'JSON' : 'cURL';
    if (strpos($exception->getMessage(), $missing) === false) {
        throw new RuntimeException('Missing extension error must name ' . $missing);
    }
}
if (!interface_exists('Psr\\Http\\Client\\ClientInterface')) {
    try {
        class_exists('AnthonyMartin\\GeoLocation\\Geocoding\\CurlClient');
        throw new RuntimeException('Optional PSR adapter must report its missing interfaces.');
    } catch (UnexpectedResponseException $exception) {
        if (strpos($exception->getMessage(), 'psr/http-client') === false) {
            throw new RuntimeException('Missing PSR error must identify the package.');
        }
    }
}
restore_error_handler();
echo 'Geometry and missing-extension handling passed on PHP ' . PHP_VERSION . ".\n";
