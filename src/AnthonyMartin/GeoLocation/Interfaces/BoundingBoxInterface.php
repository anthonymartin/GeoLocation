<?php

declare(strict_types=1);
namespace AnthonyMartin\GeoLocation\Interfaces;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\BoundingBox;
use AnthonyMartin\GeoLocation\Polygon;

interface BoundingBoxInterface {
    /**
     * @param int|float|numeric-string $distance
     * @param string $unit_of_measurement
     */
    public static function fromGeoPoint(GeoPoint $geopoint, mixed $distance, mixed $unit_of_measurement): BoundingBox;
    public function contains(GeoPoint $point): bool;
    public function getMinLatitude(): float;
    public function getMinLongitude(): float;
    public function getMaxLatitude(): float;
    public function getMaxLongitude(): float;
    /** @return list<GeoPoint> */
    public function getVertices(): array;
    /** @return list<GeoPoint> */
    public function getGeoPoints(): array;
    public function getPolygon(): Polygon;
    public function toPolygon(): Polygon;
}
