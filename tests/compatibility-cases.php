<?php

// One shared set of 2.0.1 contracts for PHPUnit and the PHP 5.4 runtime checks.
require_once __DIR__ . '/fixtures/geocoding-transport.php';
require_once __DIR__ . '/fixtures/compatibility-subclasses.php';

use AnthonyMartin\GeoLocation\BoundingBox;
use AnthonyMartin\GeoLocation\GeoLocation;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;
use AnthonyMartin\GeoLocation\GeocodingCurlFixture;
use AnthonyMartin\GeoLocation\Exceptions\UnexpectedResponseException;
use AnthonyMartin\GeoLocation\Exceptions\CurlErrorException;

function backwardCompatibilityCases() {
    return array(
        'legacy_geocoding' => function ($same) {
            foreach (array('OK', 'ZERO_RESULTS', 'REQUEST_DENIED', 'OVER_QUERY_LIMIT') as $status) {
                foreach (array(null, 'key') as $key) {
                    GeocodingCurlFixture::reset();
                    $body = '{"status":"' . $status . '","results":[],"error_message":"details"}';
                    GeocodingCurlFixture::$body = $body;
                    $same($body, json_encode(GeoLocation::getGeocodeFromGoogle('address', $key)), 'Legacy raw status response');
                }
            }
            foreach (array(false, '', '{') as $body) {
                GeocodingCurlFixture::reset();
                GeocodingCurlFixture::$body = $body;
                $same(null, GeoLocation::getGeocodeFromGoogle('address', 'key'), 'Legacy transport/JSON failure');
            }
        },
        'transport_catch' => function ($same) {
            GeocodingCurlFixture::reset();
            GeocodingCurlFixture::$body = false;
            GeocodingCurlFixture::$error = 'Timed out';
            $caught = false;
            try {
                GeoPoint::fromAddress('address', 'key');
            } catch (UnexpectedResponseException $exception) {
                $caught = true;
                $same(true, $exception instanceof CurlErrorException, 'Specific transport exception remains available');
                $same('Timed out', $exception->getMessage(), 'Transport diagnostics remain descriptive');
            }
            $same(true, $caught, '2.0.1 UnexpectedResponseException catch must handle transport errors');
        },
        'corner_indexes' => function ($same) {
            $box = new BoundingBox(10, 30, 20, 40);
            $expected = array(array(10, 30), array(10, 40), array(20, 30), array(20, 40));
            foreach ($box->getVertices() as $index => $point) {
                $same($expected[$index], array($point->getLatitude(), $point->getLongitude()), '2.0.1 corner at index ' . $index);
            }
            $same($box->getVertices(), $box->getGeoPoints(), 'Both indexed APIs retain their order');
        },
        'subclass_overrides' => function ($same) {
            $point = new CompatibilityPoint(40, -74);
            $box = new CompatibilityBox($point, 2);
            $same(true, $box->contains($point), 'Custom constructor loads and delegates');
            $same(0.0, $point->distanceTo($point, 'km'), '2.0.1 GeoPoint override works');
            $polygon = new CompatibilityPolygon();
            foreach ($box->getPolygon()->getVertices() as $corner) { $polygon->addVertex($corner); }
            $same(true, $point->inPolygon($polygon), '2.0.1 Polygon overrides work');
        },
        'incomplete_polygons' => function ($same) {
            $point = new GeoPoint(1, 1);
            foreach (array(array(), array(array(0, 0)), array(array(0, 0), array(2, 2))) as $vertices) {
                $polygon = Polygon::fromArray($vertices);
                $same(false, $point->inPolygon($polygon), 'inPolygon with ' . count($vertices) . ' vertices');
                $same(false, $polygon->containsGeoPoint($point), 'containsGeoPoint with ' . count($vertices) . ' vertices');
                $same(false, Polygon::pointInPolygon($point, $polygon), 'pointInPolygon with ' . count($vertices) . ' vertices');
            }
        },
        'coordinate_types' => function ($same) {
            foreach (array(1, 1.5, '1.50') as $value) {
                foreach (array(false, true) as $inRadians) {
                    $point = new GeoPoint($value, $value, $inRadians);
                    $same($value, $point->getLatitude($inRadians), 'Latitude retains original input type');
                    $same($value, $point->getLongitude($inRadians), 'Longitude retains original input type');
                    $same($value, $inRadians ? $point->getRadLat() : $point->getDegLat(), 'Latitude alias retains type');
                    $same($value, $inRadians ? $point->getRadLon() : $point->getDegLon(), 'Longitude alias retains type');
                    $same($inRadians ? rad2deg((float) $value) : deg2rad((float) $value), $point->getLatitude(!$inRadians), 'Converted coordinate is a float');
                }
                $box = new BoundingBox($value, $value, $value, $value);
                foreach (array('getMinLatitude', 'getMaxLatitude', 'getMinLongitude', 'getMaxLongitude') as $getter) {
                    $same($value, $box->$getter(), 'Box getter retains input type');
                }
            }
        },
        'optional_extensions' => function ($same) {
            $composer = json_decode(file_get_contents(__DIR__ . '/../composer.json'), true);
            foreach (array('ext-curl', 'ext-json') as $extension) {
                $same(false, isset($composer['require'][$extension]), 'Geometry installation does not require ' . $extension);
                $same(true, isset($composer['suggest'][$extension]), 'Geocoding documents ' . $extension);
            }
        }
    );
}
