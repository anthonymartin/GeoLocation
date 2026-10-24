<?php

// Run with `php -n tests/without-bcmath.php` to verify containment without
// optional extensions, independently of PHPUnit's extension requirements.
require_once __DIR__ . '/runtime-bootstrap.php';

use AnthonyMartin\GeoLocation\BoundingBox;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;

if (extension_loaded('bcmath')) {
    throw new \RuntimeException('This check must run without the BCMath extension.');
}

$polygon = Polygon::fromArray(array(array(0, 0), array(0, 2), array(2, 2), array(2, 0)));
if (!$polygon->containsGeoPoint(new GeoPoint(1, 1)) || $polygon->containsGeoPoint(new GeoPoint(3, 3))) {
    throw new \RuntimeException('Polygon containment failed without BCMath.');
}

$box = new BoundingBox(-1, 179, 1, -179);
if (!$box->contains(new GeoPoint(0, 180)) || $box->contains(new GeoPoint(0, 0))) {
    throw new \RuntimeException('Bounding box containment failed without BCMath.');
}

echo "Containment works without BCMath.\n";
