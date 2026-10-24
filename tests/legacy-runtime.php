<?php

require_once __DIR__ . '/runtime-bootstrap.php';

use AnthonyMartin\GeoLocation\BoundingBox;
use AnthonyMartin\GeoLocation\Earth;
use AnthonyMartin\GeoLocation\GeoLocation;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;
use AnthonyMartin\GeoLocation\Exceptions\ExceptionInterface;

set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

function checkRuntime($condition, $message) {
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

$point = new GeoPoint('40.5', '-74.4');
checkRuntime($point->getLatitude() === 40.5, 'Numeric strings must become floats.');
checkRuntime($point->distanceTo(new GeoPoint(40.5, -74.4), 'KM') === 0.0, 'Identical points must have zero distance.');
$point->setDegLat(41);
$point->setRadLon(deg2rad(-74));
checkRuntime(abs($point->getLatitude(true) - deg2rad(41)) < 1e-14, 'Setter radians differ.');
checkRuntime(abs($point->getLongitude() + 74) < 1e-12, 'Setter degrees differ.');
foreach (array(NAN, INF, 'abc', null, true, array()) as $value) {
    try {
        new GeoPoint($value, 0);
        throw new RuntimeException('Invalid coordinate accepted.');
    } catch (ExceptionInterface $exception) {
        checkRuntime($exception->getMessage() !== '', 'Validation must explain the failure.');
    }
}
foreach (array(array(0, 0), array(90, 180), array(-90, -180)) as $coordinates) {
    $center = new GeoPoint($coordinates[0], $coordinates[1]);
    checkRuntime($center->boundingBox(0, 'km')->contains($center), 'Zero-size box excludes its center.');
}
$box = new BoundingBox(0, 0, 2, 2);
foreach (array(array(1, 0.1), array(1, 1.9), array(0.1, 1), array(1.9, 1)) as $coordinates) {
    checkRuntime($box->toPolygon()->containsGeoPoint(new GeoPoint($coordinates[0], $coordinates[1])), 'Bounding polygon must cover all four quadrants.');
}
checkRuntime((new BoundingBox(-1, 179, 1, -179))->contains(new GeoPoint(0, -180)), 'Antimeridian box failed.');
checkRuntime(!($box->contains(new GeoPoint(3, 3))), 'Outside point accepted.');
$nearEquator = Polygon::fromArray(array(array(-0.00001, 10), array(0.5, 11), array(1, 11), array(1, 10)));
checkRuntime($nearEquator->containsGeoPoint(new GeoPoint(0.3, 10.1)), 'Near-equator polygon failed.');
try {
    (new Polygon())->containsGeoPoint(new GeoPoint(0, 0));
    throw new RuntimeException('Empty polygon accepted.');
} catch (ExceptionInterface $exception) {
}
try {
    Earth::getRadius(true);
    throw new RuntimeException('Boolean unit accepted.');
} catch (InvalidArgumentException $exception) {
    checkRuntime($exception instanceof ExceptionInterface, 'Unit error must share the library catch type.');
}
$legacy = GeoLocation::fromDegrees(1.6859, 135.3926);
checkRuntime($legacy->distanceTo($legacy, 'mi') === 0.0, 'Legacy self-distance failed.');
checkRuntime(substr((string) $legacy, -1) === ')', 'Legacy string is incomplete.');
checkRuntime($legacy->getAngular() === 0.0, 'Legacy angular radius must start at zero.');
try {
    GeoPoint::fromAddress('New York');
    throw new RuntimeException('Missing API key accepted.');
} catch (ExceptionInterface $exception) {
    checkRuntime($exception->getMessage() !== '', 'Missing key error is empty.');
}

echo 'Runtime regression checks passed on PHP ' . PHP_VERSION . ".\n";
