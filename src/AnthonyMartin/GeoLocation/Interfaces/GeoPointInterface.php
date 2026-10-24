<?php

declare(strict_types=1);
namespace AnthonyMartin\GeoLocation\Interfaces;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\Polygon;

interface GeoPointInterface {
    public function inPolygon(Polygon $polygon): bool;
    public function distanceTo(GeoPoint $geopoint, mixed $unit_of_measurement): float;
}
