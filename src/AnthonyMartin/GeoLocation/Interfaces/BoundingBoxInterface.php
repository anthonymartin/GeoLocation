<?php

declare(strict_types=1);
namespace AnthonyMartin\GeoLocation\Interfaces;
use AnthonyMartin\GeoLocation\GeoPoint;
use AnthonyMartin\GeoLocation\BoundingBox;
use AnthonyMartin\GeoLocation\Polygon;

interface BoundingBoxInterface {
    public static function fromGeoPoint(GeoPoint $geopoint, mixed $distance, mixed $unit_of_measurement): BoundingBox;
    public function contains(GeoPoint $geopoint): bool;
    public function getMinLatitude(): float;
    public function getMinLongitude(): float;
    public function getMaxLatitude(): float;
    public function getMaxLongitude(): float;
    public function getVertices(): array;
    public function getGeoPoints(): array;
    public function getPolygon(): Polygon;
    public function toPolygon(): Polygon;
}
