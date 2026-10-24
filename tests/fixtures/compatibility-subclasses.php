<?php

use AnthonyMartin\GeoLocation\BoundingBox;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;

// These overrides use the public 2.0.1 signatures, including a constructor
// unrelated to BoundingBox's constructor. Loading this file is part of the test.
class CompatibilityBox extends BoundingBox {
    public function __construct(GeoPoint $center, $km) {
        $box = $center->boundingBox($km, 'km');
        parent::__construct($box->getMinLatitude(), $box->getMinLongitude(),
            $box->getMaxLatitude(), $box->getMaxLongitude());
    }
}

class CompatibilityPoint extends GeoPoint {
    public function distanceTo(GeoPoint $geopoint, $unit_of_measurement) {
        return parent::distanceTo($geopoint, $unit_of_measurement);
    }

    public function inPolygon(Polygon $polygon) {
        return parent::inPolygon($polygon);
    }
}

class CompatibilityPolygon extends Polygon {
    public function addVertex(GeoPoint $geopoint) { parent::addVertex($geopoint); }
    public static function fromArray(array $vertices) { return parent::fromArray($vertices); }
    public function containsGeoPoint(GeoPoint $p) { return parent::containsGeoPoint($p); }
    public function getVertices() { return parent::getVertices(); }
    public function getGeoPoints() { return parent::getGeoPoints(); }
}
